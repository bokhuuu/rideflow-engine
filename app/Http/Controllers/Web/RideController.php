<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Scooter;
use App\Models\Setting;
use App\Services\RideService;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Handles the browser-facing ride flow.
 *
 * This is what the client sees after scanning the QR code.
 * Returns views, not JSON.
 */
class RideController extends Controller
{
    public function __construct(
        private readonly RideService $rideService,
    ) {}

    /**
     * QR entry point - client lands here after scanning the scooter QR code.
     *
     * Shows the package selection page if scooter is available.
     * Redirects to active ride page if scooter already has an active ride.
     */
    public function start(string $imei): View|RedirectResponse
    {
        $scooter = Scooter::where('imei', $imei)->firstOrFail();

        // If scooter already has an active ride, go straight to ride page.
        $activeRide = $this->rideService->getActiveRide($imei);
        if ($activeRide) {
            return redirect()->route('ride.active', ['imei' => $imei]);
        }

        $packages = Package::active()->get();
        $unlockFee = Setting::get('unlock_fee', 1.00);

        return view('ride.start', compact('scooter', 'packages', 'unlockFee'));
    }

    /**
     * Shows the active ride page - timer, end ride button, beep button.
     *
     * Client sees this page for the entire duration of their ride.
     */
    public function active(string $imei): View|RedirectResponse
    {
        $scooter = Scooter::where('imei', $imei)->firstOrFail();

        $ride = $this->rideService->getActiveRide($imei);

        // No active ride - send back to package selection.
        if (!$ride) {
            return redirect()->route('ride.start', ['imei' => $imei]);
        }

        return view('ride.active', compact('scooter', 'ride'));
    }

    /**
     * Shows the ride summary after the ride ends.
     *
     * Displayed once, after the scooter is locked and ride is completed.
     */
    public function summary(string $imei): View
    {
        $scooter = Scooter::where('imei', $imei)->firstOrFail();

        // Get the last completed ride for this scooter.
        $ride = $scooter->rides()
            ->where('status', \App\Enums\RideStatus::Completed)
            ->latest()
            ->firstOrFail();

        return view('ride.summary', compact('scooter', 'ride'));
    }
}
