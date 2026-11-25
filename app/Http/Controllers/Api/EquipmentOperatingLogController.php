<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EquipmentOperatingLogController extends Controller
{
    /**
     * Save RUNNING status and RPM/Load values together.
     */

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'equipment_id' => 'required|integer|exists:equipment,id',
            'shift'        => 'required|in:A,B,C,G',

            'actual_rpm'   => 'required|numeric|min:0',
            'actual_load'  => 'required|numeric|min:0',

            'remarks'      => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        try {

            // Only insert into equipment_operating_logs
            $logId = DB::table('equipment_operating_logs')->insertGetId([
                'equipment_id'  => $request->equipment_id,

                // No shift_logs record is created
                'shift_log_id'  => null,

                // Only two operating values
                'standard_rpm'  => null,
                'actual_rpm'    => $request->actual_rpm,

                'standard_load' => null,
                'actual_load'   => $request->actual_load,

                'logged_by'     => $user->id,
                'shift'         => $request->shift,
                'remarks'       => $request->remarks,

                'logged_at'     => now(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $operatingLog = DB::table('equipment_operating_logs')
                ->where('id', $logId)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'RPM and Load operating log saved successfully.',
                'data'    => $operatingLog,
            ], 201);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to save operating log.',
            ], 500);
        }
    }


    /**
     * Get latest operating log for each equipment.
     */
    public function latest(Request $request)
    {
        $latestIds = DB::table('equipment_operating_logs')
            ->selectRaw('MAX(id) as id')
            ->groupBy('equipment_id');

        $query = DB::table('equipment_operating_logs as logs')
            ->joinSub($latestIds, 'latest', function ($join) {
                $join->on('logs.id', '=', 'latest.id');
            })
            ->leftJoin('users as u', 'logs.logged_by', '=', 'u.id')
            ->select(
                'logs.id',
                'logs.equipment_id',
                'logs.shift_log_id',
                'logs.standard_rpm',
                'logs.actual_rpm',
                'logs.standard_load',
                'logs.actual_load',
                'logs.logged_by',
                'u.full_name as logged_by_name',
                'logs.shift',
                'logs.remarks',
                'logs.logged_at'
            );

        if ($request->filled('equipment_id')) {
            $query->where(
                'logs.equipment_id',
                $request->equipment_id
            );
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('logs.equipment_id')->get(),
        ]);
    }
    


public function history($equipment)
    {
        try {
            $equipmentExists = DB::table('equipment')
                ->where('id', $equipment)
                ->exists();

            if (! $equipmentExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipment not found.',
                ], 404);
            }

            // 1. Operating logs (RUNNING / logged RPM & Load)
            $opLogs = DB::table('equipment_operating_logs as logs')
                ->leftJoin('users as users', 'users.id', '=', 'logs.logged_by')
                ->leftJoin('shift_logs as sl', 'sl.id', '=', 'logs.shift_log_id')
                ->leftJoin('stop_reasons as sr', 'sl.stop_reason_id', '=', 'sr.id')
                ->where('logs.equipment_id', $equipment)
                ->select([
                    'logs.id',
                    'logs.equipment_id',
                    'logs.shift_log_id',
                    'logs.standard_rpm',
                    'logs.actual_rpm',
                    'logs.standard_load',
                    'logs.actual_load',
                    'logs.shift',
                    'logs.remarks',
                    'logs.logged_by',
                    'logs.logged_at',
                    'users.full_name as logged_by_name',
                    'sr.description as stop_reason',
                    DB::raw("CASE WHEN sl.status IS NOT NULL THEN sl.status WHEN logs.actual_rpm IS NULL AND logs.actual_load IS NULL THEN 'STOPPED' ELSE 'RUNNING' END as status"),
                ])
                ->get();

            $linkedShiftIds = $opLogs->pluck('shift_log_id')->filter()->map(fn ($v) => (int) $v)->all();

            // 2. STOPPED shift_logs for this equipment (so every STOPPED log shows with reason, remarks & shift_log_id for Reply chat)
            $stopQuery = DB::table('shift_logs as sl')
                ->leftJoin('users as u', 'u.id', '=', 'sl.operator_id')
                ->leftJoin('stop_reasons as sr', 'sr.id', '=', 'sl.stop_reason_id')
                ->where('sl.equipment_id', $equipment)
                ->where('sl.status', 'STOPPED');

            if (! empty($linkedShiftIds)) {
                $stopQuery->whereNotIn('sl.id', $linkedShiftIds);
            }

            $stopLogs = $stopQuery
                ->select([
                    DB::raw('NULL as id'),
                    'sl.equipment_id',
                    DB::raw('sl.id as shift_log_id'),
                    DB::raw('NULL as standard_rpm'),
                    DB::raw('NULL as actual_rpm'),
                    DB::raw('NULL as standard_load'),
                    DB::raw('NULL as actual_load'),
                    'sl.shift',
                    'sl.remarks',
                    DB::raw('sl.operator_id as logged_by'),
                    'sl.logged_at',
                    'u.full_name as logged_by_name',
                    'sr.description as stop_reason',
                    DB::raw("'STOPPED' as status"),
                ])
                ->get();

            $merged = $opLogs->concat($stopLogs)->map(function ($row) {
                $reason = trim((string) ($row->stop_reason ?? ''));
                $remark = trim((string) ($row->remarks ?? ''));

                if (strtoupper((string) $row->status) === 'STOPPED') {
                    $parts = [];
                    if ($reason !== '') {
                        $parts[] = 'Reason: ' . $reason;
                    }
                    if ($remark !== '') {
                        $parts[] = 'Remark: ' . $remark;
                    }
                    $row->remarks = $parts ? implode(' | ', $parts) : '—';
                } else {
                    $row->remarks = $remark !== '' ? $remark : '—';
                }

                return $row;
            })->sortByDesc(function ($row) {
                $ts = strtotime((string) ($row->logged_at ?? ''));
                return $ts !== false ? $ts : 0;
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Operating log history fetched successfully.',
                'data' => $merged,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch operating log history.',
            ], 500);
        }
    }




}