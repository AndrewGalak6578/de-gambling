<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_score_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->smallInteger('score_adjustment')->default(0);
            $table->boolean('disabled')->default(false);
            $table->string('reason', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_score_overrides');
    }
};
