<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceUser extends Model
{
    protected $guarded = [];

    protected $casts = ['enabled' => 'boolean'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
