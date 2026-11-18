<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ShiftMonitoringLog extends Model { protected $guarded=[]; public $timestamps=false; protected function casts():array{return ['readings'=>'array','logged_at'=>'datetime'];} public function equipment():BelongsTo{return $this->belongsTo(Equipment::class);} public function parameter():BelongsTo{return $this->belongsTo(EquipmentMonitoringParam::class,'parameter_id');} public function logger():BelongsTo{return $this->belongsTo(User::class,'logged_by');} }
