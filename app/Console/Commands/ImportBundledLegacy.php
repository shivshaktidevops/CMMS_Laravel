<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Database\Seeders\Phase1FoundationSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ImportBundledLegacy extends Command
{
    protected $signature = 'legacy:import-bundled {--truncate : Truncate target tables before import}';

    protected $description = 'Import bundled PostgreSQL snapshot data into MySQL';

    private array $order = ['plants', 'departments', 'shifts', 'users', 'stop_reasons', 'equipment', 'equipment_monitoring', 'equipment_monitoring_types', 'equipment_monitoring_params', 'shift_logs', 'breakdowns', 'shift_monitoring_logs', 'eq_monitoring_logs', 'monitoring_logs', 'jobs', 'job_assignments', 'job_history', 'general_shift_jobs', 'audit_log'];

    private array $jsonCols = ['audit_log' => ['details'], 'equipment_monitoring' => ['reference_values', 'columns'], 'equipment_monitoring_types' => ['reference_values', 'columns'], 'equipment_monitoring_params' => ['user_boxes', 'columns'], 'eq_monitoring_logs' => ['readings'], 'monitoring_logs' => ['readings'], 'shift_monitoring_logs' => ['readings'], 'general_shift_jobs' => ['photos']];

    private array $boolCols = ['plants' => ['is_active'], 'departments' => ['is_active'], 'shifts' => ['is_active'], 'users' => ['active'], 'stop_reasons' => ['active'], 'equipment' => ['active', 'hourly_entry', 'load_monitoring'], 'equipment_monitoring' => ['is_active', 'general_shift_only', 'is_unlocked'], 'equipment_monitoring_types' => ['is_active', 'general_shift_only', 'is_unlocked'], 'equipment_monitoring_params' => ['is_active', 'is_dynamic'], 'jobs' => ['is_multi_assign']];

    private array $tsCols = ['audit_log' => ['logged_at'], 'breakdowns' => ['start_time', 'end_time', 'closed_at', 'created_at', 'updated_at'], 'departments' => ['created_at'], 'eq_monitoring_logs' => ['logged_at'], 'equipment' => ['created_at', 'updated_at'], 'equipment_monitoring' => ['unlocked_at', 'created_at', 'updated_at'], 'equipment_monitoring_types' => ['created_at', 'unlocked_at'], 'equipment_monitoring_params' => ['created_at'], 'general_shift_jobs' => ['started_at', 'completed_at', 'closed_at', 'created_at', 'updated_at'], 'job_assignments' => ['assigned_at', 'started_at', 'completed_at'], 'job_history' => ['created_at'], 'jobs' => ['created_at', 'assigned_at', 'completed_at', 'closed_at', 'reassigned_at'], 'monitoring_logs' => ['logged_at'], 'plants' => ['created_at'], 'shift_logs' => ['logged_at'], 'shift_monitoring_logs' => ['logged_at'], 'shifts' => ['created_at'], 'stop_reasons' => ['created_at'], 'users' => ['created_at', 'updated_at']];

    public function handle(): int
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            if ($this->option('truncate')) {
                $all = array_merge(['equipment_components', 'user_roles', 'role_permissions', 'permissions', 'roles', 'job_types', 'alert_priorities', 'locations'], array_reverse(array_merge($this->order, ['load_logs'])));
                foreach ($all as $t) {
                    if (Schema::hasTable($t)) {
                        DB::table($t)->truncate();
                    }
                }
            }
            foreach ($this->order as $table) {
                $file = database_path('legacy/'.$table.'.json');
                if (! is_file($file) || ! Schema::hasTable($table)) {
                    continue;
                }$rows = json_decode(file_get_contents($file), true) ?: [];
                $cols = Schema::getColumnListing($table);
                $batch = [];
                foreach ($rows as $row) {
                    $out = [];
                    foreach ($row as $k => $v) {
                        if (! in_array($k, $cols, true)) {
                            continue;
                        }$out[$k] = $this->normalize($table, $k, $v);
                    }if ($table === 'users' && isset($out['password']) && ! str_starts_with((string) $out['password'], '$2')) {
                        $out['password'] = Hash::make((string) $out['password']);
                    }$batch[] = $out;
                    if (count($batch) >= 250) {
                        DB::table($table)->insert($batch);
                        $batch = [];
                    }
                }if ($batch) {
                    DB::table($table)->insert($batch);
                }$this->info($table.': '.count($rows));
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
        $this->call('db:seed', ['--class' => Phase1FoundationSeeder::class, '--force' => true]);
        $this->newLine();
        $this->info('Bundled legacy import complete.');

        return self::SUCCESS;
    }

    private function normalize(string $table, string $col, $v)
    {
        if ($v === null) {
            return null;
        }if (in_array($col, $this->boolCols[$table] ?? [], true)) {
            return in_array(strtolower((string) $v), ['t', 'true', '1', 'yes'], true) ? 1 : 0;
        }if (in_array($col, $this->tsCols[$table] ?? [], true)) {
            try {
                return Carbon::parse($v)->utc()->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return null;
            }
        }if ($table === 'jobs' && $col === 'photos') {
            return json_encode($this->parsePgArray((string) $v));
        }if (in_array($col, $this->jsonCols[$table] ?? [], true)) {
            if (is_array($v)) {
                return json_encode($v);
            }json_decode((string) $v, true);

            return json_last_error() === JSON_ERROR_NONE ? (string) $v : json_encode($v);
        }

return $v;
    }

    private function parsePgArray(string $s): array
    {
        if ($s === '' || $s === '{}') {
            return [];
        }$s = trim($s, '{}');
        if ($s === '') {
            return [];
        }

return str_getcsv($s,',','"','\\');
    }
}
