<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ScooterException;
use App\Http\Controllers\Controller;
use App\Services\ScooterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles scooter actions triggered by the user - unlock, lock, beep.
 *
 * Talks only to ScooterService. No protocol details here.
 * Two failure cases to handle on every action:
 *   - ScooterException  → Python server unreachable → 503
 *   - success=false     → device refused the command → 422
 */
class ScooterController extends Controller
{
    public function __construct(
        private readonly ScooterService $scooterService,
    ) {}

    /**
     * Unlock a scooter and start a ride.
     *
     * In the full flow this runs after QR scan + payment hold confirmed.
     */
    public function unlock(Request $request, string $imei): JsonResponse
    {
        try {
            $result = $this->scooterService->unlock($imei, $request->user()->id);
        } catch (ScooterException) {
            return response()->json(['error' => 'Scooter service unavailable.'], 503);
        }

        if (!$result->success) {
            return response()->json(['error' => $result->message], 422);
        }

        return response()->json(['message' => 'Scooter unlocked. Ride started.']);
    }

    /**
     * Lock a scooter and end the ride.
     *
     * ride_duration_minutes comes from the device's L1 response and is used for billing.
     */
    public function lock(Request $request, string $imei): JsonResponse
    {
        try {
            $result = $this->scooterService->lock($imei, $request->user()->id);
        } catch (ScooterException) {
            return response()->json(['error' => 'Scooter service unavailable.'], 503);
        }

        if (!$result->success) {
            return response()->json(['error' => $result->message], 422);
        }

        return response()->json([
            'message'               => 'Scooter locked. Ride ended.',
            'ride_duration_minutes' => $result->get('ride_duration_minutes', 0),
        ]);
    }

    /**
     * Beep a scooter so the user can locate it.
     */
    public function beep(string $imei): JsonResponse
    {
        try {
            $result = $this->scooterService->beep($imei);
        } catch (ScooterException) {
            return response()->json(['error' => 'Scooter service unavailable.'], 503);
        }

        if (!$result->success) {
            return response()->json(['error' => $result->message], 422);
        }

        return response()->json(['message' => 'Beep sent.']);
    }
}
