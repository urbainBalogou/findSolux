<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Connexion par compte Google au lieu du code SMS (ADR-017). Le numéro de
     * téléphone n'identifie plus le compte : c'est le numéro où joindre
     * l'utilisateur, saisi par lui et non vérifié.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Identifiant Google (« sub ») : stable, contrairement à l'adresse e-mail.
            $table->string('google_id', 64)->nullable()->unique()->after('id');
            $table->string('email')->nullable()->index()->after('google_id');
            $table->dropUnique(['phone']);
            $table->dropColumn('phone_verified_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
        });

        Schema::dropIfExists('otp_codes');
    }

    public function down(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('code_hash', 64);
            $table->string('channel', 20);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['phone', 'created_at']);
            $table->index('created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropIndex(['email']);
            $table->dropColumn(['google_id', 'email']);
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->unique('phone');
        });
    }
};
