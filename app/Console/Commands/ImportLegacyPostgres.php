<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Database\Seeders\Phase1FoundationSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ImportLegacyPostgres extends Command
{
    protected $signature = 'legacy:import {--truncate : Truncate target legacy/enhancement tables before import} {--validate-only : Only compare source/target counts and FK integrity}';

    protected $description = 'Import the legacy PostgreSQL CMMS database into MySQL while preserving IDs and relationships';

    private array $order = [
        'plants', 'departments', 'shifts', 'users', 'stop_reasons', 'equipment',
        'equipment_monitoring', 'equipment_monitoring_types', 'equipment_monitoring_params',
        'shift_logs', 'breakdowns', 'shift_monitoring_logs', 'eq_monitoring_logs', 'monitoring_logs',
        'jobs', 'job_assignments', 'job_history', 'general_shift_jobs', 'load_logs', 'audit_log',
    ];

    private array $jsonCols = [
        'audit_log' => ['details'],
        'equipment_monitoring' => ['reference_values', 'columns'],
        'equipment_monitoring_types' => ['reference_values', 'columns'],
        'equipment_monitoring_params' => ['user_boxes', 'columns'],
        'eq_monitoring_logs' => ['readings'],
        'monitoring_logs' => ['readings'],
        'shift_monitoring_logs' => ['readings'],
        'general_shift_jobs' => ['photos'],
    ];

    private array $boolCols = [
        'plants' => ['is_active'], 'departments' => ['is_active'], 'shifts' => ['is_active'],
        'users' => ['active'], 'stop_reasons' => ['active'],
        'equipment' => ['active', 'hourly_entry', 'load_monitoring'],
        'equipment_monitoring' => ['is_active', 'general_shift_only', 'is_unlocked'],
        'equipment_monitoring_types' => ['is_active', 'general_shift_only', 'is_unlocked'],
        'equipment_monitoring_params' => ['is_active', 'is_dynamic'],
        'jobs' => ['is_multi_assign'],
    ];

    private array $timestampCols = [
        'audit_log' => ['logged_at'],
        'breakdowns' => ['start_time', 'end_time', 'closed_at', 'created_at', 'updated_at'],
        'departments' => ['created_at'],
        'eq_monitoring_logs' => ['logged_at'],
        'equipment' => ['created_at', 'updated_at'],
        'equipment_monitoring' => ['unlocked_at', 'created_at', 'updated_at'],
        'equipment_monitoring_types' => ['created_at', 'unlocked_at'],
        'equipment_monitoring_params' => ['created_at'],
        'general_shift_jobs' => ['started_at', 'completed_at', 'closed_at', 'created_at', 'updated_at'],
        'job_assignments' => ['assigned_at', 'started_at', 'completed_at'],
        'job_history' => ['created_at'],
        'jobs' => ['created_at', 'assigned_at', 'completed_at', 'closed_at', 'reassigned_at'],
        'monitoring_logs' => ['logged_at'],
        'plants' => ['created_at'], 'shift_logs' => ['logged_at'],
        'shift_monitoring_logs' => ['logged_at'], 'shifts' => ['created_at'],
        'stop_reasons' => ['created_at'], 'users' => ['created_at', 'updated_at'],
    ];

    public function handle(): int
    {
        if ($this->option('validate-only')) {
            return $this->validateMigration();
        }

        $legacy = DB::connection('pgsql_legacy');
        $target = DB::connection('mysql');

        try {
            $legacy->getPdo();
            $target->getPdo();
        } catch (\Throwable $e) {
            $this->error('Database connection failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $target->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            if ($this->option('truncate')) {
                $this->truncateTargets($target);
            }

            foreach ($this->order as $table) {
                if (! $this->sourceTableExists($table) || ! Schema::connection('mysql')->hasTable($table)) {
                    $this->warn("Skipping {$table}: source or target table not available");

                    continue;
                }

                $targetColumns = Schema::connection('mysql')->getColumnListing($table);
                $count = 0;
                $legacy->table($table)->orderBy('id')->chunkById(500, function ($rows) use ($target, $table, $targetColumns, &$count) {
                    $batch = [];
                    foreach ($rows as $rowObj) {
                        $row = (array) $rowObj;
                        $out = [];
                        foreach ($row as $column => $value) {
                            if (! in_array($column, $targetColumns, true)) {
                                continue;
                            }
                            $out[$column] = $this->normalize($table, $column, $value);
                        }
                        if ($table === 'users' && isset($out['password']) && ! str_starts_with((string) $out['password'], '$2')) {
                            $out['password'] = Hash::make((string) $out['password']);
                        }
                        $batch[] = $out;
                    }
                    if ($batch) {
                        $target->table($table)->insert($batch);
                    }
                    $count += count($batch);
                }, 'id');
                $this->info("{$table}: {$count}");
            }
        } finally {
            $target->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->call('db:seed', ['--class' => Phase1FoundationSeeder::class, '--force' => true]);
        $this->resetAutoIncrementValues();
        $this->newLine();
        $this->info('Legacy PostgreSQL -> MySQL import complete.');

        return $this->validateMigration();
    }

    private function truncateTargets($target): void
    {
        $enhancements = ['equipment_components', 'user_roles', 'role_permissions', 'permissions', 'roles', 'job_types', 'alert_priorities', 'locations'];
        foreach (array_merge($enhancements, array_reverse($this->order)) as $table) {
            if (Schema::connection('mysql')->hasTable($table)) {
                $target->table($table)->truncate();
            }
        }
    }

    private function sourceTableExists(string $table): bool
    {
        return (bool) DB::connection('pgsql_legacy')->table('information_schema.tables')
            ->where('table_schema', 'public')->where('table_name', $table)->exists();
    }

    private function normalize(string $table, string $column, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (in_array($column, $this->boolCols[$table] ?? [], true)) {
            if (is_bool($value)) {
                return $value ? 1 : 0;
            }

            return in_array(strtolower((string) $value), ['t', 'true', '1', 'yes'], true) ? 1 : 0;
        }
        if (in_array($column, $this->timestampCols[$table] ?? [], true)) {
            try {
                return Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return null;
            }
        }
        if ($table === 'jobs' && $column === 'photos') {
            if (is_array($value)) {
                return json_encode(array_values($value));
            }
            $decoded = json_decode((string) $value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded ?: []);
            }

            return json_encode($this->parsePgArray((string) $value));
        }
        if (in_array($column, $this->jsonCols[$table] ?? [], true)) {
            if (is_array($value) || is_object($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            json_decode((string) $value, true);

            return json_last_error() === JSON_ERROR_NONE ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $value;
    }

    private function parsePgArray(string $value): array
    {
        if ($value === '' || $value === '{}') {
            return [];
        }
        $value = trim($value, '{}');

        return $value === '' ? [] : str_getcsv($value, ',', '"', '\\');
    }

    private function resetAutoIncrementValues(): void
    {
        foreach ($this->order as $table) {
            if (! Schema::connection('mysql')->hasTable($table)) {
                continue;
            }
            $max = (int) DB::connection('mysql')->table($table)->max('id');
            if ($max > 0) {
                DB::connection('mysql')->statement('ALTER TABLE `'.$table.'` AUTO_INCREMENT = '.($max + 1));
            }
        }
    }

    private function validateMigration(): int
    {
        $legacy = DB::connection('pgsql_legacy');
        $mysql = DB::connection('mysql');
        try {
            $legacy->getPdo();
            $mysql->getPdo();
        } catch (\Throwable $e) {
            $this->error('Validation connection failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $failed = false;
        $this->table(['Table', 'PostgreSQL', 'MySQL', 'Result'], collect($this->order)->map(function ($table) use ($legacy, $mysql, &$failed) {
            if (! $this->sourceTableExists($table) || ! Schema::connection('mysql')->hasTable($table)) {
                return [$table, 'N/A', 'N/A', 'SKIPPED'];
            }
            $source = (int) $legacy->table($table)->count();
            $target = (int) $mysql->table($table)->count();
            $ok = $source === $target;
            if (! $ok) {
                $failed = true;
            }

            return [$table, $source, $target, $ok ? 'OK' : 'MISMATCH'];
        })->all());

        $checks = [
            ['shift_logs', 'equipment_id', 'equipment'], ['shift_logs', 'operator_id', 'users'],
            ['breakdowns', 'equipment_id', 'equipment'], ['breakdowns', 'shift_log_id', 'shift_logs'],
            ['jobs', 'equipment_id', 'equipment'], ['jobs', 'raised_by', 'users'],
            ['job_assignments', 'job_id', 'jobs'], ['job_history', 'job_id', 'jobs'],
            ['eq_monitoring_logs', 'equipment_id', 'equipment'], ['shift_monitoring_logs', 'equipment_id', 'equipment'],
        ];
        foreach ($checks as [$child,$fk,$parent]) {
            if (! Schema::connection('mysql')->hasTable($child) || ! Schema::connection('mysql')->hasTable($parent)) {
                continue;
            }
            $orphans = $mysql->table($child.' as c')->leftJoin($parent.' as p', 'p.id', '=', 'c.'.$fk)->whereNotNull('c.'.$fk)->whereNull('p.id')->count();
            if ($orphans) {
                $failed = true;
                $this->error("{$child}.{$fk}: {$orphans} orphan record(s)");
            }
        }
        if ($failed) {
            $this->error('Migration validation failed.');

            return self::FAILURE;
        }
        $this->info('Migration validation passed.');

        return self::SUCCESS;
    }
}
