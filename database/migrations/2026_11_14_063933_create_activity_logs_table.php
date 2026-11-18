
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // User information
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Activity information
            $table->string('module', 100)->index();
            $table->string('action', 100);
            $table->string('event_type', 50)->default('business');
            $table->string('severity', 20)->default('info');
            $table->text('description')->nullable();

            // Related record information
            $table->string('subject_type', 191)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // Before and after data
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Plant and department snapshots
            $table->string('plant', 50)->nullable();
            $table->string('department', 100)->nullable();

            // Request information
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_method', 10)->nullable();
            $table->text('request_url')->nullable();
            $table->string('request_id', 100)->nullable();

            // Additional information
            $table->json('metadata')->nullable();

            // Only created_at because logs should be immutable
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index(
                ['subject_type', 'subject_id'],
                'activity_logs_subject_index'
            );

            $table->index(
                ['user_id', 'created_at'],
                'activity_logs_user_date_index'
            );

            $table->index(
                ['module', 'created_at'],
                'activity_logs_module_date_index'
            );

            $table->index(
                ['event_type', 'created_at'],
                'activity_logs_event_date_index'
            );

            $table->index(
                ['plant', 'department', 'created_at'],
                'activity_logs_scope_date_index'
            );

            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
