import { Form } from '@inertiajs/react';
import { toast } from 'sonner';
import DepositController from '@/actions/App/Http/Controllers/DepositController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function DepositForm() {
    return (
        <Form
            {...DepositController.form()}
            resetOnSuccess
            options={{ preserveScroll: true }}
            onSuccess={() => toast.success('Depósito realizado com sucesso.')}
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
