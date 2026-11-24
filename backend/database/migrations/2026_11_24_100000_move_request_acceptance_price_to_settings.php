<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SETTING = 'request_acceptance_price_xof';

    /**
     * Le prix d'acceptation d'une demande devient un tarif unique (paramètre de
     * la plateforme) au lieu d'offres du catalogue. Le prix en vigueur, celui
     * de la première offre active, est repris ; les offres sont supprimées.
     */
    public function up(): void
    {
        $offers = DB::table('commercial_offers')->where('type', 'request_acceptance');

        $currentPrice = (clone $offers)
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->value('price_xof');

        if ($currentPrice !== null && ! DB::table('platform_settings')->where('key', self::SETTING)->exists()) {
            DB::table('platform_settings')->insert([
                'key' => self::SETTING,
                'value' => (string) $currentPrice,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $offers->delete();
    }

    public function down(): void
    {
        $price = DB::table('platform_settings')->where('key', self::SETTING)->value('value');

        if ($price !== null) {
            DB::table('commercial_offers')->insert([
                'type' => 'request_acceptance',
                'name' => 'Acceptation des demandes',
                'quantity' => null,
                'price_xof' => (int) $price,
                'duration_value' => null,
                'duration_unit' => null,
                'is_unlimited' => true,
                'is_active' => true,
                'position' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('platform_settings')->where('key', self::SETTING)->delete();
    }
};
