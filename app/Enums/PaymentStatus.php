<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Represents the lifecycle of a Flitt payment.
 *
 * pending  → user was redirected to Flitt, waiting for callback
 * approved → Flitt confirmed payment, scooter unlocked, ride started
 * failed   → payment was declined or something went wrong
 * refunded → payment was refunded after the fact (manual, from admin)
 */
enum PaymentStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Failed   = 'failed';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending  => 'Pending',
            self::Approved => 'Approved',
            self::Failed   => 'Failed',
            self::Refunded => 'Refunded',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Failed => 'danger',
            self::Refunded => 'gray',
        };
    }
}
