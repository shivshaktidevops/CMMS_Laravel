<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\AuditService;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index(Request $r)
    {
        $q = Location::with(['plant:id,name,code', 'parent:id,name,code']);
        if ($r->filled('plant_id')) {
            $q->where('plant_id', $r->plant_id);
        }
        if (! $r->boolean('include_inactive')) {
            $q->where('active', 1);
        }

        return response()->json(['success' => true, 'data' => $q->orderBy('name')->get()]);
    }

    public function tree(Request $r)
    {
        $q = Location::with('children.children.children')->whereNull('parent_id')->where('active', 1);
        if ($r->filled('plant_id')) {
            $q->where('plant_id', $r->plant_id);
        }

        return response()->json(['success' => true, 'data' => $q->orderBy('name')->get()]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'code' => 'required|string|max:50', 'name' => 'required|string|max:120',
            'plant_id' => 'nullable|exists:plants,id', 'parent_id' => 'nullable|exists:locations,id',
            'location_type' => 'nullable|string|max:30',
        ]);
        if ($data['parent_id'] ?? null) {
            $parent = Location::find($data['parent_id']);
            if ($parent && ($data['plant_id'] ?? null) && (int) $parent->plant_id !== (int) $data['plant_id']) {
                return response()->json(['success' => false, 'message' => 'Parent location must belong to the same plant'], 400);
            }
        }
        $loc = Location::create([
            'plant_id' => $data['plant_id'] ?? null, 'parent_id' => $data['parent_id'] ?? null,
            'code' => strtoupper($data['code']), 'name' => $data['name'],
            'location_type' => strtoupper($data['location_type'] ?? 'AREA'), 'active' => true,
        ]);
        AuditService::log($r->user()->id, 'CREATE_LOCATION', 'locations', $loc->id, $r->all(), $r->ip());

        return response()->json(['success' => true, 'data' => $loc], 201);
    }

    public function update(Request $r, $id)
    {
        $loc = Location::find($id);
        if (! $loc) {
            return response()->json(['success' => false, 'message' => 'Location not found'], 404);
        }
        $parentId = $r->has('parent_id') ? $r->input('parent_id') : $loc->parent_id;
        $plantId = $r->has('plant_id') ? $r->input('plant_id') : $loc->plant_id;
        if ($parentId && (int) $parentId === (int) $id) {
            return response()->json(['success' => false, 'message' => 'Location cannot be its own parent'], 400);
        }
        if ($parentId) {
            $parent = Location::find($parentId);
            if (! $parent) {
                return response()->json(['success' => false, 'message' => 'Parent location not found'], 400);
            }
            if ($plantId && (int) $parent->plant_id !== (int) $plantId) {
                return response()->json(['success' => false, 'message' => 'Parent location must belong to the same plant'], 400);
            }
            $cursor = $parent;
            while ($cursor) {
                if ((int) $cursor->id === (int) $id) {
                    return response()->json(['success' => false, 'message' => 'Location hierarchy cycle is not allowed'], 400);
                }
                $cursor = $cursor->parent_id ? Location::find($cursor->parent_id) : null;
            }
        }
        $loc->fill($r->only(['plant_id', 'parent_id', 'name', 'location_type', 'active']));
        if ($r->filled('code')) {
            $loc->code = strtoupper($r->code);
        }
        if ($r->filled('location_type')) {
            $loc->location_type = strtoupper($r->location_type);
        }
        $loc->save();
        AuditService::log($r->user()->id, 'UPDATE_LOCATION', 'locations', $loc->id, $r->all(), $r->ip());

        return response()->json(['success' => true, 'data' => $loc]);
    }

    public function destroy(Request $r, $id)
    {
        $loc = Location::find($id);
        if (! $loc) {
            return response()->json(['success' => false, 'message' => 'Location not found'], 404);
        }
        if ($loc->children()->where('active', 1)->exists()) {
            return response()->json(['success' => false, 'message' => 'Deactivate/move child locations first'], 409);
        }
        $loc->active = false;
        $loc->save();
        AuditService::log($r->user()->id, 'DEACTIVATE_LOCATION', 'locations', $loc->id, [], $r->ip());

        return response()->json(['success' => true, 'message' => 'Location deactivated']);
    }
}
