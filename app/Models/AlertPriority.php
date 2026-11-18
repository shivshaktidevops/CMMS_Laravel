<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertPriority extends Model
{
    protected $guarded = [];

    protected $casts = ['notify_in_app' => 'boolean', 'notify_email' => 'boolean', 'notify_sms' => 'boolean', 'active' => 'boolean'];
}
