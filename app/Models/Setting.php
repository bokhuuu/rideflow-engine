<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-value configuration managed from the admin panel.
 * Use Setting::get('key') to read values anywhere in the app.
 */
class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'label',
    ];

    /**
     * Read a setting value by key, cast to its correct type.
     * Returns $default if the key doesn't exist.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'float' => (float)  $setting->value,
            'integer' => (int) $setting->value,
            'boolean' => (bool) $setting->value,
            default => (string) $setting->value,
        };
    }
}
