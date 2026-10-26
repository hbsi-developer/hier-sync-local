<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'last_sync_attempt_at' => 'datetime',
        'last_sync_ok' => 'boolean',
        'auto_sync' => 'boolean',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(DeviceUser::class);
    }
}
