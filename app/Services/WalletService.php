<?php

namespace App\Services;

use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\TransactionAlreadyReversedException;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WalletService
{
    public function deposit(Wallet $wallet, int $amount, ?string $description = null, ?User $requestedBy = null, ?string $idempotencyKey = null): Transaction
    {
        if ($amount <= 0) {
            Log::warning('wallet.deposit.failed', [
                'reason' => 'invalid_amount',
                'wallet_id' => $wallet->id,
                'amount_cents' => $amount,
                'requested_by_user_id' => $requestedBy?->id,
            ]);

            throw new InvalidArgumentException('O valor deve ser maior que zero.');
        }

        if ($idempotencyKey !== null && $replay = $this->findByIdempotencyKey($idempotencyKey, $requestedBy?->id)) {
            return $replay;
        }

        try {
            $transaction = DB::transaction(function () use ($wallet, $amount, $description, $requestedBy, $idempotencyKey): Transaction {
                $locked = $this->lockWallet($wallet->id);
                $balanceAfter = $locked->balance + $amount;
                $locked->update(['balance' => $balanceAfter]);

                return $this->recordEntry(
                    wallet: $locked,
                    type: TransactionType::Deposit,
                    direction: TransactionDirection::Credit,
                    amount: $amount,
                    balanceAfter: $balanceAfter,
                    reference: (string) Str::ulid(),
                    description: $description,
                    requestedByUserId: $requestedBy?->id,
                    idempotencyKey: $idempotencyKey,
                );
            });
        } catch (QueryException $e) {
            return $this->resolveIdempotentReplay($e, $idempotencyKey, $requestedBy?->id);
        }

        Log::info('wallet.deposit', [
            'wallet_id' => $transaction->wallet_id,
            'amount_cents' => $amount,
            'balance_after' => $transaction->balance_after,
            'reference' => $transaction->reference,
            'requested_by_user_id' => $requestedBy?->id,
        ]);

        return $transaction;
    }

    /**
     * @throws InsufficientBalanceException
     */
    public function transfer(Wallet $from, Wallet $to, int $amount, ?string $description = null, ?User $requestedBy = null, ?string $idempotencyKey = null): Transaction
    {
        if ($amount <= 0) {
            Log::warning('wallet.transfer.failed', [
                'reason' => 'invalid_amount',
                'from_wallet_id' => $from->id,
                'to_wallet_id' => $to->id,
                'amount_cents' => $amount,
                'requested_by_user_id' => $requestedBy?->id,
            ]);

            throw new InvalidArgumentException('O valor deve ser maior que zero.');
        }

        if ($from->is($to)) {
            Log::warning('wallet.transfer.failed', [
                'reason' => 'same_wallet',
                'from_wallet_id' => $from->id,
                'to_wallet_id' => $to->id,
                'amount_cents' => $amount,
                'requested_by_user_id' => $requestedBy?->id,
            ]);

            throw new InvalidArgumentException('Não é possível transferir para a própria carteira.');
        }

        if ($idempotencyKey !== null && $replay = $this->findByIdempotencyKey($idempotencyKey, $requestedBy?->id)) {
            return $replay;
        }

        try {
            $debit = DB::transaction(function () use ($from, $to, $amount, $description, $requestedBy, $idempotencyKey): Transaction {
                $wallets = $this->lockWallets([$from->id, $to->id]);
                $source = $wallets[$from->id];
                $target = $wallets[$to->id];

                if ($source->balance < $amount) {
                    throw new InsufficientBalanceException;
                }

                $reference = (string) Str::ulid();

                $sourceBalance = $source->balance - $amount;
                $source->update(['balance' => $sourceBalance]);
                $debit = $this->recordEntry(
                    wallet: $source,
                    type: TransactionType::Transfer,
                    direction: TransactionDirection::Debit,
                    amount: $amount,
                    balanceAfter: $sourceBalance,
                    reference: $reference,
                    counterpartyWalletId: $target->id,
                    description: $description,
                    requestedByUserId: $requestedBy?->id,
                    idempotencyKey: $idempotencyKey,
                );

                $targetBalance = $target->balance + $amount;
                $target->update(['balance' => $targetBalance]);
                $this->recordEntry(
                    wallet: $target,
                    type: TransactionType::Transfer,
                    direction: TransactionDirection::Credit,
                    amount: $amount,
                    balanceAfter: $targetBalance,
                    reference: $reference,
                    counterpartyWalletId: $source->id,
                    description: $description,
                    requestedByUserId: $requestedBy?->id,
                );

                return $debit;
            });
        } catch (InsufficientBalanceException $e) {
            Log::warning('wallet.transfer.failed', [
                'reason' => 'insufficient_balance',
                'from_wallet_id' => $from->id,
                'to_wallet_id' => $to->id,
                'amount_cents' => $amount,
                'requested_by_user_id' => $requestedBy?->id,
            ]);

            throw $e;
        } catch (QueryException $e) {
            return $this->resolveIdempotentReplay($e, $idempotencyKey, $requestedBy?->id);
        }

        Log::info('wallet.transfer', [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount_cents' => $amount,
            'reference' => $debit->reference,
            'requested_by_user_id' => $requestedBy?->id,
        ]);

        return $debit;
    }

    /**
     * @return Collection<int, Transaction>
     *
     * @throws TransactionAlreadyReversedException
     */
    public function reverse(Transaction $transaction, ?User $requestedBy = null, ?string $description = null): Collection
    {
        try {
            $reversals = DB::transaction(function () use ($transaction, $requestedBy, $description): Collection {
                $entries = Transaction::where('reference', $transaction->reference)
                    ->lockForUpdate()
                    ->get();

                foreach ($entries as $entry) {
                    if ($entry->type === TransactionType::Reversal) {
                        throw new InvalidArgumentException('Um estorno não pode ser revertido.');
                    }

                    if ($entry->isReversed()) {
                        throw new TransactionAlreadyReversedException;
                    }
                }

                $wallets = $this->lockWallets($entries->pluck('wallet_id')->all());
                $reference = (string) Str::ulid();

                // inverso de cada lançamento
                return $entries->map(function (Transaction $entry) use ($wallets, $reference, $requestedBy, $description): Transaction {
                    $wallet = $wallets[$entry->wallet_id];
                    $inverseDirection = $entry->direction === TransactionDirection::Credit
                        ? TransactionDirection::Debit
                        : TransactionDirection::Credit;

                    $balanceAfter = $inverseDirection === TransactionDirection::Credit
                        ? $wallet->balance + $entry->amount
                        : $wallet->balance - $entry->amount;

                    $wallet->update(['balance' => $balanceAfter]);

                    $reversal = $this->recordEntry(
                        wallet: $wallet,
                        type: TransactionType::Reversal,
                        direction: $inverseDirection,
                        amount: $entry->amount,
                        balanceAfter: $balanceAfter,
                        reference: $reference,
                        counterpartyWalletId: $entry->counterparty_wallet_id,
                        reversesTransactionId: $entry->id,
                        description: $description,
                        requestedByUserId: $requestedBy?->id,
                    );

                    $entry->update(['reversed_at' => now()]);

                    return $reversal;
                });
            });
        } catch (TransactionAlreadyReversedException $e) {
            Log::warning('wallet.reversal.failed', [
                'reason' => 'already_reversed',
                'reference' => $transaction->reference,
                'requested_by_user_id' => $requestedBy?->id,
            ]);

            throw $e;
        } catch (InvalidArgumentException $e) {
            Log::warning('wallet.reversal.failed', [
                'reason' => 'reversal_of_reversal',
                'reference' => $transaction->reference,
                'requested_by_user_id' => $requestedBy?->id,
            ]);

            throw $e;
        }

        Log::info('wallet.reversal', [
            'original_reference' => $transaction->reference,
            'reversal_reference' => $reversals->first()?->reference,
            'entries' => $reversals->count(),
            'requested_by_user_id' => $requestedBy?->id,
        ]);

        return $reversals;
    }

    private function lockWallet(int $walletId): Wallet
    {
        return Wallet::whereKey($walletId)->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  array<int, int>  $walletIds
     * @return Collection<int, Wallet> indexada por id
     */
    private function lockWallets(array $walletIds): Collection
    {
        $ids = collect($walletIds)->unique()->sort()->values()->all();

        return Wallet::whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    private function recordEntry(
        Wallet $wallet,
        TransactionType $type,
        TransactionDirection $direction,
        int $amount,
        int $balanceAfter,
        string $reference,
        ?int $counterpartyWalletId = null,
        ?int $reversesTransactionId = null,
        ?string $description = null,
        ?int $requestedByUserId = null,
        ?string $idempotencyKey = null,
    ): Transaction {
        return $wallet->transactions()->create([
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey,
            'counterparty_wallet_id' => $counterpartyWalletId,
            'reverses_transaction_id' => $reversesTransactionId,
            'description' => $description,
            'requested_by_user_id' => $requestedByUserId,
        ]);
    }

    private function findByIdempotencyKey(string $idempotencyKey, ?int $requestedByUserId): ?Transaction
    {
        return Transaction::where('idempotency_key', $idempotencyKey)
            ->where('requested_by_user_id', $requestedByUserId)
            ->first();
    }

    private function resolveIdempotentReplay(QueryException $e, ?string $idempotencyKey, ?int $requestedByUserId): Transaction
    {
        if ($idempotencyKey !== null && (string) $e->getCode() === '23000') {
            $replay = $this->findByIdempotencyKey($idempotencyKey, $requestedByUserId);

            if ($replay !== null) {
                return $replay;
            }
        }

        throw $e;
    }
}
