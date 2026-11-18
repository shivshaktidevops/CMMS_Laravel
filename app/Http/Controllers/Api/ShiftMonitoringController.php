<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftMonitoringController extends Controller
{
    private function decode($x)
    {
        foreach (['columns', 'user_boxes', 'readings'] as $c) {
            if (isset($x->$c) && is_string($x->$c)) {
                $x->$c = json_decode($x->$c, true) ?: [];
            }
        }

return $x;
    }

    public function all(Request $r)
    {
        $q = DB::table('equipment_monitoring_params as p')->join('equipment as e', 'e.id', '=', 'p.equipment_id')->where('p.is_active', 1)->where('e.active', 1)->select('p.*', 'e.name as equipment_name', 'e.code as equipment_code', 'e.location', 'e.current_status', 'e.hourly_entry', 'e.plant as equipment_plant', 'e.department as equipment_department');
        if ($r->plant) {
            $q->where('e.plant', $r->plant);
        }if ($r->department) {
            $q->where('e.department', $r->department);
        }

return response()->json(['success' => true, 'data' => $q->orderBy('e.department')->orderBy('e.name')->orderBy('p.display_order')->get()->map(fn ($x) => $this->decode($x))]);
    }

    public function today(Request $r)
    {
        $q = DB::table('shift_monitoring_logs as pl')->join('equipment as e', 'e.id', '=', 'pl.equipment_id')->where('pl.shift', $r->shift)->whereDate('pl.logged_at', today())->select('pl.equipment_id', 'pl.hour_number', 'e.hourly_entry');
        if ($r->plant) {
            $q->where('e.plant', $r->plant);
        }if ($r->department) {
            $q->where('e.department', $r->department);
        }$map = [];
        foreach ($q->get() as $x) {
            $k = (string) $x->equipment_id;
            if (! isset($map[$k])) {
                $map[$k] = ['submitted' => false, 'submitted_hours' => []];
            }if ($x->hourly_entry && $x->hour_number) {
                if (! in_array($x->hour_number, $map[$k]['submitted_hours'])) {
                    $map[$k]['submitted_hours'][] = $x->hour_number;
                }if (count($map[$k]['submitted_hours']) >= 8) {
                    $map[$k]['submitted'] = true;
                }
            } elseif (! $x->hourly_entry) {
                $map[$k]['submitted'] = true;
            }
        }

return response()->json(['success' => true, 'data' => $map]);
    }

    public function storeLog(Request $r)
    {
        $entries = $r->entries;
        if (! $r->plant || ! $r->shift || ! $r->equipment_id || ! is_array($entries) || ! count($entries)) {
            return response()->json(['success' => false, 'message' => 'plant, shift, equipment_id, entries required'], 400);
        }if ($r->hour_number !== null && $r->hour_number !== '') {
            $h = (int) $r->hour_number;
            if ($h < 1 || $h > 8) {
                return response()->json(['success' => false, 'message' => 'hour_number must be 1–8'], 400);
            }
        }if ($r->department !== 'Process') {
            $dup = DB::table('shift_monitoring_logs')->where('equipment_id', $r->equipment_id)->where('shift', $r->shift)->whereDate('logged_at', today());
            if ($r->hour_number === null || $r->hour_number === '') {
                $dup->whereNull('hour_number');
            } else {
                $dup->where('hour_number', (int) $r->hour_number);
            }if ($dup->exists()) {
                return response()->json(['success' => false, 'message' => 'Parameters for '.($r->hour_number ? 'Hour '.$r->hour_number : 'this shift').' already submitted.'], 409);
            }
        } $readings = [];
        foreach ($entries as $e) {
            $std = $e['standard_value_snapshot'] ?? null;
            $actual = $e['actual_value'] ?? null;
            $status = ! $std ? 'RECORDED' : ((string) $actual === (string) $std ? 'OK' : 'DEVIATION');
            $readings[] = ['parameter_id' => $e['parameter_id'] ?? null, 'standard_value_snapshot' => $std, 'actual_value' => $actual, 'status' => $status, 'remarks' => $e['remarks'] ?? null];
        }$id = DB::table('shift_monitoring_logs')->insertGetId(['equipment_id' => $r->equipment_id, 'plant' => $r->plant, 'department' => $r->department, 'shift' => $r->shift, 'hour_number' => $r->hour_number ?: null, 'logged_by' => $r->user()->id, 'readings' => json_encode($readings), 'logged_at' => now()]);

        return response()->json(['success' => true, 'data' => ['id' => $id]]);
    }

    public function equipment($eqId)
    {
        $rows = DB::table('equipment_monitoring_params')->select('id', 'equipment_id', 'parameter_name', 'standard_value', 'unit', 'display_order', 'columns', 'is_dynamic', 'user_boxes')->where('equipment_id', $eqId)->where('is_active', 1)->orderBy('display_order')->orderBy('id')->get()->map(fn ($x) => $this->decode($x));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function storeParam(Request $r, $eqId)
    {
        if (! $r->parameter_name) {
            return response()->json(['success' => false, 'message' => 'parameter_name required'], 400);
        }$id = DB::table('equipment_monitoring_params')->insertGetId(['equipment_id' => $eqId, 'plant' => $r->plant, 'department' => $r->department, 'parameter_name' => $r->parameter_name, 'standard_value' => $r->standard_value, 'unit' => $r->unit, 'display_order' => $r->display_order ?: 1, 'columns' => json_encode($r->columns ?: []), 'is_dynamic' => $r->boolean('is_dynamic'), 'user_boxes' => json_encode($r->user_boxes ?: []), 'is_active' => 1, 'created_at' => now()]);

        return response()->json(['success' => true, 'data' => ['id' => $id]]);
    }

    public function updateParam(Request $r, $id)
    {
        $data = [];
        foreach (['parameter_name', 'standard_value', 'unit', 'display_order', 'is_dynamic'] as $f) {
            if ($r->has($f)) {
                $data[$f] = $r->input($f);
            }
        }foreach (['columns', 'user_boxes'] as $f) {
            if ($r->has($f)) {
                $data[$f] = json_encode($r->input($f) ?: []);
            }
        }if (! $data) {
            return response()->json(['success' => true, 'message' => 'No fields to update']);
        }DB::table('equipment_monitoring_params')->where('id', $id)->update($data);

        return response()->json(['success' => true]);
    }

    public function destroyParam($id)
    {
        DB::table('equipment_monitoring_params')->where('id', $id)->update(['is_active' => 0]);

        return response()->json(['success' => true]);
    }

    public function last24(Request $r)
    {
        $q = DB::table('shift_monitoring_logs as pl')->join('equipment as e', 'e.id', '=', 'pl.equipment_id')->join('users as u', 'u.id', '=', 'pl.logged_by')->where('pl.logged_at', '>=', now()->subDay())->select('pl.id', 'pl.equipment_id', 'e.name as equipment_name', 'e.code as equipment_code', 'e.department', 'e.hourly_entry', 'pl.shift', 'pl.hour_number', 'pl.readings', 'pl.logged_at', 'u.full_name as logged_by_name', 'u.department as logged_by_dept');
        if ($r->plant) {
            $q->where('e.plant', $r->plant);
        }if ($r->department) {
            $q->where('e.department',$r->department);
        }

return response()->json(['success' => true, 'data' => $q->orderByDesc('pl.logged_at')->get()->map(fn ($x) => $this->decode($x))]);
    }
}
