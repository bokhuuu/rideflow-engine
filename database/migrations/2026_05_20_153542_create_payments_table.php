<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One payment record per ride.
 * Created when user selects a package, before the ride starts.
 * Ride starts only after Flitt confirms payment via callback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ride_id')->nullable()->constrained()->cascadeOnDelete();

            $table->foreignId('package_id')->constrained()->cascadeOnDelete();

            $table->string('imei', 15);

            $table->decimal('amount', 8, 2);

            $table->string('flitt_order_id')->unique();

            $table->string('flitt_payment_id')->nullable();

            $table->enum('status', ['pending', 'approved', 'failed', 'refunded'])
                ->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
