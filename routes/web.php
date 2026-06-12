<?php

use App\Http\Controllers\DepositController;
use App\Http\Controllers\TransactionReversalController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('wallet', [WalletController::class, 'show'])->name('wallet.show');

    Route::middleware('throttle:20,1')->group(function () {
        Route::post('wallet/deposits', DepositController::class)->name('wallet.deposits.store');
        Route::post('wallet/transfers', TransferController::class)->name('wallet.transfers.store');
        Route::post('transactions/{transaction}/reversals', TransactionReversalController::class)
            ->name('transactions.reversals.store');
    });
});

require __DIR__.'/settings.php';
