<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GeneralShiftJobController extends Controller
{
    private function guard(Request $r)
    {
        if ($r->user()->role === 'admin') {
            return null;
        }$h = (int) Carbon::now('Asia/Kolkata')->format('H');

        return ($h >= 9 && $h < 18) ? null : response()->json(['success' => false, 'message' => 'General Shift Jobs can only be accessed between 9:00 AM and 6:00 PM IST'], 403);
    }

    private function nextNumber($plant, $dept)
    {
        $count = DB::table('general_shift_jobs')->where('plant', $plant)->whereYear('created_at', now()->year)->count() + 1;

        return strtoupper(preg_replace('/\s+/', '', $plant ?: 'CMMS')).'-'.strtoupper(substr($dept ?: 'GEN', 0, 3)).'-GSJ-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    public function index(Request $r)
    {
        if ($g = $this->guard($r)) {
            return $g;
        }$q = DB::table('general_shift_jobs as g')->leftJoin('users as u1', 'u1.id', '=', 'g.raised_by')->leftJoin('users as u2', 'u2.id', '=', 'g.closed_by')->select('g.*', 'u1.full_name as raised_by_name', 'u2.full_name as closed_by_name');
        if ($r->user()->role === 'maintenance') {
            $q->where('g.department', $r->user()->department);
        }if ($r->plant) {
            $q->where('g.plant', $r->plant);
        }if ($r->status) {
            $q->where('g.status', $r->status);
        }if ($r->department) {
            $q->where('g.department', $r->department);
        }$rows = $q->orderByRaw("FIELD(g.status,'OPEN','IN PROGRESS','COMPLETED','CLOSED')")->orderByDesc('g.created_at')->limit(200)->get()->map(function ($x) {
            if (is_string($x->photos)) {
                $x->photos = json_decode($x->photos, true) ?: [];
            }

return $x;
        });

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $r)
    {
        if ($g = $this->guard($r)) {
            return $g;
        }if (! $r->title) {
            return response()->json(['success' => false, 'message' => 'Title is required'], 400);
        }if (! $r->plant) {
            return response()->json(['success' => false, 'message' => 'Plant is required'], 400);
        }$dept = $r->user()->department;
        if (! $dept) {
            return response()->json(['success' => false, 'message' => 'User has no department assigned'], 400);
        }$num = $this->nextNumber($r->plant, $dept);
        $id = DB::table('general_shift_jobs')->insertGetId(['job_number' => $num, 'title' => $r->title, 'description' => $r->description, 'department' => $dept, 'plant' => $r->plant, 'expected_completion_date' => $r->expected_completion_date, 'remarks' => $r->remarks, 'photos' => json_encode($r->photos ?: []), 'raised_by' => $r->user()->id, 'raised_by_name' => $r->user()->full_name, 'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now()]);
        AuditService::log($r->user()->id, 'CREATE_GSJ', 'general_shift_jobs', $id, ['job_number' => $num, 'title' => $r->title, 'department' => $dept, 'plant' => $r->plant], $r->ip());

        return response()->json(['success' => true, 'data' => DB::table('general_shift_jobs')->find($id), 'message' => 'General shift job raised successfully'], 201);
    }

    public function status(Request $r, $id)
    {
        if ($g = $this->guard($r)) {
            return $g;
        }if (! in_array($r->status, ['IN PROGRESS', 'COMPLETED', 'CLOSED'])) {
            return response()->json(['success' => false, 'message' => 'Status must be IN PROGRESS, COMPLETED, or CLOSED'], 400);
        }$job = DB::table('general_shift_jobs')->find($id);
        if (! $job) {
            return response()->json(['success' => false, 'message' => 'Job not found'], 404);
        }if ($r->user()->role !== 'admin' && $job->department !== $r->user()->department) {
            return response()->json(['success' => false, 'message' => 'You can only update your own department jobs'], 403);
        }$allowed = ['OPEN' => ['IN PROGRESS'], 'IN PROGRESS' => ['COMPLETED'], 'COMPLETED' => ['CLOSED'], 'CLOSED' => []];
        if (! in_array($r->status, $allowed[$job->status] ?? [])) {
            return response()->json(['success' => false, 'message' => 'Invalid status transition: '.$job->status.' → '.$r->status.'. Allowed: '.implode(', ', $allowed[$job->status] ?? [])], 400);
        }$data = ['status' => $r->status, 'updated_at' => now()];
        if ($r->remarks) {
            $data['remarks'] = $r->remarks;
        }if ($r->status === 'IN PROGRESS') {
            $data['started_at'] = now();
        } elseif ($r->status === 'COMPLETED') {
            $data['completed_at'] = now();
        } else {
            $data['closed_at'] = now();
            $data['closed_by'] = $r->user()->id;
        }DB::table('general_shift_jobs')->where('id', $id)->update($data);
        AuditService::log($r->user()->id, 'GSJ_'.str_replace(' ', '_', $r->status), 'general_shift_jobs', (int) $id, ['status' => $r->status, 'remarks' => $r->remarks], $r->ip());

        return response()->json(['success' => true, 'data' => DB::table('general_shift_jobs')->find($id), 'message' => 'Job updated to '.$r->status]);
    }

    public function show(Request $r, $id)
    {
        if ($g = $this->guard($r)) {
            return $g;
        }$row = DB::table('general_shift_jobs as g')->leftJoin('users as u1', 'u1.id', '=', 'g.raised_by')->leftJoin('users as u2', 'u2.id', '=', 'g.closed_by')->where('g.id', $id)->select('g.*', 'u1.full_name as raised_by_name', 'u2.full_name as closed_by_name')->first();
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Job not found'], 404);
        }if (is_string($row->photos)) {
            $row->photos = json_decode($row->photos, true) ?: [];
        }

return response()->json(['success' => true, 'data' => $row]);
    }

    public function destroy(Request $r, $id)
    {
        if ($g = $this->guard($r)) {
            return $g;
        }DB::table('general_shift_jobs')->where('id',$id)->delete();
        AuditService::log($r->user()->id,'DELETE_GSJ','general_shift_jobs',(int) $id,[],$r->ip());

        return response()->json(['success' => true, 'message' => 'Job deleted']);
    }
}
