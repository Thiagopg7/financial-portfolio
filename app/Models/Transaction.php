<?php

namespace App\Models;

use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $wallet_id
 * @property TransactionType $type
 * @property TransactionDirection $direction
 * @property int $amount
 * @property int $balance_after
 * @property string $reference
 * @property int|null $counterparty_wallet_id
 * @property int|null $reverses_transaction_id
 * @property Carbon|null $reversed_at
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'type',
        'direction',
        'amount',
        'balance_after',
        'reference',
        'counterparty_wallet_id',
        'reverses_transaction_id',
        'reversed_at',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'direction' => TransactionDirection::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
            'reversed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function counterpartyWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'counterparty_wallet_id');
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function reversesTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'reverses_transaction_id');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}
