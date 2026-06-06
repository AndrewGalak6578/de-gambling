<?php

namespace Tests\Unit\Finance;

use App\Modules\Finance\Gateways\ConfiguredPaymentGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConfiguredPaymentGatewayTest extends TestCase
{
    public function test_create_deposit_invoice_maps_configured_provider_response(): void
    {
        config()->set('services.payment_provider.base_url', 'https://payments.example');
        config()->set('services.payment_provider.deposit_invoice_path', '/invoices');
        config()->set('services.payment_provider.api_key', 'secret-key');

        Http::fake([
            'https://payments.example/invoices' => Http::response([
                'data' => [
                    'id' => 'inv_123',
                    'public_id' => 'pub_123',
                    'external_id' => 'ext_123',
                    'status' => 'PENDING',
                    'coin' => 'btc',
                    'asset_key' => 'btc',
                    'network_key' => 'bitcoin',
                    'pay_address' => 'bc1qaddress',
                    'amount_coin' => '0.001',
                    'expected_usd' => '50.00',
                    'rate_usd' => '50000.00',
                    'expires_at' => '2026-06-06T10:00:00Z',
                    'hosted_url' => 'https://payments.example/i/pub_123',
                ],
            ]),
        ]);

        $invoice = (new ConfiguredPaymentGateway())->createDepositInvoice([
            'amount_usd' => '50.00',
            'coin' => 'btc',
            'expires_minutes' => 20,
            'external_id' => 'ext_123',
        ]);

        $this->assertSame('inv_123', $invoice['provider_invoice_id']);
        $this->assertSame('pub_123', $invoice['provider_public_id']);
        $this->assertSame('pending', $invoice['status']);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret-key'));
    }

    public function test_parse_webhook_reads_nested_invoice_payload(): void
    {
        $payload = [
            'event' => 'invoice.paid',
            'data' => [
                'invoice' => [
                    'id' => 11,
                    'public_id' => 'public-11',
                    'external_id' => 'external-11',
                    'status' => 'PAID',
                ],
            ],
        ];

        $webhook = (new ConfiguredPaymentGateway())->parseWebhook($payload);

        $this->assertSame('invoice.paid', $webhook['event']);
        $this->assertSame('11', $webhook['provider_invoice_id']);
        $this->assertSame('public-11', $webhook['provider_public_id']);
        $this->assertSame('external-11', $webhook['external_id']);
        $this->assertSame('paid', $webhook['status']);
    }

    public function test_verify_webhook_signature_accepts_valid_hmac_and_rejects_invalid_signature(): void
    {
        config()->set('services.payment_provider.webhook_secret', 'webhook-secret');
        $rawBody = '{"event":"invoice.paid"}';
        $signature = hash_hmac('sha256', $rawBody, 'webhook-secret');

        $gateway = new ConfiguredPaymentGateway();

        $this->assertTrue($gateway->verifyWebhookSignature($rawBody, $signature));
        $this->assertTrue($gateway->verifyWebhookSignature($rawBody, 'sha256='.$signature));
        $this->assertFalse($gateway->verifyWebhookSignature($rawBody, 'bad-signature'));
    }
}
