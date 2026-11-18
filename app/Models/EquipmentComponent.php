<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentComponent extends Model
{
    protected $guarded = [];

    protected $casts = ['active' => 'boolean', 'installed_at' => 'date'];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(EquipmentComponent::class, 'parent_component_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(EquipmentComponent::class, 'parent_component_id')->orderBy('component_code');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
