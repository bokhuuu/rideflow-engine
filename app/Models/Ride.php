<?php

namespace App\Models;

use App\Enums\RideStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Represents a single scooter trip from unlock to lock.
 */
class Ride extends Model
{
    protected $fillable = [
        'scooter_id',
        'payment_session_id',
        'started_at',
        'ended_at',
        'duration_minutes',
        'start_lat',
        'start_lng',
        'end_lat',
        'end_lng',
        'last_lat',
        'last_lng',
        'zone_violated_at',
        'cost',
        'unlock_fee',
        'package_minutes',
        'status',
    ];

    protected $casts = [
        'status' => RideStatus::class,
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function scooter(): BelongsTo
    {
        return $this->belongsTo(Scooter::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
