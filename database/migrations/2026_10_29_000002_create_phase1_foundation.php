<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plant_id')->nullable()->constrained('plants')->nullOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('locations')->nullOnDelete();
            $t->string('code', 50);
            $t->string('name', 120);
            $t->string('location_type', 30)->default('AREA');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['plant_id', 'code']);
            $t->index(['parent_id', 'active']);
        });
        Schema::create('job_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 60)->unique();
            $t->string('name', 120);
            $t->text('description')->nullable();
            $t->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->unsignedBigInteger('default_priority_id')->nullable();
            $t->unsignedInteger('default_target_minutes')->nullable();
            $t->unsignedInteger('avg_completion_minutes')->nullable();
            $t->unsignedInteger('completion_sample_count')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('alert_priorities', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name', 50);
            $t->unsignedTinyInteger('severity_rank');
            $t->string('color_key', 30)->nullable();
            $t->boolean('notify_in_app')->default(true);
            $t->boolean('notify_email')->default(false);
            $t->boolean('notify_sms')->default(false);
            $t->unsignedInteger('response_minutes')->nullable();
            $t->unsignedInteger('escalation_minutes')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::table('job_types', fn (Blueprint $t) => $t->foreign('default_priority_id')->references('id')->on('alert_priorities')->nullOnDelete());
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('code', 50)->unique();
            $t->string('name', 100);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('code', 100)->unique();
            $t->string('name', 150);
            $t->string('module', 60);
            $t->timestamps();
        });
        Schema::create('role_permissions', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $t->primary(['role_id', 'permission_id']);
        });
        Schema::create('user_roles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $t->foreignId('plant_id')->nullable()->constrained('plants')->cascadeOnDelete();
            $t->foreignId('department_id')->nullable()->constrained('departments')->cascadeOnDelete();
            $t->unique(['user_id', 'role_id', 'plant_id', 'department_id'], 'user_role_scope_unique');
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('plant_id')->nullable()->after('plant')->constrained('plants')->nullOnDelete();
            $t->foreignId('department_id')->nullable()->after('plant_id')->constrained('departments')->nullOnDelete();
            $t->foreignId('shift_id')->nullable()->after('department_id')->constrained('shifts')->nullOnDelete();
            $t->foreignId('primary_role_id')->nullable()->after('shift_id')->constrained('roles')->nullOnDelete();
        });
        Schema::table('equipment', function (Blueprint $t) {
            $t->string('asset_uid', 80)->nullable()->unique()->after('code');
            $t->string('machine_tag', 80)->nullable()->unique()->after('asset_uid');
            $t->foreignId('location_id')->nullable()->after('location')->constrained('locations')->nullOnDelete();
            $t->foreignId('parent_equipment_id')->nullable()->after('location_id')->constrained('equipment')->nullOnDelete();
            $t->string('serial_number', 100)->nullable();
            $t->string('manufacturer', 120)->nullable();
            $t->string('model_number', 120)->nullable();
            $t->date('commissioned_at')->nullable();
            $t->unsignedInteger('critical_downtime_minutes')->nullable();
            $t->index(['plant', 'department', 'location_id']);
        });
        Schema::table('jobs', function (Blueprint $t) {
            $t->foreignId('job_type_id')->nullable()->after('job_number')->constrained('job_types')->nullOnDelete();
            $t->foreignId('priority_id')->nullable()->after('job_type_id')->constrained('alert_priorities')->nullOnDelete();
            $t->string('source_type', 40)->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->unsignedBigInteger('automation_rule_id')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('target_completion_at')->nullable();
            $t->timestamp('on_hold_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->decimal('progress_percentage', 5, 2)->default(0);
            $t->unsignedInteger('estimated_minutes')->nullable();
            $t->unsignedInteger('benchmark_minutes')->nullable();
            $t->boolean('created_by_system')->default(false);
            $t->index(['job_type_id', 'status']);
            $t->index(['priority_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $t) {
            $t->dropForeign(['job_type_id']);
            $t->dropForeign(['priority_id']);
            $t->dropColumn(['job_type_id', 'priority_id', 'source_type', 'source_id', 'automation_rule_id', 'started_at', 'target_completion_at', 'on_hold_at', 'cancelled_at', 'progress_percentage', 'estimated_minutes', 'benchmark_minutes', 'created_by_system']);
        });
        Schema::table('equipment', function (Blueprint $t) {
            $t->dropForeign(['location_id']);
            $t->dropForeign(['parent_equipment_id']);
            $t->dropColumn(['asset_uid', 'machine_tag', 'location_id', 'parent_equipment_id', 'serial_number', 'manufacturer', 'model_number', 'commissioned_at', 'critical_downtime_minutes']);
        });
        Schema::table('users', function (Blueprint $t) {
            foreach (['plant_id', 'department_id', 'shift_id', 'primary_role_id'] as $c) {
                $t->dropForeign([$c]);
            } $t->dropColumn(['plant_id', 'department_id', 'shift_id', 'primary_role_id']);
        });
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::table('job_types', fn (Blueprint $t) => $t->dropForeign(['default_priority_id']));
        Schema::dropIfExists('alert_priorities');
        Schema::dropIfExists('job_types');
        Schema::dropIfExists('locations');
    }
};
