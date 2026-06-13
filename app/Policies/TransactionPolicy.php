<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TransactionPolicy
{
    /**
     * Qualquer participante da operação pode revertê-la: basta ser dono de algum
     * lançamento da mesma reference (remetente ou destinatário, numa transferência).
     */
    public function reverse(User $user, Transaction $transaction): bool
    {
        return Transaction::where('reference', $transaction->reference)
            ->whereHas('wallet', fn (Builder $query) => $query->where('user_id', $user->id))
            ->exists();
    }
}
