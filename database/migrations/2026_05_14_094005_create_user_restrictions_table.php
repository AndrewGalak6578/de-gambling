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
        Schema::create('user_restrictions', function (Blueprint $table) {
    $table->id();

    $table->foreignId('user_id')->constrained()->onDelete('cascade');

    $table->decimal('daily_deposit_limit', 15, 2)->nullable();
    $table->decimal('daily_bet_limit', 15, 2)->nullable();
    $table->decimal('daily_loss_limit', 15, 2)->nullable();

    $table->timestamp('cool_off_until')->nullable();

    $table->timestamps();
});
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_restrictions');
    }
};
