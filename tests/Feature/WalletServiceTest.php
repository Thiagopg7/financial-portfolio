<?php

use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\TransactionAlreadyReversedException;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->service = app(WalletService::class);
});

describe('deposit', function () {
    it('soma o valor ao saldo e registra o lançamento', function () {
        $wallet = Wallet::factory()->withBalance(1000)->create();

        $transaction = $this->service->deposit($wallet, 2500);

        expect($wallet->fresh()->balance)->toBe(3500)
            ->and($transaction->type)->toBe(TransactionType::Deposit)
            ->and($transaction->direction)->toBe(TransactionDirection::Credit)
            ->and($transaction->amount)->toBe(2500)
            ->and($transaction->balance_after)->toBe(3500);
    });

    it('abate a dívida quando o saldo está negativo', function () {
        $wallet = Wallet::factory()->withBalance(-1000)->create();

        $this->service->deposit($wallet, 700);

        expect($wallet->fresh()->balance)->toBe(-300);
    });

    it('rejeita valores não positivos', function () {
        $wallet = Wallet::factory()->create();

        expect(fn () => $this->service->deposit($wallet, 0))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('transfer', function () {
    it('move o saldo entre as carteiras e cria dois lançamentos vinculados', function () {
        $from = Wallet::factory()->withBalance(5000)->create();
        $to = Wallet::factory()->withBalance(0)->create();

        $debit = $this->service->transfer($from, $to, 2000);

        expect($from->fresh()->balance)->toBe(3000)
            ->and($to->fresh()->balance)->toBe(2000)
            ->and($debit->direction)->toBe(TransactionDirection::Debit)
            ->and($debit->counterparty_wallet_id)->toBe($to->id);

        $credit = Transaction::where('reference', $debit->reference)
            ->where('direction', TransactionDirection::Credit->value)
            ->first();

        expect($credit->wallet_id)->toBe($to->id)
            ->and($credit->counterparty_wallet_id)->toBe($from->id)
            ->and($credit->amount)->toBe(2000);
    });

    it('lança exceção e não altera saldos quando falta saldo', function () {
        $from = Wallet::factory()->withBalance(1000)->create();
        $to = Wallet::factory()->withBalance(0)->create();

        expect(fn () => $this->service->transfer($from, $to, 2000))
            ->toThrow(InsufficientBalanceException::class);

        expect($from->fresh()->balance)->toBe(1000)
            ->and($to->fresh()->balance)->toBe(0)
            ->and(Transaction::count())->toBe(0);
    });

    it('não permite transferir para a própria carteira', function () {
        $wallet = Wallet::factory()->withBalance(5000)->create();

        expect(fn () => $this->service->transfer($wallet, $wallet, 1000))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('reverse', function () {
    it('estorna um depósito restaurando o saldo e marcando o original', function () {
        $wallet = Wallet::factory()->withBalance(0)->create();
        $deposit = $this->service->deposit($wallet, 3000);

        $reversals = $this->service->reverse($deposit);

        expect($wallet->fresh()->balance)->toBe(0)
            ->and($deposit->fresh()->reversed_at)->not->toBeNull()
            ->and($reversals)->toHaveCount(1);

        $reversal = $reversals->first();
        expect($reversal->type)->toBe(TransactionType::Reversal)
            ->and($reversal->direction)->toBe(TransactionDirection::Debit)
            ->and($reversal->reverses_transaction_id)->toBe($deposit->id);
    });

    it('estorna uma transferência restaurando os dois saldos', function () {
        $from = Wallet::factory()->withBalance(5000)->create();
        $to = Wallet::factory()->withBalance(0)->create();
        $debit = $this->service->transfer($from, $to, 2000);

        $reversals = $this->service->reverse($debit);

        expect($from->fresh()->balance)->toBe(5000)
            ->and($to->fresh()->balance)->toBe(0)
            ->and($reversals)->toHaveCount(2);
    });

    it('deixa o destinatário negativo ao estornar um valor já gasto (chargeback)', function () {
        $from = Wallet::factory()->withBalance(5000)->create();
        $to = Wallet::factory()->withBalance(0)->create();
        $other = Wallet::factory()->withBalance(0)->create();

        $transfer = $this->service->transfer($from, $to, 2000);
        $this->service->transfer($to, $other, 2000);
        expect($to->fresh()->balance)->toBe(0);

        $this->service->reverse($transfer);

        expect($to->fresh()->balance)->toBe(-2000)
            ->and($from->fresh()->balance)->toBe(5000);
    });

    it('não permite reverter uma operação já revertida', function () {
        $wallet = Wallet::factory()->withBalance(0)->create();
        $deposit = $this->service->deposit($wallet, 3000);
        $this->service->reverse($deposit);

        expect(fn () => $this->service->reverse($deposit->fresh()))
            ->toThrow(TransactionAlreadyReversedException::class);
    });

    it('não permite reverter um estorno', function () {
        $wallet = Wallet::factory()->withBalance(0)->create();
        $deposit = $this->service->deposit($wallet, 3000);
        $reversal = $this->service->reverse($deposit)->first();

        expect(fn () => $this->service->reverse($reversal))
            ->toThrow(InvalidArgumentException::class);
    });

    it('registra quem solicitou a reversão', function () {
        $wallet = Wallet::factory()->withBalance(0)->create();
        $requester = User::factory()->create();
        $deposit = $this->service->deposit($wallet, 3000);

        $reversal = $this->service->reverse($deposit, $requester)->first();

        expect($reversal->requested_by_user_id)->toBe($requester->id);
    });
});

describe('idempotência', function () {
    it('não duplica depósito com a mesma chave', function () {
        $wallet = Wallet::factory()->withBalance(0)->create();
        $key = (string) Str::uuid();

        $first = $this->service->deposit($wallet, 1000, requestedBy: $wallet->user, idempotencyKey: $key);
        $second = $this->service->deposit($wallet, 1000, requestedBy: $wallet->user, idempotencyKey: $key);

        expect($second->id)->toBe($first->id)
            ->and($wallet->fresh()->balance)->toBe(1000)
            ->and(Transaction::count())->toBe(1);
    });

    it('não duplica transferência com a mesma chave', function () {
        $from = Wallet::factory()->withBalance(5000)->create();
        $to = Wallet::factory()->withBalance(0)->create();
        $key = (string) Str::uuid();

        $first = $this->service->transfer($from, $to, 2000, requestedBy: $from->user, idempotencyKey: $key);
        $second = $this->service->transfer($from, $to, 2000, requestedBy: $from->user, idempotencyKey: $key);

        expect($second->id)->toBe($first->id)
            ->and($from->fresh()->balance)->toBe(3000)
            ->and($to->fresh()->balance)->toBe(2000)
            ->and(Transaction::count())->toBe(2);
    });

    it('chaves diferentes geram operações distintas', function () {
        $wallet = Wallet::factory()->withBalance(0)->create();

        $this->service->deposit($wallet, 1000, requestedBy: $wallet->user, idempotencyKey: (string) Str::uuid());
        $this->service->deposit($wallet, 1000, requestedBy: $wallet->user, idempotencyKey: (string) Str::uuid());

        expect($wallet->fresh()->balance)->toBe(2000)
            ->and(Transaction::count())->toBe(2);
    });
});

describe('observabilidade', function () {
    it('emite log estruturado ao depositar', function () {
        Log::spy();
        $wallet = Wallet::factory()->withBalance(0)->create();

        $transaction = $this->service->deposit($wallet, 1500, requestedBy: $wallet->user);

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool => $message === 'wallet.deposit'
                && $context['amount_cents'] === 1500
                && $context['reference'] === $transaction->reference
                && $context['requested_by_user_id'] === $wallet->user->id)
            ->once();
    });
});
