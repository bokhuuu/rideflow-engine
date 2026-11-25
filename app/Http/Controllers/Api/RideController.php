<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles ride lifecycle API calls - start and end a ride.
 *
 * Called by the ride page frontend after payment is confirmed.
 * All business logic lives in RideService.
 */
class RideController extends Controller
{
    public function __construct(
        private readonly RideService $rideService,
    ) {}

    /**
     * Start a ride after payment is confirmed.
     *
     * Expects: imei (route), package_minutes, payment_session_id (body)
     */
    public function start(Request $request, string $imei): JsonResponse
    {
        $validated = $request->validate([
            'package_minutes' => ['required', 'integer', 'in:10,15,20,25,30'],
            'payment_session_id' => ['required', 'string'],
        ]);

        try {
            $ride = $this->rideService->startRide(
                imei: $imei,
                packageMinutes: $validated['package_minutes'],
                paymentSessionId: $validated['payment_session_id'],
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Scooter not found.'], 404);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Ride started.',
            'ride_id' => $ride->id,
            'package_minutes' => $ride->package_minutes,
            'cost' => $ride->cost,
            'started_at' => $ride->started_at,
        ]);
    }

    /**
     * End a ride and lock the scooter.
     *
     * Expects: imei (route)
     */
    public function end(string $imei): JsonResponse
    {
        try {
            $ride = $this->rideService->endRide($imei);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Scooter not found.'], 404);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Ride ended.',
            'ride_id' => $ride->id,
            'duration_minutes' => $ride->duration_minutes,
            'cost' => $ride->cost,
            'ended_at' => $ride->ended_at,
        ]);
    }

    /**
     * Get the active ride for a scooter - used by frontend to restore ride state.
     *
     * Returns 404 if no active ride exists.
     */
    public function active(string $imei): JsonResponse
    {
        $ride = $this->rideService->getActiveRide($imei);

        if (!$ride) {
            return response()->json(['error' => 'No active ride found.'], 404);
        }

        return response()->json([
            'ride_id' => $ride->id,
            'package_minutes' => $ride->package_minutes,
            'cost' => $ride->cost,
            'started_at' => $ride->started_at,
            'zone_violated_at' => $ride->zone_violated_at,
        ]);
    }
}
