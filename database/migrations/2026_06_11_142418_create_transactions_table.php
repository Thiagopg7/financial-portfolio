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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete preserva o histórico financeiro ao remover a carteira.
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('direction');
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->ulid('reference')->index();
            $table->foreignId('counterparty_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->foreignId('reverses_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
