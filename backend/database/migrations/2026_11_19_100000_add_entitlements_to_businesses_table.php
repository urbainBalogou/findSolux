<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Droits issus des offres achetées (ADR-015), recopiés sur la fiche et ses
 * services pour que la recherche reste une simple requête indexée. Recalculés
 * par App\Services\Offers\BusinessEntitlements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // Fin de l'adhésion en cours, prolongations comprises. Null : aucune adhésion.
            $table->dateTime('membership_ends_at')->nullable()->after('status');
            $table->boolean('has_unlimited_membership')->default(false)->after('membership_ends_at');
            // Nombre de services publiables (somme des packs actifs).
            $table->unsignedSmallInteger('services_quota')->default(0)->after('has_unlimited_membership');

            $table->index('membership_ends_at');
        });

        Schema::table('business_services', function (Blueprint $table) {
            // Service couvert par les packs actifs, donc visible des clients.
            $table->boolean('is_published')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('business_services', function (Blueprint $table) {
            $table->dropColumn('is_published');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['membership_ends_at']);
            $table->dropColumn(['membership_ends_at', 'has_unlimited_membership', 'services_quota']);
        });
    }
};
