
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `equipment_operating_logs`
            MODIFY `shift_log_id` BIGINT UNSIGNED NULL
        ");
    }

    public function down(): void
    {
        $hasNullRecords = DB::table('equipment_operating_logs')
            ->whereNull('shift_log_id')
            ->exists();

        if ($hasNullRecords) {
            throw new RuntimeException(
                'Cannot rollback: NULL shift_log_id records exist.'
            );
        }

        DB::statement("
            ALTER TABLE `equipment_operating_logs`
            MODIFY `shift_log_id` BIGINT UNSIGNED NOT NULL
        ");
    }
};
