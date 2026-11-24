<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membres de l'équipe d'un garage (employés ou répondeurs) : comptes créés
     * par le garagiste avec un identifiant et un mot de passe, dans la limite
     * de ses packs « Employés et répondeurs ». Ils reçoivent et acceptent les
     * demandes au nom du garage.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_business_id')->nullable()->after('id')->constrained('businesses')->cascadeOnDelete();
            $table->string('username', 30)->nullable()->unique()->after('team_business_id');
            $table->string('password')->nullable()->after('username');
        });

        Schema::table('businesses', function (Blueprint $table) {
            // Nombre de membres permis par les packs actifs, recalculé comme services_quota.
            $table->unsignedSmallInteger('team_quota')->default(0)->after('services_quota');
        });

        // Qui a accepté : le garagiste ou un membre de son équipe.
        Schema::table('distress_signals', function (Blueprint $table) {
            $table->foreignId('accepted_by_user_id')->nullable()->after('accepted_business_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('contact_requests', function (Blueprint $table) {
            $table->foreignId('accepted_by_user_id')->nullable()->after('responded_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contact_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accepted_by_user_id');
        });

        Schema::table('distress_signals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accepted_by_user_id');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('team_quota');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropConstrainedForeignId('team_business_id');
            $table->dropColumn(['username', 'password']);
        });
    }
};
