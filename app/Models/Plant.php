<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plant extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['is_active' => 'boolean'];
}
