<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * last_lat / last_lng - the scooter's real-time position during a ride.
     *  
     * zone_violated_at - the exact moment the scooter first crossed the zone boundary.
     */
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->decimal('last_lat', 10, 7)->nullable()->after('end_lng');

            $table->decimal('last_lng', 10, 7)->nullable()->after('last_lat');

            $table->timestamp('zone_violated_at')->nullable()->after('last_lng');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['last_lat', 'last_lng', 'zone_violated_at']);
        });
    }
};
