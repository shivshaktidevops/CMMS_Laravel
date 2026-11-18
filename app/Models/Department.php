<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['is_active' => 'boolean'];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }
}
