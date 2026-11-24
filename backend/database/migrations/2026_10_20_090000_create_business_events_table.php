<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vues et clics sur les fiches : base des statistiques des garagistes (P6).
        Schema::create('business_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            // Identifiant anonyme généré par l'application ou la page web.
            $table->string('device_id', 64);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('occurred_on');
            $table->timestamp('created_at')->nullable();

            // Un appareil compte une fois par jour et par action : des
            // statistiques crédibles pour le garagiste (R3).
            $table->unique(['business_id', 'type', 'device_id', 'occurred_on'], 'business_events_daily_unique');
            $table->index(['business_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_events');
    }
};
