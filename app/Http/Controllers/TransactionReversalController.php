<?php

namespace App\Http\Controllers;

use App\Exceptions\TransactionAlreadyReversedException;
use App\Models\Transaction;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class TransactionReversalController extends Controller
{
    public function __invoke(Request $request, Transaction $transaction, WalletService $walletService): RedirectResponse
    {
        Gate::authorize('reverse', $transaction);

        try {
            $walletService->reverse($transaction, $request->user());
        } catch (TransactionAlreadyReversedException|InvalidArgumentException $e) {
            throw ValidationException::withMessages(['transaction' => $e->getMessage()]);
        }

        return back()->with('success', 'Operação revertida com sucesso.');
    }
}
