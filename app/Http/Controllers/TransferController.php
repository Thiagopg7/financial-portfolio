<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Requests\TransferRequest;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class TransferController extends Controller
{
    public function __invoke(TransferRequest $request, WalletService $walletService): RedirectResponse
    {
        $recipient = User::where('email', $request->validated('recipient_email'))->firstOrFail();

        try {
            $walletService->transfer(
                from: $request->user()->ensureWallet(),
                to: $recipient->ensureWallet(),
                amount: $request->amountInCents(),
                description: $request->validated('description'),
                requestedBy: $request->user(),
            );
        } catch (InsufficientBalanceException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return back()->with('success', 'Transferência realizada com sucesso.');
    }
}
