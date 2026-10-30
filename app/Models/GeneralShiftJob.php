<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class GeneralShiftJob extends Model { protected $guarded=[]; public $timestamps=false; protected function casts():array{return ['photos'=>'array','expected_completion_date'=>'date','started_at'=>'datetime','completed_at'=>'datetime','closed_at'=>'datetime','created_at'=>'datetime','updated_at'=>'datetime'];} public function raisedBy():BelongsTo{return $this->belongsTo(User::class,'raised_by');} public function closedBy():BelongsTo{return $this->belongsTo(User::class,'closed_by');} }
