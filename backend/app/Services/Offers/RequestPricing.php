<?php

namespace App\Services\Offers;

use App\Services\Settings\PlatformSettings;

/**
 * Prix débité au garage qui accepte une demande (§4.3) : un tarif unique, fixé
 * dans le back-office (« Tarifs et offres »), indépendant des offres
 * souscrites. À 0, l'acceptation est gratuite.
 */
class RequestPricing
{
    public function __construct(private readonly PlatformSettings $settings) {}

    public function acceptancePriceXof(): int
    {
        return $this->settings->requestAcceptancePriceXof();
    }
}
