
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'module',
        'action',
        'event_type',
        'severity',
        'description',
        'subject_type',
        'subject_id',
        'old_values',
        'new_values',
        'plant',
        'department',
        'ip_address',
        'user_agent',
        'request_method',
        'request_url',
        'request_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
