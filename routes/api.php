<?php
use App\Http\Controllers\Api\{AdminController,AuthController,BreakdownController,DashboardController,EquipmentController,EqMonitoringController,GeneralShiftJobController,JobController,LoadLogController,MastersController,ReportController,ShiftLogController,ShiftMonitoringController,UploadController};
use Illuminate\Support\Facades\Route;

$register = function () {
    Route::get('/health', fn()=>response()->json(['status'=>'ok','version'=>'3.0','uptime'=>null,'ts'=>now()->toISOString()]));
    Route::post('/upload',[UploadController::class,'store']);

    Route::prefix('auth')->group(function(){
        Route::post('/login',[AuthController::class,'login']);
        Route::get('/me',[AuthController::class,'me'])->middleware('jwt');
    });

    Route::middleware('jwt')->group(function(){
        Route::prefix('equipment')->group(function(){
            Route::get('/',[EquipmentController::class,'index']);
            Route::post('/',[EquipmentController::class,'store'])->middleware('roles:admin');
            Route::get('/{id}',[EquipmentController::class,'show']);
            Route::put('/{id}',[EquipmentController::class,'update'])->middleware('roles:admin');
            Route::delete('/{id}',[EquipmentController::class,'destroy'])->middleware('roles:admin');
        });

        Route::prefix('shift-logs')->group(function(){
            Route::get('/stop-reasons',[ShiftLogController::class,'stopReasons']);
            Route::get('/',[ShiftLogController::class,'index'])->middleware('roles:management,admin');
            Route::post('/',[ShiftLogController::class,'store'])->middleware('roles:operator,admin');
        });

        Route::prefix('breakdowns')->middleware('roles:maintenance,management,admin')->group(function(){
            Route::get('/open',[BreakdownController::class,'open']);
            Route::get('/',[BreakdownController::class,'index']);
            Route::get('/{id}',[BreakdownController::class,'show']);
            Route::patch('/{id}',[BreakdownController::class,'update'])->middleware('roles:maintenance,admin');
            Route::post('/{id}/close',[BreakdownController::class,'close'])->middleware('roles:maintenance,admin');
        });

        Route::prefix('dashboard')->middleware('roles:management,admin,maintenance')->group(function(){
            Route::get('/summary',[DashboardController::class,'summary']);
            Route::get('/top-reasons',[DashboardController::class,'topReasons']);
            Route::get('/downtime-by-equipment',[DashboardController::class,'downtime']);
            Route::get('/trend',[DashboardController::class,'trend']);
            Route::get('/department-view',[DashboardController::class,'departmentView']);
            Route::get('/dept-performance',[DashboardController::class,'deptPerformance']);
            Route::get('/shift-performance',[DashboardController::class,'shiftPerformance']);
            Route::get('/tpd-comparison',[DashboardController::class,'tpdComparison']);
        });

        Route::prefix('admin')->middleware('roles:admin')->group(function(){
            Route::get('/users',[AdminController::class,'users']);
            Route::post('/users',[AdminController::class,'createUser']);
            Route::put('/users/{id}',[AdminController::class,'updateUser']);
            Route::post('/users/{id}/reset-password',[AdminController::class,'resetPassword']);
            Route::delete('/users/{id}',[AdminController::class,'deleteUser']);
            Route::get('/stop-reasons',[AdminController::class,'stopReasons']);
            Route::post('/stop-reasons',[AdminController::class,'createStopReason']);
            Route::put('/stop-reasons/{id}',[AdminController::class,'updateStopReason']);
            Route::get('/audit-log',[AdminController::class,'auditLog']);
        });

        Route::prefix('masters')->group(function(){
            Route::get('/plants',[MastersController::class,'plants']);Route::post('/plants',[MastersController::class,'createPlant'])->middleware('roles:admin');Route::put('/plants/{id}',[MastersController::class,'updatePlant'])->middleware('roles:admin');Route::delete('/plants/{id}',[MastersController::class,'deletePlant'])->middleware('roles:admin');
            Route::get('/departments',[MastersController::class,'departments']);Route::post('/departments',[MastersController::class,'createDepartment'])->middleware('roles:admin');Route::put('/departments/{id}',[MastersController::class,'updateDepartment'])->middleware('roles:admin');Route::delete('/departments/{id}',[MastersController::class,'deleteDepartment'])->middleware('roles:admin');
            Route::get('/shifts',[MastersController::class,'shifts']);Route::post('/shifts',[MastersController::class,'createShift'])->middleware('roles:admin');Route::put('/shifts/{id}',[MastersController::class,'updateShift'])->middleware('roles:admin');Route::delete('/shifts/{id}',[MastersController::class,'deleteShift'])->middleware('roles:admin');
        });

        Route::prefix('shift-monitoring')->group(function(){
            Route::get('/all',[ShiftMonitoringController::class,'all'])->middleware('roles:operator,maintenance,management,admin');
            Route::get('/logs/today',[ShiftMonitoringController::class,'today'])->middleware('roles:operator,maintenance,management,admin');
            Route::get('/logs/24hr',[ShiftMonitoringController::class,'last24'])->middleware('roles:operator,maintenance,management,admin');
            Route::post('/log',[ShiftMonitoringController::class,'log']);
            Route::get('/equipment/{eqId}',[ShiftMonitoringController::class,'byEquipment'])->middleware('roles:operator,maintenance,management,admin');
            Route::post('/equipment/{eqId}',[ShiftMonitoringController::class,'createParam']);
            Route::put('/{paramId}',[ShiftMonitoringController::class,'updateParam']);
            Route::delete('/{paramId}',[ShiftMonitoringController::class,'deleteParam']);
        });

        Route::prefix('eq-monitoring')->group(function(){
            Route::get('/logs/today',[EqMonitoringController::class,'today'])->middleware('roles:operator,maintenance,management,admin');
            Route::get('/logs/24hr',[EqMonitoringController::class,'last24'])->middleware('roles:operator,maintenance,management,admin');
            Route::post('/log',[EqMonitoringController::class,'log'])->middleware('roles:operator,admin');
            Route::get('/all',[EqMonitoringController::class,'all'])->middleware('roles:operator,maintenance,management,admin');
            Route::get('/locked',[EqMonitoringController::class,'locked'])->middleware('roles:admin');
            Route::get('/equipment/{eqId}',[EqMonitoringController::class,'byEquipment'])->middleware('roles:operator,maintenance,management,admin');
            Route::post('/equipment/{eqId}',[EqMonitoringController::class,'create'])->middleware('roles:admin');
            Route::patch('/{id}/unlock',[EqMonitoringController::class,'unlock'])->middleware('roles:admin');
            Route::patch('/{id}/lock',[EqMonitoringController::class,'lock'])->middleware('roles:admin');
            Route::put('/{id}',[EqMonitoringController::class,'update'])->middleware('roles:admin');
            Route::delete('/{id}',[EqMonitoringController::class,'destroy'])->middleware('roles:admin');
        });

        Route::prefix('load-logs')->group(function(){
            Route::get('/today',[LoadLogController::class,'today'])->middleware('roles:operator,maintenance,management,admin');
            Route::get('/24hr',[LoadLogController::class,'last24'])->middleware('roles:operator,maintenance,management,admin');
            Route::post('/',[LoadLogController::class,'store'])->middleware('roles:operator,admin');
        });

        Route::prefix('jobs')->group(function(){
            Route::post('/',[JobController::class,'store'])->middleware('roles:operator,maintenance,admin');
            Route::get('/',[JobController::class,'index']);
            Route::patch('/{id}/assign',[JobController::class,'assign'])->middleware('roles:operator,maintenance,admin');
            Route::patch('/{id}/dept-status',[JobController::class,'deptStatus'])->middleware('roles:operator,maintenance,admin');
            Route::patch('/{id}/status',[JobController::class,'status'])->middleware('roles:operator,maintenance,admin');
            Route::patch('/{id}/reassign',[JobController::class,'reassign'])->middleware('roles:maintenance,admin');
            Route::get('/{id}/history',[JobController::class,'history'])->middleware('roles:maintenance,admin,management');
            Route::get('/{id}/assignments',[JobController::class,'assignments'])->middleware('roles:maintenance,admin,management');
            Route::get('/{id}',[JobController::class,'show']);
        });

        Route::prefix('general-shift-jobs')->middleware(['roles:maintenance,admin','general.shift'])->group(function(){
            Route::get('/',[GeneralShiftJobController::class,'index']);Route::post('/',[GeneralShiftJobController::class,'store']);Route::patch('/{id}/status',[GeneralShiftJobController::class,'status']);Route::get('/{id}',[GeneralShiftJobController::class,'show']);Route::delete('/{id}',[GeneralShiftJobController::class,'destroy'])->middleware('roles:admin');
        });

        Route::prefix('reports')->middleware('roles:maintenance,management,admin')->group(function(){
            Route::get('/breakdowns',[ReportController::class,'breakdowns']);Route::get('/jobs',[ReportController::class,'jobs']);Route::get('/parameters',[ReportController::class,'parameters']);Route::get('/shift-duty',[ReportController::class,'shiftDuty']);Route::get('/general-shift-jobs/export/csv',[ReportController::class,'exportGsj']);Route::get('/general-shift-jobs/department/{dept}',[ReportController::class,'departmentGsj']);Route::get('/general-shift-jobs',[ReportController::class,'generalShiftJobs']);
        });
    });
};

Route::middleware(['legacy.cors','throttle:500,15'])->group(function() use ($register){
    $register();
    Route::prefix('v1')->group($register);
});
