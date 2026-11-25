<?php

namespace App\Http\Controllers\Web;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Scooter;
use App\Models\Setting;
use App\Services\FlittService;
use App\Services\RideService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Handles the payment flow for scooter rides.
 *
 * initiate() - user selected a package, create payment, redirect to Flitt
 * callback() - Flitt POSTs here after payment, verify and unlock scooter
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly FlittService $flittService,
        private readonly RideService $rideService,
    ) {}

    /**
     * Initiates a payment for a scooter ride.
     *
     * Called when user selects a package and clicks Pay on the ride.start view.
     * Creates a pending payment record, gets checkout URL from Flitt
     * and redirects user to Flitt hosted payment page.
     */
    public function initiate(Request $request, string $imei): RedirectResponse
    {
        $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
        ]);

        Scooter::where('imei', $imei)->firstOrFail();

        $package = Package::findOrFail($request->package_id);

        $unlockFee = (float) Setting::get('unlock_fee', 1.00);
        $totalAmount = $unlockFee + $package->price;

        $payment = Payment::create([
            'package_id'      => $package->id,
            'imei'            => $imei,
            'amount'          => $totalAmount,
            'flitt_order_id'  => 'RF-' . strtoupper(Str::random(12)),
            'status'          => PaymentStatus::Pending,
        ]);

        $checkoutUrl = $this->flittService->createCheckoutUrl($payment);

        return redirect()->away($checkoutUrl);
    }

    /**
     * Handles the server-to-server callback from Flitt.
     *
     * Flitt POSTs here after the user completes (or fails) payment.
     * This is separate from the browser redirect - it happens in the background.
     *
     * Must return HTTP 200 - otherwise Flitt will retry the callback
     * at increasing intervals (2s, 60s, 300s, 600s, 1h, 24h).
     */
    public function callback(Request $request): Response
    {
        $data = $request->all();

        // Step 1: Verify the signature to confirm this is really from Flitt.
        if (! $this->flittService->verifyCallback($data)) {
            Log::warning('Flitt callback: invalid signature', $data);
            return response('invalid signature', 200);
        }

        // Step 2: Find the payment record by the order_id we generated.
        $payment = Payment::where('flitt_order_id', $data['order_id'])->first();

        if (! $payment) {
            Log::warning('Flitt callback: payment not found', ['order_id' => $data['order_id']]);
            return response('payment not found', 200);
        }

        // Step 3: Ignore duplicate callbacks.
        if ($payment->status !== PaymentStatus::Pending) {
            return response('already processed', 200);
        }

        // Step 4: Check if payment was approved or failed.
        if ($data['order_status'] === 'approved') {

            // Save payment as approved FIRST - money was taken, record it immediately.
            // Unlock failure after this point is a scooter issue, not a payment issue.
            $payment->update([
                'flitt_payment_id' => $data['payment_id'],
                'status'           => PaymentStatus::Approved,
            ]);

            // Now attempt to start the ride.
            try {
                $ride = $this->rideService->startRide(
                    $payment->imei,
                    $payment->package->duration_minutes,
                    $payment->flitt_order_id
                );
                $payment->update(['ride_id' => $ride->id]);
            } catch (\RuntimeException $e) {
                // Scooter unreachable or offline - log it for admin to investigate.
                // Payment is already recorded as approved - money is safe.
                Log::error('Callback: ride start failed after payment approved', [
                    'payment_id' => $payment->id,
                    'imei'       => $payment->imei,
                    'error'      => $e->getMessage(),
                ]);
            }
        } else {

            $payment->update(['status' => PaymentStatus::Failed]);

            Log::info('Flitt callback: payment not approved', [
                'order_id'     => $data['order_id'],
                'order_status' => $data['order_status'],
            ]);
        }

        // Always return 200 - Flitt requires this to stop retrying.
        return response('ok', 200);
    }
}
