<?php

use App\Models\Zone;
use App\Services\GeofenceService;

/*
 * We test isInsideOperatingZone() - the method that queries the database
 * for active operating zones and checks if a GPS point is inside any of them.
 *
 * RefreshDatabase wipes and rebuilds the SQLite in-memory DB before each test.
 * So every test starts with a completely empty zones table.
 */

beforeEach(function () {
    $this->service = new GeofenceService();

    $this->square = [
        [41.70, 44.70],
        [41.70, 44.80],
        [41.60, 44.80],
        [41.60, 44.70],
    ];
});

it('returns true when no zones are configured', function () {
    $result = $this->service->isInsideOperatingZone(41.65, 44.75);

    expect($result)->toBeTrue();
});

it('returns true when point is inside an active operating zone', function () {
    Zone::create([
        'name'        => 'Test Zone',
        'type'        => 'operating',
        'coordinates' => $this->square,
        'is_active'   => true,
    ]);

    $result = $this->service->isInsideOperatingZone(41.65, 44.75);

    expect($result)->toBeTrue();
});

it('returns false when point is outside all active operating zones', function () {
    Zone::create([
        'name'        => 'Test Zone',
        'type'        => 'operating',
        'coordinates' => $this->square,
        'is_active'   => true,
    ]);

    $result = $this->service->isInsideOperatingZone(41.00, 44.00);

    expect($result)->toBeFalse();
});

it('ignores inactive zones and returns true', function () {
    Zone::create([
        'name'        => 'Inactive Zone',
        'type'        => 'operating',
        'coordinates' => $this->square,
        'is_active'   => false,
    ]);

    $result = $this->service->isInsideOperatingZone(41.65, 44.75);

    expect($result)->toBeTrue();
});
