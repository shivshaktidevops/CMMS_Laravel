<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plants', function (Blueprint $t) {
            $t->id();
            $t->string('name', 50);
            $t->string('code', 20)->unique();
            $t->boolean('is_active')->default(true);
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->foreignId('plant_id')->nullable()->constrained('plants')->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['name', 'plant_id']);
        });
        Schema::create('shifts', function (Blueprint $t) {
            $t->id();
            $t->string('name', 20);
            $t->char('code', 2);
            $t->foreignId('plant_id')->nullable()->constrained('plants')->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['code', 'plant_id']);
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('username', 50)->unique();
            $t->string('password', 255);
            $t->string('full_name', 100);
            $t->string('role', 20);
            $t->string('department', 50)->nullable();
            $t->string('shift', 10)->nullable();
            $t->boolean('active')->default(true);
            $t->string('plant', 20)->default('100TPD');
            $t->timestamps();
            $t->index(['role', 'active']);
        });
        Schema::create('stop_reasons', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('description', 100);
            $t->string('category', 50)->nullable();
            $t->boolean('active')->default(true);
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('equipment', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name', 100);
            $t->string('location', 100)->nullable();
            $t->string('department', 50);
            $t->string('type', 50)->nullable();
            $t->string('capacity', 50)->nullable();
            $t->string('current_status', 10)->default('RUNNING');
            $t->boolean('active')->default(true);
            $t->string('plant', 20)->default('100TPD');
            $t->boolean('hourly_entry')->default(false);
            $t->decimal('kw_capacity', 10, 2)->nullable();
            $t->decimal('load_100pct', 10, 2)->nullable();
            $t->decimal('critical_load', 10, 2)->nullable();
            $t->boolean('load_monitoring')->default(false);
            $t->timestamps();
            $t->index(['plant', 'department', 'active']);
            $t->index('current_status');
        });
        Schema::create('equipment_monitoring', function (Blueprint $t) {
            $t->id();
            $t->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $t->string('monitoring_name', 100);
            $t->string('unit', 50)->nullable();
            $t->json('reference_values')->nullable();
            $t->json('columns')->nullable();
            $t->integer('display_order')->default(1);
            $t->boolean('is_active')->default(true);
            $t->boolean('general_shift_only')->default(false);
            $t->boolean('is_unlocked')->default(false);
            $t->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('unlocked_at')->nullable();
            $t->text('unlock_note')->nullable();
            $t->timestamps();
        });
        Schema::create('equipment_monitoring_types', function (Blueprint $t) {
            $t->id();
            $t->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $t->string('monitoring_name', 100);
            $t->string('unit', 30)->nullable();
            $t->json('reference_values')->nullable();
            $t->json('columns')->nullable();
            $t->integer('display_order')->default(1);
            $t->boolean('is_active')->default(true);
            $t->boolean('general_shift_only')->default(false);
            $t->boolean('is_unlocked')->default(false);
            $t->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('unlocked_at')->nullable();
            $t->text('unlock_note')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('equipment_monitoring_params', function (Blueprint $t) {
            $t->id();
            $t->string('plant', 20);
            $t->string('department', 100);
            $t->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $t->string('parameter_name', 100);
            $t->string('standard_value', 50)->nullable();
            $t->string('unit', 20)->nullable();
            $t->integer('display_order')->default(1);
            $t->boolean('is_active')->default(true);
            $t->json('user_boxes')->nullable();
            $t->json('columns')->nullable();
            $t->boolean('is_dynamic')->default(false);
            $t->timestamp('created_at')->useCurrent();
            $t->index(['plant', 'department', 'equipment_id']);
        });
        Schema::create('shift_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('equipment_id')->constrained('equipment');
            $t->foreignId('operator_id')->constrained('users');
            $t->string('shift', 10);
            $t->date('shift_date')->default(DB::raw('(CURRENT_DATE)'));
            $t->string('status', 10);
            $t->foreignId('stop_reason_id')->nullable()->constrained('stop_reasons')->nullOnDelete();
            $t->string('remarks', 255)->nullable();
            $t->timestamp('logged_at')->useCurrent();
            $t->index(['equipment_id', 'shift_date']);
        });
        Schema::create('breakdowns', function (Blueprint $t) {
            $t->id();
            $t->string('bd_number', 20)->unique();
            $t->foreignId('equipment_id')->constrained('equipment');
            $t->foreignId('shift_log_id')->constrained('shift_logs');
            $t->foreignId('initial_reason_id')->nullable()->constrained('stop_reasons')->nullOnDelete();
            $t->timestamp('start_time')->useCurrent();
            $t->timestamp('end_time')->nullable();
            $t->decimal('downtime_minutes', 10, 2)->nullable();
            $t->string('status', 10)->default('OPEN');
            $t->text('findings')->nullable();
            $t->text('actions_taken')->nullable();
            $t->text('spares_used')->nullable();
            $t->text('resolution_notes')->nullable();
            $t->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
            $t->index(['equipment_id', 'status']);
            $t->index('start_time');
        });
        Schema::create('shift_monitoring_logs', function (Blueprint $t) {
            $t->id();
            $t->string('plant', 20);
            $t->string('department', 100);
            $t->char('shift', 1);
            $t->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $t->foreignId('parameter_id')->nullable()->constrained('equipment_monitoring_params')->nullOnDelete();
            $t->string('standard_value_snapshot', 50)->nullable();
            $t->string('actual_value', 50)->nullable();
            $t->string('status', 20)->default('OK');
            $t->text('remarks')->nullable();
            $t->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('logged_at')->useCurrent();
            $t->integer('hour_number')->nullable();
            $t->json('readings')->nullable();
            $t->index(['plant', 'department', 'shift']);
        });
        Schema::create('eq_monitoring_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('monitoring_id');
            $t->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $t->string('plant', 20)->nullable();
            $t->string('department', 100)->nullable();
            $t->char('shift', 1);
            $t->foreignId('logged_by')->constrained('users');
            $t->json('readings');
            $t->text('remarks')->nullable();
            $t->date('logged_date')->default(DB::raw('(CURRENT_DATE)'));
            $t->timestamp('logged_at')->useCurrent();
            $t->index(['equipment_id', 'logged_date', 'shift']);
        });
        Schema::create('monitoring_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('monitoring_id');
            $t->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $t->string('plant', 50);
            $t->string('department', 100)->nullable();
            $t->string('shift', 20);
            $t->foreignId('logged_by')->constrained('users');
            $t->json('readings');
            $t->text('remarks')->nullable();
            $t->date('logged_date')->default(DB::raw('(CURRENT_DATE)'));
            $t->timestamp('logged_at')->useCurrent();
            $t->index(['equipment_id', 'logged_date']);
        });
        Schema::create('jobs', function (Blueprint $t) {
            $t->id();
            $t->string('job_number', 30)->unique();
            $t->string('plant', 20);
            $t->char('shift', 1);
            $t->string('title', 200);
            $t->text('description')->nullable();
            $t->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $t->unsignedBigInteger('parameter_log_id')->nullable();
            $t->foreignId('raised_by')->constrained('users');
            $t->string('raised_by_dept', 100)->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->string('assigned_dept', 100)->nullable();
            $t->string('status', 20)->default('OPEN');
            $t->json('photos')->nullable();
            $t->text('remarks')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->string('reassigned_to', 50)->nullable();
            $t->timestamp('reassigned_at')->nullable();
            $t->foreignId('reassigned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('reassign_reason')->nullable();
            $t->string('reassigned_from_dept', 50)->nullable();
            $t->boolean('is_multi_assign')->default(false);
            $t->index(['plant', 'status']);
            $t->index(['equipment_id', 'status']);
        });
        Schema::create('job_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $t->string('department', 100);
            $t->string('status', 30)->default('ASSIGNED');
            $t->timestamp('assigned_at')->useCurrent();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('remarks')->nullable();
            $t->unique(['job_id', 'department']);
        });
        Schema::create('job_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $t->string('action', 30);
            $t->string('from_dept', 50)->nullable();
            $t->string('to_dept', 50)->nullable();
            $t->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('done_by_name', 100)->nullable();
            $t->text('remarks')->nullable();
            $t->string('to_status', 50)->nullable();
            $t->string('from_status', 50)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['job_id', 'created_at']);
        });
        Schema::create('general_shift_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('job_number', 50)->unique();
            $t->string('title', 255);
            $t->text('description')->nullable();
            $t->string('department', 100);
            $t->string('plant', 50);
            $t->string('status', 20)->default('OPEN');
            $t->date('expected_completion_date')->nullable();
            $t->text('remarks')->nullable();
            $t->json('photos')->nullable();
            $t->foreignId('raised_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('raised_by_name', 100)->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['plant', 'department', 'status']);
        });
        Schema::create('load_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $t->string('plant', 20)->nullable();
            $t->string('department', 100)->nullable();
            $t->char('shift', 1);
            $t->foreignId('logged_by')->constrained('users');
            $t->decimal('red_load', 10, 2)->nullable();
            $t->decimal('yellow_load', 10, 2)->nullable();
            $t->decimal('blue_load', 10, 2)->nullable();
            $t->text('remarks')->nullable();
            $t->timestamp('logged_at')->useCurrent();
            $t->index(['equipment_id', 'shift', 'logged_at']);
        });
        Schema::create('audit_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 50);
            $t->string('table_name', 50)->nullable();
            $t->unsignedBigInteger('record_id')->nullable();
            $t->json('details')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamp('logged_at')->useCurrent();
            $t->index(['table_name', 'record_id']);
            $t->index(['user_id', 'logged_at']);
        });

        // Preserve PostgreSQL shift-log immutability at DB level.
        DB::unprepared("CREATE TRIGGER trg_shift_logs_no_update BEFORE UPDATE ON shift_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Shift logs are immutable';");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_shift_logs_no_update');
        foreach (['audit_log', 'load_logs', 'general_shift_jobs', 'job_history', 'job_assignments', 'jobs', 'monitoring_logs', 'eq_monitoring_logs', 'shift_monitoring_logs', 'breakdowns', 'shift_logs', 'equipment_monitoring_params', 'equipment_monitoring_types', 'equipment_monitoring', 'equipment', 'stop_reasons', 'users', 'shifts', 'departments', 'plants'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
