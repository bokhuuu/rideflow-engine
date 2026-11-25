<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zones define geographic boundaries for scooter operations.
     * One zone type for now: 'operating' - scooters must stay inside.
     *
     * Coordinates are stored as JSON (array of [lat, lng] points), not MySQL
     * SPATIAL types.
     */
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->enum('type', ['operating'])->default('operating');

            $table->json('coordinates');

            $table->string('color')->default('#3B82F6');

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
