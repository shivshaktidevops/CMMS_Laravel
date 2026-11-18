<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BreakdownController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EquipmentComponentController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\EquipmentMonitoringController;
use App\Http\Controllers\Api\GeneralShiftJobController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\LoadLogController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MasterController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ShiftLogController;
use App\Http\Controllers\Api\ShiftMonitoringController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EquipmentOperatingLogController;


Route::get('/health', fn () => response()->json(['status' => 'ok', 'version' => '4.0-laravel-phase2', 'uptime' => 'n/a', 'ts' => now()->toISOString()]));

$register = function (string $prefix = '') {
    Route::prefix($prefix)->group(function () {
        Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:cmms-api');
        Route::post('/upload', [UploadController::class, 'store'])->middleware('throttle:cmms-api');
        Route::middleware(['throttle:cmms-api', 'jwt'])->group(function () {
            Route::get('/auth/me', [AuthController::class, 'me']);

            // Phase 2 foundations
            Route::get('/locations', [LocationController::class, 'index']);
            Route::get('/locations/tree', [LocationController::class, 'tree']);
            Route::middleware('role:admin')->group(function () {
                Route::post('/locations', [LocationController::class, 'store']);
                Route::put('/locations/{id}', [LocationController::class, 'update']);
                Route::delete('/locations/{id}', [LocationController::class, 'destroy']);
                Route::post('/equipment/{equipmentId}/components', [EquipmentComponentController::class, 'store']);
                Route::put('/equipment/{equipmentId}/components/{id}', [EquipmentComponentController::class, 'update']);
                Route::delete('/equipment/{equipmentId}/components/{id}', [EquipmentComponentController::class, 'destroy']);
            });
            Route::get('/equipment/{equipmentId}/components', [EquipmentComponentController::class, 'index']);
            Route::get('/equipment/{equipmentId}/components/tree', [EquipmentComponentController::class, 'tree']);
                            Route::get('/admin/dashboard-stats', [AdminController::class,'dashboardStats']);

            // Equipment legacy contract + Phase 2 fields
            Route::get('/equipment/tag/{tag}', [EquipmentController::class, 'byTag']);
            Route::get('/equipment', [EquipmentController::class, 'index']);
            Route::get('/equipment/{id}', [EquipmentController::class, 'show'])->whereNumber('id');
            Route::middleware('role:admin')->group(function () {
                Route::post('/equipment', [EquipmentController::class, 'store']);
                Route::put('/equipment/{id}', [EquipmentController::class, 'update']);
                Route::delete('/equipment/{id}', [EquipmentController::class, 'destroy']);
            });


            // Equipment Operating Logs
            Route::middleware('role:operator,admin')->group(function () {

                // Save RUNNING status with RPM and Load
                Route::post('/equipment-operating-logs',[EquipmentOperatingLogController::class, 'store']);

                // Get latest RPM and Load values
                Route::get('/equipment-operating-logs/latest',[EquipmentOperatingLogController::class, 'latest']);

            });


            // Masters
            Route::get('/masters/plants', [MasterController::class, 'plants']);
            Route::get('/masters/departments', [MasterController::class, 'departments']);
            Route::get('/masters/shifts', [MasterController::class, 'shifts']);
            Route::middleware('role:admin')->group(function () {
                 // Dashboard Statistics

                Route::post('/masters/plants', [MasterController::class, 'storePlant']);
                Route::put('/masters/plants/{id}', [MasterController::class, 'updatePlant']);
                Route::delete('/masters/plants/{id}', [MasterController::class, 'deletePlant']);
                Route::post('/masters/departments', [MasterController::class, 'storeDepartment']);
                Route::put('/masters/departments/{id}', [MasterController::class, 'updateDepartment']);
                Route::delete('/masters/departments/{id}', [MasterController::class, 'deleteDepartment']);
                Route::post('/masters/shifts', [MasterController::class, 'storeShift']);
                Route::put('/masters/shifts/{id}', [MasterController::class, 'updateShift']);
                Route::delete('/masters/shifts/{id}', [MasterController::class, 'deleteShift']);
            });

            // Admin
            Route::middleware('role:admin')->prefix('admin')->group(function () {
                Route::get('/users', [AdminController::class, 'users']);
                Route::post('/users', [AdminController::class, 'storeUser']);
                Route::put('/users/{id}', [AdminController::class, 'updateUser']);
                Route::post('/users/{id}/reset-password', [AdminController::class, 'resetPassword']);
                Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
                Route::get('/stop-reasons', [AdminController::class, 'stopReasons']);
                Route::post('/stop-reasons', [AdminController::class, 'storeStopReason']);
                Route::put('/stop-reasons/{id}', [AdminController::class, 'updateStopReason']);
                Route::get('/audit-log', [AdminController::class, 'audit']);

                // Role Based Menu Permissions

                Route::get('/role-menu-permissions/{role}', [AdminController::class, 'getRoleMenuPermissions']);
                Route::put('/role-menu-permissions/{role}', [AdminController::class, 'updateRoleMenuPermissions']);

            });


            // Role Based Menu Permissions

            // Management, Maintenance and Admin can read permissions
            Route::middleware('role:management,maintenance,admin')->get('/admin/role-menu-permissions/{role}',
                    [AdminController::class, 'getRoleMenuPermissions']
                );

            // Only Admin can update permissions
            Route::middleware('role:admin')
                ->put(
                    '/admin/role-menu-permissions/{role}',
                    [AdminController::class, 'updateRoleMenuPermissions']
                );


            // Shift logs
            Route::get('/shift-logs/stop-reasons', [ShiftLogController::class, 'stopReasons']);
            Route::get('/shift-logs', [ShiftLogController::class, 'index'])->middleware('role:management,admin');
            Route::post('/shift-logs', [ShiftLogController::class, 'store'])->middleware('role:operator,admin');

            // Breakdowns
            Route::middleware('role:maintenance,management,admin')->group(function () {
                Route::get('/breakdowns', [BreakdownController::class, 'index']);
                Route::get('/breakdowns/open', [BreakdownController::class, 'open']);
                Route::get('/breakdowns/{id}', [BreakdownController::class, 'show']);
            });
            Route::middleware('role:maintenance,admin')->group(function () {
                Route::patch('/breakdowns/{id}', [BreakdownController::class, 'update']);
                Route::post('/breakdowns/{id}/close', [BreakdownController::class, 'close']);
            });

            // Jobs
            Route::get('/jobs', [JobController::class, 'index']);
            Route::get('/jobs/{id}', [JobController::class, 'show'])->whereNumber('id');
            Route::post('/jobs', [JobController::class, 'store'])->middleware('role:operator,maintenance,admin');
            Route::patch('/jobs/{id}/assign', [JobController::class, 'assign'])->middleware('role:operator,maintenance,admin');
            Route::patch('/jobs/{id}/dept-status', [JobController::class, 'deptStatus'])->middleware('role:operator,maintenance,admin');
            Route::patch('/jobs/{id}/status', [JobController::class, 'status'])->middleware('role:operator,maintenance,admin');
            Route::patch('/jobs/{id}/reassign', [JobController::class, 'reassign'])->middleware('role:maintenance,admin');
            Route::get('/jobs/{id}/history', [JobController::class, 'history'])->middleware('role:maintenance,admin,management');
            Route::get('/jobs/{id}/assignments', [JobController::class, 'assignments'])->middleware('role:maintenance,admin,management');

            // Equipment monitoring
            Route::middleware('role:operator,maintenance,management,admin')->group(function () {
                Route::get('/eq-monitoring/logs/today', [EquipmentMonitoringController::class, 'today']);
                Route::get('/eq-monitoring/logs/24hr', [EquipmentMonitoringController::class, 'last24']);
                Route::get('/eq-monitoring/all', [EquipmentMonitoringController::class, 'all']);
                Route::get('/eq-monitoring/equipment/{eqId}', [EquipmentMonitoringController::class, 'equipment']);
            });
            Route::post('/eq-monitoring/log', [EquipmentMonitoringController::class, 'storeLog'])->middleware('role:operator,admin');
            Route::middleware('role:admin')->group(function () {
                Route::post('/eq-monitoring/equipment/{eqId}', [EquipmentMonitoringController::class, 'storeType']);
                Route::get('/eq-monitoring/locked', [EquipmentMonitoringController::class, 'locked']);
                Route::patch('/eq-monitoring/{id}/unlock', [EquipmentMonitoringController::class, 'unlock']);
                Route::patch('/eq-monitoring/{id}/lock', [EquipmentMonitoringController::class, 'lock']);
                Route::put('/eq-monitoring/{id}', [EquipmentMonitoringController::class, 'updateType']);
                Route::delete('/eq-monitoring/{id}', [EquipmentMonitoringController::class, 'destroyType']);
            });

            // Shift monitoring
            Route::middleware('role:operator,maintenance,management,admin')->group(function () {
                Route::get('/shift-monitoring/all', [ShiftMonitoringController::class, 'all']);
                Route::get('/shift-monitoring/logs/today', [ShiftMonitoringController::class, 'today']);
                Route::get('/shift-monitoring/logs/24hr', [ShiftMonitoringController::class, 'last24']);
                Route::get('/shift-monitoring/equipment/{eqId}', [ShiftMonitoringController::class, 'equipment']);
            });
            Route::post('/shift-monitoring/log', [ShiftMonitoringController::class, 'storeLog']);
            Route::post('/shift-monitoring/equipment/{eqId}', [ShiftMonitoringController::class, 'storeParam']);
            Route::put('/shift-monitoring/{id}', [ShiftMonitoringController::class, 'updateParam']);
            Route::delete('/shift-monitoring/{id}', [ShiftMonitoringController::class, 'destroyParam']);

            // Load logs
            Route::middleware('role:operator,maintenance,management,admin')->group(function () {
                Route::get('/load-logs/today', [LoadLogController::class, 'today']);
                Route::get('/load-logs/24hr', [LoadLogController::class, 'last24']);
            });
            Route::post('/load-logs', [LoadLogController::class, 'store'])->middleware('role:operator,admin');

            // General shift jobs
            Route::middleware('role:maintenance,admin')->group(function () {
                Route::get('/general-shift-jobs', [GeneralShiftJobController::class, 'index']);
                Route::post('/general-shift-jobs', [GeneralShiftJobController::class, 'store']);
                Route::patch('/general-shift-jobs/{id}/status', [GeneralShiftJobController::class, 'status']);
                Route::get('/general-shift-jobs/{id}', [GeneralShiftJobController::class, 'show']);
                Route::delete('/general-shift-jobs/{id}', [GeneralShiftJobController::class, 'destroy'])->middleware('role:admin');
            });

            // Dashboard
            Route::middleware('role:management,admin,maintenance')->prefix('dashboard')->group(function () {
                Route::get('/summary', [DashboardController::class, 'summary']);
                Route::get('/top-reasons', [DashboardController::class, 'topReasons']);
                Route::get('/downtime-by-equipment', [DashboardController::class, 'downtimeByEquipment']);
                Route::get('/trend', [DashboardController::class, 'trend']);
                Route::get('/department-view', [DashboardController::class, 'departmentView']);
                Route::get('/dept-performance', [DashboardController::class, 'deptPerformance']);
                Route::get('/shift-performance', [DashboardController::class, 'shiftPerformance']);
                Route::get('/tpd-comparison', [DashboardController::class, 'tpdComparison']);
            });

            // Reports
            Route::middleware('role:maintenance,management,admin')->prefix('reports')->group(function () {
                Route::get('/breakdowns', [ReportController::class, 'breakdowns']);
                Route::get('/jobs', [ReportController::class, 'jobs']);
                Route::get('/parameters', [ReportController::class, 'parameters']);
                Route::get('/shift-duty',[ReportController::class, 'shiftDuty']);
                Route::get('/general-shift-jobs/export/csv',[ReportController::class, 'generalShiftCsv']);
                Route::get('/general-shift-jobs/department/{dept}',[ReportController::class, 'generalShiftDepartment']);
                Route::get('/general-shift-jobs',[ReportController::class, 'generalShiftJobs']);
            });
        });
    });
};

$register('');
$register('v1');
