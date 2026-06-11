<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function reverse(User $user, Transaction $transaction): bool
    {
        return $transaction->wallet->user_id === $user->id;
    }
}
