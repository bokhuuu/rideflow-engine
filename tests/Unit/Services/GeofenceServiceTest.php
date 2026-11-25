<?php

use App\Models\Zone;
use App\Services\GeofenceService;

/*
 * We test the ray casting algorithm inside GeofenceService.
 * Ray casting shoots an imaginary ray from the point eastward
 * and counts how many times it crosses a polygon edge.
 * Odd crossings = inside. Even crossings = outside.
 *
 * These tests are pure math - no database, no HTTP, no Laravel magic.
 * We just give the method a polygon and a point and check the answer.
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

function makeZone(array $coordinates): Zone
{
    $zone = new Zone();
    $zone->coordinates = $coordinates;
    $zone->type = 'operating';
    $zone->is_active = true;
    return $zone;
}

it('returns true when point is clearly inside the polygon', function () {
    $zone = makeZone($this->square);

    $result = $this->service->isPointInZone(41.65, 44.75, $zone);

    expect($result)->toBeTrue();
});


it('returns false when point is clearly outside the polygon', function () {
    $zone = makeZone($this->square);

    $result = $this->service->isPointInZone(41.00, 44.00, $zone);

    expect($result)->toBeFalse();
});

it('returns false when polygon has fewer than 3 points', function () {
    $zone = makeZone([
        [41.70, 44.70],
        [41.70, 44.80],
    ]);

    $result = $this->service->isPointInZone(41.65, 44.75, $zone);

    expect($result)->toBeFalse();
});
