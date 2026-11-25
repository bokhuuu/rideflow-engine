<?php

namespace App\Listeners\Geofence;

use App\Events\ScooterLeftZoneEvent;
use App\Services\ScooterService;
use App\Exceptions\ScooterException;
use Illuminate\Support\Facades\Log;

/**
 * Sends immediate physical warning to scooter when boundary is crossed.
 * Runs synchronously - no queue delay, rider must feel this immediately.
 *
 * V0 code 1 = out-of-range alert beep
 * S4 = cap all speeds to 6 km/h
 */
class GeofenceWarningListener
{
    public function __construct(
        private readonly ScooterService $scooterService,
    ) {}

    public function handle(ScooterLeftZoneEvent $event): void
    {
        $imei = $event->scooter->imei;

        try {
            $this->scooterService->beep($imei);

            $this->scooterService->slowDown($imei);

            Log::info('[Geofence] Warning sent to scooter', ['imei' => $imei]);
        } catch (ScooterException $e) {
            Log::error('[Geofence] Failed to send warning to scooter', [
                'imei' => $imei,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
