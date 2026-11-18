<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class JobAssignment extends Model { protected $guarded=[]; public $timestamps=false; protected function casts():array{return ['assigned_at'=>'datetime','started_at'=>'datetime','completed_at'=>'datetime'];} public function job():BelongsTo{return $this->belongsTo(Job::class);} }
