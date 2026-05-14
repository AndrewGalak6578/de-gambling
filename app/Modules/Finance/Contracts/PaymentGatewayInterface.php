<?php

namespace App\Modules\Finance\Contracts;

interface PaymentGatewayInterface
{
    /**
     * @param  array{
     *     amount_usd: string,
     *     coin: string,
     *     expires_minutes: int,
     *     external_id?: string|null
     * }  $payload
     * @return array{
     *     provider_invoice_id: string,
     *     provider_public_id: string,
     *     external_id: string|null,
     *     status: string,
     *     coin: string,
     *     asset_key: string,
     *     network_key: string,
     *     pay_address: string,
     *     amount_coin: string,
     *     expected_usd: string,
     *     rate_usd: string,
     *     expires_at: string|null,
     *     hosted_url: string|null,
     *     payload: array<string, mixed>
     * }
     */
    public function createDepositInvoice(array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     event: string,
     *     provider_invoice_id?: string|null,
     *     provider_public_id?: string|null,
     *     status?: string|null,
     *     payload: array<string, mixed>
     * }
     */
    public function parseWebhook(array $payload): array;

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool;
}
