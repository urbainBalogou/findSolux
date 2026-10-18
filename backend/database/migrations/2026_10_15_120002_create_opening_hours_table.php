<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            // 1 = lundi … 7 = dimanche (ISO 8601). Plusieurs plages par jour possibles.
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');

            $table->index(['business_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_hours');
    }
};
