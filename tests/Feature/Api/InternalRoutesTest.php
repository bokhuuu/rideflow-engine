<?php

use App\Models\Scooter;
use App\Enums\ScooterStatus;

/*
 * These routes are only meant to be called by the Python TCP server.
 * They are protected by X-Internal-Secret header middleware.
 *
 * Two routes:
 *   POST /api/internal/gps-update  → receives GPS coordinates from Python
 *   POST /api/internal/heartbeat   → receives battery % from Python
 */

function internalSecret(): string
{
    return 'test-secret-123';
}


function internalPost(string $url, array $data = [])
{
    return test()->withHeaders([
        'X-Internal-Secret' => internalSecret(),
    ])->postJson($url, $data);
}

/*
|--------------------------------------------------------------------------
| Security — middleware enforcement
|--------------------------------------------------------------------------
*/

it('rejects request to gps-update without secret header', function () {
    $response = $this->postJson('/api/internal/gps-update', []);

    $response->assertStatus(401);
});

it('rejects request to gps-update with wrong secret', function () {
    $response = $this->withHeaders([
        'X-Internal-Secret' => 'wrong-secret',
    ])->postJson('/api/internal/gps-update', []);

    $response->assertStatus(401);
});

it('rejects request to heartbeat without secret header', function () {
    $response = $this->postJson('/api/internal/heartbeat', []);

    $response->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| GPS Update
|--------------------------------------------------------------------------
*/

it('accepts valid gps update with correct secret', function () {
    $scooter = Scooter::create([
        'imei'    => '123456789012345',
        'name'    => 'Test Scooter',
        'status'  => ScooterStatus::InRide,
        'battery' => 80,
    ]);

    $response = internalPost('/api/internal/gps-update', [
        'imei' => $scooter->imei,
        'lat'  => 41.991,
        'lng'  => 41.765,
    ]);

    $response->assertStatus(200);
});

it('rejects gps update with missing imei', function () {
    $response = internalPost('/api/internal/gps-update', [
        'lat' => 41.991,
        'lng' => 41.765,
    ]);

    $response->assertStatus(422);
});

it('rejects gps update with missing coordinates', function () {
    $response = internalPost('/api/internal/gps-update', [
        'imei' => '123456789012345',
    ]);

    $response->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| Heartbeat — battery update
|--------------------------------------------------------------------------
*/

it('updates scooter battery on valid heartbeat', function () {
    $scooter = Scooter::create([
        'imei'    => '123456789012345',
        'name'    => 'Test Scooter',
        'status'  => ScooterStatus::Available,
        'battery' => 50,
    ]);

    $response = internalPost('/api/internal/heartbeat', [
        'imei'    => $scooter->imei,
        'battery' => 75,
    ]);

    $response->assertStatus(200);

    expect($scooter->fresh()->battery)->toBe(75);
});

it('returns unknown device when imei not found on heartbeat', function () {
    $response = internalPost('/api/internal/heartbeat', [
        'imei'    => '999999999999999',
        'battery' => 75,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'unknown_device']);
});

it('rejects heartbeat with battery out of range', function () {
    $response = internalPost('/api/internal/heartbeat', [
        'imei'    => '123456789012345',
        'battery' => 150,
    ]);

    $response->assertStatus(422);
});
