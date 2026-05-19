<?php

namespace Tests\Unit;

use App\Modules\Finance\Gateways\ConfiguredPaymentGateway;
use PHPUnit\Framework\TestCase;

class ConfiguredPaymentGatewayTest extends TestCase
{
    public function test_parse_webhook_reads_top_level_invoice_payload(): void
    {
        $payload = [
            'event' => 'invoice.expired',
            'sent_at' => '2026-05-19T14:47:39+00:00',
            'invoice' => [
                'id' => 11,
                'public_id' => '2tutujaysnn113tk',
                'external_id' => '4f463305-f63e-453d-9845-2440e8ffe3f5',
                'status' => 'expired',
            ],
        ];

        $webhook = (new ConfiguredPaymentGateway())->parseWebhook($payload);

        $this->assertSame('invoice.expired', $webhook['event']);
        $this->assertSame('11', $webhook['provider_invoice_id']);
        $this->assertSame('2tutujaysnn113tk', $webhook['provider_public_id']);
        $this->assertSame('4f463305-f63e-453d-9845-2440e8ffe3f5', $webhook['external_id']);
        $this->assertSame('expired', $webhook['status']);
        $this->assertSame($payload, $webhook['payload']);
    }
}
