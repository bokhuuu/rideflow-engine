<?php

use App\DTOs\ScooterCommandResult;
use App\Enums\RideStatus;
use App\Enums\ScooterStatus;
use App\Exceptions\ScooterException;
use App\Models\Package;
use App\Models\Scooter;
use App\Models\Setting;
use App\Services\RideService;
use App\Services\ScooterService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * RideService coordinates two things:
 *   - ScooterService → physical device commands (unlock, lock, tracking)
 *   - RideRepository → database records (create, complete, find)
 *
 * Strategy:
 *   - ScooterService is MOCKED - no real HTTP to Python
 *   - RideRepository hits real SQLite in-memory DB
 */

uses(RefreshDatabase::class);

function makeScooter(ScooterStatus $status = ScooterStatus::Available): Scooter
{
    return Scooter::create([
        'imei'    => '123456789012345',
        'name'    => 'Test Scooter',
        'status'  => $status,
        'battery' => 80,
    ]);
}

function makePackage(int $minutes = 10, float $price = 2.00): Package
{
    return Package::create([
        'name'             => "{$minutes} min",
        'duration_minutes' => $minutes,
        'price'            => $price,
        'is_active'        => true,
    ]);
}

function makeUnlockFeeSetting(float $fee = 1.00): Setting
{
    return Setting::create([
        'key'   => 'unlock_fee',
        'value' => $fee,
        'type'  => 'float',
        'label' => 'Unlock Fee',
    ]);
}

function mockScooterService(array $overrides = []): void
{
    $successResult = new ScooterCommandResult(
        success: true,
        message: 'ok',
        data: ['ride_duration_minutes' => 10],
    );

    test()->mock(ScooterService::class, function ($mock) use ($overrides, $successResult) {
        $methods = ['unlock', 'lock', 'startTracking', 'stopTracking'];

        foreach ($methods as $method) {
            $mock->shouldReceive($method)
                ->andReturn($overrides[$method] ?? $successResult);
        }
    });
}

/*
|--------------------------------------------------------------------------
| startRide()
|--------------------------------------------------------------------------
*/

it('creates a ride record when scooter unlocks successfully', function () {
    $scooter = makeScooter();
    makePackage(10, 2.00);
    makeUnlockFeeSetting(1.00);

    mockScooterService();

    $service = app(RideService::class);
    $ride = $service->startRide($scooter->imei, 10, 'session_abc');

    expect($ride->status)->toBe(RideStatus::Active);
    expect($ride->package_minutes)->toBe(10);
    expect($ride->payment_session_id)->toBe('session_abc');

    expect($ride->cost)->toBe(3.0);
});

it('marks scooter as in_ride after successful unlock', function () {
    $scooter = makeScooter();
    makePackage(10);
    makeUnlockFeeSetting();
    mockScooterService();

    $service = app(RideService::class);
    $service->startRide($scooter->imei, 10, 'session_abc');

    expect($scooter->fresh()->status)->toBe(ScooterStatus::InRide);
});

/*
|--------------------------------------------------------------------------
| startRide()
|--------------------------------------------------------------------------
*/

it('throws when scooter is not available', function () {
    $scooter = makeScooter(ScooterStatus::InRide);
    makePackage(10);
    makeUnlockFeeSetting();
    mockScooterService();

    $service = app(RideService::class);

    expect(fn() => $service->startRide($scooter->imei, 10, 'session_abc'))
        ->toThrow(\RuntimeException::class);
});

it('throws when package minutes is invalid', function () {
    $scooter = makeScooter();
    makePackage(10);
    makeUnlockFeeSetting();
    mockScooterService();

    $service = app(RideService::class);

    expect(fn() => $service->startRide($scooter->imei, 99, 'session_abc'))
        ->toThrow(\RuntimeException::class);
});

it('throws when scooter unlock fails', function () {
    $scooter = makeScooter();
    makePackage(10);
    makeUnlockFeeSetting();

    test()->mock(ScooterService::class, function ($mock) {
        $mock->shouldReceive('unlock')
            ->andThrow(new ScooterException('Python down'));

        $successResult = new ScooterCommandResult(success: true, message: 'ok');
        $mock->shouldReceive('lock')->andReturn($successResult);
        $mock->shouldReceive('startTracking')->andReturn($successResult);
        $mock->shouldReceive('stopTracking')->andReturn($successResult);
    });

    $service = app(RideService::class);

    expect(fn() => $service->startRide($scooter->imei, 10, 'session_abc'))
        ->toThrow(\RuntimeException::class);
});

it('throws when scooter refuses unlock', function () {
    $scooter = makeScooter();
    makePackage(10);
    makeUnlockFeeSetting();

    mockScooterService([
        'unlock' => new ScooterCommandResult(
            success: false,
            message: 'Device refused',
        ),
    ]);

    $service = app(RideService::class);

    expect(fn() => $service->startRide($scooter->imei, 10, 'session_abc'))
        ->toThrow(\RuntimeException::class);
});

/*
|--------------------------------------------------------------------------
| endRide()
|--------------------------------------------------------------------------
*/

it('completes a ride and marks scooter available', function () {
    $scooter = makeScooter(ScooterStatus::InRide);
    makePackage(10);
    makeUnlockFeeSetting();
    mockScooterService();

    $ride = \App\Models\Ride::create([
        'scooter_id'         => $scooter->id,
        'package_minutes'    => 10,
        'unlock_fee'         => 1.00,
        'cost'               => 3.00,
        'payment_session_id' => 'session_abc',
        'status'             => RideStatus::Active,
        'started_at'         => now()->subMinutes(8),
    ]);

    $service = app(RideService::class);
    $completedRide = $service->endRide($scooter->imei);

    expect($completedRide->status)->toBe(RideStatus::Completed);
    expect($completedRide->ended_at)->not->toBeNull();

    expect($scooter->fresh()->status)->toBe(ScooterStatus::Available);
});

it('throws when ending a ride that does not exist', function () {
    $scooter = makeScooter(ScooterStatus::Available);
    mockScooterService();

    $service = app(RideService::class);

    expect(fn() => $service->endRide($scooter->imei))
        ->toThrow(\RuntimeException::class);
});

/*
|--------------------------------------------------------------------------
| getActiveRide()
|--------------------------------------------------------------------------
*/

it('returns active ride when one exists', function () {
    $scooter = makeScooter(ScooterStatus::InRide);

    $ride = \App\Models\Ride::create([
        'scooter_id'         => $scooter->id,
        'package_minutes'    => 10,
        'unlock_fee'         => 1.00,
        'cost'               => 3.00,
        'payment_session_id' => 'session_abc',
        'status'             => RideStatus::Active,
        'started_at'         => now(),
    ]);

    $service = app(RideService::class);
    $found = $service->getActiveRide($scooter->imei);

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($ride->id);
});

it('returns null when no active ride exists', function () {
    $scooter = makeScooter();

    $service = app(RideService::class);
    $found = $service->getActiveRide($scooter->imei);

    expect($found)->toBeNull();
});
