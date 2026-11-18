<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EquipmentMonitoringController extends Controller
{
    public function equipment($eqId)
    {
        return response()->json(['success' => true, 'data' => DB::table('equipment_monitoring_types')->where('equipment_id', $eqId)->where('is_active', 1)->orderBy('display_order')->orderBy('id')->get()->map(fn ($x) => $this->jsonize($x))]);
    }

    private function jsonize($x)
    {
        foreach (['reference_values', 'columns'] as $c) {
            if (isset($x->$c) && is_string($x->$c)) {
                $x->$c = json_decode($x->$c, true) ?: [];
            }
        }

return $x;
    }

    public function storeType(Request $r, $eqId)
    {
        if (! $r->monitoring_name) {
            return response()->json(['success' => false, 'message' => 'Monitoring name is required'], 400);
        }$cols = $r->columns ?: [['label' => 'Value']];
        $id = DB::table('equipment_monitoring_types')->insertGetId(['equipment_id' => $eqId, 'monitoring_name' => $r->monitoring_name, 'unit' => $r->unit, 'reference_values' => json_encode($r->reference_values ?: []), 'columns' => json_encode($cols), 'display_order' => $r->display_order ?: 1, 'general_shift_only' => $r->boolean('general_shift_only'), 'is_active' => 1, 'is_unlocked' => 0, 'created_at' => now()]);

        return response()->json(['success' => true, 'data' => $this->jsonize(DB::table('equipment_monitoring_types')->find($id))]);
    }

    public function updateType(Request $r, $id)
    {
        if (! DB::table('equipment_monitoring_types')->where('id', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }$cols = $r->columns ?: [['label' => 'Value']];
        DB::table('equipment_monitoring_types')->where('id', $id)->update(['monitoring_name' => $r->monitoring_name, 'unit' => $r->unit, 'reference_values' => json_encode($r->reference_values ?: []), 'columns' => json_encode($cols), 'display_order' => $r->display_order ?: 1, 'general_shift_only' => $r->boolean('general_shift_only')]);

        return response()->json(['success' => true, 'data' => $this->jsonize(DB::table('equipment_monitoring_types')->find($id))]);
    }

    public function destroyType($id)
    {
        DB::table('equipment_monitoring_types')->where('id', $id)->update(['is_active' => 0]);

        return response()->json(['success' => true]);
    }

    public function today(Request $r)
    {
        $q = DB::table('eq_monitoring_logs as ml')->join('equipment as e', 'e.id', '=', 'ml.equipment_id')->where('ml.shift', $r->shift)->whereDate('ml.logged_at', today());
        if ($r->plant) {
            $q->where('e.plant', $r->plant);
        }if ($r->department) {
            $q->where('e.department', $r->department);
        }

return response()->json(['success' => true, 'data' => $q->pluck('ml.monitoring_id')->values()]);
    }

    public function last24(Request $r)
    {
        $q = DB::table('eq_monitoring_logs as ml')->join('equipment_monitoring_types as em', 'em.id', '=', 'ml.monitoring_id')->join('equipment as e', 'e.id', '=', 'ml.equipment_id')->join('users as u', 'u.id', '=', 'ml.logged_by')->where('ml.logged_at', '>=', now()->subDay())->select('ml.*', 'em.monitoring_name', 'em.unit', 'em.reference_values', 'em.columns', 'e.name as equipment_name', 'e.code as equipment_code', 'u.full_name as logged_by_name');
        if ($r->plant) {
            $q->where('e.plant', $r->plant);
        }if ($r->department) {
            $q->where('e.department', $r->department);
        }$rows = $q->orderByDesc('ml.logged_at')->get()->map(fn ($x) => $this->jsonize($x));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function storeLog(Request $r)
    {
        if (! $r->monitoring_id || ! $r->equipment_id || ! $r->shift || ! is_array($r->readings) || ! count($r->readings)) {
            return response()->json(['success' => false, 'message' => 'monitoring_id, equipment_id, shift, readings required'], 400);
        }if (DB::table('eq_monitoring_logs')->where('monitoring_id', $r->monitoring_id)->where('shift', $r->shift)->where('logged_date', today()->toDateString())->exists()) {
            return response()->json(['success' => false, 'message' => 'Already submitted for this shift'], 409);
        }$id = DB::table('eq_monitoring_logs')->insertGetId(['monitoring_id' => $r->monitoring_id, 'equipment_id' => $r->equipment_id, 'plant' => $r->plant, 'department' => $r->department, 'shift' => $r->shift, 'logged_by' => $r->user()->id, 'readings' => json_encode($r->readings), 'remarks' => $r->remarks, 'logged_date' => today()->toDateString(), 'logged_at' => now()]);

        return response()->json(['success' => true, 'data' => ['id' => $id]]);
    }

    public function all(Request $r)
    {
        $q = DB::table('equipment_monitoring_types as em')->join('equipment as e', 'e.id', '=', 'em.equipment_id')->leftJoin('users as u', 'u.id', '=', 'em.unlocked_by')->where('e.active', 1)->where('em.is_active', 1)->select('em.*', 'e.name as equipment_name', 'e.code as equipment_code', 'e.department', 'e.location', 'e.plant', 'u.full_name as unlocked_by_name');
        if ($r->plant) {
            $q->where('e.plant', $r->plant);
        }if ($r->department) {
            $q->where('e.department', $r->department);
        }

return response()->json(['success' => true, 'data' => $q->orderBy('e.department')->orderBy('e.name')->orderBy('em.display_order')->get()->map(fn ($x) => $this->jsonize($x))]);
    }

    public function locked()
    {
        return response()->json(['success' => true, 'data' => DB::table('equipment_monitoring_types as em')->join('equipment as e', 'e.id', '=', 'em.equipment_id')->leftJoin('users as u', 'u.id', '=', 'em.unlocked_by')->where('em.general_shift_only', 1)->select('em.id', 'em.monitoring_name', 'em.general_shift_only', 'em.is_unlocked', 'em.unlocked_at', 'em.unlock_note', 'e.name as equipment_name', 'e.code as equipment_code', 'e.plant', 'e.department', 'u.full_name as unlocked_by_name')->orderBy('e.plant')->orderBy('e.department')->orderBy('e.name')->orderBy('em.monitoring_name')->get()]);
    }

    public function unlock(Request $r, $id)
    {
        $n = DB::table('equipment_monitoring_types')->where('id', $id)->update(['is_unlocked' => 1, 'unlocked_by' => $r->user()->id, 'unlocked_at' => now(), 'unlock_note' => $r->unlock_note]);
        if (! $n) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

return response()->json(['success' => true, 'data' => DB::table('equipment_monitoring_types')->select('id', 'monitoring_name', 'is_unlocked')->find($id)]);
    }

    public function lock($id)
    {
        $n = DB::table('equipment_monitoring_types')->where('id', $id)->update(['is_unlocked' => 0, 'unlocked_by' => null, 'unlocked_at' => null, 'unlock_note' => null]);
        if (! $n) {
            return response()->json(['success' => false, 'message' => 'Not found'],404);
        }

return response()->json(['success' => true, 'data' => DB::table('equipment_monitoring_types')->select('id','monitoring_name','is_unlocked')->find($id)]);
    }
}
