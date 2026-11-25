<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ScooterStatus: string implements HasColor, HasLabel
{
    case Available = 'available';
    case InRide = 'in_ride';
    case Offline = 'offline';
    case Maintenance = 'maintenance';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::InRide => 'In Ride',
            self::Offline => 'Offline',
            self::Maintenance => 'Maintenance',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Available => 'success',
            self::InRide => 'warning',
            self::Offline => 'gray',
            self::Maintenance => 'danger',
        };
    }
}
