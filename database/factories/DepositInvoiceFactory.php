<?php

namespace Database\Factories;

use App\Models\DepositInvoice;
use App\Models\User;
use App\Modules\Finance\Enums\DepositInvoiceStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepositInvoice>
 */
class DepositInvoiceFactory extends Factory
{
    protected $model = DepositInvoice::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'configured',
            'provider_invoice_id' => fake()->unique()->bothify('inv-########'),
            'provider_public_id' => fake()->unique()->bothify('pub-########'),
            'external_id' => fake()->uuid(),
            'status' => DepositInvoiceStatus::Pending->value,
            'asset_key' => 'btc',
            'coin' => 'btc',
            'network_key' => 'bitcoin',
            'pay_address' => fake()->bothify('bc1q????????????????????'),
            'amount_coin' => '0.00100000',
            'expected_usd' => '50.00000000',
            'rate_usd' => '50000.00000000',
            'hosted_url' => 'https://payments.test/invoice',
            'expires_at' => now()->addMinutes(20),
            'credited_at' => null,
            'payload' => [],
        ];
    }
}
