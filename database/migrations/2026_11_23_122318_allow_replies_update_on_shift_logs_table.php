<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Existing BEFORE UPDATE trigger ko find karke drop karein
        $triggers = DB::select("
            SELECT TRIGGER_NAME
            FROM information_schema.TRIGGERS
            WHERE TRIGGER_SCHEMA = DATABASE()
              AND EVENT_OBJECT_TABLE = 'shift_logs'
              AND EVENT_MANIPULATION = 'UPDATE'
        ");

        foreach ($triggers as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger->TRIGGER_NAME}`");
        }

        // 2. Naya trigger banayein: sirf 'replies' update allow hoga, baaki sab immutable rahega
        DB::unprepared("
            CREATE TRIGGER `trg_shift_logs_immutable_except_replies`
            BEFORE UPDATE ON `shift_logs`
            FOR EACH ROW
            BEGIN
                IF NOT (
                    OLD.equipment_id <=> NEW.equipment_id AND
                    OLD.operator_id <=> NEW.operator_id AND
                    OLD.shift <=> NEW.shift AND
                    OLD.shift_date <=> NEW.shift_date AND
                    OLD.status <=> NEW.status AND
                    OLD.stop_reason_id <=> NEW.stop_reason_id AND
                    OLD.remarks <=> NEW.remarks AND
                    OLD.logged_at <=> NEW.logged_at
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Shift logs are immutable';
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS `trg_shift_logs_immutable_except_replies`");

        DB::unprepared("
            CREATE TRIGGER `trg_shift_logs_immutable`
            BEFORE UPDATE ON `shift_logs`
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Shift logs are immutable';
            END
        ");
    }
};