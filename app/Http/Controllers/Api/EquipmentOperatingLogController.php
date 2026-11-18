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
            'shift' => 'required|in:A,B,C,G',

            'standard_rpm' => 'nullable|numeric|min:0',
            'actual_rpm' => 'required|numeric|min:0',

            'standard_load' => 'nullable|numeric|min:0',
            'actual_load' => 'required|numeric|min:0',

            'remarks' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
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
            $result = DB::transaction(function () use ($request, $user) {

                // Lock equipment while processing
                $equipment = DB::table('equipment')
                    ->where('id', $request->equipment_id)
                    ->where('active', 1)
                    ->lockForUpdate()
                    ->first();

                if (!$equipment) {
                    throw new \RuntimeException(
                        '404|Equipment not found or inactive'
                    );
                }

                // Do not create duplicate RUNNING log
                if ($equipment->current_status === 'RUNNING') {
                    throw new \RuntimeException(
                        '409|Equipment is already RUNNING'
                    );
                }

                // Existing active job check
                $activeJob = DB::table('jobs')
                    ->where('equipment_id', $equipment->id)
                    ->where('status', '!=', 'CLOSED')
                    ->orderByDesc('created_at')
                    ->first(['job_number', 'status']);

                if ($activeJob) {
                    throw new \RuntimeException(
                        '400|Cannot mark RUNNING. Active job '
                        . $activeJob->job_number
                        . ' must be CLOSED first.'
                    );
                }

                // 1. Create shift log first
                $shiftLogId = DB::table('shift_logs')->insertGetId([
                    'equipment_id' => $equipment->id,
                    'operator_id' => $user->id,
                    'shift' => $request->shift,
                    'shift_date' => today()->toDateString(),
                    'status' => 'RUNNING',
                    'stop_reason_id' => null,
                    'remarks' => $request->remarks,
                    'logged_at' => now(),
                ]);

                // 2. Save RPM and Load details
                $operatingLogId = DB::table(
                    'equipment_operating_logs'
                )->insertGetId([
                    'equipment_id' => $equipment->id,
                    'shift_log_id' => $shiftLogId,

                    'standard_rpm' => $request->standard_rpm,
                    'actual_rpm' => $request->actual_rpm,

                    'standard_load' => $request->standard_load,
                    'actual_load' => $request->actual_load,

                    'logged_by' => $user->id,
                    'shift' => $request->shift,
                    'remarks' => $request->remarks,

                    'logged_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 3. Update equipment current status
                DB::table('equipment')
                    ->where('id', $equipment->id)
                    ->update([
                        'current_status' => 'RUNNING',
                        'updated_at' => now(),
                    ]);

                $operatingLog = DB::table('equipment_operating_logs')
                    ->where('id', $operatingLogId)
                    ->first();

                return [
                    'shift_log_id' => $shiftLogId,
                    'operating_log' => $operatingLog,
                ];
            });

            // Audit entry
            AuditService::log(
                $user->id,
                'LOG_RUNNING_WITH_OPERATING_VALUES',
                'equipment_operating_logs',
                $result['operating_log']->id,
                [
                    'equipment_id' => $request->equipment_id,
                    'shift_log_id' => $result['shift_log_id'],
                    'shift' => $request->shift,
                    'actual_rpm' => $request->actual_rpm,
                    'actual_load' => $request->actual_load,
                ],
                $request->ip()
            );

            return response()->json([
                'success' => true,
                'message' => 'Equipment marked RUNNING and operating values saved successfully.',
                'data' => $result,
            ], 201);

        } catch (\RuntimeException $e) {
            $parts = explode('|', $e->getMessage(), 2);

            $statusCode = isset($parts[1]) && is_numeric($parts[0])
                ? (int) $parts[0]
                : 500;

            return response()->json([
                'success' => false,
                'message' => $parts[1] ?? $e->getMessage(),
            ], $statusCode);

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
}