<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class EquipmentMonitoringParam extends Model { protected $guarded=[]; public $timestamps=false; protected function casts():array{return ['user_boxes'=>'array','columns'=>'array','is_dynamic'=>'boolean','is_active'=>'boolean','created_at'=>'datetime'];} public function equipment():BelongsTo{return $this->belongsTo(Equipment::class);} }
