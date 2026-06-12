<?php

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

describe('deposit', function () {
    it('deposita na carteira do usuário', function () {
        $wallet = Wallet::factory()->withBalance(1000)->create();

        $this->actingAs($wallet->user)
            ->post(route('wallet.deposits.store'), ['amount' => '25.00'])
            ->assertSessionHas('success');

        expect($wallet->fresh()->balance)->toBe(3500);
    });

    it('valida valor positivo', function () {
        $wallet = Wallet::factory()->create();

        $this->actingAs($wallet->user)
            ->post(route('wallet.deposits.store'), ['amount' => '0'])
            ->assertSessionHasErrors('amount');
    });

    it('exige autenticação', function () {
        $this->post(route('wallet.deposits.store'), ['amount' => '10.00'])
            ->assertRedirect(route('login'));
    });
});

describe('transfer', function () {
    it('transfere por e-mail entre carteiras', function () {
        $sender = Wallet::factory()->withBalance(5000)->create();
        $recipient = Wallet::factory()->withBalance(0)->create();

        $this->actingAs($sender->user)
            ->post(route('wallet.transfers.store'), [
                'amount' => '20.00',
                'recipient_email' => $recipient->user->email,
            ])
            ->assertSessionHas('success');

        expect($sender->fresh()->balance)->toBe(3000)
            ->and($recipient->fresh()->balance)->toBe(2000);
    });

    it('rejeita transferência sem saldo', function () {
        $sender = Wallet::factory()->withBalance(1000)->create();
        $recipient = Wallet::factory()->create();

        $this->actingAs($sender->user)
            ->post(route('wallet.transfers.store'), [
                'amount' => '20.00',
                'recipient_email' => $recipient->user->email,
            ])
            ->assertSessionHasErrors('amount');

        expect($sender->fresh()->balance)->toBe(1000);
    });

    it('rejeita destinatário inexistente', function () {
        $sender = Wallet::factory()->withBalance(5000)->create();

        $this->actingAs($sender->user)
            ->post(route('wallet.transfers.store'), [
                'amount' => '20.00',
                'recipient_email' => 'naoexiste@example.com',
            ])
            ->assertSessionHasErrors('recipient_email');
    });

    it('impede transferir para si mesmo', function () {
        $sender = Wallet::factory()->withBalance(5000)->create();

        $this->actingAs($sender->user)
            ->post(route('wallet.transfers.store'), [
                'amount' => '20.00',
                'recipient_email' => $sender->user->email,
            ])
            ->assertSessionHasErrors('recipient_email');
    });

    it('impede transferir para si mesmo ignorando a capitalização do e-mail', function () {
        $sender = Wallet::factory()->withBalance(5000)->create();

        $this->actingAs($sender->user)
            ->post(route('wallet.transfers.store'), [
                'amount' => '20.00',
                'recipient_email' => strtoupper($sender->user->email),
            ])
            ->assertSessionHasErrors('recipient_email');
    });
});

describe('reversal', function () {
    it('participante reverte e registra o solicitante', function () {
        $wallet = Wallet::factory()->withBalance(0)->create();
        $deposit = app(WalletService::class)->deposit($wallet, 3000);

        $this->actingAs($wallet->user)
            ->post(route('transactions.reversals.store', $deposit))
            ->assertSessionHas('success');

        expect($wallet->fresh()->balance)->toBe(0);

        $reversal = Transaction::where('type', TransactionType::Reversal->value)->first();
        expect($reversal->requested_by_user_id)->toBe($wallet->user->id);
    });

    it('impede não-participante de reverter', function () {
        $wallet = Wallet::factory()->withBalance(0)->create();
        $deposit = app(WalletService::class)->deposit($wallet, 3000);
        $stranger = Wallet::factory()->create();

        $this->actingAs($stranger->user)
            ->post(route('transactions.reversals.store', $deposit))
            ->assertForbidden();
    });

    it('rejeita reverter operação já revertida', function () {
        $wallet = Wallet::factory()->withBalance(0)->create();
        $service = app(WalletService::class);
        $deposit = $service->deposit($wallet, 3000);
        $service->reverse($deposit);

        $this->actingAs($wallet->user)
            ->post(route('transactions.reversals.store', $deposit))
            ->assertSessionHasErrors('transaction');
    });
});

describe('show', function () {
    it('exibe a carteira e o extrato', function () {
        $wallet = Wallet::factory()->withBalance(5000)->create();

        $this->actingAs($wallet->user)
            ->get(route('wallet.show'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('wallet/index')
                ->where('wallet.balance', 5000)
                ->has('transactions')
            );
    });

    it('cria a carteira ao acessar caso o usuário ainda não tenha', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('wallet.show'))->assertOk();

        expect($user->wallet()->exists())->toBeTrue();
    });
});

describe('idempotência', function () {
    it('não duplica o depósito ao reenviar a mesma chave', function () {
        $wallet = Wallet::factory()->withBalance(0)->create();
        $key = (string) Str::uuid();

        foreach (range(1, 2) as $ignored) {
            $this->actingAs($wallet->user)
                ->post(route('wallet.deposits.store'), [
                    'amount' => '10.00',
                    'idempotency_key' => $key,
                ])
                ->assertRedirect();
        }

        expect($wallet->fresh()->balance)->toBe(1000)
            ->and(Transaction::count())->toBe(1);
    });
});

describe('rate limiting', function () {
    it('bloqueia depósitos acima do limite por minuto', function () {
        $wallet = Wallet::factory()->withBalance(0)->create();

        foreach (range(1, 20) as $ignored) {
            $this->actingAs($wallet->user)
                ->post(route('wallet.deposits.store'), ['amount' => '1.00'])
                ->assertRedirect();
        }

        $this->actingAs($wallet->user)
            ->post(route('wallet.deposits.store'), ['amount' => '1.00'])
            ->assertStatus(429);
    });
});
