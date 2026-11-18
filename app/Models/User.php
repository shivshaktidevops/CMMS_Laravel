<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];

    protected $casts = ['active' => 'boolean'];

    public function plantMaster(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function departmentMaster(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function shiftMaster(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function primaryRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'primary_role_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withPivot(['plant_id', 'department_id']);
    }
}
