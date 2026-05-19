<?php

namespace App\Modules\Finance\Services;

use App\Models\DepositInvoice;
use App\Models\User;
use App\Modules\Finance\Contracts\BalanceServiceInterface;
use App\Modules\Finance\Contracts\PaymentGatewayInterface;
use App\Modules\Finance\Data\CreateDepositInvoiceData;
use App\Modules\Finance\Enums\DepositInvoiceStatus;
use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DepositService
{
    private const INTERNAL_CURRENCY = 'USD';

    public function __construct(
        private PaymentGatewayInterface $paymentGateway,
        private BalanceServiceInterface $balanceService,
    ) {}

    public function createInvoice(User $user, CreateDepositInvoiceData $data): DepositInvoice
    {
        $externalId = (string) Str::uuid();

        $invoice = $this->paymentGateway->createDepositInvoice([
            'amount_usd' => $data->amountUsd,
            'coin' => $data->coin,
            'expires_minutes' => $data->expiresMinutes,
            'external_id' => $externalId,
            'metadata' => [
                ...$data->metadata,
                'user_id' => $user->id,
            ],
        ]);

        return DepositInvoice::create([
            'user_id' => $user->id,
            'provider' => (string) config('services.payment_provider.name', 'configured'),
            'provider_invoice_id' => $invoice['provider_invoice_id'],
            'provider_public_id' => $invoice['provider_public_id'],
            'external_id' => $invoice['external_id'] ?? $externalId,
            'status' => $invoice['status'],
            'asset_key' => $invoice['asset_key'],
            'coin' => $invoice['coin'],
            'network_key' => $invoice['network_key'],
            'pay_address' => $invoice['pay_address'],
            'amount_coin' => $invoice['amount_coin'],
            'expected_usd' => $invoice['expected_usd'],
            'rate_usd' => $invoice['rate_usd'],
            'hosted_url' => $invoice['hosted_url'],
            'expires_at' => $invoice['expires_at'],
            'payload' => $invoice['payload'],
        ]);
    }

    /**
     * @param  array{
     *     event: string,
     *     provider_invoice_id?: string|null,
     *     provider_public_id?: string|null,
     *     external_id?: string|null,
     *     status?: string|null,
     *     payload: array<string, mixed>
     * }  $webhook
     */
    public function handleWebhook(array $webhook): DepositInvoice
    {
        return DB::transaction(function () use ($webhook) {
            $invoice = $this->findInvoiceForWebhook($webhook);
            $status = $this->statusFromWebhook($webhook);

            if ($this->shouldAdvanceStatus($invoice->status, $status)) {
                $invoice->status = $status->value;
            }

            $invoice->payload = $webhook['payload'];

            if ($webhook['event'] === 'invoice.paid' && $invoice->credited_at === null) {
                $this->balanceService->credit(
                    userId: $invoice->user_id,
                    amount: $invoice->expected_usd,
                    currency: self::INTERNAL_CURRENCY,
                    type: TransactionType::Deposit,
                    reason: 'Deposit invoice paid.',
                    meta: [
                        'deposit_invoice_id' => $invoice->id,
                        'provider' => $invoice->provider,
                        'provider_invoice_id' => $invoice->provider_invoice_id,
                        'provider_public_id' => $invoice->provider_public_id,
                        'asset_key' => $invoice->asset_key,
                        'coin' => $invoice->coin,
                        'network_key' => $invoice->network_key,
                        'amount_coin' => $invoice->amount_coin,
                        'rate_usd' => $invoice->rate_usd,
                    ],
                    status: TransactionStatus::Confirmed,
                );

                $invoice->credited_at = now();
            }

            $invoice->save();

            return $invoice;
        });
    }

    /**
     * @param  array<string, mixed>  $webhook
     */
    private function findInvoiceForWebhook(array $webhook): DepositInvoice
    {
        $query = DepositInvoice::query();

        if (! empty($webhook['provider_invoice_id'])) {
            $query->where('provider_invoice_id', $webhook['provider_invoice_id']);
        } elseif (! empty($webhook['provider_public_id'])) {
            $query->where('provider_public_id', $webhook['provider_public_id']);
        } elseif (! empty($webhook['external_id'])) {
            $query->where('external_id', $webhook['external_id']);
        } else {
            throw new ModelNotFoundException('Webhook does not identify a deposit invoice.');
        }

        /** @var DepositInvoice $invoice */
        $invoice = $query->lockForUpdate()->firstOrFail();

        return $invoice;
    }

    /**
     * @param  array<string, mixed>  $webhook
     */
    private function statusFromWebhook(array $webhook): DepositInvoiceStatus
    {
        return match ($webhook['event']) {
            'invoice.fixated' => DepositInvoiceStatus::Fixated,
            'invoice.paid' => DepositInvoiceStatus::Paid,
            'invoice.forwarded' => DepositInvoiceStatus::Forwarded,
            default => DepositInvoiceStatus::tryFrom((string) ($webhook['status'] ?? ''))
                ?? DepositInvoiceStatus::Pending,
        };
    }

    private function shouldAdvanceStatus(string $currentStatus, DepositInvoiceStatus $nextStatus): bool
    {
        $rank = [
            DepositInvoiceStatus::Pending->value => 10,
            DepositInvoiceStatus::Fixated->value => 20,
            DepositInvoiceStatus::Paid->value => 30,
            DepositInvoiceStatus::Forwarded->value => 40,
            DepositInvoiceStatus::Expired->value => 50,
            DepositInvoiceStatus::Failed->value => 50,
        ];

        return ($rank[$nextStatus->value] ?? 0) >= ($rank[$currentStatus] ?? 0);
    }
}
