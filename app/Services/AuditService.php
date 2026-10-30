<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class AuditService
{
    public function log(?int $userId, string $action, ?string $tableName, $recordId, array $details = [], ?string $ip = null): void
    {
        try {
            DB::table('audit_log')->insert([
                'user_id' => $userId,
                'action' => $action,
                'table_name' => $tableName,
                'record_id' => $recordId,
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip_address' => $ip,
                'logged_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
