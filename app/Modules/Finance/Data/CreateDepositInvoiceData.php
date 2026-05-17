<?php

namespace App\Modules\Finance\Data;

final readonly class CreateDepositInvoiceData
{
    public function __construct(
        public string $amountUsd,
        public string $coin,
        public int $expiresMinutes,
        public array $metadata = [],
    ) {}

    /**
     * @param  array{amount_usd: int|float|string, coin: string, expires_minutes?: int|null, metadata?: array<string, mixed>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            amountUsd: number_format((float) $data['amount_usd'], 2, '.', ''),
            coin: strtolower($data['coin']),
            expiresMinutes: (int) ($data['expires_minutes'] ?? 20),
            metadata: $data['metadata'] ?? [],
        );
    }
}
