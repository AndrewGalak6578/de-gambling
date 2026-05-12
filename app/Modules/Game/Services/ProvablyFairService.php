<?php

namespace App\Modules\Game\Services;

use Illuminate\Support\Str;

class ProvablyFairService
{
    /**
     * Generate a provably fair result float between 0 and 1.
     *
     * @param string $serverSeed
     * @param string $clientSeed
     * @param int $nonce
     * @return float
     */
    public function generateResult(string $serverSeed, string $clientSeed, int $nonce): float
    {
        $hash = hash_hmac('sha256', "{$clientSeed}-{$nonce}", $serverSeed);

        // Take the first 13 hex characters to generate a float
        $hexSubstring = substr($hash, 0, 13);
        $decimalValue = hexdec($hexSubstring);

        // 13 hex chars max value is 16^13 - 1
        $maxHexValue = pow(16, 13) - 1;

        return $decimalValue / $maxHexValue;
    }

    /**
     * Generate a new server seed.
     */
    public function generateServerSeed(): string
    {
        return Str::random(64);
    }

    /**
     * Get the hash of a server seed to show to the user before they play.
     */
    public function hashServerSeed(string $serverSeed): string
    {
        return hash('sha256', $serverSeed);
    }
}

