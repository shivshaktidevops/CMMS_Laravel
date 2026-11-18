
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_operating_logs', function (Blueprint $table) {

            $table->id();

            // Equipment reference
            $table->foreignId('equipment_id')
                ->constrained('equipment')
                ->restrictOnDelete();

            // Related shift log
            $table->foreignId('shift_log_id')
                ->unique()
                ->constrained('shift_logs')
                ->restrictOnDelete();

            // RPM readings
            $table->decimal('standard_rpm', 12, 2)->nullable();
            $table->decimal('actual_rpm', 12, 2);

            // Load readings
            $table->decimal('standard_load', 12, 2)->nullable();
            $table->decimal('actual_load', 12, 2);

            // Operator reference
            $table->foreignId('logged_by')
                ->constrained('users')
                ->restrictOnDelete();

            // Shift information (No ENUM)
            $table->string('shift', 20);

            // Optional remarks
            $table->text('remarks')->nullable();

            // Actual logging timestamp
            $table->timestamp('logged_at')->useCurrent();

            // Laravel timestamps
            $table->timestamps();

            // Query performance indexes
            $table->index(
                ['equipment_id', 'logged_at'],
                'eq_operating_equipment_time_idx'
            );

            $table->index(
                ['logged_by', 'logged_at'],
                'eq_operating_user_time_idx'
            );

            $table->index(
                ['shift', 'logged_at'],
                'eq_operating_shift_time_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_operating_logs');
    }
};
