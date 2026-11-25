<?php

return [
    /*
     * Your merchant ID from portal.flitt.com
     * Merchant Settings → select merchant → Merchant Number
     */
    'merchant_id' => env('FLITT_MERCHANT_ID'),

    /*
     * Your secret payment key from portal.flitt.com
     * Merchant Settings → select merchant → Payment Key
     */
    'payment_key' => env('FLITT_PAYMENT_KEY'),

    /*
     * Flitt's API endpoint for creating checkout URLs.
     * Same URL for both test and production - test/live is determined
     * by whether your merchant account is in test mode or live mode.
     */
    'checkout_url' => env('FLITT_CHECKOUT_URL', 'https://pay.flitt.com/api/checkout/url'),
];
