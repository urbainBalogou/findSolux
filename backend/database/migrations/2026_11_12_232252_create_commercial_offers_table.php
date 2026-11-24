<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('commercial_offers', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('name', 100);
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->unsignedInteger('price_xof');
            $table->unsignedSmallInteger('duration_value')->nullable();
            $table->string('duration_unit', 10)->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['type', 'is_active', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commercial_offers');
    }
};
