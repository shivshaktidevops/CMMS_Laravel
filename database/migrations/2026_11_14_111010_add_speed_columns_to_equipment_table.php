<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            // Adding columns after 'critical_load' for better structure
            $table->decimal('rpm_speed', 10, 2)->nullable()->after('critical_load')->comment('RPM Speed of the equipment');
            $table->decimal('load_speed', 10, 2)->nullable()->after('rpm_speed')->comment('Load Speed of the equipment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            // Drop columns if migration is rolled back
            $table->dropColumn(['rpm_speed', 'load_speed']);
        });
    }
};
