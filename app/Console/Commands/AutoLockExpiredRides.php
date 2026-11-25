<?php

namespace App\Console\Commands;

use App\Enums\RideStatus;
use App\Enums\ScooterStatus;
use App\Models\Ride;
use App\Models\Setting;
use App\Services\ScooterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Finds rides where the package time + timeout buffer has passed
 * and the rider never ended the ride manually (browser crash, phone died, etc).
 *
 * Schedule: runs every minute via routes/console.php
 */
class AutoLockExpiredRides extends Command
{
    protected $signature = 'rides:auto-lock';

    protected $description = 'Lock and complete rides that have exceeded their package time + timeout buffer';

    public function __construct(
        private readonly ScooterService $scooterService,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $bufferMinutes = Setting::get('ride_timeout_buffer', 5);

        $expiredRides = Ride::where('status', RideStatus::Active)
            ->whereNotNull('started_at')
            ->whereRaw(
                'DATE_ADD(started_at, INTERVAL (package_minutes + ?) MINUTE) < NOW()',
                [$bufferMinutes]
            )
            ->with('scooter')
            ->get();

        if ($expiredRides->isEmpty()) {
            return;
        }

        Log::info('[AutoLock] Found expired rides', [
            'count' => $expiredRides->count(),
        ]);

        foreach ($expiredRides as $ride) {
            $this->lockExpiredRide($ride);
        }
    }

    /**
     * Lock and complete a single expired ride.
     * Mirrors what RideService::endRide() does — lock scooter, complete record.
     *
     * Here we want to complete the DB record regardless of hardware response.
     */
    private function lockExpiredRide(Ride $ride): void
    {
        $imei = $ride->scooter->imei;

        Log::info('[AutoLock] Locking expired ride', [
            'ride_id' => $ride->id,
            'imei' => $imei,
            'started_at' => $ride->started_at,
            'package_minutes' => $ride->package_minutes,
        ]);

        try {
            $this->scooterService->lock($imei, 0);
        } catch (\Exception $e) {
            Log::warning('[AutoLock] Could not lock scooter — completing record anyway', [
                'ride_id' => $ride->id,
                'imei' => $imei,
                'error' => $e->getMessage(),
            ]);
        }

        $durationMinutes = (int) $ride->started_at->diffInMinutes(now());

        $ride->update([
            'status' => RideStatus::Completed,
            'ended_at' => now(),
            'duration_minutes' => $durationMinutes,
        ]);

        $ride->scooter->update([
            'status' => ScooterStatus::Available,
        ]);

        Log::info('[AutoLock] Ride completed', [
            'ride_id' => $ride->id,
            'imei' => $imei,
            'duration' => $durationMinutes,
        ]);
    }
}
