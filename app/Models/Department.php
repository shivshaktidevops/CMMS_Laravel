<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Department extends Model { protected $guarded=[]; public $timestamps=false; protected function casts(): array{return ['is_active'=>'boolean','created_at'=>'datetime'];} public function plant():BelongsTo{return $this->belongsTo(Plant::class);} }
