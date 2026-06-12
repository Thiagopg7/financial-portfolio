import { Form } from '@inertiajs/react';
import { useRef } from 'react';
import { toast } from 'sonner';
import TransferController from '@/actions/App/Http/Controllers/TransferController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function TransferForm() {
    const idempotencyKey = useRef(crypto.randomUUID());

    return (
        <Form
            {...TransferController.form()}
            transform={(data) => ({
                ...data,
                idempotency_key: idempotencyKey.current,
            })}
            resetOnSuccess
            options={{ preserveScroll: true }}
            onSuccess={() => {
                toast.success('Transferência realizada com sucesso.');
                idempotencyKey.current = crypto.randomUUID();
            }}
            className="space-y-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="recipient-email">
                            E-mail do destinatário
                        </Label>
                        <Input
                            id="recipient-email"
                            name="recipient_email"
                            type="email"
                            placeholder="destinatario@exemplo.com"
                            required
                        />
                        <InputError message={errors.recipient_email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="transfer-amount">Valor (R$)</Label>
                        <Input
                            id="transfer-amount"
                            name="amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            required
                        />
                        <InputError message={errors.amount} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="transfer-description">
                            Descrição (opcional)
                        </Label>
                        <Input
                            id="transfer-description"
                            name="description"
                            type="text"
                            placeholder="Ex.: aluguel"
                        />
                        <InputError message={errors.description} />
                    </div>

                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-full"
                    >
                        Transferir
                    </Button>
                </>
            )}
        </Form>
    );
}
