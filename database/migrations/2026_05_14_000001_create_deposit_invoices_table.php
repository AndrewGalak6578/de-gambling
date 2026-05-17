<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deposit_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('provider_invoice_id', 64)->unique();
            $table->string('provider_public_id', 64)->unique();
            $table->string('external_id', 120)->nullable();
            $table->string('status', 40);
            $table->string('asset_key', 40);
            $table->string('coin', 40);
            $table->string('network_key', 40);
            $table->string('pay_address');
            $table->decimal('amount_coin', 24, 8);
            $table->decimal('expected_usd', 24, 8);
            $table->decimal('rate_usd', 24, 8);
            $table->string('hosted_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_invoices');
    }
};
