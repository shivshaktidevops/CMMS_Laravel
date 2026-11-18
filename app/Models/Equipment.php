<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $guarded = [];

    protected $casts = ['active' => 'boolean', 'hourly_entry' => 'boolean', 'load_monitoring' => 'boolean', 'commissioned_at' => 'date', 'kw_capacity' => 'decimal:2', 'load_100pct' => 'decimal:2', 'critical_load' => 'decimal:2'];

    public function locationMaster(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function parentEquipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'parent_equipment_id');
    }

    public function childEquipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'parent_equipment_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(EquipmentComponent::class)->orderBy('component_code');
    }
}
