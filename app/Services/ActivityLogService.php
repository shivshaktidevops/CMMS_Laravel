
<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    public static function log(
        string $module,
        string $action,
        ?string $subjectType = null,
        int|string|null $subjectId = null,
        array $data = []
    ): ActivityLog {

        $request = app()->bound('request')
            ? app('request')
            : null;

        return ActivityLog::create([

            'user_id' => $data['user_id'] ?? Auth::id(),

            'module' => $module,
            'action' => $action,

            'event_type' => $data['event_type'] ?? 'business',
            'severity' => $data['severity'] ?? 'info',

            'description' => $data['description'] ?? null,

            'subject_type' => $subjectType,
            'subject_id' => $subjectId !== null
                ? (string) $subjectId
                : null,

            'old_values' => self::sanitize(
                $data['old_values'] ?? []
            ),

            'new_values' => self::sanitize(
                $data['new_values'] ?? []
            ),

            'properties' => self::sanitize(
                $data['properties'] ?? []
            ),

            'ip_address' => $request?->ip(),

            'user_agent' => $request
                ? mb_substr((string) $request->userAgent(), 0, 1000)
                : null,

            'request_method' => $request?->method(),

            // Query string intentionally excluded
            'request_url' => $request
                ? mb_substr((string) $request->url(), 0, 2048)
                : null,

            'request_id' => $request?->header('X-Request-ID'),

            'created_at' => now(),
        ]);
    }

    /**
     * Sensitive information ko activity log mein save hone se rokta hai.
     */
    private static function sanitize(array $data): array
    {
        $sensitivePattern = '/password|passwd|otp|token|secret|api.?key|private.?key|authorization|cookie/i';

        foreach ($data as $key => $value) {

            if (preg_match($sensitivePattern, (string) $key)) {
                $data[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::sanitize($value);
            }
        }

        return $data;
    }
}
