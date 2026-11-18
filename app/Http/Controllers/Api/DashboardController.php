<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $r)
    {
        $eq = DB::table('equipment')->where('active', 1);
        if ($r->plant) {
            $eq->where('plant', $r->plant);
        }$total = (clone $eq)->count();
        $running = (clone $eq)->where('current_status', 'RUNNING')->count();
        $stopped = (clone $eq)->where('current_status', 'STOPPED')->count();
        $bd = DB::table('breakdowns as b')->join('equipment as e', 'e.id', '=', 'b.equipment_id');
        if ($r->plant) {
            $bd->where('e.plant', $r->plant);
        }$open = (clone $bd)->where('b.status', 'OPEN')->count();
        $started = (clone $bd)->whereDate('b.start_time', today())->count();
        $closedQ = (clone $bd)->whereDate('b.start_time', today())->where('b.status', 'CLOSED');
        $closed = $closedQ->count();
        $sum = (float) ($closedQ->sum('b.downtime_minutes') ?: 0);
        $avg = (float) ($closedQ->avg('b.downtime_minutes') ?: 0);
        $b30 = DB::table('breakdowns as b')->join('equipment as e', 'e.id', '=', 'b.equipment_id')->where('b.start_time', '>=', now()->subDays(30))->where('b.status', 'CLOSED');
        if ($r->plant) {
            $b30->where('e.plant', $r->plant);
        }$count30 = $b30->count();
        $sum30 = (float) ($b30->sum('b.downtime_minutes') ?: 0);
        $mtbf = $count30 ? round(((30 * 24 * 60 - $sum30) / $count30) / 60, 1) : null;

        return response()->json(['success' => true, 'data' => ['availability' => ['total' => $total, 'running' => $running, 'stopped' => $stopped, 'availability_pct' => $total ? round($running / $total * 100, 1) : 0], 'today' => ['open_breakdowns' => $open, 'started_today' => $started, 'closed_today' => $closed, 'total_downtime_today_min' => $sum, 'avg_mttr_min' => $avg], 'mtbf' => ['breakdowns_30d' => $count30, 'total_downtime_min_30d' => $sum30, 'mtbf_hours' => $mtbf]]]);
    }

    public function topReasons(Request $r)
    {
        $days = (int) ($r->days ?: 30);
        $q = DB::table('breakdowns as b')->join('stop_reasons as sr', 'b.initial_reason_id', '=', 'sr.id')->join('equipment as e', 'e.id', '=', 'b.equipment_id')->where('b.start_time', '>=', now()->subDays($days));
        if ($r->plant) {
            $q->where('e.plant', $r->plant);
        }$rows = $q->select('sr.description', 'sr.category', DB::raw('COUNT(*) as count'), DB::raw('ROUND(SUM(COALESCE(b.downtime_minutes,0))) as total_downtime_min'))->groupBy('sr.description', 'sr.category')->orderByDesc('count')->limit(10)->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function downtimeByEquipment(Request $r)
    {
        $days = (int) ($r->days ?: 7);
        $q = DB::table('equipment as e')->leftJoin('breakdowns as b', function ($j) use ($days) {
            $j->on('b.equipment_id', '=', 'e.id')->where('b.start_time', '>=', now()->subDays($days))->where('b.status', 'CLOSED');
        })->where('e.active', 1);
        if ($r->plant) {
            $q->where('e.plant', $r->plant);
        }$rows = $q->select('e.code', 'e.name', 'e.department', 'e.plant', DB::raw('COUNT(b.id) as breakdown_count'), DB::raw('COALESCE(ROUND(SUM(b.downtime_minutes)),0) as total_downtime_min'), DB::raw('COALESCE(ROUND(AVG(b.downtime_minutes)),0) as avg_downtime_min'))->groupBy('e.id', 'e.code', 'e.name', 'e.department', 'e.plant')->orderByDesc('total_downtime_min')->limit(20)->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function trend(Request $r)
    {
        $days = (int) ($r->days ?: 14);
        $q = DB::table('breakdowns as b')->join('equipment as e', 'e.id', '=', 'b.equipment_id')->where('b.start_time', '>=', now()->subDays($days));
        if ($r->plant) {
            $q->where('e.plant', $r->plant);
        }$rows = $q->selectRaw('DATE(b.start_time) as day, COUNT(*) as breakdowns, COALESCE(ROUND(SUM(b.downtime_minutes)),0) as downtime_min')->groupByRaw('DATE(b.start_time)')->orderBy('day')->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function departmentView(Request $r)
    {
        $q = DB::table('equipment')->where('active', 1);
        if ($r->plant) {
            $q->where('plant', $r->plant);
        }$eq = $q->get()->groupBy('department');
        $out = [];
        foreach ($eq as $dept => $items) {
            $ids = $items->pluck('id');
            $out[] = ['department' => $dept, 'plant' => $r->plant, 'total_equipment' => $items->count(), 'running' => $items->where('current_status', 'RUNNING')->count(), 'stopped' => $items->where('current_status', 'STOPPED')->count(), 'open_breakdowns' => DB::table('breakdowns')->whereIn('equipment_id', $ids)->where('status', 'OPEN')->count()];
        }

return response()->json(['success' => true, 'data' => $out]);
    }

    public function deptPerformance(Request $r)
    {
        $q = DB::table('equipment')->whereNotNull('department');
        if ($r->plant) {
            $q->where('plant', $r->plant);
        }$depts = $q->distinct()->orderBy('department')->pluck('department');
        $rows = [];
        foreach ($depts as $d) {
            $jobs = DB::table('jobs')->where('assigned_dept', $d);
            if ($r->plant) {
                $jobs->where('plant', $r->plant);
            }$closed = (clone $jobs)->where('status', 'CLOSED')->whereDate('closed_at', today())->get(['created_at', 'closed_at']);
            $avg = $closed->count() ? round($closed->avg(fn ($j) => (strtotime($j->closed_at) - strtotime($j->created_at)) / 60)) : 0;
            $rows[] = ['dept' => $d, 'open_jobs' => (clone $jobs)->whereIn('status', ['OPEN', 'ASSIGNED', 'IN PROGRESS'])->count(), 'closed_jobs' => $closed->count(), 'avg_repair_min' => $avg];
        }

return response()->json(['success' => true, 'data' => $rows]);
    }

    public function shiftPerformance(Request $r)
    {
        $rows = [];
        foreach (['A', 'B', 'C'] as $s) {
            $insp = DB::table('shift_monitoring_logs')->where('shift', $s)->whereDate('logged_at', today());
            $jobs = DB::table('jobs')->where('shift', $s)->whereDate('created_at', today());
            $dev = DB::table('breakdowns as b')->join('shift_logs as sl', 'sl.id', '=', 'b.shift_log_id')->join('equipment as e', 'e.id', '=', 'b.equipment_id')->where('sl.shift', $s)->whereDate('b.start_time', today());
            if ($r->plant) {
                $insp->where('plant', $r->plant);
                $jobs->where('plant', $r->plant);
                $dev->where('e.plant', $r->plant);
            } $rows[] = ['shift' => $s, 'inspections' => $insp->distinct('equipment_id')->count('equipment_id'), 'deviations' => $dev->count(), 'jobs_created' => $jobs->count()];
        }

return response()->json(['success' => true, 'data' => $rows]);
    }

    public function tpdComparison()
    {
        $plants = DB::table('equipment')->distinct()->pluck('plant');
        $rows = [];
        foreach ($plants as $p) {
            $eq = DB::table('equipment')->where('plant', $p);
            $total = $eq->count();
            $run = (clone $eq)->where('current_status', 'RUNNING')->count();
            $dev = DB::table('breakdowns as b')->join('equipment as e', 'e.id', '=', 'b.equipment_id')->where('e.plant', $p)->whereDate('b.start_time', today())->count();
            $jobs = DB::table('jobs')->where('plant',$p)->whereDate('created_at',today())->count();
            $rows[] = ['plant' => $p, 'deviations' => $dev, 'jobs' => $jobs, 'efficiency' => $total ? round($run / $total * 100,1) : 0];
        }

return response()->json(['success' => true, 'data' => $rows]);
    }
}
