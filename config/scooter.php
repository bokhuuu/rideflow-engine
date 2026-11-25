<?php

/**
 * Scooter / Python TCP server configuration.
 *
 * Add these to your .env:
 *
 *   TCP_SERVER_URL=http://127.0.0.1:8080
 *   TCP_SERVER_SECRET=your-secret-here
 *   TCP_SERVER_TIMEOUT=15
 *   SCOOTER_UNLOCK_FEE=1.00
 *   MAP_CENTER_LAT=41.9969
 *   MAP_CENTER_LNG=41.7781
 *   MAP_ZOOM=13
 */
return [

    // Base URL of the Python server's internal HTTP API.
    'tcp_server_url' => env('TCP_SERVER_URL', 'http://127.0.0.1:8080'),

    // Shared secret sent on every request so the Python server rejects anything not from Laravel.
    'internal_secret' => env('TCP_SERVER_SECRET', ''),

    // Seconds to wait for a response. Needs to be generous - Python must complete
    // the full R0 → L0/L1 device handshake before it can reply.
    'timeout' => (int) env('TCP_SERVER_TIMEOUT', 15),

    // Flat fee charged on every ride start, regardless of package.
    'unlock_fee' => env('SCOOTER_UNLOCK_FEE', 1.00),

    // Default map center for zone drawing in admin panel.
    // Set to deployment city center - currently Ureki, Georgia.
    'map_center_lat' => (float) env('MAP_CENTER_LAT', 41.9969),
    'map_center_lng' => (float) env('MAP_CENTER_LNG', 41.7781),
    'map_zoom' => (int)   env('MAP_ZOOM', 13),

    // Admin email for geofence violation notifications
    'admin_email' => env('ADMIN_EMAIL', null),

    // Seconds to wait after geofence violation before locking the scooter.
    // Gives the rider a chance to return to the zone before being locked out.
    'geofence_grace_period' => (int) env('GEOFENCE_GRACE_PERIOD', 10),
];
