<?php

namespace Tests\Unit;

use App\Modules\Game\Services\ProvablyFairService;
use PHPUnit\Framework\TestCase;

class ProvablyFairServiceTest extends TestCase
{
    private ProvablyFairService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProvablyFairService();
    }

    public function test_generate_result_returns_float_between_zero_and_one(): void
    {
        $result = $this->service->generateResult('server-seed-123', 'client-seed-456', 1);

        $this->assertIsFloat($result);
        $this->assertGreaterThanOrEqual(0.0, $result);
        $this->assertLessThanOrEqual(1.0, $result);
    }

    public function test_generate_result_is_deterministic(): void
    {
        $result1 = $this->service->generateResult('abc', 'xyz', 5);
        $result2 = $this->service->generateResult('abc', 'xyz', 5);

        $this->assertSame($result1, $result2);
    }

    public function test_different_seeds_produce_different_results(): void
    {
        $result1 = $this->service->generateResult('seed-a', 'client', 1);
        $result2 = $this->service->generateResult('seed-b', 'client', 1);

        $this->assertNotSame($result1, $result2);
    }

    public function test_different_client_seeds_produce_different_results(): void
    {
        $result1 = $this->service->generateResult('server', 'client-a', 1);
        $result2 = $this->service->generateResult('server', 'client-b', 1);

        $this->assertNotSame($result1, $result2);
    }

    public function test_different_nonces_produce_different_results(): void
    {
        $result1 = $this->service->generateResult('server', 'client', 1);
        $result2 = $this->service->generateResult('server', 'client', 2);

        $this->assertNotSame($result1, $result2);
    }

    public function test_generate_server_seed_returns_64_char_string(): void
    {
        $seed = $this->service->generateServerSeed();

        $this->assertSame(64, strlen($seed));
    }

    public function test_generate_server_seed_is_random(): void
    {
        $seed1 = $this->service->generateServerSeed();
        $seed2 = $this->service->generateServerSeed();

        $this->assertNotSame($seed1, $seed2);
    }

    public function test_hash_server_seed_returns_sha256(): void
    {
        $seed = 'test-server-seed';
        $hash = $this->service->hashServerSeed($seed);

        $this->assertSame(64, strlen($hash));
        $this->assertSame(hash('sha256', $seed), $hash);
    }

    public function test_known_seed_produces_known_hash(): void
    {
        $seed = 'hello';
        $hash = $this->service->hashServerSeed($seed);

        $this->assertSame('2cf24dba5fb0a30e26e83b2ac5b9e29e1b161e5c1fa7425e73043362938b9824', $hash);
    }
}
