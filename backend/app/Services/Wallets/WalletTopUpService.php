<?php

namespace App\Services\Wallets;

use App\Enums\WalletEntryDirection;
use App\Enums\WalletEntryType;
use App\Enums\WalletPaymentMethod;
use App\Enums\WalletTopUpStatus;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTopUp;
use App\Services\Payments\FedaPayClient;
use App\Services\Payments\FedaPayException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletTopUpService
{
    public function __construct(
        private readonly FedaPayClient $fedaPay,
        private readonly WalletService $wallets,
    ) {}

    public function start(
        User $user,
        int $amountXof,
        WalletPaymentMethod $paymentMethod,
        string $phone,
    ): WalletTopUp {
        $topUp = $this->wallets->walletFor($user)->topUps()->create([
            'public_id' => (string) Str::uuid(),
            'amount_xof' => $amountXof,
            'status' => WalletTopUpStatus::Pending,
            'payment_method' => $paymentMethod,
            'provider' => 'fedapay',
            'expires_at' => now()->addDay(),
        ]);

        try {
            $transaction = $this->fedaPay->createTransaction($topUp, $user, $phone);
            $transactionId = $transaction['id'] ?? null;

            if (! is_int($transactionId) && ! is_string($transactionId)) {
                throw new FedaPayException('FedaPay n’a pas renvoyé l’identifiant de transaction.');
            }

            $topUp->update([
                'provider_transaction_id' => (string) $transactionId,
                'provider_status' => $transaction['status'] ?? 'pending',
            ]);

            $token = $this->fedaPay->generatePaymentToken((string) $transactionId);
            $this->fedaPay->sendMobileMoney($paymentMethod->value, $token, $phone);
        } catch (FedaPayException $exception) {
            $topUp->update([
                'status' => $topUp->provider_transaction_id
                    ? WalletTopUpStatus::Pending
                    : WalletTopUpStatus::Failed,
                'failure_reason' => 'La demande de recharge n’a pas pu être confirmée.',
            ]);
            throw $exception;
        }

        return $topUp->refresh();
    }

    public function synchronize(WalletTopUp $topUp): WalletTopUp
    {
        if ($topUp->provider_transaction_id === null) {
            return $topUp;
        }

        $transaction = $this->fedaPay->retrieveTransaction(
            $topUp->provider_transaction_id,
        );

        return $this->applyProviderTransaction($topUp, $transaction);
    }

    public function synchronizeByProviderId(string $providerTransactionId): ?WalletTopUp
    {
        $topUp = WalletTopUp::query()
            ->where('provider_transaction_id', $providerTransactionId)
            ->first();

        return $topUp === null ? null : $this->synchronize($topUp);
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function applyProviderTransaction(
        WalletTopUp $topUp,
        array $transaction,
    ): WalletTopUp {
        $this->assertMatchingTransaction($topUp, $transaction);
        $providerStatus = strtolower((string) ($transaction['status'] ?? 'pending'));

        return $this->applyStatus($topUp, $providerStatus);
    }

    private function applyStatus(
        WalletTopUp $topUp,
        string $providerStatus,
    ): WalletTopUp {
        return DB::transaction(function () use ($topUp, $providerStatus): WalletTopUp {
            $lockedTopUp = WalletTopUp::query()
                ->lockForUpdate()
                ->findOrFail($topUp->getKey());
            $lockedTopUp->provider_status = $providerStatus;

            if (in_array($providerStatus, ['approved', 'transferred'], true)) {
                $this->credit($lockedTopUp);
            } elseif ($providerStatus === 'refunded') {
                $this->refund($lockedTopUp);
            } elseif (
                in_array($providerStatus, ['declined', 'expired'], true)
                && $lockedTopUp->status !== WalletTopUpStatus::Succeeded
            ) {
                $lockedTopUp->status = WalletTopUpStatus::Failed;
                $lockedTopUp->failure_reason = 'La recharge a été refusée.';
            } elseif (
                in_array($providerStatus, ['canceled', 'cancelled'], true)
                && $lockedTopUp->status !== WalletTopUpStatus::Succeeded
            ) {
                $lockedTopUp->status = WalletTopUpStatus::Cancelled;
                $lockedTopUp->failure_reason = 'La recharge a été annulée.';
            }

            $lockedTopUp->save();

            return $lockedTopUp->refresh();
        });
    }

    private function credit(WalletTopUp $topUp): void
    {
        if (in_array(
            $topUp->status,
            [WalletTopUpStatus::Succeeded, WalletTopUpStatus::Refunded],
            true,
        )) {
            return;
        }

        $wallet = Wallet::query()->lockForUpdate()->findOrFail($topUp->wallet_id);
        $wallet->balance_xof += $topUp->amount_xof;
        $wallet->save();
        $wallet->entries()->create([
            'type' => WalletEntryType::TopUp,
            'direction' => WalletEntryDirection::Credit,
            'amount_xof' => $topUp->amount_xof,
            'balance_after_xof' => $wallet->balance_xof,
            'reference' => 'fedapay:'.$topUp->provider_transaction_id.':credit',
            'description' => 'Recharge du portefeuille',
        ]);

        $topUp->status = WalletTopUpStatus::Succeeded;
        $topUp->completed_at ??= now();
        $topUp->failure_reason = null;
    }

    private function refund(WalletTopUp $topUp): void
    {
        if ($topUp->status === WalletTopUpStatus::Refunded) {
            return;
        }

        if ($topUp->status === WalletTopUpStatus::Succeeded) {
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($topUp->wallet_id);
            $wallet->balance_xof -= $topUp->amount_xof;
            $wallet->save();
            $wallet->entries()->create([
                'type' => WalletEntryType::TopUpRefund,
                'direction' => WalletEntryDirection::Debit,
                'amount_xof' => $topUp->amount_xof,
                'balance_after_xof' => $wallet->balance_xof,
                'reference' => 'fedapay:'.$topUp->provider_transaction_id.':refund',
                'description' => 'Remboursement de la recharge',
            ]);
        }

        $topUp->status = WalletTopUpStatus::Refunded;
        $topUp->failure_reason = 'Cette recharge a été remboursée.';
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function assertMatchingTransaction(
        WalletTopUp $topUp,
        array $transaction,
    ): void {
        $providerId = $transaction['id'] ?? null;
        $amount = $transaction['amount'] ?? null;
        $currency = is_array($transaction['currency'] ?? null)
            ? ($transaction['currency']['iso'] ?? null)
            : ($transaction['currency'] ?? null);
        $merchantReference = $transaction['merchant_reference'] ?? null;

        $matches = (string) $providerId === $topUp->provider_transaction_id
            && is_numeric($amount)
            && (int) $amount === $topUp->amount_xof
            && ($currency === null || strtoupper((string) $currency) === 'XOF')
            && ($merchantReference === null || $merchantReference === $topUp->public_id);

        if (! $matches) {
            throw new FedaPayException(
                'La transaction FedaPay ne correspond pas à la recharge demandée.',
            );
        }
    }
}
