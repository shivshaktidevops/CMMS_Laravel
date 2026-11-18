<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class JobHistory extends Model { protected $guarded=[]; public $timestamps=false; protected function casts():array{return ['created_at'=>'datetime'];} public function job():BelongsTo{return $this->belongsTo(Job::class);} }
