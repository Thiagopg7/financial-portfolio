import { Form } from '@inertiajs/react';
import { useRef } from 'react';
import { toast } from 'sonner';
import DepositController from '@/actions/App/Http/Controllers/DepositController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function DepositForm() {
    const idempotencyKey = useRef(crypto.randomUUID());

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
            }}
            className="space-y-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="deposit-amount">Valor (R$)</Label>
                        <Input
                            id="deposit-amount"
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
