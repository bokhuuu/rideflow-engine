<?php

namespace App\Listeners\Geofence;

use App\Events\ScooterReturnedToZoneEvent;
use App\Exceptions\ScooterException;
use App\Services\ScooterService;
use Illuminate\Support\Facades\Log;

/**
 * Handles scooter recovery when rider returns to zone during grace period.
 * Runs synchronously - restore speed immediately so rider can continue.
 *
 * V0 code 2 = friendly return beep
 * S4 = restore normal speed limits (15/20/25 km/h)
 */
class GeofenceReturnListener
{
    public function __construct(
        private readonly ScooterService $scooterService,
    ) {}

    public function handle(ScooterReturnedToZoneEvent $event): void
    {
        $imei = $event->scooter->imei;

        try {
            $this->scooterService->beep($imei);

            $this->scooterService->restoreSpeed($imei);

            Log::info('[Geofence] Speed restored after rider returned to zone', [
                'imei' => $imei,
                'ride_id' => $event->ride->id,
            ]);
        } catch (ScooterException $e) {
            Log::error('[Geofence] Failed to restore speed after return', [
                'imei' => $imei,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
