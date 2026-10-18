<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_id')->constrained();
            $table->string('slug', 50);
            $table->string('name', 100);
            // Clé d'icône interprétée par l'application mobile.
            $table->string('icon', 50)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['trade_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
