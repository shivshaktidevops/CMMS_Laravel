<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo; use Illuminate\Database\Eloquent\Relations\HasMany;
class EquipmentMonitoring extends Model { protected $guarded=[]; public $timestamps=false; protected function casts():array{return ['reference_values'=>'array','columns'=>'array','is_active'=>'boolean','general_shift_only'=>'boolean','is_unlocked'=>'boolean','unlocked_at'=>'datetime','created_at'=>'datetime','updated_at'=>'datetime'];} public function equipment():BelongsTo{return $this->belongsTo(Equipment::class);} public function logs():HasMany{return $this->hasMany(MonitoringLog::class,'monitoring_id');} }
