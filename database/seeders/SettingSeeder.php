<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Seed the settings table with initial configuration values.
     * These are the defaults - admin can change them from the panel.
     */
    public function run(): void
    {
        Setting::truncate();

        $settings = [
            [
                'key' => 'unlock_fee',
                'value' => '1.00',
                'type' => 'float',
                'label' => 'Unlock Fee (GEL)',
            ],
            [
                'key' => 'ride_timeout_buffer',
                'value' => '10',
                'type' => 'integer',
                'label' => 'Ride Timeout Buffer (minutes after package ends)',
            ],
            [
                'key' => 'support_phone',
                'value' => '',
                'type' => 'string',
                'label' => 'Support Phone Number',
            ],
            [
                'key' => 'support_email',
                'value' => '',
                'type' => 'string',
                'label' => 'Support Email',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::create($setting);
        }
    }
}
