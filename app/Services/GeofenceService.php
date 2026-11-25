<?php

namespace App\Services;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Collection;

/**
 * Handles geofence boundary checks for active operating zones.
 * Uses ray casting to determine if a GPS coordinate is inside a polygon.
 * Supports multiple zones - scooter must be inside at least one.
 */
class GeofenceService
{
    /**
     * Check if a GPS coordinate is inside a zone's polygon boundary.
     * Uses ray casting - shoots an imaginary ray east and counts polygon edge crossings.
     * Odd crossings = inside. Even crossings = outside.
     */
    public function isPointInZone(float $lat, float $lng, Zone $zone): bool
    {
        $polygon = $zone->coordinates;

        if (count($polygon) < 3) {
            return false;
        }

        $crossings = 0;
        $count = count($polygon);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $vertexALat = $polygon[$i][0];
            $vertexALng = $polygon[$i][1];
            $vertexBLat = $polygon[$j][0];
            $vertexBLng = $polygon[$j][1];

            $edgeStraddlesOurLatitude = ($vertexALat > $lat) !== ($vertexBLat > $lat);

            if ($edgeStraddlesOurLatitude) {
                $intersectionLng = ($vertexBLng - $vertexALng)
                    * ($lat - $vertexALat)
                    / ($vertexBLat - $vertexALat)
                    + $vertexALng;

                if ($lng < $intersectionLng) {
                    $crossings++;
                }
            }
        }

        return ($crossings % 2) !== 0;
    }

    /**
     * Get all active operating zones.
     * Multiple zones supported - scooter must be inside at least one.
     */
    public function getActiveOperatingZones(): Collection
    {
        return Zone::active()->operating()->get();
    }

    /**
     * Check if a GPS coordinate is inside the active operating zone.
     * Returns true if inside, or if no zone is configured (safe default).
     * Returns false if outside - caller should trigger violation response.
     */
    public function isInsideOperatingZone(float $lat, float $lng): bool
    {
        $zones = $this->getActiveOperatingZones();

        if ($zones->isEmpty()) {
            return true;
        }

        foreach ($zones as $zone) {
            if ($this->isPointInZone($lat, $lng, $zone)) {
                return true;
            }
        }

        return false;
    }
}
