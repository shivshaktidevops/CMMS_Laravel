<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BreakdownController extends Controller
{
    private function base()
    {
        return DB::table('breakdowns as b')->join('equipment as e', 'b.equipment_id', '=', 'e.id')->leftJoin('stop_reasons as sr', 'b.initial_reason_id', '=', 'sr.id')->leftJoin('users as u', 'b.closed_by', '=', 'u.id')->select('b.*', 'e.name as equipment_name', 'e.code as equipment_code', 'e.department', 'e.location', 'e.plant as equipment_plant', 'sr.description as initial_reason', 'sr.category as reason_category', 'u.full_name as closed_by_name');
    }

    public function index(Request $r)
    {
        $q = $this->base();
        foreach (['status' => 'b.status', 'equipment_id' => 'b.equipment_id', 'department' => 'e.department', 'plant' => 'e.plant'] as $p => $c) {
            if ($r->filled($p)) {
                $q->where($c, $r->$p);
            }
        }if ($r->from_date) {
            $q->whereDate('b.start_time', '>=', $r->from_date);
        }if ($r->to_date) {
            $q->whereDate('b.start_time', '<=', $r->to_date);
        }$rows = $q->orderByDesc('b.start_time')->limit(500)->get()->map(function ($x) {
            $x->runtime_downtime_min = round(((strtotime($x->end_time ?: now()) - strtotime($x->start_time)) / 60));

            return $x;
        });

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function open(Request $r)
    {
        $q = $this->base()->where('b.status', 'OPEN');
        if ($r->plant) {
            $q->where('e.plant', $r->plant);
        }$rows = $q->orderBy('b.start_time')->get()->map(function ($x) {
            $x->current_downtime_minutes = round((time() - strtotime($x->start_time)) / 60);

            return $x;
        });

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function show($id)
    {
        $row = DB::table('breakdowns as b')->join('equipment as e', 'b.equipment_id', '=', 'e.id')->leftJoin('stop_reasons as sr', 'b.initial_reason_id', '=', 'sr.id')->leftJoin('users as u', 'b.closed_by', '=', 'u.id')->leftJoin('shift_logs as sl', 'b.shift_log_id', '=', 'sl.id')->leftJoin('users as slu', 'sl.operator_id', '=', 'slu.id')->where('b.id', $id)->select('b.*', 'e.name as equipment_name', 'e.code as equipment_code', 'e.department', 'e.location', 'sr.description as initial_reason', 'sr.category as reason_category', 'u.full_name as closed_by_name', 'slu.full_name as reported_by', 'sl.shift as reported_shift')->first();
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

return response()->json(['success' => true, 'data' => $row]);
    }

    public function update(Request $r, $id)
    {
        $n = DB::table('breakdowns')->where('id', $id)->where('status', 'OPEN')->update(['findings' => $r->findings, 'actions_taken' => $r->actions_taken, 'spares_used' => $r->spares_used, 'resolution_notes' => $r->resolution_notes, 'updated_at' => now()]);
        if (! $n) {
            return response()->json(['success' => false, 'message' => 'Breakdown not found or already closed'], 404);
        }AuditService::log($r->user()->id, 'UPDATE_BREAKDOWN', 'breakdowns', (int) $id, $r->all(), $r->ip());

        return response()->json(['success' => true, 'data' => DB::table('breakdowns')->find($id)]);
    }

    public function close(Request $r, $id)
    {
        if (! $r->resolution_notes) {
            return response()->json(['success' => false, 'message' => 'Resolution notes required to close'], 400);
        }try {
            $closed = DB::transaction(function () use ($r, $id) {
                $bd = DB::table('breakdowns')->where('id', $id)->where('status', 'OPEN')->lockForUpdate()->first();
                if (! $bd) {
                    throw new \RuntimeException('Open breakdown not found');
                }$mins = round((time() - strtotime($bd->start_time)) / 60, 2);
                DB::table('breakdowns')->where('id', $id)->update(['status' => 'CLOSED', 'end_time' => now(), 'downtime_minutes' => $mins, 'findings' => $r->findings, 'actions_taken' => $r->actions_taken, 'spares_used' => $r->spares_used, 'resolution_notes' => $r->resolution_notes, 'closed_by' => $r->user()->id, 'closed_at' => now(), 'updated_at' => now()]);
                DB::table('equipment')->where('id', $bd->equipment_id)->update(['current_status' => 'RUNNING', 'updated_at' => now()]);

                return DB::table('breakdowns')->find($id);
            });
            AuditService::log($r->user()->id, 'CLOSE_BREAKDOWN', 'breakdowns', (int) $id, ['downtime_minutes' => $closed->downtime_minutes], $r->ip());

            return response()->json(['success' => true, 'message' => 'Breakdown closed. Downtime: '.$closed->downtime_minutes.' minutes.', 'data' => $closed]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()],404);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()],500);
        }
    }
}
