<?php

use App\DTOs\ScooterCommandResult;
use App\Exceptions\ScooterException;
use App\Services\ScooterService;
use Illuminate\Support\Facades\Http;

/*
 * ScooterService talks to the Python TCP server via HTTP.
 *
 * Http::fake() - intercepts every outgoing HTTP request
 * and returns a fake response we define. No real network call happens.
 *
 */

beforeEach(function () {
    $this->service = new ScooterService();
    $this->imei = '123456789012345';
});

/*
|--------------------------------------------------------------------------
| unlock()
|--------------------------------------------------------------------------
*/

it('unlock returns successful result when python responds with success', function () {
    Http::fake([
        '*' => Http::response([
            'success' => true,
            'message' => 'Scooter unlocked',
            'data'    => [],
        ], 200),
    ]);

    $result = $this->service->unlock($this->imei, 0);

    expect($result)->toBeInstanceOf(ScooterCommandResult::class);

    expect($result->success)->toBeTrue();

    expect($result->message)->toBe('Scooter unlocked');
});

it('unlock returns failed result when python says success false', function () {
    Http::fake([
        '*' => Http::response([
            'success' => false,
            'message' => 'Device refused unlock',
            'data'    => [],
        ], 200),
    ]);

    $result = $this->service->unlock($this->imei, 0);

    expect($result)->toBeInstanceOf(ScooterCommandResult::class);

    expect($result->success)->toBeFalse();
    expect($result->message)->toBe('Device refused unlock');
});

it('unlock throws ScooterException when python server is unreachable', function () {
    Http::fake([
        '*' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        },
    ]);

    expect(fn() => $this->service->unlock($this->imei, 0))
        ->toThrow(ScooterException::class);
});

it('unlock throws ScooterException when python returns HTTP 500', function () {
    Http::fake([
        '*' => Http::response('Internal Server Error', 500),
    ]);

    expect(fn() => $this->service->unlock($this->imei, 0))
        ->toThrow(ScooterException::class);
});

/*
|--------------------------------------------------------------------------
| lock()
|--------------------------------------------------------------------------
*/

it('lock returns successful result when python responds with success', function () {
    Http::fake([
        '*' => Http::response([
            'success' => true,
            'message' => 'Scooter locked',
            'data'    => ['ride_duration_minutes' => 15],
        ], 200),
    ]);

    $result = $this->service->lock($this->imei, 0);

    expect($result)->toBeInstanceOf(ScooterCommandResult::class);
    expect($result->success)->toBeTrue();

    expect($result->get('ride_duration_minutes'))->toBe(15);
});

it('lock throws ScooterException when python is unreachable', function () {
    Http::fake([
        '*' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        },
    ]);

    expect(fn() => $this->service->lock($this->imei, 0))
        ->toThrow(ScooterException::class);
});

/*
|--------------------------------------------------------------------------
| beep()
|--------------------------------------------------------------------------
*/

it('beep returns successful result when python responds with success', function () {
    Http::fake([
        '*' => Http::response([
            'success' => true,
            'message' => 'Beep sent',
            'data'    => [],
        ], 200),
    ]);

    $result = $this->service->beep($this->imei);

    expect($result)->toBeInstanceOf(ScooterCommandResult::class);
    expect($result->success)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| ScooterCommandResult::get()
|--------------------------------------------------------------------------
| Tests the DTO helper directly - not ScooterService, just the DTO.
| No HTTP needed. Pure PHP object test.
*/

it('get returns value from data by key', function () {
    $result = new ScooterCommandResult(
        success: true,
        message: 'ok',
        data: ['ride_duration_minutes' => 20],
    );

    expect($result->get('ride_duration_minutes'))->toBe(20);
});

it('get returns default when key does not exist', function () {
    $result = new ScooterCommandResult(
        success: true,
        message: 'ok',
        data: [],
    );

    expect($result->get('ride_duration_minutes', 0))->toBe(0);
});
