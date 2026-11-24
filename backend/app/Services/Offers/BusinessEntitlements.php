<?php

namespace App\Services\Offers;

use App\Enums\CommercialOfferType;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\BusinessSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Droits d'un garage issus de ses offres actives (ADR-015), recopiés sur la
 * fiche et ses services :
 * - adhésion : fin de l'adhésion en cours, prolongations programmées comprises ;
 * - packs de services : nombre de services publiés, dans l'ordre d'ajout ;
 * - packs « Employés et répondeurs » : nombre de membres de l'équipe actifs,
 *   les premiers créés (Business::activeTeamMembers).
 */
class BusinessEntitlements
{
    public function sync(Business $business): void
    {
        $now = CarbonImmutable::now();
        $memberships = $business->subscriptions()
            ->where('status', SubscriptionStatus::Active)
            ->where('offer_type', CommercialOfferType::Membership)
            ->orderBy('starts_at')
            ->get();
        [$membershipEndsAt, $isUnlimited] = $this->membershipCoverage($memberships, $now);

        $quota = (int) $business->subscriptions()
            ->current($now)
            ->where('offer_type', CommercialOfferType::ServiceBundle)
            ->sum('quantity');

        $teamQuota = (int) $business->subscriptions()
            ->current($now)
            ->where('offer_type', CommercialOfferType::StaffBundle)
            ->sum('quantity');

        $business->forceFill([
            'membership_ends_at' => $membershipEndsAt,
            'has_unlimited_membership' => $isUnlimited,
            'services_quota' => min($quota, 65535),
            'team_quota' => min($teamQuota, 65535),
        ])->save();

        $this->publishServices($business, $quota);
    }

    /**
     * Fin de la période couverte sans interruption à partir de maintenant.
     *
     * @param  Collection<int, BusinessSubscription>  $memberships  Triées par début.
     * @return array{0: ?CarbonImmutable, 1: bool}
     */
    private function membershipCoverage(Collection $memberships, CarbonImmutable $now): array
    {
        $coveredUntil = null;

        foreach ($memberships as $membership) {
            $startsInCoverage = $membership->starts_at->lte($coveredUntil ?? $now);

            if (! $startsInCoverage) {
                continue;
            }

            if ($membership->is_unlimited) {
                return [$coveredUntil, true];
            }

            if ($membership->ends_at->gt($coveredUntil ?? $now)) {
                $coveredUntil = $membership->ends_at;
            }
        }

        return [$coveredUntil, false];
    }

    /**
     * Publie les premiers services ajoutés, dans la limite du quota.
     */
    private function publishServices(Business $business, int $quota): void
    {
        $publishedServiceIds = $business->services()
            ->orderBy('id')
            ->pluck('service_id')
            ->unique()
            ->take($quota)
            ->values()
            ->all();

        $business->services()
            ->whereIn('service_id', $publishedServiceIds)
            ->update(['is_published' => true]);

        $business->services()
            ->whereNotIn('service_id', $publishedServiceIds)
            ->update(['is_published' => false]);
    }
}
