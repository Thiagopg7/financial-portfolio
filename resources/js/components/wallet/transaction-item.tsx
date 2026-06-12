import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import TransactionReversalController from '@/actions/App/Http/Controllers/TransactionReversalController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatCurrency } from '@/lib/utils';
import type { WalletTransaction } from '@/types';

const typeLabels: Record<WalletTransaction['type'], string> = {
    deposit: 'Depósito',
    transfer: 'Transferência',
    reversal: 'Estorno',
};

export default function TransactionItem({
    transaction,
}: {
    transaction: WalletTransaction;
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const isCredit = transaction.direction === 'credit';

    const reverse = () => {
        router.post(
            TransactionReversalController(transaction.id).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onSuccess: () => {
                    toast.success('Operação revertida com sucesso.');
                    setOpen(false);
                },
                onError: (errors) =>
                    toast.error(
                        errors.transaction ?? 'Não foi possível reverter.',
                    ),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div className="flex items-center justify-between gap-4 py-3">
            <div className="flex flex-col gap-1">
                <div className="flex items-center gap-2">
                    <Badge variant="secondary">
                        {typeLabels[transaction.type]}
                    </Badge>
                    {transaction.is_reversed && (
                        <Badge variant="outline">Revertido</Badge>
                    )}
                </div>

                <span className="text-sm text-muted-foreground">
                    {transaction.counterparty
                        ? `${isCredit ? 'De' : 'Para'} ${transaction.counterparty}`
                        : (transaction.description ?? '—')}
                </span>

                <span className="text-xs text-muted-foreground">
                    {new Date(transaction.created_at).toLocaleString('pt-BR', {
                        dateStyle: 'short',
                        timeStyle: 'short',
                    })}
                </span>
            </div>

            <div className="flex items-center gap-3">
                <span
                    className={
                        isCredit
                            ? 'font-semibold text-emerald-600 dark:text-emerald-400'
                            : 'font-semibold text-red-600 dark:text-red-400'
                    }
                >
                    {isCredit ? '+' : '−'} {formatCurrency(transaction.amount)}
                </span>

                {transaction.can_reverse && (
                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild>
                            <Button variant="outline" size="sm">
                                Reverter
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Reverter operação</DialogTitle>
                                <DialogDescription>
                                    Esta ação cria um estorno e ajusta os saldos
                                    envolvidos. Não pode ser desfeita.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                    onClick={reverse}
                                >
                                    Confirmar reversão
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                )}
            </div>
        </div>
    );
}
