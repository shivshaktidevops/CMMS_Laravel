<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\HasMany;
class StopReason extends Model { protected $guarded=[]; public $timestamps=false; protected function casts(): array{return ['active'=>'boolean','created_at'=>'datetime'];} public function shiftLogs():HasMany{return $this->hasMany(ShiftLog::class);} public function breakdowns():HasMany{return $this->hasMany(Breakdown::class,'initial_reason_id');} }
