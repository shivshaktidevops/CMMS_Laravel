<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class LoadLog extends Model { protected $guarded=[]; public $timestamps=false; protected function casts():array{return ['red_load'=>'decimal:2','yellow_load'=>'decimal:2','blue_load'=>'decimal:2','logged_at'=>'datetime'];} public function equipment():BelongsTo{return $this->belongsTo(Equipment::class);} public function logger():BelongsTo{return $this->belongsTo(User::class,'logged_by');} }
