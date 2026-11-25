<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Represents a ride package available for purchase.
 * Managed by admin - prices and availability configurable from panel.
 */
class Package extends Model
{
    protected $fillable = [
        'name',
        'duration_minutes',
        'price',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'float',
    ];

    /**
     * Scope to get only active packages in display order.
     * Used by the ride page to show available packages to the client.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
