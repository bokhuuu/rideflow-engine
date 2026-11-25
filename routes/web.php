<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\RideController;
use App\Http\Controllers\Web\PaymentController;

Route::get('/', fn() => view('pages.home'))->name('home');

// QR entry point - user scans QR, lands here, shows package selection
Route::get('/ride/{imei}', [RideController::class, 'start'])->name('ride.start');
Route::match(['GET', 'POST'], '/ride/{imei}/active', [RideController::class, 'active'])->name('ride.active');
Route::get('/ride/{imei}/summary', [RideController::class, 'summary'])->name('ride.summary');

// User selected a package - create payment and redirect to Flitt
Route::post('/payment/{imei}/initiate', [PaymentController::class, 'initiate'])->name('payment.initiate');

// Flitt POSTs here after payment - server to server, not browser
Route::post('/payment/callback/flitt', [PaymentController::class, 'callback'])->name('payment.callback.flitt');

// Static pages - required by Flitt for production approval
Route::get('/terms', fn() => view('pages.terms'))->name('pages.terms');
Route::get('/privacy', fn() => view('pages.privacy'))->name('pages.privacy');
Route::get('/contact', fn() => view('pages.contact'))->name('pages.contact');
