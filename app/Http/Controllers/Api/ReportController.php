<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private function breakdownQuery(Request $r)
    {
        $q = DB::table('breakdowns as b')->join('equipment as e', 'e.id', '=', 'b.equipment_id')->leftJoin('shift_logs as sl', 'sl.id', '=', 'b.shift_log_id');
        if ($r->from_date) {
            $q->whereDate('b.start_time', '>=', $r->from_date);
        }if ($r->to_date) {
            $q->whereDate('b.start_time', '<=', $r->to_date);
        }if ($r->plant) {
            $q->where('e.plant', $r->plant);
        }if ($r->shift) {
            $q->where('sl.shift', $r->shift);
        }if ($r->department) {
            $q->where('e.department', $r->department);
        }if ($r->status) {
            $q->where('b.status', $r->status);
        }if ($r->equipment) {
            $s = $r->equipment;
            $q->where(fn ($x) => $x->where('e.name', 'like', '%'.$s.'%')->orWhere('e.code', 'like', '%'.$s.'%'));
        }

return $q;
    }

    public function breakdowns(Request $r)
    {
        $base = $this->breakdownQuery($r);
        $all = (clone $base)->select('b.*')->get();
        $closed = $all->where('status', 'CLOSED');
        $k = ['total' => $all->count(), 'open' => $all->where('status', 'OPEN')->count(), 'closed' => $closed->count(), 'total_downtime_min' => round((float) $closed->sum('downtime_minutes')), 'avg_mttr_min' => round((float) ($closed->avg('downtime_minutes') ?: 0)), 'equipment_affected' => $all->pluck('equipment_id')->unique()->count()];
        $data = $base->leftJoin('stop_reasons as sr', 'sr.id', '=', 'b.initial_reason_id')->leftJoin('users as u1', 'u1.id', '=', 'sl.operator_id')->leftJoin('users as u2', 'u2.id', '=', 'b.closed_by')->select('b.bd_number', 'e.name as equipment_name', 'e.code as equipment_code', 'e.department', 'e.plant', 'sl.shift', 'sr.description as reason', 'sr.category as reason_category', 'b.status', 'b.start_time', 'b.end_time', 'b.downtime_minutes', 'b.findings', 'b.actions_taken', 'b.spares_used', 'b.resolution_notes', 'u1.full_name as reported_by', 'u2.full_name as closed_by')->orderByDesc('b.start_time')->limit(1000)->get();

        return response()->json(['success' => true, 'kpi' => $k, 'data' => $data]);
    }

    private function jobQuery(Request $r)
    {
        $q = DB::table('jobs as j');
        if ($r->from_date) {
            $q->whereDate('j.created_at', '>=', $r->from_date);
        }if ($r->to_date) {
            $q->whereDate('j.created_at', '<=', $r->to_date);
        }if ($r->plant) {
            $q->where('j.plant', $r->plant);
        }if ($r->shift) {
            $q->where('j.shift', $r->shift);
        }if ($r->department) {
            $q->where('j.assigned_dept', $r->department);
        }if ($r->status) {
            $q->where('j.status', $r->status);
        }

return $q;
    }

    public function jobs(Request $r)
    {
        $all = (clone $this->jobQuery($r))->get();
        $k = ['total' => $all->count(), 'open' => $all->where('status', 'OPEN')->count(), 'assigned' => $all->where('status', 'ASSIGNED')->count(), 'in_progress' => $all->where('status', 'IN PROGRESS')->count(), 'completed' => $all->where('status', 'COMPLETED')->count(), 'closed' => $all->where('status', 'CLOSED')->count(), 'from_stoppage' => $all->whereNotNull('description')->count(), 'depts_involved' => $all->pluck('assigned_dept')->filter()->unique()->count()];
        $q = $this->jobQuery($r)->leftJoin('equipment as e', 'e.id', '=', 'j.equipment_id')->leftJoin('users as u1', 'u1.id', '=', 'j.raised_by')->leftJoin('users as u2', 'u2.id', '=', 'j.assigned_to')->select('j.job_number', 'j.title', 'j.description', 'j.plant', 'j.shift', 'j.status', 'j.assigned_dept', 'j.remarks', 'j.created_at', 'j.assigned_at', 'j.completed_at', 'j.closed_at', 'e.name as equipment_name', 'e.code as equipment_code', 'e.department as equipment_dept', 'u1.full_name as raised_by', 'u1.department as raised_by_dept', 'u2.full_name as assigned_to');

        return response()->json(['success' => true, 'kpi' => $k, 'data' => $q->orderByDesc('j.created_at')->limit(1000)->get()]);
    }

    public function parameters(Request $r)
    {
        $q = DB::table('eq_monitoring_logs as pl')->leftJoin('equipment as e', 'e.id', '=', 'pl.equipment_id')->leftJoin('equipment_monitoring_types as emt', 'emt.id', '=', 'pl.monitoring_id')->leftJoin('users as u', 'u.id', '=', 'pl.logged_by');
        if ($r->from_date) {
            $q->whereDate('pl.logged_at', '>=', $r->from_date);
        }if ($r->to_date) {
            $q->whereDate('pl.logged_at', '<=', $r->to_date);
        }if ($r->plant) {
            $q->where('pl.plant', $r->plant);
        }if ($r->shift) {
            $q->where('pl.shift', $r->shift);
        }if ($r->department) {
            $q->where('pl.department', $r->department);
        }$logs = (clone $q)->select('pl.*', 'e.name as equipment_name', 'e.code as equipment_code', 'emt.monitoring_name as parameter_name', 'emt.reference_values', 'emt.unit', 'u.full_name as logged_by')->orderByDesc('pl.logged_at')->limit(2000)->get();
        $data = [];
        foreach ($logs as $l) {
            $reads = json_decode($l->readings ?? '[]', true) ?: [];
            $refs = json_decode($l->reference_values ?? '[]', true) ?: [];
            foreach ($reads as $rd) {
                $data[] = ['logged_at' => $l->logged_at, 'plant' => $l->plant, 'department' => $l->department, 'shift' => $l->shift, 'equipment_name' => $l->equipment_name, 'equipment_code' => $l->equipment_code, 'parameter_name' => $l->parameter_name, 'unit' => $rd['unit'] ?? $l->unit ?? '', 'actual_value' => $rd['value'] ?? null, 'standard_value_snapshot' => json_encode($refs), 'standard_value' => $refs[0]['value'] ?? null, 'reading_remarks' => $l->remarks, 'logged_by' => $l->logged_by];
            }
        }$k = ['total_readings' => count($data), 'equipment_count' => $logs->pluck('equipment_id')->unique()->count(), 'operators_count' => $logs->pluck('logged_by')->unique()->count(), 'days_covered' => $logs->pluck('logged_date')->unique()->count()];

        return response()->json(['success' => true, 'kpi' => $k, 'data' => $data]);
    }

    public function shiftDuty(Request $r)
    {
        $q = DB::table('shift_logs as sl')->join('equipment as e', 'e.id', '=', 'sl.equipment_id')->join('users as u', 'u.id', '=', 'sl.operator_id');
        if ($r->from_date) {
            $q->whereDate('sl.logged_at', '>=', $r->from_date);
        }if ($r->to_date) {
            $q->whereDate('sl.logged_at', '<=', $r->to_date);
        }if ($r->plant) {
            $q->where('e.plant', $r->plant);
        }if ($r->shift) {
            $q->where('sl.shift', $r->shift);
        }if ($r->department) {
            $q->where('u.department', $r->department);
        }$rows = $q->selectRaw('DATE(sl.logged_at) as duty_date, sl.shift, e.plant, u.department, u.full_name as shift_incharge, COUNT(DISTINCT sl.id) as entries_logged, MIN(sl.logged_at) as first_entry, MAX(sl.logged_at) as last_entry')->groupByRaw('DATE(sl.logged_at), sl.shift, e.plant, u.department, u.full_name')->orderByDesc('duty_date')->orderBy('sl.shift')->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    private function gsQuery(Request $r)
    {
        $q = DB::table('general_shift_jobs as g');
        if ($r->from_date) {
            $q->whereDate('g.created_at', '>=', $r->from_date);
        }if ($r->to_date) {
            $q->whereDate('g.created_at', '<=', $r->to_date);
        }if ($r->plant) {
            $q->where('g.plant', $r->plant);
        }if ($r->department) {
            $q->where('g.department', $r->department);
        }if ($r->status) {
            $q->where('g.status', $r->status);
        }

return $q;
    }

    public function generalShiftJobs(Request $r)
    {
        $all = (clone $this->gsQuery($r))->get();
        $closed = $all->where('status', 'CLOSED')->filter(fn ($x) => $x->closed_at);
        $avg = $closed->count() ? round($closed->avg(fn ($x) => (strtotime($x->closed_at) - strtotime($x->created_at)) / 86400)) : 0;
        $k = ['total' => $all->count(), 'open' => $all->where('status', 'OPEN')->count(), 'in_progress' => $all->where('status', 'IN PROGRESS')->count(), 'completed' => $all->where('status', 'COMPLETED')->count(), 'closed' => $closed->count(), 'avg_completion_days' => $avg, 'depts_involved' => $all->pluck('department')->unique()->count(), 'plants_covered' => $all->pluck('plant')->unique()->count()];
        $q = $this->gsQuery($r)->leftJoin('users as u1', 'u1.id', '=', 'g.raised_by')->leftJoin('users as u2', 'u2.id', '=', 'g.closed_by')->select('g.*', 'u1.full_name as raised_by_name', 'u2.full_name as closed_by_name');
        $data = $q->orderByDesc('g.created_at')->limit(2000)->get()->map(function ($x) {
            $x->completion_days = $x->closed_at ? ((strtotime($x->closed_at) - strtotime($x->created_at)) / 86400) : null;
            $age = (time() - strtotime($x->created_at)) / 86400;
            $x->health_status = (($x->status === 'OPEN' && $age > 7) || ($x->status === 'IN PROGRESS' && $age > 14)) ? 'overdue' : 'on_track';

            return $x;
        });

        return response()->json(['success' => true, 'kpi' => $k, 'data' => $data]);
    }

    public function generalShiftDepartment(Request $r, $dept)
    {
        if ($r->user()->role === 'maintenance' && $r->user()->department !== $dept) {
            return response()->json(['success' => false, 'message' => 'You can only view your own department reports'], 403);
        }$q = DB::table('general_shift_jobs')->where('department', $dept);
        if ($r->from_date) {
            $q->whereDate('created_at', '>=', $r->from_date);
        }if ($r->to_date) {
            $q->whereDate('created_at', '<=', $r->to_date);
        }$all = $q->get();
        $monthly = $all->groupBy(fn ($x) => substr($x->created_at, 0, 7))->map(function ($items, $month) {
            $closed = $items->where('status', 'CLOSED')->filter(fn ($x) => $x->closed_at);

            return ['month' => $month, 'total_jobs' => $items->count(), 'closed_jobs' => $closed->count(), 'avg_days_to_close' => $closed->count() ? round($closed->avg(fn ($x) => (strtotime($x->closed_at) - strtotime($x->created_at)) / 86400), 1) : 0];
        })->values()->sortByDesc('month')->values();
        $open = $all->whereIn('status', ['OPEN', 'IN PROGRESS'])->map(function ($x) {
            $x->age_days = (time() - strtotime($x->created_at)) / 86400;

            return $x;
        })->values();

        return response()->json(['success' => true, 'data' => ['department' => $dept, 'monthly_performance' => $monthly, 'open_jobs' => $open, 'summary' => ['total_open' => $open->where('status', 'OPEN')->count(), 'total_in_progress' => $open->where('status', 'IN PROGRESS')->count()]]]);
    }

    public function generalShiftCsv(Request $r)
    {
        $rows = $this->gsQuery($r)->leftJoin('users as u1', 'u1.id', '=', 'g.raised_by')->leftJoin('users as u2', 'u2.id', '=', 'g.closed_by')->select('g.*', 'u1.full_name as raised_by_name', 'u2.full_name as closed_by_name')->orderByDesc('g.created_at')->get();
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['Job Number', 'Title', 'Description', 'Status', 'Department', 'Plant', 'Created At', 'Started At', 'Completed At', 'Closed At', 'Raised By', 'Closed By', 'Completion Days']);
        foreach ($rows as $x) {
            fputcsv($fh,[$x->job_number, $x->title, $x->description, $x->status, $x->department, $x->plant, $x->created_at, $x->started_at, $x->completed_at, $x->closed_at, $x->raised_by_name, $x->closed_by_name, $x->closed_at ? round((strtotime($x->closed_at) - strtotime($x->created_at)) / 86400,2) : '']);
        }rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return response($csv,200,['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename=general_shift_jobs_'.now()->format('Y-m-d').'.csv']);
    }
}
