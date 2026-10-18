<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained();
            // Null : le prix vaut pour tous les véhicules pris en charge.
            $table->foreignId('vehicle_type_id')->nullable()->constrained();
            // Fourchette indicative en FCFA (entiers).
            $table->unsignedInteger('price_min')->nullable();
            $table->unsignedInteger('price_max')->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index(['service_id', 'business_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_services');
    }
};
