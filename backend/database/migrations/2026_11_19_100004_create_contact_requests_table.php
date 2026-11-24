<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de contact d'un client à un garage (C4) et contacts enregistrés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('client_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('message', 500)->nullable();
            $table->string('status', 20)->default('pending');
            $table->dateTime('expires_at');
            $table->dateTime('responded_at')->nullable();
            $table->unsignedInteger('price_xof')->nullable();
            $table->foreignId('wallet_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['client_user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        // Contacts échangés : la demande de contact n'est débitée qu'une fois par couple.
        Schema::create('business_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source', 20);
            $table->timestamps();

            $table->unique(['business_id', 'client_user_id']);
            $table->index('client_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_contacts');
        Schema::dropIfExists('contact_requests');
    }
};
