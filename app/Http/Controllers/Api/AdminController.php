<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function users()
    {
        return response()->json(['success' => true, 'data' => DB::table('users')->select('id', 'username', 'full_name', 'role', 'department', 'plant', 'active', 'created_at')->orderBy('role')->orderBy('full_name')->get()]);
    }

    public function storeUser(Request $r)
    {
        foreach (['username', 'password', 'full_name', 'role'] as $f) {
            if (! $r->input($f)) {
                return response()->json(['success' => false, 'message' => 'username, password, full_name, role required'], 400);
            }
        }if (! in_array($r->role, ['operator', 'maintenance', 'management', 'admin'])) {
            return response()->json(['success' => false, 'message' => 'Invalid role'], 400);
        }try {
            $id = DB::table('users')->insertGetId(['username' => $r->username, 'password' => Hash::make($r->password), 'full_name' => $r->full_name, 'role' => $r->role, 'department' => $r->department, 'plant' => $r->plant, 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
            AuditService::log($r->user()->id, 'CREATE_USER', 'users', $id, ['username' => $r->username, 'role' => $r->role], $r->ip());

            return response()->json(['success' => true, 'data' => DB::table('users')->select('id', 'username', 'full_name', 'role', 'department', 'plant')->find($id), 'message' => 'User created'], 201);
        } catch (\Throwable $e) {
            $s = str_contains(strtolower($e->getMessage()), 'duplicate') ? 409 : 500;

            return response()->json(['success' => false, 'message' => $s === 409 ? 'Username already exists' : $e->getMessage()], $s);
        }
    }

    public function updateUser(Request $r, $id)
    {
        $data = ['full_name' => $r->full_name, 'role' => $r->role, 'department' => $r->department, 'plant' => $r->plant, 'updated_at' => now()];
        if ($r->filled('active')) {
            $data['active'] = (bool) $r->active;
        }if ($r->filled('password')) {
            $data['password'] = Hash::make($r->password);
        }$n = DB::table('users')->where('id', $id)->update($data);
        if (! $n && ! DB::table('users')->where('id', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

return response()->json(['success' => true, 'data' => DB::table('users')->select('id', 'username', 'full_name', 'role', 'department', 'plant', 'active')->find($id), 'message' => 'User updated successfully']);
    }

    public function resetPassword(Request $r, $id)
    {
        if (! $r->new_password || strlen($r->new_password) < 6) {
            return response()->json(['success' => false, 'message' => 'Password must be 6+ characters'], 400);
        }DB::table('users')->where('id', $id)->update(['password' => Hash::make($r->new_password), 'updated_at' => now()]);
        AuditService::log($r->user()->id, 'RESET_PASSWORD', 'users', (int) $id, [], $r->ip());

        return response()->json(['success' => true, 'message' => 'Password updated']);
    }

    public function deleteUser($id)
    {
        DB::table('users')->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'User deleted']);
    }

    public function stopReasons()
    {
        return response()->json(['success' => true, 'data' => DB::table('stop_reasons')->orderBy('category')->orderBy('description')->get()]);
    }

    public function storeStopReason_backup(Request $r)
    {
        if (! $r->code || ! $r->description) {
            return response()->json(['success' => false, 'message' => 'code and description required'], 400);
        }try {
            $id = DB::table('stop_reasons')->insertGetId(['code' => strtoupper($r->code), 'description' => $r->description, 'category' => $r->category, 'active' => 1, 'created_at' => now()]);

            return response()->json(['success' => true, 'data' => DB::table('stop_reasons')->find($id)], 201);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => str_contains(strtolower($e->getMessage()), 'duplicate') ? 'Code already exists' : $e->getMessage()], str_contains(strtolower($e->getMessage()), 'duplicate') ? 409 : 500);
        }
    }

    public function storeStopReason(Request $r)
    {
        $validator = \Validator::make($r->all(), [
            'code' => 'required|string|max:50|unique:stop_reasons,code',
            'description' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'remark' => 'nullable|string',
            'take_time_minutes' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $id = DB::table('stop_reasons')->insertGetId([
                'code' => strtoupper(trim($r->code)),
                'description' => trim($r->description),
                'remark' => $r->filled('remark')
                                    ? trim($r->remark)
                                    : null,
                'category' => $r->category,
                'take_time_minutes' => $r->input('take_time_minutes'),
                'active' => 1,
                'created_at' => now(),
            ]);

            $stopReason = DB::table('stop_reasons')
                ->where('id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Stop reason added successfully',
                'data' => $stopReason,
            ], 201);

        } catch (\Throwable $e) {
            \Log::error('Stop Reason Store Error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to save stop reason',
            ], 500);
        }
    }

    public function updateStopReason_backup(Request $r, $id)
    {
        DB::table('stop_reasons')->where('id', $id)->update(['description' => $r->description, 'category' => $r->category, 'active' => (bool) $r->active]);
        $row = DB::table('stop_reasons')->find($id);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

return response()->json(['success' => true, 'data' => $row]);
    }

    public function updateStopReason(Request $r, $id)
    {
        // Check existing record
        $row = DB::table('stop_reasons')
            ->where('id', $id)
            ->first();

        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'Stop reason not found',
            ], 404);
        }

        // Validation
        $validator = \Validator::make($r->all(), [
            'description' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'remark' => 'nullable|string',
            'take_time_minutes' => 'nullable|integer|min:0',
            'active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {

            // Update all fields including remark
            DB::table('stop_reasons')
                ->where('id', $id)
                ->update([
                    'description' => trim($r->description),
                    'category' => $r->category,
                    'remark' => $r->filled('remark')
                                        ? trim($r->remark)
                                        : null,
                    'active' => (bool) $r->active,
                    'take_time_minutes' => $r->input('take_time_minutes'),
                ]);

            // Get updated record
            $updatedRow = DB::table('stop_reasons')
                ->where('id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Stop reason updated successfully',
                'data' => $updatedRow,
            ], 200);

        } catch (\Throwable $e) {

            \Log::error('Stop Reason Update Error', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update stop reason',
            ], 500);
        }
    }

    public function audit()
    {
        return response()->json(['success' => true, 'data' => DB::table('audit_log as al')->leftJoin('users as u', 'al.user_id', '=', 'u.id')->select('al.*', 'u.username', 'u.full_name')->orderByDesc('al.logged_at')->limit(200)->get()]);
    }

    public function getRoleMenuPermissions($role)
    {
        $allowedRoles = [
            'admin',
            'management',
            'maintenance',
            'operator',
        ];

        if (! in_array($role, $allowedRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role',
            ], 422);
        }

        try {

            $menus = [
                [
                    'key' => 'breakdown_report',
                    'name' => 'Breakdown Report',
                ],
                [
                    'key' => 'job_report',
                    'name' => 'Job Report',
                ],
                [
                    'key' => 'monitoring_report',
                    'name' => 'Monitoring Report',
                ],
                [
                    'key' => 'equipment_history',
                    'name' => 'Equipment History',
                ],
                [
                    'key' => 'shift_duty_report',
                    'name' => 'Shift Duty Report',
                ],
                [
                    'key' => 'general_shift_jobs',
                    'name' => 'General Shift Jobs',
                ],
            ];

            $assignedMenus = DB::table('role_menu_permissions')
                ->where('role', $role)
                ->pluck('menu_key')
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => $role,
                    'menus' => $menus,
                    'assigned_menu_keys' => $assignedMenus,
                ],
            ], 200);

        } catch (\Throwable $e) {

            \Log::error('Get Role Menu Permission Error', [
                'role' => $role,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch menu permissions',
            ], 500);
        }
    }

    public function updateRoleMenuPermissions(Request $request, $role)
    {
        $allowedRoles = [
            'admin',
            'management',
            'maintenance',
            'operator',
        ];

        if (! in_array($role, $allowedRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role',
            ], 422);
        }

        $allowedMenus = [
            'breakdown_report',
            'job_report',
            'monitoring_report',
            'equipment_history',
            'shift_duty_report',
            'general_shift_jobs',
        ];

        $validator = Validator::make($request->all(), [
            'selected_menu_keys' => 'present|array',
            'selected_menu_keys.*' => [
                'required',
                'string',
                'distinct',
                'in:'.implode(',', $allowedMenus),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $menuNames = [
            'breakdown_report' => 'Breakdown Report',
            'job_report' => 'Job Report',
            'monitoring_report' => 'Monitoring Report',
            'equipment_history' => 'Equipment History',
            'shift_duty_report' => 'Shift Duty Report',
            'general_shift_jobs' => 'General Shift Jobs',
        ];

        try {

            DB::transaction(function () use (
                $request,
                $role,
                $menuNames
            ) {

                // Remove previous permissions for this role
                DB::table('role_menu_permissions')
                    ->where('role', $role)
                    ->delete();

                // Insert newly selected permissions
                foreach ($request->input('selected_menu_keys', []) as $key) {

                    DB::table('role_menu_permissions')->insert([
                        'role' => $role,
                        'menu_key' => $key,
                        'menu_name' => $menuNames[$key],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

            });

            return response()->json([
                'success' => true,
                'message' => 'Role menu permissions updated successfully',
            ], 200);

        } catch (\Throwable $e) {

            \Log::error('Update Role Menu Permission Error', [
                'role' => $role,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update menu permissions',
            ], 500);
        }
    }


    
    public function dashboardStats()
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | 1. ALL DATABASE TABLE COUNTS
            |--------------------------------------------------------------------------
            */

            $tables = [
                'activity_logs',
                'alert_priorities',
                'audit_log',
                'breakdowns',
                'departments',
                'equipment',
                'equipment_components',
                'equipment_monitoring',
                'equipment_monitoring_params',
                'equipment_monitoring_types',
                'eq_monitoring_logs',
                'general_shift_jobs',
                'job_assignments',
                'job_history',
                'job_types',
                'jobs',
                'load_logs',
                'locations',
                'migrations',
                'monitoring_logs',
                'permissions',
                'plants',
                'roles',
                'role_menu_permissions',
                'role_permissions',
                'shifts',
                'shift_logs',
                'shift_monitoring_logs',
                'stop_reasons',
                'user_roles',
                'users',
            ];

            $tableCounts = [];

            foreach ($tables as $table) {
                $tableCounts[$table] = DB::table($table)->count();
            }

            /*
            |--------------------------------------------------------------------------
            | 2. EQUIPMENT STATISTICS
            |--------------------------------------------------------------------------
            */

            $equipment = [
                'total' => DB::table('equipment')->count(),

                'active' => DB::table('equipment')
                    ->where('active', 1)
                    ->count(),

                'inactive' => DB::table('equipment')
                    ->where('active', 0)
                    ->count(),

                'status_wise' => DB::table('equipment')
                    ->select('current_status', DB::raw('COUNT(*) as total'))
                    ->groupBy('current_status')
                    ->pluck('total', 'current_status'),
            ];

            /*
            |--------------------------------------------------------------------------
            | 3. USER STATISTICS
            |--------------------------------------------------------------------------
            */

            $users = [
                'total' => DB::table('users')->count(),

                'active' => DB::table('users')
                    ->where('active', 1)
                    ->count(),

                'inactive' => DB::table('users')
                    ->where('active', 0)
                    ->count(),

                'role_wise' => DB::table('users')
                    ->select('role', DB::raw('COUNT(*) as total'))
                    ->groupBy('role')
                    ->pluck('total', 'role'),
            ];

            /*
            |--------------------------------------------------------------------------
            | 4. BREAKDOWN STATISTICS
            |--------------------------------------------------------------------------
            */

            $breakdowns = [
                'total' => DB::table('breakdowns')->count(),

                'status_wise' => DB::table('breakdowns')
                    ->select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status'),

                'today' => DB::table('breakdowns')
                    ->whereDate('start_time', today())
                    ->count(),

                'open' => DB::table('breakdowns')
                    ->where('status', 'OPEN')
                    ->count(),

                'closed' => DB::table('breakdowns')
                    ->where('status', 'CLOSED')
                    ->count(),

                'total_downtime_minutes' => DB::table('breakdowns')
                    ->sum('downtime_minutes'),
            ];

            /*
            |--------------------------------------------------------------------------
            | 5. JOB STATISTICS
            |--------------------------------------------------------------------------
            */

            $jobs = [
                'total' => DB::table('jobs')->count(),

                'today' => DB::table('jobs')
                    ->whereDate('created_at', today())
                    ->count(),

                'status_wise' => DB::table('jobs')
                    ->select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status'),

                'priority_wise' => DB::table('jobs as j')
                    ->leftJoin('alert_priorities as p', 'j.priority_id', '=', 'p.id')
                    ->select(
                        'p.name as priority',
                        DB::raw('COUNT(j.id) as total')
                    )
                    ->groupBy('p.name')
                    ->get(),

                'completed' => DB::table('jobs')
                    ->whereNotNull('completed_at')
                    ->count(),
            ];

            /*
            |--------------------------------------------------------------------------
            | 6. GENERAL SHIFT JOB STATISTICS
            |--------------------------------------------------------------------------
            */

            $generalShiftJobs = [
                'total' => DB::table('general_shift_jobs')->count(),

                'today' => DB::table('general_shift_jobs')
                    ->whereDate('created_at', today())
                    ->count(),

                'status_wise' => DB::table('general_shift_jobs')
                    ->select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status'),
            ];

            /*
            |--------------------------------------------------------------------------
            | 7. SHIFT LOG STATISTICS
            |--------------------------------------------------------------------------
            */

            $shiftLogs = [
                'total' => DB::table('shift_logs')->count(),

                'today' => DB::table('shift_logs')
                    ->whereDate('shift_date', today())
                    ->count(),

                'status_wise' => DB::table('shift_logs')
                    ->select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status'),

                'shift_wise' => DB::table('shift_logs')
                    ->select('shift', DB::raw('COUNT(*) as total'))
                    ->groupBy('shift')
                    ->pluck('total', 'shift'),
            ];

            /*
            |--------------------------------------------------------------------------
            | 8. MONITORING STATISTICS
            |--------------------------------------------------------------------------
            */

            $monitoring = [
                'equipment_monitoring' => DB::table('equipment_monitoring')->count(),

                'monitoring_parameters' => DB::table('equipment_monitoring_params')->count(),

                'monitoring_types' => DB::table('equipment_monitoring_types')->count(),

                'eq_monitoring_logs' => DB::table('eq_monitoring_logs')->count(),

                'monitoring_logs' => DB::table('monitoring_logs')->count(),

                'shift_monitoring_logs' => DB::table('shift_monitoring_logs')->count(),

                'today_eq_logs' => DB::table('eq_monitoring_logs')
                    ->whereDate('logged_date', today())
                    ->count(),

                'today_monitoring_logs' => DB::table('monitoring_logs')
                    ->whereDate('logged_date', today())
                    ->count(),

                'today_shift_monitoring_logs' => DB::table('shift_monitoring_logs')
                    ->whereDate('logged_at', today())
                    ->count(),
            ];

            /*
            |--------------------------------------------------------------------------
            | 9. LOAD LOG STATISTICS
            |--------------------------------------------------------------------------
            */

            $loadLogs = [
                'total' => DB::table('load_logs')->count(),

                'today' => DB::table('load_logs')
                    ->whereDate('logged_at', today())
                    ->count(),
            ];

            /*
            |--------------------------------------------------------------------------
            | 10. MASTER DATA STATISTICS
            |--------------------------------------------------------------------------
            */

            $masters = [
                'plants' => DB::table('plants')->count(),

                'active_plants' => DB::table('plants')
                    ->where('is_active', 1)
                    ->count(),

                'departments' => DB::table('departments')->count(),

                'active_departments' => DB::table('departments')
                    ->where('is_active', 1)
                    ->count(),

                'locations' => DB::table('locations')->count(),

                'active_locations' => DB::table('locations')
                    ->where('active', 1)
                    ->count(),

                'shifts' => DB::table('shifts')->count(),

                'active_shifts' => DB::table('shifts')
                    ->where('is_active', 1)
                    ->count(),

                'stop_reasons' => DB::table('stop_reasons')->count(),

                'active_stop_reasons' => DB::table('stop_reasons')
                    ->where('active', 1)
                    ->count(),

                'job_types' => DB::table('job_types')->count(),

                'active_job_types' => DB::table('job_types')
                    ->where('active', 1)
                    ->count(),

                'alert_priorities' => DB::table('alert_priorities')->count(),

                'active_alert_priorities' => DB::table('alert_priorities')
                    ->where('active', 1)
                    ->count(),
            ];

            /*
            |--------------------------------------------------------------------------
            | 11. OTHER MODULE STATISTICS
            |--------------------------------------------------------------------------
            */

            $other = [
                'equipment_components' => DB::table('equipment_components')->count(),

                'job_assignments' => DB::table('job_assignments')->count(),

                'job_history' => DB::table('job_history')->count(),

                'permissions' => DB::table('permissions')->count(),

                'roles' => DB::table('roles')->count(),

                'active_roles' => DB::table('roles')
                    ->where('active', 1)
                    ->count(),

                'role_permissions' => DB::table('role_permissions')->count(),

                'role_menu_permissions' => DB::table('role_menu_permissions')->count(),

                'user_roles' => DB::table('user_roles')->count(),
            ];

            /*
            |--------------------------------------------------------------------------
            | 12. PLANT WISE EQUIPMENT
            |--------------------------------------------------------------------------
            */

            $plantWiseEquipment = DB::table('equipment')
                ->select(
                    'plant',
                    DB::raw('COUNT(*) as total_equipment')
                )
                ->groupBy('plant')
                ->orderBy('plant')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | 13. DEPARTMENT WISE EQUIPMENT
            |--------------------------------------------------------------------------
            */

            $departmentWiseEquipment = DB::table('equipment')
                ->select(
                    'department',
                    DB::raw('COUNT(*) as total_equipment')
                )
                ->groupBy('department')
                ->orderBy('department')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | 14. RECENT ACTIVITY LOGS
            |--------------------------------------------------------------------------
            */

            $recentActivities = DB::table('activity_logs as al')
                ->leftJoin('users as u', 'al.user_id', '=', 'u.id')
                ->select(
                    'al.id',
                    'al.module',
                    'al.action',
                    'al.event_type',
                    'al.severity',
                    'al.description',
                    'al.plant',
                    'al.department',
                    'al.created_at',
                    'u.username',
                    'u.full_name'
                )
                ->orderByDesc('al.created_at')
                ->limit(10)
                ->get();

            /*
            |--------------------------------------------------------------------------
            | 15. RECENT AUDIT LOGS
            |--------------------------------------------------------------------------
            */

            $recentAuditLogs = DB::table('audit_log as al')
                ->leftJoin('users as u', 'al.user_id', '=', 'u.id')
                ->select(
                    'al.id',
                    'al.action',
                    'al.table_name',
                    'al.record_id',
                    'al.ip_address',
                    'al.logged_at',
                    'u.username',
                    'u.full_name'
                )
                ->orderByDesc('al.logged_at')
                ->limit(10)
                ->get();

            /*
            |--------------------------------------------------------------------------
            | 16. RESPONSE
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'message' => 'Dashboard statistics loaded successfully',

                'data' => [

                    'table_counts' => $tableCounts,

                    'equipment' => $equipment,

                    'users' => $users,

                    'breakdowns' => $breakdowns,

                    'jobs' => $jobs,

                    'general_shift_jobs' => $generalShiftJobs,

                    'shift_logs' => $shiftLogs,

                    'monitoring' => $monitoring,

                    'load_logs' => $loadLogs,

                    'masters' => $masters,

                    'other' => $other,

                    'plant_wise_equipment' => $plantWiseEquipment,

                    'department_wise_equipment' => $departmentWiseEquipment,

                    'recent_activities' => $recentActivities,

                    'recent_audit_logs' => $recentAuditLogs,

                ],
            ], 200);

        } catch (\Throwable $e) {

            \Log::error('Admin Dashboard Statistics Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load dashboard statistics',
            ], 500);
        }
    }

}
