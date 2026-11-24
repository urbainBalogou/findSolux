<?php

namespace App\Enums;

/**
 * Types d'offres du catalogue. Le prix d'acceptation d'une demande n'en fait
 * pas partie : c'est un tarif unique, réglé à part (PlatformSettings).
 */
enum CommercialOfferType: string
{
    case Membership = 'membership';
    case ServiceBundle = 'service_bundle';
    case StaffBundle = 'staff_bundle';

    public function label(): string
    {
        return match ($this) {
            self::Membership => 'Adhésion mécanicien',
            self::ServiceBundle => 'Offres de services',
            self::StaffBundle => 'Employés et répondeurs',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Membership => 'Tarifs d’accès à la plateforme pour les mécaniciens.',
            self::ServiceBundle => 'Nombre de services publiables pour un prix donné.',
            self::StaffBundle => 'Nombre de membres de l’équipe (employés ou répondeurs) qui reçoivent et acceptent les demandes au nom du garage.',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Membership => 1,
            self::ServiceBundle => 2,
            self::StaffBundle => 3,
        };
    }

    /**
     * Achetable depuis le portefeuille et accordable par l'administrateur.
     */
    public function isPurchasable(): bool
    {
        return in_array($this, [self::Membership, self::ServiceBundle, self::StaffBundle], true);
    }

    public function requiresQuantity(): bool
    {
        return in_array($this, [self::ServiceBundle, self::StaffBundle], true);
    }

    public function quantityLabel(): string
    {
        return match ($this) {
            self::ServiceBundle => 'services',
            self::StaffBundle => 'collaborateurs',
            default => '',
        };
    }
}
