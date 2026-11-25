<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

/**
 * Handles all communication with the Flitt payment gateway.
 * 
 * Creating a checkout URL - sends user to Flitt payment page
 * Verifying callback signature - confirms callback is really from Flitt
 */
class FlittService
{
    private int $merchantId;

    private string $paymentKey;

    private string $checkoutUrl;

    public function __construct()
    {
        $this->merchantId = (int) config('flitt.merchant_id');
        $this->paymentKey = config('flitt.payment_key');
        $this->checkoutUrl = config('flitt.checkout_url');
    }

    /**
     * Creates a Flitt checkout URL for the given payment.
     *
     * Sends a POST request to Flitt with the order details.
     * Flitt responds with a checkout_url where we redirect the user.
     */
    public function createCheckoutUrl(Payment $payment): string
    {
        $params = [
            'merchant_id'         => $this->merchantId,
            'order_id'            => $payment->flitt_order_id,
            'order_desc'          => 'SGScooters scooter ride - ' . $payment->package->name,
            'currency'            => 'GEL',
            'amount'              => (int) round($payment->amount * 100),
            'server_callback_url' => route('payment.callback.flitt'),
            'response_url'        => route('ride.active', ['imei' => $payment->imei]),
        ];

        $params['signature'] = $this->buildSignature($params);

        $response = Http::post($this->checkoutUrl, [
            'request' => $params,
        ]);

        $body = $response->json();

        if (
            ! isset($body['response']['response_status']) ||
            $body['response']['response_status'] !== 'success'
        ) {
            throw new \RuntimeException(
                'Flitt checkout creation failed: ' . json_encode($body)
            );
        }

        return $body['response']['checkout_url'];
    }

    /**
     * Verifies that a callback POST request genuinely came from Flitt.
     *
     * Flitt signs every callback with payment key using the same
     * SHA1 formula used for requests. Rebuild the signature from
     * the callback data and compare it to what Flitt sent.
     */
    public function verifyCallback(array $data): bool
    {
        $receivedSignature = $data['signature'] ?? '';

        $params = collect($data)
            ->except(['signature', 'response_signature_string'])
            ->toArray();

        $expectedSignature = $this->buildSignature($params);

        return hash_equals($expectedSignature, $receivedSignature);
    }

    /**
     * Builds a SHA1 signature from the given parameters.
     */
    private function buildSignature(array $params): string
    {
        // Remove empty values - Flitt excludes empty params from signature.
        $filtered = array_filter($params, fn($value) => $value !== '' && $value !== null);

        // Sort alphabetically by key name.
        ksort($filtered);

        // Take only the values, not the keys.
        $values = array_values($filtered);

        // Prepend the payment key as the first element.
        array_unshift($values, $this->paymentKey);

        // Join with | separator.
        $string = implode('|', $values);

        // SHA1 hash - must be lowercase, Flitt rejects uppercase.
        return strtolower(sha1($string));
    }
}
