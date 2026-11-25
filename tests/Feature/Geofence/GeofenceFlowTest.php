<?php

use App\Enums\RideStatus;
use App\Enums\ScooterStatus;
use App\Events\ScooterLeftZoneEvent;
use App\Events\ScooterReturnedToZoneEvent;
use App\Models\Ride;
use App\Models\Scooter;
use App\Models\Zone;
use Illuminate\Support\Facades\Event;

/*
 * Tests the full geofence violation sequence triggered by GPS updates.
 * GPS comes from Python → POST /api/internal/gps-update → GpsWebhookController
 * → GeofenceService checks zone → fires events → listeners react.
 *
 *   1. GPS inside zone → no event fired
 *   2. GPS outside zone → ScooterLeftZoneEvent fired
 *   3. GPS returns inside → ScooterReturnedToZoneEvent fired
 *   4. Second GPS outside (already handled) → no duplicate event
 *   5. No active ride → no event fired
 *   6. Unknown IMEI → handled gracefully
 */

function geofenceSecret(): string
{
    return 'test-secret-123';
}

function sendGpsUpdate(string $imei, float $lat, float $lng)
{
    return test()->withHeaders([
        'X-Internal-Secret' => geofenceSecret(),
    ])->postJson('/api/internal/gps-update', [
        'imei' => $imei,
        'lat'  => $lat,
        'lng'  => $lng,
    ]);
}

function makeGeofenceScooter(): Scooter
{
    return Scooter::create([
        'imei'    => '123456789012345',
        'name'    => 'Test Scooter',
        'status'  => ScooterStatus::InRide,
        'battery' => 80,
    ]);
}

function makeGeofenceRide(Scooter $scooter): Ride
{
    return Ride::create([
        'scooter_id'         => $scooter->id,
        'package_minutes'    => 10,
        'unlock_fee'         => 1.00,
        'cost'               => 3.00,
        'payment_session_id' => 'session_abc',
        'status'             => RideStatus::Active,
        'started_at'         => now()->subMinutes(5),
    ]);
}

function makeOperatingZone(): Zone
{
    return Zone::create([
        'name'        => 'Operating Zone',
        'type'        => 'operating',
        'coordinates' => [
            [41.70, 44.70],
            [41.70, 44.80],
            [41.60, 44.80],
            [41.60, 44.70],
        ],
        'is_active'   => true,
    ]);
}

/*
|--------------------------------------------------------------------------
| GPS inside zone - no violation
|--------------------------------------------------------------------------
*/

it('does not fire event when scooter is inside zone', function () {
    Event::fake();

    $scooter = makeGeofenceScooter();
    makeGeofenceRide($scooter);
    makeOperatingZone();

    $response = sendGpsUpdate($scooter->imei, 41.65, 44.75);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'inside_zone']);

    Event::assertNotDispatched(ScooterLeftZoneEvent::class);
});

/*
|--------------------------------------------------------------------------
| GPS outside zone - violation triggered
|--------------------------------------------------------------------------
*/

it('fires ScooterLeftZoneEvent when scooter leaves zone', function () {
    Event::fake();

    $scooter = makeGeofenceScooter();
    $ride = makeGeofenceRide($scooter);
    makeOperatingZone();

    $response = sendGpsUpdate($scooter->imei, 41.00, 44.00);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'violation_triggered']);

    Event::assertDispatched(ScooterLeftZoneEvent::class);
});

it('sets zone_violated_at on ride when violation triggers', function () {
    Event::fake();

    $scooter = makeGeofenceScooter();
    $ride = makeGeofenceRide($scooter);
    makeOperatingZone();

    sendGpsUpdate($scooter->imei, 41.00, 44.00);

    expect($ride->fresh()->zone_violated_at)->not->toBeNull();
});

it('updates scooter location on GPS update', function () {
    Event::fake();

    $scooter = makeGeofenceScooter();
    makeGeofenceRide($scooter);
    makeOperatingZone();

    sendGpsUpdate($scooter->imei, 41.00, 44.00);

    expect($scooter->fresh()->lat)->toEqual(41.00);
    expect($scooter->fresh()->lng)->toEqual(44.00);
});

/*
|--------------------------------------------------------------------------
| Duplicate violation
|--------------------------------------------------------------------------
*/

it('does not fire second event when violation already recorded', function () {
    Event::fake();

    $scooter = makeGeofenceScooter();
    makeOperatingZone();

    Ride::create([
        'scooter_id'         => $scooter->id,
        'package_minutes'    => 10,
        'unlock_fee'         => 1.00,
        'cost'               => 3.00,
        'payment_session_id' => 'session_abc',
        'status'             => RideStatus::Active,
        'started_at'         => now()->subMinutes(5),
        'zone_violated_at'   => now()->subSeconds(5),
    ]);

    $response = sendGpsUpdate($scooter->imei, 41.00, 44.00);

    $response->assertJson(['status' => 'already_handled']);

    Event::assertNotDispatched(ScooterLeftZoneEvent::class);
});

/*
|--------------------------------------------------------------------------
| Rider returns to zone during grace period
|--------------------------------------------------------------------------
*/

it('fires ScooterReturnedToZoneEvent when rider returns to zone', function () {
    Event::fake();

    $scooter = makeGeofenceScooter();
    makeOperatingZone();

    Ride::create([
        'scooter_id'         => $scooter->id,
        'package_minutes'    => 10,
        'unlock_fee'         => 1.00,
        'cost'               => 3.00,
        'payment_session_id' => 'session_abc',
        'status'             => RideStatus::Active,
        'started_at'         => now()->subMinutes(5),
        'zone_violated_at'   => now()->subSeconds(5), // was violated
    ]);

    $response = sendGpsUpdate($scooter->imei, 41.65, 44.75);

    $response->assertJson(['status' => 'inside_zone']);

    Event::assertDispatched(ScooterReturnedToZoneEvent::class);
});

it('clears zone_violated_at when rider returns to zone', function () {
    Event::fake();

    $scooter = makeGeofenceScooter();
    makeOperatingZone();

    $ride = Ride::create([
        'scooter_id'         => $scooter->id,
        'package_minutes'    => 10,
        'unlock_fee'         => 1.00,
        'cost'               => 3.00,
        'payment_session_id' => 'session_abc',
        'status'             => RideStatus::Active,
        'started_at'         => now()->subMinutes(5),
        'zone_violated_at'   => now()->subSeconds(5),
    ]);

    sendGpsUpdate($scooter->imei, 41.65, 44.75);

    expect($ride->fresh()->zone_violated_at)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Edge cases
|--------------------------------------------------------------------------
*/

it('returns unknown device for unregistered imei', function () {
    Event::fake();

    $response = sendGpsUpdate('999999999999999', 41.65, 44.75);

    $response->assertJson(['status' => 'unknown_device']);
    Event::assertNotDispatched(ScooterLeftZoneEvent::class);
});

it('returns no active ride when scooter has no active ride', function () {
    Event::fake();

    $scooter = makeGeofenceScooter();

    $response = sendGpsUpdate($scooter->imei, 41.00, 44.00);

    $response->assertJson(['status' => 'no_active_ride']);
    Event::assertNotDispatched(ScooterLeftZoneEvent::class);
});
