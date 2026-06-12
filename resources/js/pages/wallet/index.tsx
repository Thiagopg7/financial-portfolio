import { Head } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import DepositForm from '@/components/wallet/deposit-form';
import TransactionItem from '@/components/wallet/transaction-item';
import TransferForm from '@/components/wallet/transfer-form';
import { formatCurrency } from '@/lib/utils';
import { show as walletShow } from '@/routes/wallet';
import type { BreadcrumbItem, WalletSummary, WalletTransaction } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Carteira',
        href: walletShow(),
    },
];

type WalletPageProps = {
    wallet: WalletSummary;
    transactions: WalletTransaction[];
};

export default function WalletIndex({ wallet, transactions }: WalletPageProps) {
    return (
        <>
            <Head title="Carteira" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardDescription>Saldo disponível</CardDescription>
                        <CardTitle className="text-3xl">
                            {formatCurrency(wallet.balance)}
                        </CardTitle>
                    </CardHeader>
                </Card>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Depositar</CardTitle>
                            <CardDescription>
                                Adicione saldo à sua carteira.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <DepositForm />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Transferir</CardTitle>
                            <CardDescription>
                                Envie saldo para outro usuário pelo e-mail.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <TransferForm />
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Extrato</CardTitle>
                        <CardDescription>
                            Últimas movimentações.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {transactions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nenhuma movimentação ainda.
                            </p>
                        ) : (
                            <div className="divide-y">
                                {transactions.map((transaction) => (
                                    <TransactionItem
                                        key={transaction.id}
                                        transaction={transaction}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

WalletIndex.layout = {
    breadcrumbs,
};
