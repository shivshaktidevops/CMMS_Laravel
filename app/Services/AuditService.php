<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AuditService
{
    public static function log(?int $userId, string $action, ?string $table = null, ?int $recordId = null, array $details = [], ?string $ip = null): void
    {
        try {
            DB::table('audit_log')->insert(['user_id' => $userId, 'action' => $action, 'table_name' => $table, 'record_id' => $recordId, 'details' => json_encode($details, JSON_UNESCAPED_UNICODE), 'ip_address' => $ip, 'logged_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
