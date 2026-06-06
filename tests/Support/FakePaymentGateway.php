<?php

namespace Tests\Support;

use App\Modules\Finance\Contracts\PaymentGatewayInterface;

class FakePaymentGateway implements PaymentGatewayInterface
{
    public array $invoiceResponse;

    public bool $signatureValid = true;

    public ?array $parsedWebhook = null;

    public array $lastCreatePayload = [];

    public function __construct()
    {
        $this->invoiceResponse = [
            'provider_invoice_id' => 'provider-invoice-1',
            'provider_public_id' => 'public-invoice-1',
            'external_id' => 'external-1',
            'status' => 'pending',
            'coin' => 'btc',
            'asset_key' => 'btc',
            'network_key' => 'bitcoin',
            'pay_address' => 'bc1qtestaddress',
            'amount_coin' => '0.00100000',
            'expected_usd' => '50.00000000',
            'rate_usd' => '50000.00000000',
            'expires_at' => now()->addMinutes(20)->toISOString(),
            'hosted_url' => 'https://payments.test/invoice',
            'payload' => ['fake' => true],
        ];
    }

    public function createDepositInvoice(array $payload): array
    {
        $this->lastCreatePayload = $payload;

        return [
            ...$this->invoiceResponse,
            'external_id' => $payload['external_id'] ?? $this->invoiceResponse['external_id'],
            'expected_usd' => number_format((float) $payload['amount_usd'], 8, '.', ''),
            'coin' => strtolower((string) $payload['coin']),
        ];
    }

    public function parseWebhook(array $payload): array
    {
        if ($this->parsedWebhook !== null) {
            return $this->parsedWebhook;
        }

        $invoice = $payload['invoice'] ?? $payload['data']['invoice'] ?? $payload['data'] ?? $payload;

        return [
            'event' => (string) ($payload['event'] ?? ''),
            'provider_invoice_id' => isset($invoice['id']) ? (string) $invoice['id'] : null,
            'provider_public_id' => isset($invoice['public_id']) ? (string) $invoice['public_id'] : null,
            'external_id' => isset($invoice['external_id']) ? (string) $invoice['external_id'] : null,
            'status' => isset($invoice['status']) ? strtolower((string) $invoice['status']) : null,
            'payload' => $payload,
        ];
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        return $this->signatureValid && $signature !== null && $signature !== '';
    }
}
