<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo; use Illuminate\Database\Eloquent\Relations\HasMany;
class ShiftLog extends Model { protected $guarded=[]; public $timestamps=false; protected function casts():array{return ['shift_date'=>'date','logged_at'=>'datetime'];} public function equipment():BelongsTo{return $this->belongsTo(Equipment::class);} public function operator():BelongsTo{return $this->belongsTo(User::class,'operator_id');} public function stopReason():BelongsTo{return $this->belongsTo(StopReason::class);} public function breakdowns():HasMany{return $this->hasMany(Breakdown::class);} }
