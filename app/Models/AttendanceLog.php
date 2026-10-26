<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    protected $guarded = [];

    protected $casts = ['punched_at' => 'datetime'];

    public const VERIFY_MODES = [0 => 'Password', 1 => 'Fingerprint', 2 => 'Card', 15 => 'Face'];

    public const IN_OUT_MODES = [0 => 'Check-In', 1 => 'Check-Out', 2 => 'Break-Out', 3 => 'Break-In', 4 => 'OT-In', 5 => 'OT-Out'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function getVerifyLabelAttribute(): string
    {
        return self::VERIFY_MODES[$this->verify_mode] ?? (string) $this->verify_mode;
    }

    public function getInOutLabelAttribute(): string
    {
        return self::IN_OUT_MODES[$this->in_out_mode] ?? (string) $this->in_out_mode;
    }
}
