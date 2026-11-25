<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    protected $fillable = [
        'name',
        'type',
        'coordinates',
        'is_active',
    ];

    protected $casts = [
        'coordinates' => 'array',
        'is_active'   => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOperating(Builder $query): Builder
    {
        return $query->where('type', 'operating');
    }
}
