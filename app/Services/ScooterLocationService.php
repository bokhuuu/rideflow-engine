<?php

namespace App\Services;

/**
 * Handles GPS coordinate parsing and conversion for scooter location data.
 * Converts raw OMNI M151 DDMM.MMMM format into decimal degrees for geofencing and maps.
 */
class ScooterLocationService
{
    /**
     * Convert a raw DDMM.MMMM coordinate from the device to decimal degrees (WGS84).
     * Formula: decimal = degrees + (minutes / 60)
     * Example: "4141.6280" N → 41 + (41.6280 / 60) = 41.6938°N
     */
    public function convertDdmmToDecimal(string $raw, string $hemisphere): float
    {
        $dotPosition = strpos($raw, '.');

        $degreesStr = substr($raw, 0, $dotPosition - 2);

        $minutesStr = substr($raw, $dotPosition - 2);

        $degrees = (float) $degreesStr;
        $minutes = (float) $minutesStr;

        $decimal = $degrees + ($minutes / 60);

        if ($hemisphere === 'S' || $hemisphere === 'W') {
            $decimal = -$decimal;
        }

        return round($decimal, 7);
    }

    /**
     * Parse fields from a D0 GPS packet into a [lat, lng] coordinate array.
     * Returns null if the device has no satellite lock yet (validity flag = 'V').
     */
    public function parseD0Fields(array $fields): ?array
    {
        // ?? 'V' means: if index 2 doesn't exist, default to 'V' (invalid).
        $isValid = ($fields[2] ?? 'V') === 'A';

        if (! $isValid) {
            return null;
        }

        return [
            'lat' => $this->convertDdmmToDecimal($fields[3], $fields[4]),
            'lng' => $this->convertDdmmToDecimal($fields[5], $fields[6]),
        ];
    }

    /**
     * Return hardcoded Ureki coordinates for development before hardware arrives.
     * Replace with real D0/D1 parsed data when OMNI device is connected.
     */
    public function getStubLocation(): array
    {
        return [
            'lat' => 41.991,
            'lng' => 41.765,
        ];
    }
}
