<?php

namespace App\Repositories;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\Scooter;

/**
 * Handles all database operations for rides.
 */
class RideRepository
{
    /**
     * Create a new ride record when a scooter is unlocked.
     */
    public function create(Scooter $scooter, int $packageMinutes, float $unlockFee, float $packagePrice, string $paymentSessionId): Ride
    {
        return Ride::create([
            'scooter_id' => $scooter->id,
            'package_minutes' => $packageMinutes,
            'unlock_fee' => $unlockFee,
            'cost' => $unlockFee + $packagePrice,
            'payment_session_id' => $paymentSessionId,
            'status' => RideStatus::Active,
            'started_at' => now(),
        ]);
    }

    /**
     * Find the currently active ride on a scooter.
     * Returns null if the scooter has no active ride.
     */
    public function findActiveByScooter(Scooter $scooter): ?Ride
    {
        return Ride::where('scooter_id', $scooter->id)
            ->where('status', RideStatus::Active)
            ->first();
    }

    /**
     * Mark a ride as completed when the scooter is locked.
     */
    public function complete(Ride $ride, int $durationMinutes): Ride
    {
        $ride->update([
            'status' => RideStatus::Completed,
            'ended_at' => now(),
            'duration_minutes' => $durationMinutes,
        ]);

        return $ride->fresh();
    }

    /**
     * Mark a ride as cancelled - used when payment fails after unlock.
     */
    public function cancel(Ride $ride): Ride
    {
        $ride->update([
            'status' => RideStatus::Cancelled,
            'ended_at' => now(),
        ]);

        return $ride->fresh();
    }
}
