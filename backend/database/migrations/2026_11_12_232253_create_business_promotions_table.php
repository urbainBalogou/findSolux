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
        Schema::create('business_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->unsignedSmallInteger('duration_value')->nullable();
            $table->string('duration_unit', 10)->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
            $table->index('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_promotions');
    }
};
