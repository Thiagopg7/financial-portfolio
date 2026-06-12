<?php

namespace App\Http\Resources;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'direction' => $this->direction->value,
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'description' => $this->description,
            'counterparty' => $this->counterpartyWallet?->user?->name,
            'created_at' => $this->created_at,
            'is_reversed' => $this->isReversed(),
            'can_reverse' => ! $this->isReversed() && $this->type !== TransactionType::Reversal,
        ];
    }
}
