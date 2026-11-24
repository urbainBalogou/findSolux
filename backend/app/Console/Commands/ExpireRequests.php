<?php

namespace App\Console\Commands;

use App\Services\Requests\ContactRequestService;
use App\Services\Requests\DistressSignalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('requests:expire')]
#[Description('Clore les signaux de détresse et les demandes de contact restés sans réponse (planifiée chaque minute)')]
class ExpireRequests extends Command
{
    public function handle(DistressSignalService $distressSignals, ContactRequestService $contactRequests): int
    {
        $signals = $distressSignals->expireDue();
        $requests = $contactRequests->expireDue();

        $this->info("Signaux clos : {$signals} · Demandes de contact expirées : {$requests}");

        return self::SUCCESS;
    }
}
