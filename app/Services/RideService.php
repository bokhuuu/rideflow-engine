<?php

namespace App\Services;

use App\Enums\ScooterStatus;
use App\Exceptions\ScooterException;
use App\Models\Package;
use App\Models\Ride;
use App\Models\Scooter;
use App\Models\Setting;
use App\Repositories\RideRepository;
use Illuminate\Support\Facades\Log;

/**
 * Manages the full lifecycle of a scooter ride.
 *
 * Coordinates between ScooterService (physical device) and RideRepository (database).
 * This is the main entry point for all ride-related actions.
 *
 * Flow:
 *   startRide() → validate → unlock scooter → create ride record → mark scooter in_ride
 *   endRide() → lock scooter → complete ride record → mark scooter available
 */
class RideService
{
    public function __construct(
        private readonly ScooterService $scooterService,
        private readonly RideRepository $rideRepository,
    ) {}

    /**
     * Start a ride - validate the request, charge payment, unlock scooter, record the ride.
     */
    public function startRide(string $imei, int $packageMinutes, string $paymentSessionId): Ride
    {
        // Find the scooter by IMEI - the QR code gives us this
        $scooter = Scooter::where('imei', $imei)->firstOrFail();

        // Reject if scooter already has an active ride - can't unlock twice.
        if ($this->rideRepository->findActiveByScooter($scooter)) {
            throw new \RuntimeException("Scooter {$imei} already has an active ride.");
        }

        // Reject if scooter is not available - offline or in maintenance.
        if ($scooter->status !== ScooterStatus::Available) {
            throw new \RuntimeException("Scooter {$imei} is not available. Status: {$scooter->status->getLabel()}");
        }

        // Validate the package - must be one of the configured options.
        $packages = Package::active()->get();
        if (!$packages->contains('duration_minutes', $packageMinutes)) {
            throw new \RuntimeException("Invalid package: {$packageMinutes} minutes.");
        }
        $package = $packages->firstWhere('duration_minutes', $packageMinutes);
        $packagePrice = (float) $package->price;
        $unlockFee = Setting::get('unlock_fee', 1.00);

        // Unlock the physical scooter via the Python TCP server.
        try {
            $result = $this->scooterService->unlock($imei, 0);
        } catch (ScooterException $e) {
            Log::error('RideService: scooter unlock failed', ['imei' => $imei, 'error' => $e->getMessage()]);
            throw new \RuntimeException("Could not unlock scooter. Please try again.");
        }

        if (!$result->success) {
            throw new \RuntimeException("Scooter refused unlock: {$result->message}");
        }

        // Scooter is now physically unlocked - create the ride record immediately.
        // We do this right after unlock so we never have an unlocked scooter with no record.
        $ride = $this->rideRepository->create(
            scooter: $scooter,
            packageMinutes: $packageMinutes,
            unlockFee: $unlockFee,
            packagePrice: $packagePrice,
            paymentSessionId: $paymentSessionId,
        );

        // Start GPS tracking - scooter sends location every 10 seconds.
        // We don't throw if this fails - ride is already started, tracking failure
        // is not critical enough to roll back the unlock.
        try {
            $this->scooterService->startTracking($imei);
        } catch (ScooterException $e) {
            Log::warning('RideService: failed to start GPS tracking', [
                'imei'  => $imei,
                'error' => $e->getMessage(),
            ]);
        }

        // Mark scooter as in_ride so no one else can unlock it.
        $scooter->update(['status' => ScooterStatus::InRide]);

        Log::info('RideService: ride started', [
            'ride_id' => $ride->id,
            'imei' => $imei,
            'package' => $packageMinutes,
        ]);

        return $ride;
    }

    /**
     * End a ride - lock the scooter, complete the ride record, mark scooter available.
     */
    public function endRide(string $imei): Ride
    {
        $scooter = Scooter::where('imei', $imei)->firstOrFail();

        // Find the active ride - can't end a ride that doesn't exist.
        $ride = $this->rideRepository->findActiveByScooter($scooter);
        if (!$ride) {
            throw new \RuntimeException("No active ride found for scooter {$imei}.");
        }

        // Lock the physical scooter. We attempt this even if something else fails -
        // a locked scooter with a messy record is better than an unlocked one.
        try {
            $result = $this->scooterService->lock($imei, 0);
        } catch (ScooterException $e) {
            Log::error('RideService: scooter lock failed', ['imei' => $imei, 'error' => $e->getMessage()]);
            throw new \RuntimeException("Could not lock scooter. Please try again.");
        }

        if (!$result->success) {
            throw new \RuntimeException("Scooter refused lock: {$result->message}");
        }

        // Duration comes from the device's L1 response via the Python server.
        $durationMinutes = $result->get('ride_duration_minutes', 0);

        // Complete the ride record in the database.
        $ride = $this->rideRepository->complete($ride, $durationMinutes);

        // Stop GPS tracking - no need to keep receiving location after ride ends.
        try {
            $this->scooterService->stopTracking($imei);
        } catch (ScooterException $e) {
            Log::warning('RideService: failed to stop GPS tracking', [
                'imei'  => $imei,
                'error' => $e->getMessage(),
            ]);
        }

        // Mark scooter available again.
        $scooter->update(['status' => ScooterStatus::Available]);

        Log::info('RideService: ride ended', [
            'ride_id' => $ride->id,
            'imei' => $imei,
            'duration' => $durationMinutes,
            'cost' => $ride->cost,
        ]);

        return $ride;
    }

    /**
     * Get the active ride for a scooter - used by the ride page to restore state.
     * Returns null if no active ride exists.
     */
    public function getActiveRide(string $imei): ?Ride
    {
        $scooter = Scooter::where('imei', $imei)->firstOrFail();

        return $this->rideRepository->findActiveByScooter($scooter);
    }
}
