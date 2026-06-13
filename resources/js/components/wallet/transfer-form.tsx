import { Form } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import TransferController from '@/actions/App/Http/Controllers/TransferController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CurrencyInput from '@/components/wallet/currency-input';

export default function TransferForm() {
    const idempotencyKey = useRef(crypto.randomUUID());
    const [formKey, setFormKey] = useState(0);

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
                setFormKey((key) => key + 1);
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
                        <CurrencyInput
                            key={formKey}
                            id="transfer-amount"
                            name="amount"
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
