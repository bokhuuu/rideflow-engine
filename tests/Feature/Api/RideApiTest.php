<?php

use App\DTOs\ScooterCommandResult;
use App\Enums\RideStatus;
use App\Enums\ScooterStatus;
use App\Models\Package;
use App\Models\Ride;
use App\Models\Scooter;
use App\Models\Setting;
use App\Services\ScooterService;

/*
 * Tests the full HTTP request → controller → service → DB → response cycle.
 * These are the endpoints the ride page frontend calls after payment.
 *
 * Three endpoints:
 *   POST /api/ride/{imei}/start  → unlock scooter, create ride record
 *   POST /api/ride/{imei}/end    → lock scooter, complete ride record
 *   GET  /api/ride/{imei}/active → return active ride data for state restore
 *
 * ScooterService is mocked - no real HTTP to Python.
 * Everything else hits real SQLite in-memory DB.
 */

function mockScooterServiceForApi(array $overrides = []): void
{
    $successResult = new ScooterCommandResult(
        success: true,
        message: 'ok',
        data: ['ride_duration_minutes' => 10],
    );

    test()->mock(ScooterService::class, function ($mock) use ($overrides, $successResult) {
        foreach (['unlock', 'lock', 'startTracking', 'stopTracking'] as $method) {
            $mock->shouldReceive($method)
                ->andReturn($overrides[$method] ?? $successResult);
        }
    });
}

function makeApiScooter(ScooterStatus $status = ScooterStatus::Available): Scooter
{
    return Scooter::create([
        'imei'    => '123456789012345',
        'name'    => 'Test Scooter',
        'status'  => $status,
        'battery' => 80,
    ]);
}

function makeRidePrerequisites(int $minutes = 10, float $price = 2.00): void
{
    Package::create([
        'name'             => "{$minutes} min",
        'duration_minutes' => $minutes,
        'price'            => $price,
        'is_active'        => true,
        'sort_order'       => 1,
    ]);

    Setting::create([
        'key'   => 'unlock_fee',
        'value' => '1.00',
        'type'  => 'float',
        'label' => 'Unlock Fee',
    ]);
}

function makeActiveRide(Scooter $scooter): Ride
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

/*
|--------------------------------------------------------------------------
| POST /api/ride/{imei}/start
|--------------------------------------------------------------------------
*/

it('starts a ride and returns ride data', function () {
    $scooter = makeApiScooter();
    makeRidePrerequisites();
    mockScooterServiceForApi();

    $response = $this->postJson("/api/ride/{$scooter->imei}/start", [
        'package_minutes'    => 10,
        'payment_session_id' => 'session_abc',
    ]);

    $response->assertStatus(200);

    $response->assertJsonStructure([
        'message',
        'ride_id',
        'package_minutes',
        'cost',
        'started_at',
    ]);

    $response->assertJsonPath('cost', 3);
});

it('marks scooter as in_ride after starting', function () {
    $scooter = makeApiScooter();
    makeRidePrerequisites();
    mockScooterServiceForApi();

    $this->postJson("/api/ride/{$scooter->imei}/start", [
        'package_minutes'    => 10,
        'payment_session_id' => 'session_abc',
    ]);

    expect($scooter->fresh()->status)->toBe(ScooterStatus::InRide);
});

it('returns 422 when scooter already has active ride', function () {
    $scooter = makeApiScooter(ScooterStatus::InRide);
    makeRidePrerequisites();
    mockScooterServiceForApi();

    makeActiveRide($scooter);

    $response = $this->postJson("/api/ride/{$scooter->imei}/start", [
        'package_minutes'    => 10,
        'payment_session_id' => 'session_abc',
    ]);

    $response->assertStatus(422);
});

it('returns 404 when scooter imei not found', function () {
    makeRidePrerequisites();
    mockScooterServiceForApi();

    $response = $this->postJson('/api/ride/999999999999999/start', [
        'package_minutes'    => 10,
        'payment_session_id' => 'session_abc',
    ]);

    $response->assertStatus(404);
});

it('returns 422 when package minutes is invalid', function () {
    $scooter = makeApiScooter();
    makeRidePrerequisites(10);
    mockScooterServiceForApi();

    $response = $this->postJson("/api/ride/{$scooter->imei}/start", [
        'package_minutes'    => 99,
        'payment_session_id' => 'session_abc',
    ]);

    $response->assertStatus(422);
});

it('returns 422 when payment session id is missing', function () {
    $scooter = makeApiScooter();
    makeRidePrerequisites();
    mockScooterServiceForApi();

    $response = $this->postJson("/api/ride/{$scooter->imei}/start", [
        'package_minutes' => 10,
    ]);

    $response->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| POST /api/ride/{imei}/end
|--------------------------------------------------------------------------
*/

it('ends a ride and returns completed ride data', function () {
    $scooter = makeApiScooter(ScooterStatus::InRide);
    makeActiveRide($scooter);
    mockScooterServiceForApi();

    $response = $this->postJson("/api/ride/{$scooter->imei}/end");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'message',
        'ride_id',
        'duration_minutes',
        'cost',
        'ended_at',
    ]);
});

it('marks scooter as available after ending ride', function () {
    $scooter = makeApiScooter(ScooterStatus::InRide);
    makeActiveRide($scooter);
    mockScooterServiceForApi();

    $this->postJson("/api/ride/{$scooter->imei}/end");

    expect($scooter->fresh()->status)->toBe(ScooterStatus::Available);
});

it('marks ride as completed after ending', function () {
    $scooter = makeApiScooter(ScooterStatus::InRide);
    $ride = makeActiveRide($scooter);
    mockScooterServiceForApi();

    $this->postJson("/api/ride/{$scooter->imei}/end");

    expect($ride->fresh()->status)->toBe(RideStatus::Completed);
});

it('returns 422 when ending ride with no active ride', function () {
    $scooter = makeApiScooter(ScooterStatus::Available);
    mockScooterServiceForApi();

    $response = $this->postJson("/api/ride/{$scooter->imei}/end");

    $response->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| GET /api/ride/{imei}/active
|--------------------------------------------------------------------------
*/

it('returns active ride data when ride exists', function () {
    $scooter = makeApiScooter(ScooterStatus::InRide);
    $ride = makeActiveRide($scooter);

    $response = $this->getJson("/api/ride/{$scooter->imei}/active");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'ride_id',
        'package_minutes',
        'cost',
        'started_at',
        'zone_violated_at',
    ]);

    $response->assertJsonPath('ride_id', $ride->id);
});

it('returns 404 when no active ride exists', function () {
    $scooter = makeApiScooter(ScooterStatus::Available);

    $response = $this->getJson("/api/ride/{$scooter->imei}/active");

    $response->assertStatus(404);
});
