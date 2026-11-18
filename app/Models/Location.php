<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $guarded = [];

    protected $casts = ['active' => 'boolean'];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id')->orderBy('name');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }
}
