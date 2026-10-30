<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
class User extends Authenticatable {
 protected $table='users'; protected $guarded=[]; protected $hidden=['password']; public $timestamps=false;
 protected function casts(): array { return ['active'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime']; }
 public function shiftLogs(): HasMany { return $this->hasMany(ShiftLog::class,'operator_id'); }
 public function raisedJobs(): HasMany { return $this->hasMany(Job::class,'raised_by'); }
 public function auditLogs(): HasMany { return $this->hasMany(AuditLog::class); }
}
