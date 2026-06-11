<?php

namespace Database\Factories;

use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->numberBetween(100, 100000);

        return [
            'wallet_id' => Wallet::factory(),
            'type' => TransactionType::Deposit,
            'direction' => TransactionDirection::Credit,
            'amount' => $amount,
            'balance_after' => $amount,
            'reference' => (string) Str::ulid(),
            'counterparty_wallet_id' => null,
            'reverses_transaction_id' => null,
            'reversed_at' => null,
            'description' => null,
        ];
    }
}
