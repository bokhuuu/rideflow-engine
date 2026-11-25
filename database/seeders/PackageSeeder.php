<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Seed the packages table with client-confirmed prices.
     */
    public function run(): void
    {
        // Disable foreign key checks so we can truncate even with payments referencing packages.
        // Re-enable immediately after - only needed for the truncate operation.
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Package::truncate();
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $packages = [
            ['name' => '5 minutes', 'duration_minutes' => 5,  'price' => 3.00, 'sort_order' => 1],
            ['name' => '10 minutes', 'duration_minutes' => 10, 'price' => 5.00, 'sort_order' => 2],
            ['name' => '20 minutes', 'duration_minutes' => 20, 'price' => 9.00, 'sort_order' => 3],
            ['name' => '30 minutes', 'duration_minutes' => 30, 'price' => 15.00, 'sort_order' => 4],
            ['name' => '1 hour', 'duration_minutes' => 60, 'price' => 20.00, 'sort_order' => 5],
        ];

        foreach ($packages as $package) {
            Package::create([...$package, 'is_active' => true]);
        }
    }
}
