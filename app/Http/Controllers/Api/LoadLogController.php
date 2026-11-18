<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoadLogController extends Controller
{
    public function today(Request $r)
    {
        $q = DB::table('load_logs as ll')->join('equipment as e', 'e.id', '=', 'll.equipment_id')->where('ll.shift', $r->shift)->whereDate('ll.logged_at', today());
        if ($r->plant) {
            $q->where('e.plant', $r->plant);
        }if ($r->department) {
            $q->where('e.department', $r->department);
        }

return response()->json(['success' => true, 'data' => $q->pluck('ll.equipment_id')->map(fn ($x) => (string) $x)->values()]);
    }

    public function last24(Request $r)
    {
        $q = DB::table('load_logs as ll')->join('equipment as e', 'e.id', '=', 'll.equipment_id')->join('users as u', 'u.id', '=', 'll.logged_by')->where('ll.logged_at', '>=', now()->subDay())->select('ll.*', 'e.name as equipment_name', 'e.code as equipment_code', 'e.kw_capacity', 'e.load_100pct', 'e.critical_load', 'u.full_name as logged_by_name');
        if ($r->plant) {
            $q->where('e.plant', $r->plant);
        }if ($r->department) {
            $q->where('e.department', $r->department);
        }

return response()->json(['success' => true, 'data' => $q->orderByDesc('ll.logged_at')->get()]);
    }

    public function store(Request $r)
    {
        if (! $r->equipment_id || ! $r->shift) {
            return response()->json(['success' => false, 'message' => 'equipment_id and shift required'], 400);
        }if (DB::table('load_logs')->where('equipment_id', $r->equipment_id)->where('shift', $r->shift)->whereDate('logged_at', today())->exists()) {
            return response()->json(['success' => false, 'message' => 'Load log already submitted for this shift'], 409);
        }$id = DB::table('load_logs')->insertGetId(['equipment_id' => $r->equipment_id, 'plant' => $r->plant, 'department' => $r->department, 'shift' => $r->shift, 'logged_by' => $r->user()->id, 'red_load' => $r->red_load, 'yellow_load' => $r->yellow_load, 'blue_load' => $r->blue_load, 'remarks' => $r->remarks, 'logged_at' => now()]);

        return response()->json(['success' => true, 'data' => ['id' => $id]]);
    }
}
