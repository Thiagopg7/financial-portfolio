<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function show(Request $request): Response
    {
        $wallet = $request->user()->ensureWallet();

        $transactions = $wallet->transactions()
            ->with('counterpartyWallet.user')
            ->latest()
            ->latest('id')
            ->limit(50)
            ->get();

        return Inertia::render('wallet/index', [
            'wallet' => [
                'balance' => $wallet->balance,
            ],
            'transactions' => TransactionResource::collection($transactions)->resolve(),
        ]);
    }
}
