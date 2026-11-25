<?php

namespace App\Listeners\Geofence;

use App\Events\ScooterLeftZoneEvent;
use App\Exceptions\ScooterException;
use App\Services\ScooterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use App\Mail\GeofenceLockMail;
use Illuminate\Support\Facades\Mail;

/**
 * Locks the scooter after the grace period following a zone violation.
 * Also notifies admin by email - only fires if rider did NOT return in time.
 *
 * Implements ShouldQueue - runs in background via queue worker.
 * Delay is read from config so it's changeable via .env.
 */
class GeofenceLockListener implements ShouldQueue
{
    public int $delay;

    public function __construct(
        private readonly ScooterService  $scooterService,
    ) {
        $this->delay = (int) config('scooter.geofence_grace_period', 10);
    }

    public function handle(ScooterLeftZoneEvent $event): void
    {
        $ride = $event->ride;
        $scooter = $event->scooter;

        $ride->refresh();

        if ($ride->status->value !== 'active') {
            Log::info('[Geofence] Grace period: ride already ended, skipping lock', [
                'ride_id' => $ride->id,
            ]);
            return;
        }

        if ($ride->zone_violated_at === null) {
            Log::info('[Geofence] Grace period: rider returned to zone, skipping lock', [
                'ride_id' => $ride->id,
            ]);
            return;
        }

        Log::warning('[Geofence] Grace period expired - locking scooter', [
            'ride_id' => $ride->id,
            'imei' => $scooter->imei,
        ]);

        try {
            $this->scooterService->lock($scooter->imei, 0);
        } catch (ScooterException $e) {
            Log::error('[Geofence] Failed to lock scooter after grace period', [
                'imei'  => $scooter->imei,
                'error' => $e->getMessage(),
            ]);
        }

        $adminEmail = config('scooter.admin_email');

        if ($adminEmail) {
            try {
                Mail::to($adminEmail)->send(new GeofenceLockMail(
                    ride: $ride,
                    scooter: $scooter,
                    lat: (float) ($ride->last_lat ?? 0),
                    lng: (float) ($ride->last_lng ?? 0),
                ));

                Log::info('[Geofence] Admin notified of scooter lock', [
                    'ride_id' => $ride->id,
                    'imei' => $scooter->imei,
                    'to' => $adminEmail,
                ]);
            } catch (\Throwable $e) {
                Log::error('[Geofence] Failed to send admin lock notification', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
