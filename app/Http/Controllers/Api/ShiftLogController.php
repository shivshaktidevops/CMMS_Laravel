<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftLogController extends Controller
{
    public function stopReasons()
    {
        return response()->json(['success' => true, 'data' => DB::table('stop_reasons')->select('id', 'code', 'description', 'category')->where('active', 1)->orderBy('category')->orderBy('description')->get()]);
    }

    public function index(Request $r)
    {
        $q = DB::table('shift_logs as sl')->join('equipment as e', 'sl.equipment_id', '=', 'e.id')->join('users as u', 'sl.operator_id', '=', 'u.id')->leftJoin('stop_reasons as sr', 'sl.stop_reason_id', '=', 'sr.id')->select('sl.*', 'e.name as equipment_name', 'e.code as equipment_code', 'u.full_name as operator_name', 'sr.description as stop_reason');
        if ($r->date) {
            $q->where('sl.shift_date', $r->date);
        }if ($r->equipment_id) {
            $q->where('sl.equipment_id', $r->equipment_id);
        }if ($r->shift) {
            $q->where('sl.shift', $r->shift);
        }

        return response()->json(['success' => true, 'data' => $q->orderByDesc('sl.logged_at')->limit(200)->get()]);
    }

    public function store_backup(Request $r)
    {
        $equipmentId = $r->equipment_id;
        $status = $r->status;
        $shift = $r->shift;
        $reason = $r->stop_reason_id;
        if (! $equipmentId || ! $status || ! $shift) {
            return response()->json(['success' => false, 'message' => 'equipment_id, status, and shift are required'], 400);
        }if (! in_array($status, ['RUNNING', 'STOPPED'])) {
            return response()->json(['success' => false, 'message' => 'Invalid status'], 400);
        }if ($status === 'STOPPED' && ! $reason) {
            return response()->json(['success' => false, 'message' => 'Stop reason is required when reporting STOPPED'], 400);
        }if (! in_array($shift, ['A', 'B', 'C', 'G'])) {
            return response()->json(['success' => false, 'message' => 'Invalid shift'], 400);
        }
        try {
            $result = DB::transaction(function () use ($r, $equipmentId, $status, $shift, $reason) {
                $eq = DB::table('equipment')->where('id', $equipmentId)->where('active', 1)->lockForUpdate()->first();
                if (! $eq) {
                    throw new \RuntimeException('404|Equipment not found');
                }if ($eq->current_status === $status) {
                    throw new \RuntimeException('409|Equipment is already '.$status.'. Log a different status.');
                }if ($status === 'RUNNING') {
                    $active = DB::table('jobs')->where('equipment_id', $equipmentId)->where('status', '!=', 'CLOSED')->orderByDesc('created_at')->first(['job_number', 'title', 'status']);
                    if ($active) {
                        throw new \RuntimeException('400|Cannot mark RUNNING — active job '.$active->job_number.' ('.$active->status.') must be CLOSED first.');
                    }
                }if ($reason && ! DB::table('stop_reasons')->where('id', $reason)->where('active', 1)->exists()) {
                    throw new \RuntimeException('400|Invalid stop reason');
                }$id = DB::table('shift_logs')->insertGetId(['equipment_id' => $equipmentId, 'operator_id' => $r->user()->id, 'shift' => $shift, 'shift_date' => today()->toDateString(), 'status' => $status, 'stop_reason_id' => $reason, 'remarks' => $r->remarks, 'logged_at' => now()]);
                DB::table('equipment')->where('id', $equipmentId)->update(['current_status' => $status, 'updated_at' => now()]);
                $bd = null;
                if ($status === 'STOPPED' && ! DB::table('breakdowns')->where('equipment_id', $equipmentId)->where('status', 'OPEN')->exists()) {
                    $seq = DB::table('breakdowns')->whereDate('start_time', today())->count() + 1;
                    $num = 'BD-'.now()->format('Ymd').'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
                    $bdId = DB::table('breakdowns')->insertGetId(['bd_number' => $num, 'equipment_id' => $equipmentId, 'shift_log_id' => $id, 'initial_reason_id' => $reason, 'start_time' => now(), 'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now()]);
                    $bd = DB::table('breakdowns')->find($bdId);
                }

            return ['shift_log' => DB::table('shift_logs')->find($id), 'breakdown' => $bd];
            });
            AuditService::log($r->user()->id, 'LOG_'.$status, 'shift_logs', $result['shift_log']->id, ['equipment_id' => $equipmentId, 'status' => $status, 'stop_reason_id' => $reason, 'breakdown_id' => $result['breakdown']->id ?? null], $r->ip());

            return response()->json(['success' => true, 'message' => $status === 'STOPPED' ? 'Equipment stopped. Breakdown '.($result['breakdown']->bd_number ?? '').' created.' : 'Equipment marked as RUNNING.', 'data' => $result], 201);
        } catch (\RuntimeException $e) {
            [$c,$m] = array_pad(explode('|', $e->getMessage(), 2), 2, '');

            return response()->json(['success' => false, 'message' => $m ?: $e->getMessage()], ctype_digit($c) ? (int) $c : 500);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()],500);
        }
    }


 public function store(Request $r)
    {
        $equipmentId = $r->equipment_id;
        $status = $r->status;
        $shift = $r->shift;
        $reason = $r->stop_reason_id;
        if (! $equipmentId || ! $status || ! $shift) {
            return response()->json(['success' => false, 'message' => 'equipment_id, status, and shift are required'], 400);
        }if (! in_array($status, ['RUNNING', 'STOPPED'])) {
            return response()->json(['success' => false, 'message' => 'Invalid status'], 400);
        }if ($status === 'STOPPED' && ! $reason) {
            return response()->json(['success' => false, 'message' => 'Stop reason is required when reporting STOPPED'], 400);
        }if (! in_array($shift, ['A', 'B', 'C', 'G'])) {
            return response()->json(['success' => false, 'message' => 'Invalid shift'], 400);
        }
        try {
            $result = DB::transaction(function () use ($r, $equipmentId, $status, $shift, $reason) {
                $eq = DB::table('equipment')->where('id', $equipmentId)->where('active', 1)->lockForUpdate()->first();
                if (! $eq) {
                    throw new \RuntimeException('404|Equipment not found');
                }if ($eq->current_status === $status) {
                    throw new \RuntimeException('409|Equipment is already '.$status.'. Log a different status.');
                }if ($status === 'RUNNING') {
                    $active = DB::table('jobs')->where('equipment_id', $equipmentId)->where('status', '!=', 'CLOSED')->orderByDesc('created_at')->first(['job_number', 'title', 'status']);
                    if ($active) {
                        throw new \RuntimeException('400|Cannot mark RUNNING — active job '.$active->job_number.' ('.$active->status.') must be CLOSED first.');
                    }
                }if ($reason && ! DB::table('stop_reasons')->where('id', $reason)->where('active', 1)->exists()) {
                    throw new \RuntimeException('400|Invalid stop reason');
                }$id = DB::table('shift_logs')->insertGetId(['equipment_id' => $equipmentId, 'operator_id' => $r->user()->id, 'shift' => $shift, 'shift_date' => today()->toDateString(), 'status' => $status, 'stop_reason_id' => $reason, 'remarks' => $r->remarks, 'logged_at' => now()]);
                DB::table('equipment')->where('id', $equipmentId)->update(['current_status' => $status, 'updated_at' => now()]);
                $bd = null;
                if ($status === 'STOPPED' && ! DB::table('breakdowns')->where('equipment_id', $equipmentId)->where('status', 'OPEN')->exists()) {
                    $seq = DB::table('breakdowns')->whereDate('start_time', today())->count() + 1;
                    $num = 'BD-'.now()->format('Ymd').'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
                    $bdId = DB::table('breakdowns')->insertGetId(['bd_number' => $num, 'equipment_id' => $equipmentId, 'shift_log_id' => $id, 'initial_reason_id' => $reason, 'start_time' => now(), 'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now()]);
                    $bd = DB::table('breakdowns')->find($bdId);
                }

                return ['shift_log' => DB::table('shift_logs')->find($id), 'breakdown' => $bd];
            });
            AuditService::log($r->user()->id, 'LOG_'.$status, 'shift_logs', $result['shift_log']->id, ['equipment_id' => $equipmentId, 'status' => $status, 'stop_reason_id' => $reason, 'breakdown_id' => $result['breakdown']->id ?? null], $r->ip());

            return response()->json(['success' => true, 'message' => $status === 'STOPPED' ? 'Equipment stopped. Breakdown '.($result['breakdown']->bd_number ?? '').' created.' : 'Equipment marked as RUNNING.', 'data' => $result], 201);
        } catch (\RuntimeException $e) {
            [$c,$m] = array_pad(explode('|', $e->getMessage(), 2), 2, '');

            return response()->json(['success' => false, 'message' => $m ?: $e->getMessage()], ctype_digit($c) ? (int) $c : 500);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()],500);
        }
    }



    public function getReplies(Request $r, $id)
    {
        $log = DB::table('shift_logs')
            ->where('id', $id)
            ->first(['id', 'equipment_id', 'status', 'remarks', 'replies']);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Shift log not found',
            ], 404);
        }

        $replies = $log->replies
            ? json_decode($log->replies, true)
            : [];

        return response()->json([
            'success' => true,
            'data' => [
                'shift_log' => $log,
                'replies' => $replies,
            ],
        ]);
    }

    public function storeReply(Request $r, $id)
    {
        $validator = \Validator::make($r->all(), [
            'message' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($r, $id) {

                $log = DB::table('shift_logs')
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (!$log) {
                    throw new \RuntimeException('404|Shift log not found');
                }

                $user = $r->user();

                $replies = $log->replies
                    ? json_decode($log->replies, true)
                    : [];

                if (!is_array($replies)) {
                    $replies = [];
                }

                $newReply = [
                    'user_id' => $user->id,
                    'user_name' => $user->full_name ?? $user->username,
                    'role' => $user->role,
                    'message' => trim($r->message),
                    'sent_at' => now()->format('Y-m-d H:i:s'),
                ];

                $replies[] = $newReply;

                DB::table('shift_logs')
                    ->where('id', $id)
                    ->update([
                        'replies' => json_encode(
                            $replies,
                            JSON_UNESCAPED_UNICODE
                        ),
                    ]);

                return $newReply;
            });

            return response()->json([
                'success' => true,
                'message' => 'Reply sent successfully',
                'data' => $result,
            ], 201);

        } catch (\RuntimeException $e) {

            [$code, $message] = array_pad(
                explode('|', $e->getMessage(), 2),
                2,
                ''
            );

            return response()->json([
                'success' => false,
                'message' => $message ?: $e->getMessage(),
            ], ctype_digit($code) ? (int) $code : 500);

        } catch (\Throwable $e) {

            \Log::error('Shift Log Reply Error', [
                'shift_log_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to send reply',
            ], 500);
        }
    }


}
