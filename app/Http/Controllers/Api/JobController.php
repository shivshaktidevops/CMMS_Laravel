<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobController extends Controller
{
    private function nextNumber(string $plant): string
    {
        $n = DB::table('jobs')->where('plant', $plant)->count() + 1;

        return strtoupper(preg_replace('/\s+/', '', $plant)).'-JOB-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private function row(int $id)
    {
        $j = DB::table('jobs as j')->leftJoin('equipment as e', 'e.id', '=', 'j.equipment_id')->leftJoin('users as u1', 'u1.id', '=', 'j.raised_by')->leftJoin('users as u2', 'u2.id', '=', 'j.assigned_to')->where('j.id', $id)->select('j.*', 'e.name as equipment_name', 'e.code as equipment_code', 'e.department as equipment_dept', 'u1.full_name as raised_by_name', 'u2.full_name as assigned_to_name')->first();
        if ($j) {
            $j->photos = is_string($j->photos) ? (json_decode($j->photos, true) ?: []) : ($j->photos ?? []);
            $j->assignments = DB::table('job_assignments')->where('job_id', $id)->orderBy('department')->get();
        }

return $j;
    }

    public function store(Request $r)
    {
        if (! $r->title) {
            return response()->json(['success' => false, 'message' => 'Title is required'], 400);
        }if (! $r->plant) {
            return response()->json(['success' => false, 'message' => 'Plant is required'], 400);
        }if (! $r->shift) {
            return response()->json(['success' => false, 'message' => 'Shift is required'], 400);
        }$num = $this->nextNumber($r->plant);
        $id = DB::table('jobs')->insertGetId(['job_number' => $num, 'job_type_id' => $r->job_type_id, 'priority_id' => $r->priority_id, 'title' => $r->title, 'description' => $r->description, 'equipment_id' => $r->equipment_id, 'remarks' => $r->remarks, 'plant' => $r->plant, 'shift' => $r->shift, 'raised_by' => $r->user()->id, 'raised_by_dept' => $r->raised_by_dept, 'status' => 'OPEN', 'photos' => json_encode($r->photos ?: []), 'source_type' => $r->source_type, 'source_id' => $r->source_id, 'estimated_minutes' => $r->estimated_minutes, 'benchmark_minutes' => $r->benchmark_minutes, 'created_by_system' => false, 'created_at' => now()]);
        DB::table('job_history')->insert(['job_id' => $id, 'action' => 'OPEN', 'to_dept' => $r->raised_by_dept, 'done_by' => $r->user()->id, 'done_by_name' => $r->user()->full_name, 'remarks' => $r->remarks, 'created_at' => now()]);
        AuditService::log($r->user()->id, 'CREATE_JOB', 'jobs', $id, ['job_number' => $num, 'title' => $r->title, 'plant' => $r->plant, 'shift' => $r->shift], $r->ip());

        return response()->json(['success' => true, 'data' => $this->row($id), 'message' => 'Job raised successfully'], 201);
    }

    public function index(Request $r)
    {
        $q = DB::table('jobs as j')->leftJoin('equipment as e', 'e.id', '=', 'j.equipment_id')->leftJoin('users as u1', 'u1.id', '=', 'j.raised_by')->leftJoin('users as u2', 'u2.id', '=', 'j.assigned_to')->select('j.*', 'e.name as equipment_name', 'e.code as equipment_code', 'e.department as equipment_dept', 'u1.full_name as raised_by_name', 'u2.full_name as assigned_to_name');
        $u = $r->user();
        if ($u->role === 'operator') {
            $q->where(function ($x) use ($u) {
                $x->where('j.raised_by', $u->id)->orWhere('j.assigned_dept', $u->department)->orWhere('j.raised_by_dept', $u->department)->orWhereExists(function ($s) use ($u) {
                    $s->selectRaw('1')->from('job_assignments as ja')->whereColumn('ja.job_id', 'j.id')->where('ja.department', $u->department);
                });
            });
        }if ($u->role === 'maintenance') {
            $q->where(function ($x) use ($u) {
                $x->where('j.assigned_dept', $u->department)->orWhereExists(function ($s) use ($u) {
                    $s->selectRaw('1')->from('job_assignments as ja')->whereColumn('ja.job_id', 'j.id')->where('ja.department', $u->department);
                });
            });
        }if ($r->plant) {
            $q->where('j.plant', $r->plant);
        }if ($r->status) {
            $q->where('j.status', $r->status);
        }if ($r->shift) {
            $q->where('j.shift', $r->shift);
        }if ($r->dept) {
            $q->where(function ($x) use ($r) {
                $x->where('j.assigned_dept', $r->dept)->orWhere('j.raised_by_dept', $r->dept);
            });
        }$rows = $q->orderByDesc('j.created_at')->limit(500)->get();
        foreach ($rows as $j) {
            $j->photos = is_string($j->photos) ? (json_decode($j->photos, true) ?: []) : ($j->photos ?? []);
            $j->assignments = DB::table('job_assignments')->where('job_id', $j->id)->orderBy('department')->get();
        }

return response()->json(['success' => true, 'data' => $rows]);
    }

    public function show($id)
    {
        $j = $this->row((int) $id);
        if (! $j) {
            return response()->json(['success' => false, 'message' => 'Job not found'], 404);
        }

return response()->json(['success' => true, 'data' => $j]);
    }

    public function assign(Request $r, $id)
    {
        $list = is_array($r->departments) && count($r->departments) ? $r->departments : ($r->assigned_dept ? [$r->assigned_dept] : []);
        if (! $list) {
            return response()->json(['success' => false, 'message' => 'At least one department is required'], 400);
        }$job = DB::table('jobs')->find($id);
        if (! $job) {
            return response()->json(['success' => false, 'message' => 'Job not found'], 404);
        }if (! in_array($job->status, ['OPEN', 'REASSIGNED'])) {
            return response()->json(['success' => false, 'message' => 'Job is not in OPEN or REASSIGNED status'], 400);
        }DB::transaction(function () use ($r, $id, $list) {
            DB::table('jobs')->where('id', $id)->update(['assigned_dept' => $list[0], 'assigned_to' => $r->assigned_to, 'assigned_at' => now(), 'status' => 'ASSIGNED', 'is_multi_assign' => count($list) > 1, 'remarks' => $r->remarks ?: DB::raw('remarks')]);
            DB::table('job_assignments')->where('job_id', $id)->delete();
            foreach ($list as $d) {
                DB::table('job_assignments')->insert(['job_id' => $id, 'department' => $d, 'status' => 'ASSIGNED', 'assigned_by' => $r->user()->id, 'remarks' => $r->remarks, 'assigned_at' => now()]);
            }DB::table('job_history')->insert(['job_id' => $id, 'action' => 'ASSIGNED', 'to_dept' => implode(', ', $list), 'done_by' => $r->user()->id, 'done_by_name' => $r->user()->full_name, 'remarks' => $r->remarks, 'created_at' => now()]);
        });
        AuditService::log($r->user()->id, 'ASSIGN_JOB', 'jobs', (int) $id, ['departments' => $list, 'isMulti' => count($list) > 1], $r->ip());

        return response()->json(['success' => true, 'data' => $this->row((int) $id), 'message' => count($list) > 1 ? 'Job assigned to '.count($list).' departments: '.implode(', ', $list) : 'Job assigned to '.$list[0]]);
    }

    public function deptStatus(Request $r, $id)
    {
        if (! $r->department) {
            return response()->json(['success' => false, 'message' => 'department is required'], 400);
        }if (! in_array($r->status, ['IN PROGRESS', 'COMPLETED'])) {
            return response()->json(['success' => false, 'message' => 'Status must be IN PROGRESS or COMPLETED'], 400);
        }if ($r->user()->role !== 'admin' && $r->user()->department !== $r->department) {
            return response()->json(['success' => false, 'message' => 'You can only update your own department ('.$r->user()->department.'), not '.$r->department], 403);
        }$data = ['status' => $r->status];
        if ($r->remarks) {
            $data['remarks'] = $r->remarks;
        }if ($r->status === 'IN PROGRESS') {
            $data['started_at'] = now();
        } else {
            $data['completed_at'] = now();
            $data['completed_by'] = $r->user()->id;
        }$n = DB::table('job_assignments')->where('job_id', $id)->where('department', $r->department)->update($data);
        if (! $n) {
            return response()->json(['success' => false, 'message' => 'Assignment not found for this department'], 404);
        }$all = DB::table('job_assignments')->where('job_id', $id)->pluck('status');
        $allDone = $all->count() > 0 && $all->every(fn ($s) => $s === 'COMPLETED');
        $new = null;
        if ($allDone) {
            $new = 'COMPLETED';
            DB::table('jobs')->where('id', $id)->update(['status' => 'COMPLETED', 'completed_at' => now(), 'progress_percentage' => 100]);
        } elseif ($r->status === 'IN PROGRESS') {
            $new = 'IN PROGRESS';
            DB::table('jobs')->where('id', $id)->where('status', 'ASSIGNED')->update(['status' => 'IN PROGRESS', 'started_at' => now()]);
        }DB::table('job_history')->insert(['job_id' => $id, 'action' => $r->status, 'from_dept' => $r->department, 'to_dept' => $r->department, 'done_by' => $r->user()->id, 'done_by_name' => $r->user()->full_name, 'remarks' => $r->remarks, 'created_at' => now()]);

        return response()->json(['success' => true, 'message' => $allDone ? 'All departments completed — job marked COMPLETED' : $r->department.' updated to '.$r->status, 'job_status' => $new, 'all_completed' => $allDone]);
    }

    public function status(Request $r, $id)
    {
        if (! in_array($r->status, ['IN PROGRESS', 'COMPLETED', 'CLOSED'])) {
            return response()->json(['success' => false, 'message' => 'Status must be one of: IN PROGRESS, COMPLETED, CLOSED'], 400);
        }$job = DB::table('jobs')->find($id);
        if (! $job) {
            return response()->json(['success' => false, 'message' => 'Job not found'], 404);
        }if ($job->is_multi_assign && $r->status !== 'CLOSED') {
            return response()->json(['success' => false, 'message' => 'For multi-department jobs, use /dept-status to update individual department progress'], 400);
        }if ($job->is_multi_assign && $r->status === 'CLOSED') {
            $pending = DB::table('job_assignments')->where('job_id', $id)->where('status', '!=', 'COMPLETED')->pluck('department');
            if ($pending->count()) {
                return response()->json(['success' => false, 'message' => 'Cannot close — these departments have not completed yet: '.$pending->join(', ')], 400);
            }
        } $data = ['status' => $r->status];
        if ($r->remarks) {
            $data['remarks'] = $r->remarks;
        }if ($r->status === 'IN PROGRESS' && ! $job->started_at) {
            $data['started_at'] = now();
        }if ($r->status === 'COMPLETED') {
            $data['completed_at'] = now();
            $data['progress_percentage'] = 100;
        }if ($r->status === 'CLOSED') {
            $data['closed_at'] = now();
        }DB::table('jobs')->where('id', $id)->update($data);
        DB::table('job_history')->insert(['job_id' => $id, 'action' => $r->status, 'from_dept' => $job->assigned_dept, 'to_dept' => $job->assigned_dept, 'done_by' => $r->user()->id, 'done_by_name' => $r->user()->full_name, 'remarks' => $r->remarks, 'created_at' => now()]);
        AuditService::log($r->user()->id, 'JOB_'.str_replace(' ', '_', $r->status), 'jobs', (int) $id, ['status' => $r->status], $r->ip());

        return response()->json(['success' => true, 'data' => $this->row((int) $id), 'message' => 'Job status updated to '.$r->status]);
    }

    public function reassign(Request $r, $id)
    {
        if (! $r->to_dept) {
            return response()->json(['success' => false, 'message' => 'to_dept is required'], 400);
        }$job = DB::table('jobs')->find($id);
        if (! $job) {
            return response()->json(['success' => false, 'message' => 'Job not found'], 404);
        }DB::transaction(function () use ($r, $id, $job) {
            DB::table('jobs')->where('id', $id)->update(['status' => 'REASSIGNED', 'assigned_dept' => $r->to_dept, 'is_multi_assign' => 0, 'reassigned_at' => now(), 'reassigned_by' => $r->user()->id, 'reassigned_from_dept' => $job->assigned_dept, 'reassign_reason' => $r->remarks, 'remarks' => $r->remarks ?: $job->remarks]);
            DB::table('job_assignments')->where('job_id', $id)->delete();
            DB::table('job_history')->insert(['job_id' => $id, 'action' => 'REASSIGNED', 'from_dept' => $job->assigned_dept, 'to_dept' => $r->to_dept, 'done_by' => $r->user()->id, 'done_by_name' => $r->user()->full_name, 'remarks' => $r->remarks, 'created_at' => now()]);
        });
        AuditService::log($r->user()->id, 'JOB_REASSIGNED', 'jobs', (int) $id, ['from' => $job->assigned_dept, 'to' => $r->to_dept], $r->ip());

        return response()->json(['success' => true, 'data' => $this->row((int) $id), 'message' => 'Job reassigned to '.$r->to_dept]);
    }

    public function history($id)
    {
        return response()->json(['success' => true, 'data' => DB::table('job_history')->where('job_id',$id)->orderBy('created_at')->get()]);
    }

    public function assignments($id)
    {
        return response()->json(['success' => true, 'data' => DB::table('job_assignments as ja')->leftJoin('users as u1','u1.id','=','ja.assigned_by')->leftJoin('users as u2','u2.id','=','ja.completed_by')->where('ja.job_id',$id)->select('ja.*','u1.full_name as assigned_by_name','u2.full_name as completed_by_name')->orderBy('ja.department')->get()]);
    }
}
