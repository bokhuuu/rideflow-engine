<?php

use App\Enums\RideStatus;
use App\Enums\ScooterStatus;
use App\Models\Package;
use App\Models\Ride;
use App\Models\Scooter;
use App\Models\Setting;

/*
 * Tests the browser-facing ride flow - what the rider sees after QR scan.
 * These routes return HTML views, not JSON.
 *
 * Flow:
 *   1. Rider scans QR code → GET /ride/{imei} → package selection page
 *   2. Rider pays → ride starts
 *   3. Rider sees active ride page → GET /ride/{imei}/active
 *   4. Ride ends → GET /ride/{imei}/summary → summary page
 *
 * These routes only READ data,
 * they never send commands to the scooter.
 */

function makeWebScooter(ScooterStatus $status = ScooterStatus::Available): Scooter
{
    return Scooter::create([
        'imei'    => '123456789012345',
        'name'    => 'Test Scooter',
        'status'  => $status,
        'battery' => 80,
    ]);
}

function makeWebPrerequisites(): void
{
    Package::create([
        'name'             => '10 min',
        'duration_minutes' => 10,
        'price'            => 2.00,
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

function makeWebActiveRide(Scooter $scooter): Ride
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
| GET /ride/{imei} - QR entry point
|--------------------------------------------------------------------------
*/

it('shows package selection page for available scooter', function () {
    $scooter = makeWebScooter();
    makeWebPrerequisites();

    $response = $this->get("/ride/{$scooter->imei}");

    $response->assertStatus(200);

    $response->assertViewIs('ride.start');
    $response->assertViewHas('packages');
    $response->assertViewHas('scooter');
    $response->assertViewHas('unlockFee');
});

it('redirects to active page when scooter already has active ride', function () {
    $scooter = makeWebScooter(ScooterStatus::InRide);
    makeWebActiveRide($scooter);

    $response = $this->get("/ride/{$scooter->imei}");

    $response->assertRedirect(route('ride.active', ['imei' => $scooter->imei]));
});

it('returns 404 for unknown scooter imei on start page', function () {
    $response = $this->get('/ride/999999999999999');

    $response->assertStatus(404);
});

/*
|--------------------------------------------------------------------------
| GET /ride/{imei}/active - active ride page
|--------------------------------------------------------------------------
*/

it('shows active ride page when ride exists', function () {
    $scooter = makeWebScooter(ScooterStatus::InRide);
    makeWebActiveRide($scooter);

    $response = $this->get("/ride/{$scooter->imei}/active");

    $response->assertStatus(200);
    $response->assertViewIs('ride.active');
    $response->assertViewHas('ride');
    $response->assertViewHas('scooter');
});

it('redirects to start page when no active ride exists', function () {
    $scooter = makeWebScooter();

    $response = $this->get("/ride/{$scooter->imei}/active");

    $response->assertRedirect(route('ride.start', ['imei' => $scooter->imei]));
});

/*
|--------------------------------------------------------------------------
| GET /ride/{imei}/summary - post-ride summary page
|--------------------------------------------------------------------------
*/

it('shows summary page after ride is completed', function () {
    $scooter = makeWebScooter();

    Ride::create([
        'scooter_id'         => $scooter->id,
        'package_minutes'    => 10,
        'unlock_fee'         => 1.00,
        'cost'               => 3.00,
        'payment_session_id' => 'session_abc',
        'status'             => RideStatus::Completed,
        'started_at'         => now()->subMinutes(10),
        'ended_at'           => now(),
        'duration_minutes'   => 10,
    ]);

    $response = $this->get("/ride/{$scooter->imei}/summary");

    $response->assertStatus(200);
    $response->assertViewIs('ride.summary');
    $response->assertViewHas('ride');
    $response->assertViewHas('scooter');
});

it('returns 404 on summary page when no completed ride exists', function () {
    $scooter = makeWebScooter();

    $response = $this->get("/ride/{$scooter->imei}/summary");

    $response->assertStatus(404);
});
