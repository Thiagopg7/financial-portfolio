export type TransactionType = 'deposit' | 'transfer' | 'reversal';

export type TransactionDirection = 'credit' | 'debit';

export type WalletSummary = {
    balance: number;
};

export type WalletTransaction = {
    id: number;
    type: TransactionType;
    direction: TransactionDirection;
    amount: number;
    balance_after: number;
    description: string | null;
    counterparty: string | null;
    created_at: string;
    is_reversed: boolean;
    can_reverse: boolean;
};
