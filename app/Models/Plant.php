<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Plant extends Model { protected $guarded=[]; public $timestamps=false; protected function casts(): array { return ['is_active'=>'boolean','created_at'=>'datetime']; } public function departments(): HasMany{return $this->hasMany(Department::class);} public function shifts(): HasMany{return $this->hasMany(Shift::class);} }
