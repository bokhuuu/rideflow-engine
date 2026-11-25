<?php

use App\Http\Controllers\Api\RideController;
use App\Http\Controllers\Api\ScooterController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GpsWebhookController;
use App\Http\Middleware\InternalSecretMiddleware;
use App\Models\Scooter;
use Illuminate\Http\Request;

Route::prefix('ride')->group(function () {
    Route::post('/{imei}/start', [RideController::class, 'start']);
    Route::post('/{imei}/end', [RideController::class, 'end']);
    Route::get('/{imei}/active', [RideController::class, 'active']);
    Route::post('/{imei}/lock', [ScooterController::class, 'lock']);
    Route::post('/{imei}/beep', [ScooterController::class, 'beep']);
});

// Internal route - called only by Python TCP server, never by the public
Route::post('/internal/gps-update', [GpsWebhookController::class, 'update'])
    ->middleware(InternalSecretMiddleware::class)
    ->name('api.internal.gps-update');

// Internal route - called by Python TCP server when H0 heartbeat is received.
// Updates battery level on the scooter record.
Route::post('/internal/heartbeat', function (Request $request) {
    $request->validate([
        'imei' => ['required', 'string', 'size:15'],
        'battery' => ['required', 'integer', 'between:0,100'],
    ]);

    $scooter = Scooter::where('imei', $request->imei)->first();

    if (! $scooter) {
        return response()->json(['status' => 'unknown_device']);
    }

    $scooter->update(['battery' => $request->battery]);

    return response()->json(['status' => 'ok']);
})->middleware(InternalSecretMiddleware::class);
