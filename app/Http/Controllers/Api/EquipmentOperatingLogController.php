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

	        // Check equipment exists
	        $equipmentExists = DB::table('equipment')
	            ->where('id', $equipment)
	            ->exists();

	        if (!$equipmentExists) {
	            return response()->json([
	                'success' => false,
	                'message' => 'Equipment not found.',
	            ], 404);
	        }

	        // Fetch all logs of selected equipment
	        $logs = DB::table('equipment_operating_logs as logs')
	            ->leftJoin('users as users', 'users.id', '=', 'logs.logged_by')
	            ->where('logs.equipment_id', $equipment)
	            ->select([
	                'logs.id',
	                'logs.equipment_id',
	                'logs.standard_rpm',
	                'logs.actual_rpm',
	                'logs.standard_load',
	                'logs.actual_load',
	                'logs.shift',
	                'logs.remarks',
	                'logs.logged_by',
	                'logs.logged_at',
	                'users.full_name as logged_by_name',
	            ])
	            ->orderByDesc('logs.logged_at')
	            ->orderByDesc('logs.id')
	            ->get();

	        return response()->json([
	            'success' => true,
	            'message' => 'Operating log history fetched successfully.',
	            'data' => $logs,
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