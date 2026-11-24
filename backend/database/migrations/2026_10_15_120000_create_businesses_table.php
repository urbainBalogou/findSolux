<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            // Unique en phase 1 : un garagiste, une fiche. À lever pour les
            // propriétaires de plusieurs ateliers.
            $table->foreignId('owner_user_id')->unique()->constrained('users');
            $table->foreignId('trade_id')->constrained();
            $table->string('name', 120);
            // Identifiant stable des pages web publiques (R4) : ne change pas
            // quand le nom est modifié, pour ne pas casser les liens partagés.
            $table->string('slug', 160)->unique();
            $table->string('phone', 20);
            $table->string('whatsapp_phone', 20)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('city_id')->constrained();
            $table->foreignId('district_id')->constrained();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('landmark')->nullable();
            $table->boolean('is_mobile')->default(false);
            $table->unsignedTinyInteger('service_radius_km')->nullable();
            $table->boolean('is_24_7')->default(false);
            $table->string('status', 20)->default('published');
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
            $table->index('status');
        });

        Schema::create('business_vehicle_type', function (Blueprint $table) {
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->constrained();

            $table->primary(['business_id', 'vehicle_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_vehicle_type');
        Schema::dropIfExists('businesses');
    }
};
