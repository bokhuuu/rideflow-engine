<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use App\Models\Package;
use App\Models\Payment;
use App\Enums\PaymentStatus;
use App\Services\FlittService;
use Illuminate\Support\Facades\Http;

/*
 * FlittService handles all communication with the Flitt payment gateway.
 *
 * buildSignature() - SHA1 hash of sorted params joined with | separator
 * createCheckoutUrl() - POSTs to Flitt API, returns checkout_url
 * verifyCallback() - rebuilds signature from callback data and compares
 *
 * Http::fake() intercepts all outgoing HTTP calls - no real Flitt API called.
 * Config values are set directly in each test - no .env needed.
 */

beforeEach(function () {
    config([
        'flitt.merchant_id'  => 1549901,
        'flitt.payment_key'  => 'test',
        'flitt.checkout_url' => 'https://pay.flitt.com/api/checkout/url',
    ]);

    $this->service = new FlittService();
});

/*
|--------------------------------------------------------------------------
| buildSignature()
|--------------------------------------------------------------------------
|
| Signature formula (from Flitt docs):
|   1. Remove empty values
|   2. Sort params alphabetically by key
|   3. Take values only
|   4. Prepend payment key
|   5. Join with |
|   6. SHA1 lowercase
|
| We test this indirectly via createCheckoutUrl() and verifyCallback()
| since buildSignature() is private. But we can verify the output
| by reconstructing what the signature should be.
*/

it('builds correct sha1 signature for known params', function () {
    // Reproduce what buildSignature() does internally.
    // payment_key = 'test', params = ['amount' => 100, 'currency' => 'GEL', 'order_id' => 'RF-ABC']
    // sorted keys: amount, currency, order_id
    // string: "test|100|GEL|RF-ABC"
    $expected = strtolower(sha1('test|100|GEL|RF-ABC'));

    // Verify by checking the callback verification uses same formula.
    // We build a callback payload that matches this signature.
    $callbackData = [
        'amount'    => '100',
        'currency'  => 'GEL',
        'order_id'  => 'RF-ABC',
        'signature' => $expected,
    ];

    expect($this->service->verifyCallback($callbackData))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| verifyCallback()
|--------------------------------------------------------------------------
*/

it('verifyCallback returns true when signature matches', function () {
    // Build a valid signature the same way Flitt would.
    $params = [
        'amount'       => '400',
        'currency'     => 'GEL',
        'order_id'     => 'RF-TESTORDER',
        'order_status' => 'approved',
        'payment_id'   => '123456',
    ];

    // Sort alphabetically, prepend key, join, SHA1.
    $sorted = $params;
    ksort($sorted);
    $string = 'test|' . implode('|', array_values($sorted));
    $signature = strtolower(sha1($string));

    $callbackData = array_merge($params, ['signature' => $signature]);

    expect($this->service->verifyCallback($callbackData))->toBeTrue();
});

it('verifyCallback returns false when signature is wrong', function () {
    $callbackData = [
        'amount'       => '400',
        'currency'     => 'GEL',
        'order_id'     => 'RF-TESTORDER',
        'order_status' => 'approved',
        'signature'    => 'totallywrongsignature',
    ];

    expect($this->service->verifyCallback($callbackData))->toBeFalse();
});

it('verifyCallback excludes signature and response_signature_string from calculation', function () {
    // If signature or response_signature_string were included in the calculation,
    // the rebuilt signature would never match. This test confirms they are excluded.
    $params = [
        'amount'   => '400',
        'currency' => 'GEL',
        'order_id' => 'RF-TESTORDER',
    ];

    ksort($params);
    $string = 'test|' . implode('|', array_values($params));
    $signature = strtolower(sha1($string));

    $callbackData = array_merge($params, [
        'signature'                => $signature,
        'response_signature_string' => 'some_debug_string_from_flitt',
    ]);

    expect($this->service->verifyCallback($callbackData))->toBeTrue();
});

it('verifyCallback returns false when signature is missing', function () {
    $callbackData = [
        'amount'       => '400',
        'currency'     => 'GEL',
        'order_id'     => 'RF-TESTORDER',
        'order_status' => 'approved',
        // no signature key
    ];

    expect($this->service->verifyCallback($callbackData))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| createCheckoutUrl()
|--------------------------------------------------------------------------
*/

it('createCheckoutUrl returns checkout url from flitt response', function () {
    // Create a real package and payment in DB.
    $package = Package::create([
        'name'             => '5 min',
        'duration_minutes' => 5,
        'price'            => 3.00,
        'is_active'        => true,
        'sort_order'       => 1,
    ]);

    $payment = Payment::create([
        'package_id'     => $package->id,
        'imei'           => '123456789012345',
        'amount'         => 4.00,
        'flitt_order_id' => 'RF-TESTCHECKOUT',
        'status'         => PaymentStatus::Pending,
    ]);

    // Fake Flitt API response.
    Http::fake([
        'pay.flitt.com/*' => Http::response([
            'response' => [
                'response_status' => 'success',
                'checkout_url'    => 'https://pay.flitt.com/merchants/test/index.html?token=abc123',
                'payment_id'      => '123456789',
            ],
        ], 200),
    ]);

    $url = $this->service->createCheckoutUrl($payment);

    expect($url)->toBe('https://pay.flitt.com/merchants/test/index.html?token=abc123');
});

it('createCheckoutUrl throws RuntimeException when flitt returns error', function () {
    $package = Package::create([
        'name'             => '5 min',
        'duration_minutes' => 5,
        'price'            => 3.00,
        'is_active'        => true,
        'sort_order'       => 1,
    ]);

    $payment = Payment::create([
        'package_id'     => $package->id,
        'imei'           => '123456789012345',
        'amount'         => 4.00,
        'flitt_order_id' => 'RF-TESTERROR',
        'status'         => PaymentStatus::Pending,
    ]);

    Http::fake([
        'pay.flitt.com/*' => Http::response([
            'response' => [
                'response_status' => 'failure',
                'error_message'   => 'Invalid signature',
            ],
        ], 200),
    ]);

    expect(fn() => $this->service->createCheckoutUrl($payment))
        ->toThrow(\RuntimeException::class);
});

it('createCheckoutUrl sends correct amount in tetri not lari', function () {
    $package = Package::create([
        'name'             => '5 min',
        'duration_minutes' => 5,
        'price'            => 3.00,
        'is_active'        => true,
        'sort_order'       => 1,
    ]);

    $payment = Payment::create([
        'package_id'     => $package->id,
        'imei'           => '123456789012345',
        'amount'         => 4.00,
        'flitt_order_id' => 'RF-TESTAMOUNT',
        'status'         => PaymentStatus::Pending,
    ]);

    Http::fake([
        'pay.flitt.com/*' => Http::response([
            'response' => [
                'response_status' => 'success',
                'checkout_url'    => 'https://pay.flitt.com/test',
                'payment_id'      => '999',
            ],
        ], 200),
    ]);

    $this->service->createCheckoutUrl($payment);

    // Verify the request sent to Flitt had amount in tetri (400, not 4.00).
    Http::assertSent(function ($request) {
        $body = $request->data();
        return $body['request']['amount'] === 400;
    });
});
