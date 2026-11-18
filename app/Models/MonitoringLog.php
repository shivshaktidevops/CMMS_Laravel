<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MonitoringLog extends Model { protected $guarded=[]; public $timestamps=false; protected function casts():array{return ['readings'=>'array','logged_date'=>'date','logged_at'=>'datetime'];} public function monitoring():BelongsTo{return $this->belongsTo(EquipmentMonitoring::class,'monitoring_id');} public function equipment():BelongsTo{return $this->belongsTo(Equipment::class);} public function logger():BelongsTo{return $this->belongsTo(User::class,'logged_by');} }
