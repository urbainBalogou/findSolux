<?php

namespace App\Console\Commands;

use App\Services\Offers\SubscriptionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:process')]
#[Description('Renouveler, faire expirer et rappeler les offres des garages (planifiée toutes les 5 minutes)')]
class ProcessSubscriptions extends Command
{
    public function handle(SubscriptionService $subscriptions): int
    {
        $result = $subscriptions->processDue();

        $this->info("Renouvelées : {$result['renewed']} · Terminées : {$result['expired']} · Rappels : {$result['reminded']}");

        return self::SUCCESS;
    }
}
