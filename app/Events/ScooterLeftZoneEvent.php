<?php

namespace App\Events;

use App\Models\Ride;
use App\Models\Scooter;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a scooter crosses the operating zone boundary during an active ride.
 *
 * This is the public API of the geofencing module.
 * Any part of the app that needs to react to a zone violation listens to this event.
 * Currently: admin email (immediate) + scooter lock (after 30s grace period).
 */
class ScooterLeftZoneEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Ride $ride,
        public readonly Scooter $scooter,
        public readonly float $lat,
        public readonly float $lng,
    ) {}
}
