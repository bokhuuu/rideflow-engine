<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One record per trip. Created on unlock, completed on lock.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->id();

            $table->foreignId('scooter_id')->constrained()->cascadeOnDelete();

            // Payment reference token from the bank.
            $table->string('payment_session_id');

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();

            // Duration reported by the device in the L1 lock response.
            $table->unsignedInteger('duration_minutes')->nullable();

            // GPS position at ride start and end.
            $table->decimal('start_lat', 10, 7)->nullable();
            $table->decimal('start_lng', 10, 7)->nullable();
            $table->decimal('end_lat', 10, 7)->nullable();
            $table->decimal('end_lng', 10, 7)->nullable();

            // Final cost in GEL. Null until ride ends.
            $table->decimal('cost', 8, 2)->nullable();

            // Flat unlock fee charged on every ride start.
            $table->decimal('unlock_fee', 8, 2)->default(0);

            // Which package the client chose (10/15/20/25/30 minutes).
            $table->unsignedTinyInteger('package_minutes');

            // active - scooter unlocked, ride in progress
            // completed - scooter locked, cost calculated, payment captured
            // cancelled - ended without riding (e.g. payment failed)
            $table->enum('status', ['active', 'completed', 'cancelled'])
                ->default('active');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
