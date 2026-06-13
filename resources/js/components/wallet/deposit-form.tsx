import { Form } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import DepositController from '@/actions/App/Http/Controllers/DepositController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CurrencyInput from '@/components/wallet/currency-input';

export default function DepositForm() {
    const idempotencyKey = useRef(crypto.randomUUID());
    const [formKey, setFormKey] = useState(0);

    return (
        <Form
            {...DepositController.form()}
            transform={(data) => ({
                ...data,
                idempotency_key: idempotencyKey.current,
            })}
            resetOnSuccess
            options={{ preserveScroll: true }}
            onSuccess={() => {
                toast.success('Depósito realizado com sucesso.');
                idempotencyKey.current = crypto.randomUUID();
                setFormKey((key) => key + 1);
            }}
            className="space-y-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="deposit-amount">Valor (R$)</Label>
                        <CurrencyInput
                            key={formKey}
                            id="deposit-amount"
                            name="amount"
                            required
                        />
                        <InputError message={errors.amount} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="deposit-description">
                            Descrição (opcional)
                        </Label>
                        <Input
                            id="deposit-description"
                            name="description"
                            type="text"
                            placeholder="Ex.: salário"
                        />
                        <InputError message={errors.description} />
                    </div>

                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-full"
                    >
                        Depositar
                    </Button>
                </>
            )}
        </Form>
    );
}
