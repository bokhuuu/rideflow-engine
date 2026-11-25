<?php

use App\Enums\PaymentStatus;
use App\Enums\ScooterStatus;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Scooter;
use App\Models\Setting;
use App\Services\FlittService;
use App\Services\RideService;
use Illuminate\Support\Facades\Http;

/*
 * Tests the full payment flow from the rider's perspective.
 *
 * initiate() - rider selects package, payment record created, redirected to Flitt
 * callback() - Flitt POSTs result, payment updated, scooter unlocked
 *
 * RideService is mocked - we don't test scooter unlocking here,
 * that's covered in RideServiceTest. We only test payment logic.
 *
 * FlittService::verifyCallback() is mocked in callback tests
 * so we don't need to build real signatures in every test.
 */

beforeEach(function () {
    // Set Flitt config so FlittService can be instantiated in CI where .env is absent.
    config([
        'flitt.merchant_id'  => 1549901,
        'flitt.payment_key'  => 'test',
        'flitt.checkout_url' => 'https://pay.flitt.com/api/checkout/url',
    ]);

    // Disable CSRF for all tests in this file.
    // In production CSRF is bypassed for payment routes via bootstrap/app.php.
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
});

function makePayScooter(): Scooter
{
    return Scooter::create([
        'imei'    => '123456789012345',
        'name'    => 'Test Scooter',
        'status'  => ScooterStatus::Available,
        'battery' => 80,
    ]);
}

function makePayPackage(): Package
{
    return Package::create([
        'name'             => '5 min',
        'duration_minutes' => 5,
        'price'            => 3.00,
        'is_active'        => true,
        'sort_order'       => 1,
    ]);
}

function makePaySetting(): void
{
    Setting::create([
        'key'   => 'unlock_fee',
        'value' => '1.00',
        'type'  => 'float',
        'label' => 'Unlock Fee',
    ]);
}

function makeFlittCallbackPayload(string $orderId, string $status = 'approved'): array
{
    // Build a valid Flitt callback payload with correct signature.
    // Uses test payment key from config.
    $params = [
        'amount'       => '400',
        'currency'     => 'GEL',
        'merchant_id'  => '1549901',
        'order_id'     => $orderId,
        'order_status' => $status,
        'payment_id'   => '989999999',
    ];

    ksort($params);
    $key = config('flitt.payment_key');
    $string = $key . '|' . implode('|', array_values($params));
    $signature = strtolower(sha1($string));

    return array_merge($params, ['signature' => $signature]);
}

/*
|--------------------------------------------------------------------------
| POST /payment/{imei}/initiate
|--------------------------------------------------------------------------
*/

it('initiate creates pending payment and redirects to flitt', function () {
    $scooter = makePayScooter();
    $package = makePayPackage();
    makePaySetting();

    // Fake Flitt checkout URL response.
    Http::fake([
        'pay.flitt.com/*' => Http::response([
            'response' => [
                'response_status' => 'success',
                'checkout_url'    => 'https://pay.flitt.com/test?token=abc',
                'payment_id'      => '999',
            ],
        ], 200),
    ]);

    $response = $this->post("/payment/{$scooter->imei}/initiate", [
        'package_id' => $package->id,
    ]);

    // Should redirect to Flitt payment page.
    $response->assertRedirect('https://pay.flitt.com/test?token=abc');

    // Payment record should be created as pending.
    $this->assertDatabaseHas('payments', [
        'imei'       => $scooter->imei,
        'package_id' => $package->id,
        'status'     => 'pending',
        'amount'     => 4.00,
    ]);
});

it('initiate fails validation when package_id is missing', function () {
    $scooter = makePayScooter();

    $response = $this->post("/payment/{$scooter->imei}/initiate", []);

    $response->assertSessionHasErrors('package_id');
});

it('initiate fails validation when package_id does not exist', function () {
    $scooter = makePayScooter();

    $response = $this->post("/payment/{$scooter->imei}/initiate", [
        'package_id' => 9999,
    ]);

    $response->assertSessionHasErrors('package_id');
});

it('initiate returns 404 for unknown scooter imei', function () {
    $package = makePayPackage();

    $response = $this->post('/payment/999999999999999/initiate', [
        'package_id' => $package->id,
    ]);

    $response->assertStatus(404);
});

/*
|--------------------------------------------------------------------------
| POST /payment/callback/flitt
|--------------------------------------------------------------------------
*/

it('callback marks payment approved and starts ride when order approved', function () {
    $scooter = makePayScooter();
    $package = makePayPackage();

    Payment::create([
        'package_id'     => $package->id,
        'imei'           => $scooter->imei,
        'amount'         => 4.00,
        'flitt_order_id' => 'RF-TESTAPPROVED',
        'status'         => PaymentStatus::Pending,
    ]);

    // Mock RideService - we don't test scooter unlock here.
    $ride = new \App\Models\Ride(['id' => 1]);
    $mockRideService = Mockery::mock(RideService::class);
    $mockRideService->shouldReceive('startRide')->once()->andReturn($ride);
    $this->app->instance(RideService::class, $mockRideService);

    // Mock FlittService::verifyCallback to return true.
    $mockFlitt = Mockery::mock(FlittService::class);
    $mockFlitt->shouldReceive('verifyCallback')->once()->andReturn(true);
    $this->app->instance(FlittService::class, $mockFlitt);

    $response = $this->post('/payment/callback/flitt', [
        'order_id'     => 'RF-TESTAPPROVED',
        'order_status' => 'approved',
        'payment_id'   => '989999999',
        'signature'    => 'fake_but_mocked',
    ]);

    $response->assertStatus(200);
    $response->assertSee('ok');

    $this->assertDatabaseHas('payments', [
        'flitt_order_id'  => 'RF-TESTAPPROVED',
        'status'          => 'approved',
        'flitt_payment_id' => '989999999',
    ]);
});

it('callback marks payment failed when order is not approved', function () {
    $scooter = makePayScooter();
    $package = makePayPackage();

    Payment::create([
        'package_id'     => $package->id,
        'imei'           => $scooter->imei,
        'amount'         => 4.00,
        'flitt_order_id' => 'RF-TESTFAILED',
        'status'         => PaymentStatus::Pending,
    ]);

    $mockFlitt = Mockery::mock(FlittService::class);
    $mockFlitt->shouldReceive('verifyCallback')->once()->andReturn(true);
    $this->app->instance(FlittService::class, $mockFlitt);

    $response = $this->post('/payment/callback/flitt', [
        'order_id'     => 'RF-TESTFAILED',
        'order_status' => 'declined',
        'signature'    => 'fake_but_mocked',
    ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('payments', [
        'flitt_order_id' => 'RF-TESTFAILED',
        'status'         => 'failed',
    ]);
});

it('callback returns 200 and rejects when signature is invalid', function () {
    $mockFlitt = Mockery::mock(FlittService::class);
    $mockFlitt->shouldReceive('verifyCallback')->once()->andReturn(false);
    $this->app->instance(FlittService::class, $mockFlitt);

    $response = $this->post('/payment/callback/flitt', [
        'order_id'     => 'RF-FAKE',
        'order_status' => 'approved',
        'signature'    => 'invalidsignature',
    ]);

    $response->assertStatus(200);
    $response->assertSee('invalid signature');

    // No payment should be created or modified.
    $this->assertDatabaseCount('payments', 0);
});

it('callback ignores duplicate callbacks on already processed payment', function () {
    $scooter = makePayScooter();
    $package = makePayPackage();

    // Payment already approved from a previous callback.
    Payment::create([
        'package_id'      => $package->id,
        'imei'            => $scooter->imei,
        'amount'          => 4.00,
        'flitt_order_id'  => 'RF-DUPLICATE',
        'flitt_payment_id' => '989999999',
        'status'          => PaymentStatus::Approved,
    ]);

    $mockFlitt = Mockery::mock(FlittService::class);
    $mockFlitt->shouldReceive('verifyCallback')->once()->andReturn(true);
    $this->app->instance(FlittService::class, $mockFlitt);

    $response = $this->post('/payment/callback/flitt', [
        'order_id'     => 'RF-DUPLICATE',
        'order_status' => 'approved',
        'signature'    => 'fake_but_mocked',
    ]);

    $response->assertStatus(200);
    $response->assertSee('already processed');
});

it('callback returns 200 when payment not found', function () {
    $mockFlitt = Mockery::mock(FlittService::class);
    $mockFlitt->shouldReceive('verifyCallback')->once()->andReturn(true);
    $this->app->instance(FlittService::class, $mockFlitt);

    $response = $this->post('/payment/callback/flitt', [
        'order_id'     => 'RF-DOESNOTEXIST',
        'order_status' => 'approved',
        'signature'    => 'fake_but_mocked',
    ]);

    $response->assertStatus(200);
    $response->assertSee('payment not found');
});
