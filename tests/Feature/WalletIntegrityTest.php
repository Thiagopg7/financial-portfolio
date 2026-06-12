<?php

use App\Enums\TransactionDirection;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Wallet;
use App\Services\WalletService;

beforeEach(function () {
    $this->service = app(WalletService::class);
});

it('mantém o saldo materializado consistente com o ledger após várias operações', function () {
    $a = Wallet::factory()->withBalance(0)->create();
    $b = Wallet::factory()->withBalance(0)->create();

    $this->service->deposit($a, 10000);
    $this->service->transfer($a, $b, 3000);
    $deposit = $this->service->deposit($b, 500);
    $this->service->transfer($b, $a, 1500);
    $this->service->reverse($deposit);

    foreach ([$a, $b] as $wallet) {
        $credits = (int) $wallet->transactions()
            ->where('direction', TransactionDirection::Credit->value)
            ->sum('amount');
        $debits = (int) $wallet->transactions()
            ->where('direction', TransactionDirection::Debit->value)
            ->sum('amount');

        $latest = $wallet->transactions()->latest('id')->first();

        expect($wallet->fresh()->balance)
            ->toBe($credits - $debits)
            ->toBe($latest->balance_after);
    }
});

it('nunca deixa o saldo negativo em transferências sequenciais', function () {
    $from = Wallet::factory()->withBalance(2500)->create();
    $to = Wallet::factory()->withBalance(0)->create();

    $this->service->transfer($from, $to, 1000);
    $this->service->transfer($from, $to, 1000);

    expect(fn () => $this->service->transfer($from, $to, 1000))
        ->toThrow(InsufficientBalanceException::class);

    expect($from->fresh()->balance)->toBe(500)
        ->toBeGreaterThanOrEqual(0);
});
