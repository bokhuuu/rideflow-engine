<?php

namespace App\Http\Controllers\Api;

use App\Events\ScooterLeftZoneEvent;
use App\Events\ScooterReturnedToZoneEvent;
use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\Scooter;
use App\Services\GeofenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles real-time GPS updates forwarded by the Python TCP server.
 *
 * Every time the OMNI device sends a GPS packet during an active ride,
 * Python forwards the coordinates here. We check if the scooter is still
 * inside the operating zone and trigger the violation sequence if not.
 */
class GpsWebhookController extends Controller
{
    public function __construct(
        private readonly GeofenceService $geofenceService,
    ) {}

    /**
     * Receive a GPS coordinate from Python and run a geofence check.
     *
     * Finds the active ride for this IMEI, updates live position
     * and fires ScooterLeftZoneEvent if the scooter crossed the boundary.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'imei' => ['required', 'string', 'size:15'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $imei = $data['imei'];
        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];

        $scooter = Scooter::where('imei', $imei)->first();

        if ($scooter === null) {
            return response()->json(['status' => 'unknown_device']);
        }

        $ride = Ride::where('scooter_id', $scooter->id)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->first();

        if ($ride === null) {
            return response()->json(['status' => 'no_active_ride']);
        }

        $scooter->update([
            'lat' => $lat,
            'lng' => $lng,
        ]);

        $ride->update([
            'last_lat' => $lat,
            'last_lng' => $lng,
        ]);

        $isInside = $this->geofenceService->isInsideOperatingZone($lat, $lng);

        if ($isInside) {
            if ($ride->zone_violated_at !== null) {
                $ride->update(['zone_violated_at' => null]);
                Log::info('[Geofence] Rider returned to zone during grace period', [
                    'imei' => $imei,
                    'ride_id' => $ride->id,
                ]);

                ScooterReturnedToZoneEvent::dispatch($ride, $scooter);
            }
            return response()->json(['status' => 'inside_zone']);
        }

        if ($ride->zone_violated_at !== null) {
            return response()->json(['status' => 'already_handled']);
        }

        $ride->update(['zone_violated_at' => now()]);

        Log::warning('[Geofence] Scooter left operating zone', [
            'imei' => $imei,
            'ride_id' => $ride->id,
            'lat' => $lat,
            'lng' => $lng,
            'at' => now()->toISOString(),
        ]);

        ScooterLeftZoneEvent::dispatch($ride, $scooter, $lat, $lng);

        return response()->json(['status' => 'violation_triggered']);
    }
}
