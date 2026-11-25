<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a single payment attempt for a scooter ride.
 *
 * Created when user selects a package and is redirected to Flitt.
 * Updated when Flitt sends the callback confirming or rejecting the payment.
 */
class Payment extends Model
{
    protected $fillable = [
        'ride_id',
        'package_id',
        'imei',
        'amount',
        'flitt_order_id',
        'flitt_payment_id',
        'status',
    ];

    protected $casts = [
        'status' => PaymentStatus::class,
        'amount' => 'decimal:2',
    ];

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
