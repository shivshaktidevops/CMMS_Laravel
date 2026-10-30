<?php
namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ImportLegacyPostgres extends Command
{
    protected $signature = 'legacy:import {--truncate : Empty target CMMS tables before import} {--validate-only : Only run reconciliation checks}';
    protected $description = 'Import the legacy PostgreSQL CMMS database into MySQL while preserving primary keys.';

    private array $jsonColumns = [
        'audit_log' => ['details'],
        'equipment_monitoring' => ['reference_values','columns'],
        'equipment_monitoring_types' => ['reference_values','columns'],
        'equipment_monitoring_params' => ['user_boxes','columns'],
        'shift_monitoring_logs' => ['readings'],
        'eq_monitoring_logs' => ['readings'],
        'monitoring_logs' => ['readings'],
        'general_shift_jobs' => ['photos'],
    ];

    private array $dateTimeColumns = [
        'plants'=>['created_at'], 'departments'=>['created_at'], 'shifts'=>['created_at'],
        'users'=>['created_at','updated_at'], 'stop_reasons'=>['created_at'], 'equipment'=>['created_at','updated_at'],
        'equipment_monitoring_params'=>['created_at'], 'equipment_monitoring'=>['unlocked_at','created_at','updated_at'],
        'equipment_monitoring_types'=>['created_at','unlocked_at'], 'shift_monitoring_logs'=>['logged_at'],
        'shift_logs'=>['logged_at'], 'breakdowns'=>['start_time','end_time','closed_at','created_at','updated_at'],
        'audit_log'=>['logged_at'], 'eq_monitoring_logs'=>['logged_at'], 'monitoring_logs'=>['logged_at'],
        'load_logs'=>['logged_at'], 'jobs'=>['created_at','assigned_at','completed_at','closed_at','reassigned_at'],
        'job_assignments'=>['assigned_at','started_at','completed_at'], 'job_history'=>['created_at'],
        'general_shift_jobs'=>['started_at','completed_at','closed_at','created_at','updated_at'],
    ];

    private array $booleanColumns = [
        'plants'=>['is_active'], 'departments'=>['is_active'], 'shifts'=>['is_active'], 'users'=>['active'],
        'stop_reasons'=>['active'], 'equipment'=>['active','hourly_entry','load_monitoring'],
        'equipment_monitoring_params'=>['is_active','is_dynamic'],
        'equipment_monitoring'=>['is_active','general_shift_only','is_unlocked'],
        'equipment_monitoring_types'=>['is_active','general_shift_only','is_unlocked'],
        'jobs'=>['is_multi_assign'],
    ];

    private array $tables = [
        'plants','departments','shifts','users','stop_reasons','equipment','equipment_monitoring_params',
        'equipment_monitoring','equipment_monitoring_types','shift_monitoring_logs','shift_logs','breakdowns',
        'audit_log','eq_monitoring_logs','monitoring_logs','load_logs','jobs','job_assignments','job_history','general_shift_jobs',
    ];

    public function handle(): int
    {
        DB::connection('pgsql_legacy')->getPdo();
        DB::connection('mysql')->getPdo();

        if ($this->option('validate-only')) {
            return $this->validateMigration() ? self::SUCCESS : self::FAILURE;
        }

        if ($this->option('truncate')) {
            $this->truncateTarget();
        }

        DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($this->tables as $table) {
                if (!$this->legacyHasTable($table)) {
                    $this->warn("Skipped {$table}: not present in legacy PostgreSQL database.");
                    continue;
                }
                if (!Schema::connection('mysql')->hasTable($table)) {
                    throw new RuntimeException("Target MySQL table {$table} is missing. Run php artisan migrate first.");
                }
                $this->importTable($table);
                $this->resetAutoIncrement($table);
            }
        } finally {
            DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return $this->validateMigration() ? self::SUCCESS : self::FAILURE;
    }

    private function importTable(string $table): void
    {
        $sourceCount = DB::connection('pgsql_legacy')->table($table)->count();
        if ($sourceCount === 0) {
            $this->line("{$table}: 0 rows");
            return;
        }

        $bar = $this->output->createProgressBar($sourceCount);
        $bar->start();

        DB::connection('pgsql_legacy')->table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $bar) {
            $payload = [];
            foreach ($rows as $row) {
                $data = (array) $row;
                unset($data['open_equipment_key']);
                $data = $this->transformRow($table, $data);
                $payload[] = $data;
                $bar->advance();
            }
            if ($payload !== []) {
                DB::connection('mysql')->table($table)->insert($payload);
            }
        }, 'id');

        $bar->finish();
        $this->newLine();
    }

    private function transformRow(string $table, array $row): array
    {
        foreach ($this->jsonColumns[$table] ?? [] as $column) {
            if (!array_key_exists($column, $row) || $row[$column] === null) continue;
            if (is_string($row[$column])) {
                $decoded = json_decode($row[$column], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException("Invalid JSON in {$table}.{$column}, id=".($row['id'] ?? '?').': '.json_last_error_msg());
                }
                $row[$column] = json_encode($decoded, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            } else {
                $row[$column] = json_encode($row[$column], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
        }

        if ($table === 'jobs' && array_key_exists('photos', $row)) {
            $row['photos'] = $this->postgresArrayToJson($row['photos']);
        }

        foreach ($this->booleanColumns[$table] ?? [] as $column) {
            if (!array_key_exists($column, $row) || $row[$column] === null) continue;
            $row[$column] = in_array($row[$column], [true, 1, '1', 't', 'true'], true) ? 1 : 0;
        }

        foreach ($this->dateTimeColumns[$table] ?? [] as $column) {
            if (!array_key_exists($column, $row) || $row[$column] === null || $row[$column] === '') continue;
            $row[$column] = CarbonImmutable::parse($row[$column])->utc()->format('Y-m-d H:i:s.u');
        }

        return $row;
    }

    private function postgresArrayToJson(mixed $value): ?string
    {
        if ($value === null) return null;
        if (is_array($value)) return json_encode(array_values($value), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $value = trim((string) $value);
        if ($value === '{}') return '[]';
        if (!str_starts_with($value, '{') || !str_ends_with($value, '}')) {
            return json_encode([$value], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        $inner = substr($value, 1, -1);
        if ($inner === '') return '[]';
        $items = str_getcsv($inner, ',', '"', '\\');
        return json_encode($items, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    private function truncateTarget(): void
    {
        DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (array_reverse($this->tables) as $table) {
                if (Schema::connection('mysql')->hasTable($table)) DB::connection('mysql')->table($table)->truncate();
            }
        } finally {
            DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function resetAutoIncrement(string $table): void
    {
        $max = (int) DB::connection('mysql')->table($table)->max('id');
        DB::connection('mysql')->statement('ALTER TABLE `'.$table.'` AUTO_INCREMENT = '.max(1, $max + 1));
    }

    private function legacyHasTable(string $table): bool
    {
        return Schema::connection('pgsql_legacy')->hasTable($table);
    }

    private function validateMigration(): bool
    {
        $ok = true;
        $this->newLine();
        $this->info('Reconciliation');
        foreach ($this->tables as $table) {
            if (!$this->legacyHasTable($table)) continue;
            $source = DB::connection('pgsql_legacy')->table($table)->count();
            $target = DB::connection('mysql')->table($table)->count();
            $match = $source === $target;
            $ok = $ok && $match;
            $this->line(sprintf('%-32s source=%-8d target=%-8d %s', $table, $source, $target, $match ? 'OK' : 'MISMATCH'));
        }

        $orphanChecks = [
            ['shift_logs','equipment_id','equipment'], ['shift_logs','operator_id','users'],
            ['breakdowns','equipment_id','equipment'], ['breakdowns','shift_log_id','shift_logs'],
            ['jobs','equipment_id','equipment'], ['jobs','raised_by','users'],
            ['job_assignments','job_id','jobs'], ['job_history','job_id','jobs'],
            ['shift_monitoring_logs','equipment_id','equipment'], ['shift_monitoring_logs','parameter_id','equipment_monitoring_params'],
            ['eq_monitoring_logs','monitoring_id','equipment_monitoring_types'], ['monitoring_logs','monitoring_id','equipment_monitoring'],
        ];
        foreach ($orphanChecks as [$child,$fk,$parent]) {
            if (!Schema::connection('mysql')->hasTable($child) || !Schema::connection('mysql')->hasTable($parent)) continue;
            $orphans = DB::connection('mysql')->table("{$child} as c")->leftJoin("{$parent} as p", "p.id", '=', "c.{$fk}")->whereNotNull("c.{$fk}")->whereNull('p.id')->count();
            $ok = $ok && $orphans === 0;
            $this->line("orphan {$child}.{$fk}: {$orphans}".($orphans === 0 ? ' OK' : ' FAIL'));
        }
        return $ok;
    }
}
