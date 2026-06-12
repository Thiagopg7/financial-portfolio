<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepositRequest;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;

class DepositController extends Controller
{
    public function __invoke(DepositRequest $request, WalletService $walletService): RedirectResponse
    {
        $walletService->deposit(
            wallet: $request->user()->ensureWallet(),
            amount: $request->amountInCents(),
            description: $request->validated('description'),
            requestedBy: $request->user(),
            idempotencyKey: $request->validated('idempotency_key'),
        );

        return back()->with('success', 'Depósito realizado com sucesso.');
    }
}
