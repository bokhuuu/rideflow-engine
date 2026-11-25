<?php

namespace App\Models;

use App\Enums\ScooterStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a physical scooter registered in the system.
 */
class Scooter extends Model
{
    protected $fillable = [
        'imei',
        'name',
        'status',
        'lat',
        'lng',
        'battery',
    ];

    protected $casts = [
        'status' => ScooterStatus::class,
    ];

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }
}
