<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offres achetées ou accordées à un garage (ADR-015). L'offre du catalogue est
 * recopiée : un changement de prix ou de durée ne modifie pas les achats passés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commercial_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('offer_type', 30);
            $table->string('name', 100);
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->unsignedInteger('price_xof');
            $table->unsignedSmallInteger('duration_value')->nullable();
            $table->string('duration_unit', 10)->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('source', 20);
            $table->boolean('auto_renew')->default(false);
            $table->foreignId('wallet_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('granted_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('renewed_from_id')->nullable()->constrained('business_subscriptions')->nullOnDelete();
            $table->timestamp('reminded_7_days_at')->nullable();
            $table->timestamp('reminded_1_day_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status', 'offer_type']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_subscriptions');
    }
};
