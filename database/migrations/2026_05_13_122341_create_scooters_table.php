<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores every physical scooter registered in the system.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scooters', function (Blueprint $table) {
            $table->id();

            // 15-digit hardware identifier - how we address the device over TCP.
            $table->string('imei', 15)->unique();

            // Human-readable name shown in the admin panel (e.g. "Scooter #14").
            $table->string('name');

            // Current state of the scooter.
            // available - ready to ride
            // in_ride - currently being used
            // offline - not connected to TCP server
            // maintenance - taken out of service manually
            $table->enum('status', ['available', 'in_ride', 'offline', 'maintenance'])
                ->default('offline');

            // Last known GPS position, updated on each heartbeat (H0) from the device.
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            // Battery level percentage, updated on heartbeat.
            $table->unsignedTinyInteger('battery')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scooters');
    }
};
