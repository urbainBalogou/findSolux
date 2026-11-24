<?php

namespace App\Services\Wallets;

use App\Enums\WalletEntryDirection;
use App\Enums\WalletEntryType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function walletFor(User $user): Wallet
    {
        return $user->wallet()->firstOrCreate([], ['balance_xof' => 0]);
    }

    /**
     * Débite le portefeuille, sans découvert. La ligne du portefeuille est
     * verrouillée jusqu'à la fin de la transaction en cours : l'appelant
     * enregistre ce que le débit paie dans la même transaction.
     *
     * @return WalletEntry|null Null pour un montant nul (rien n'est débité).
     *
     * @throws InsufficientBalanceException
     */
    public function debit(
        User $user,
        int $amountXof,
        WalletEntryType $type,
        string $reference,
        string $description,
    ): ?WalletEntry {
        if ($amountXof <= 0) {
            return null;
        }

        return DB::transaction(function () use ($user, $amountXof, $type, $reference, $description): WalletEntry {
            $walletId = $this->walletFor($user)->getKey();
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($walletId);

            if ($wallet->balance_xof < $amountXof) {
                throw new InsufficientBalanceException($amountXof, $wallet->balance_xof);
            }

            $wallet->balance_xof -= $amountXof;
            $wallet->save();

            return $wallet->entries()->create([
                'type' => $type,
                'direction' => WalletEntryDirection::Debit,
                'amount_xof' => $amountXof,
                'balance_after_xof' => $wallet->balance_xof,
                'reference' => $reference,
                'description' => $description,
            ]);
        });
    }

    /**
     * Solde actuel, sans créer de portefeuille.
     */
    public function balanceOf(User $user): int
    {
        return (int) ($user->wallet()->value('balance_xof') ?? 0);
    }
}
