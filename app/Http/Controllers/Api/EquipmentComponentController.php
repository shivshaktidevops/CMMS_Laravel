<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Services\AuditService;
use Illuminate\Http\Request;

class EquipmentComponentController extends Controller
{
    public function index(Request $r, $equipmentId)
    {
        if (! Equipment::whereKey($equipmentId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Equipment not found'], 404);
        }
        $q = EquipmentComponent::with(['parent:id,component_code,component_name', 'location:id,code,name,location_type'])->where('equipment_id', $equipmentId);
        if (! $r->boolean('include_inactive')) {
            $q->where('active', 1);
        }

        return response()->json(['success' => true, 'data' => $q->orderBy('component_code')->get()]);
    }

    public function tree($equipmentId)
    {
        if (! Equipment::whereKey($equipmentId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Equipment not found'], 404);
        }
        $rows = EquipmentComponent::with('children.children.children')->where('equipment_id', $equipmentId)->whereNull('parent_component_id')->where('active', 1)->orderBy('component_code')->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    private function validateParent($equipmentId, $parentId, $currentId = null)
    {
        if (! $parentId) {
            return null;
        }
        $parent = EquipmentComponent::find($parentId);
        if (! $parent || (int) $parent->equipment_id !== (int) $equipmentId) {
            return response()->json(['success' => false, 'message' => 'Parent component must belong to the same equipment'], 400);
        }
        if ($currentId && (int) $parentId === (int) $currentId) {
            return response()->json(['success' => false, 'message' => 'Component cannot be its own parent'], 400);
        }
        if ($currentId) {
            $cursor = $parent;
            while ($cursor) {
                if ((int) $cursor->id === (int) $currentId) {
                    return response()->json(['success' => false, 'message' => 'Component hierarchy cycle is not allowed'], 400);
                }
                $cursor = $cursor->parent_component_id ? EquipmentComponent::find($cursor->parent_component_id) : null;
            }
        }

        return null;
    }

    public function store(Request $r, $equipmentId)
    {
        if (! Equipment::whereKey($equipmentId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Equipment not found'], 404);
        }
        $r->validate(['component_code' => 'required|string|max:80', 'component_name' => 'required|string|max:150', 'parent_component_id' => 'nullable|exists:equipment_components,id', 'location_id' => 'nullable|exists:locations,id']);
        if ($resp = $this->validateParent($equipmentId, $r->parent_component_id)) {
            return $resp;
        }
        try {
            $c = EquipmentComponent::create(array_merge($r->only(['parent_component_id', 'component_code', 'component_name', 'component_type', 'serial_number', 'manufacturer', 'model_number', 'installed_at', 'location_id']), ['equipment_id' => $equipmentId, 'component_code' => strtoupper($r->component_code), 'active' => true]));
            AuditService::log($r->user()->id, 'CREATE_COMPONENT', 'equipment_components', $c->id, $r->all(), $r->ip());

            return response()->json(['success' => true, 'data' => $c->load(['parent', 'location'])], 201);
        } catch (\Throwable $e) {
            $dup = str_contains(strtolower($e->getMessage()), 'duplicate');

            return response()->json(['success' => false, 'message' => $dup ? 'Component code already exists for this equipment' : $e->getMessage()], $dup ? 409 : 500);
        }
    }

    public function update(Request $r, $equipmentId, $id)
    {
        $c = EquipmentComponent::where('equipment_id', $equipmentId)->find($id);
        if (! $c) {
            return response()->json(['success' => false, 'message' => 'Component not found'], 404);
        }
        if ($resp = $this->validateParent($equipmentId, $r->has('parent_component_id') ? $r->parent_component_id : $c->parent_component_id, $id)) {
            return $resp;
        }
        $data = $r->only(['parent_component_id', 'component_name', 'component_type', 'serial_number', 'manufacturer', 'model_number', 'installed_at', 'location_id', 'active']);
        if ($r->filled('component_code')) {
            $data['component_code'] = strtoupper($r->component_code);
        }
        try {
            $c->fill($data)->save();
            AuditService::log($r->user()->id, 'UPDATE_COMPONENT', 'equipment_components', $c->id, $r->all(), $r->ip());

            return response()->json(['success' => true, 'data' => $c->fresh()->load(['parent', 'location'])]);
        } catch (\Throwable $e) {
            $dup = str_contains(strtolower($e->getMessage()), 'duplicate');

            return response()->json(['success' => false, 'message' => $dup ? 'Component code already exists for this equipment' : $e->getMessage()], $dup ? 409 : 500);
        }
    }

    public function destroy(Request $r, $equipmentId, $id)
    {
        $c = EquipmentComponent::where('equipment_id', $equipmentId)->find($id);
        if (! $c) {
            return response()->json(['success' => false, 'message' => 'Component not found'], 404);
        }
        if ($c->children()->where('active', 1)->exists()) {
            return response()->json(['success' => false, 'message' => 'Deactivate/move child components first'], 409);
        }
        $c->active = false;
        $c->save();
        AuditService::log($r->user()->id, 'DEACTIVATE_COMPONENT', 'equipment_components', $c->id, [], $r->ip());

        return response()->json(['success' => true, 'message' => 'Component deactivated']);
    }
}
