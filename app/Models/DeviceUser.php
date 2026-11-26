<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceUser extends Model
{
    protected $guarded = [];

    protected $casts = ['enabled' => 'boolean'];

    /** The name to show: the full name kept in this app, else whatever the device stores. */
    public function getShownNameAttribute(): string
    {
        return (string) ($this->display_name ?: $this->name);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
