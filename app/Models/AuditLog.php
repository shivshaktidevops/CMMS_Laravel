<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AuditLog extends Model { protected $table='audit_log'; protected $guarded=[]; public $timestamps=false; protected function casts():array{return ['details'=>'array','logged_at'=>'datetime'];} public function user():BelongsTo{return $this->belongsTo(User::class);} }
