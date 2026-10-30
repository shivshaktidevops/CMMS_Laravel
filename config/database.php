<?php
use Illuminate\Support\Str;
return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql', 'url' => env('DB_URL'), 'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'), 'database' => env('DB_DATABASE', 'plant_cmms'),
            'username' => env('DB_USERNAME', 'root'), 'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''), 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '', 'prefix_indexes' => true, 'strict' => true, 'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([PDO::ATTR_EMULATE_PREPARES => false]) : [],
        ],
        'pgsql_legacy' => [
            'driver' => 'pgsql', 'host' => env('LEGACY_DB_HOST', '127.0.0.1'), 'port' => env('LEGACY_DB_PORT', '5432'),
            'database' => env('LEGACY_DB_DATABASE', 'cmms_temp'), 'username' => env('LEGACY_DB_USERNAME', 'cmms'),
            'password' => env('LEGACY_DB_PASSWORD', ''), 'charset' => 'utf8', 'prefix' => '', 'prefix_indexes' => true,
            'search_path' => 'public', 'sslmode' => 'prefer',
        ],
    ],
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
    'redis' => ['client' => env('REDIS_CLIENT', 'phpredis'), 'options' => ['cluster' => env('REDIS_CLUSTER', 'redis'), 'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-')]],
];
