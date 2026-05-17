<?php

namespace App\Modules\Finance\Gateways;

use App\Modules\Finance\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ConfiguredPaymentGateway implements PaymentGatewayInterface
{
    /**
     * @param  array{
     *     amount_usd: string,
     *     coin: string,
     *     expires_minutes: int,
     *     external_id?: string|null,
     *     metadata?: array<string, mixed>
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
     *
     * @throws RequestException
     */
    public function createDepositInvoice(array $payload): array
    {
        $baseUrl = config('services.payment_provider.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('Payment provider base URL is not configured.');
        }

        $request = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.payment_provider.timeout', 10));

        $apiKey = config('services.payment_provider.api_key');

        if (is_string($apiKey) && $apiKey !== '') {
            $header = (string) config('services.payment_provider.api_key_header', 'Authorization');
            $prefix = (string) config('services.payment_provider.api_key_prefix', 'Bearer ');

            $request = $request->withHeaders([
                $header => $prefix.$apiKey,
            ]);
        }

        $path = (string) config('services.payment_provider.deposit_invoice_path', '/invoices');
        $response = $request->post($path, $payload)->throw()->json();

        if (! is_array($response)) {
            throw new RuntimeException('Payment provider returned an invalid response.');
        }

        $data = $response['data'] ?? $response;

        if (! is_array($data)) {
            throw new RuntimeException('Payment provider response does not contain invoice data.');
        }

        return [
            'provider_invoice_id' => (string) $data['id'],
            'provider_public_id' => (string) $data['public_id'],
            'external_id' => isset($data['external_id']) ? (string) $data['external_id'] : null,
            'status' => strtolower((string) $data['status']),
            'coin' => (string) $data['coin'],
            'asset_key' => (string) $data['asset_key'],
            'network_key' => (string) $data['network_key'],
            'pay_address' => (string) $data['pay_address'],
            'amount_coin' => (string) $data['amount_coin'],
            'expected_usd' => (string) $data['expected_usd'],
            'rate_usd' => (string) $data['rate_usd'],
            'expires_at' => isset($data['expires_at']) ? (string) $data['expires_at'] : null,
            'hosted_url' => isset($data['hosted_url']) ? (string) $data['hosted_url'] : null,
            'payload' => $response,
        ];
    }

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
    public function parseWebhook(array $payload): array
    {
        $data = $payload['data'] ?? $payload;
        $data = is_array($data) ? $data : [];

        return [
            'event' => (string) ($payload['event'] ?? $payload['type'] ?? ''),
            'provider_invoice_id' => isset($data['id']) ? (string) $data['id'] : null,
            'provider_public_id' => isset($data['public_id']) ? (string) $data['public_id'] : null,
            'status' => isset($data['status']) ? strtolower((string) $data['status']) : null,
            'payload' => $payload,
        ];
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        $secret = config('services.payment_provider.webhook_secret');

        if (! is_string($secret) || $secret === '' || ! is_string($signature) || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        $provided = str_starts_with($signature, 'sha256=')
            ? substr($signature, strlen('sha256='))
            : $signature;

        return hash_equals($expected, $provided);
    }
}
