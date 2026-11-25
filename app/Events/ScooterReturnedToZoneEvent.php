<?php

namespace App\Events;

use App\Models\Ride;
use App\Models\Scooter;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a scooter returns inside the operating zone during the grace period.
 * Cancels the pending lock and notifies admin that situation is resolved.
 */
class ScooterReturnedToZoneEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Ride $ride,
        public readonly Scooter $scooter,
    ) {}
}
