<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signaux de détresse des clients (C5) et garages alertés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distress_signals', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('client_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('message', 500);
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_type_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('status', 20)->default('open');
            $table->dateTime('expires_at');
            $table->foreignId('accepted_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->dateTime('accepted_at')->nullable();
            $table->unsignedInteger('price_xof')->nullable();
            $table->foreignId('wallet_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['client_user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('distress_signal_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distress_signal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('distance_m');
            $table->dateTime('declined_at')->nullable();
            $table->timestamps();

            $table->unique(['distress_signal_id', 'business_id']);
            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distress_signal_recipients');
        Schema::dropIfExists('distress_signals');
    }
};
