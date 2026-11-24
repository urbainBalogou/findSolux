<?php

namespace App\Services\Settings;

use App\Models\PlatformSetting;

/**
 * Paramètres de la plateforme modifiables depuis le back-office. Les valeurs
 * par défaut sont dans config/findsolux.php (`requests`).
 */
class PlatformSettings
{
    public const DISTRESS_SIGNAL_WAIT_MINUTES = 'distress_signal_wait_minutes';

    public const CONTACT_REQUEST_WAIT_HOURS = 'contact_request_wait_hours';

    public const REQUEST_ACCEPTANCE_PRICE_XOF = 'request_acceptance_price_xof';

    /**
     * Délai pendant lequel un signal de détresse attend qu'un garage l'accepte.
     */
    public function distressSignalWaitMinutes(): int
    {
        return $this->integer(self::DISTRESS_SIGNAL_WAIT_MINUTES);
    }

    /**
     * Délai laissé au garage pour répondre à une demande de contact.
     */
    public function contactRequestWaitHours(): int
    {
        return $this->integer(self::CONTACT_REQUEST_WAIT_HOURS);
    }

    /**
     * Prix débité au garage pour chaque demande acceptée (§4.3), le même pour
     * tous, indépendant des offres souscrites.
     */
    public function requestAcceptancePriceXof(): int
    {
        return $this->integer(self::REQUEST_ACCEPTANCE_PRICE_XOF);
    }

    /**
     * @param  array<string, int>  $values
     */
    public function update(array $values): void
    {
        foreach ($values as $key => $value) {
            PlatformSetting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }
    }

    private function integer(string $key): int
    {
        $stored = PlatformSetting::query()->whereKey($key)->value('value');

        return (int) ($stored ?? config("findsolux.requests.{$key}"));
    }
}
