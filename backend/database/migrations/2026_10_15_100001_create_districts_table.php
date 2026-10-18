<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained();
            $table->string('name', 100);
            $table->string('slug', 100);
            // Centre du quartier : point de départ de la recherche par quartier.
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['city_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
