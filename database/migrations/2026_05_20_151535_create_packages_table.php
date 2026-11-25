<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores ride packages managed by the admin.
 * Prices and durations are client-configurable from the admin panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->unsignedSmallInteger('duration_minutes');

            $table->decimal('price', 8, 2);

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
