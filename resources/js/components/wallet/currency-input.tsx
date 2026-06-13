import { useState } from 'react';
import { Input } from '@/components/ui/input';

const displayFormatter = new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function centsToDisplay(cents: number): string {
    return displayFormatter.format(cents / 100);
}

function centsToDecimal(cents: number): string {
    return (cents / 100).toFixed(2);
}

type CurrencyInputProps = {
    id: string;
    name: string;
    required?: boolean;
};

export default function CurrencyInput({
    id,
    name,
    required,
}: CurrencyInputProps) {
    const [cents, setCents] = useState(0);

    function handleChange(event: React.ChangeEvent<HTMLInputElement>) {
        const digits = event.target.value.replace(/\D/g, '');
        setCents(digits === '' ? 0 : Number(digits));
    }

    return (
        <>
            <Input
                id={id}
                type="text"
                inputMode="numeric"
                placeholder="0,00"
                value={cents === 0 ? '' : centsToDisplay(cents)}
                onChange={handleChange}
                required={required}
            />
            <input
                type="hidden"
                name={name}
                value={cents === 0 ? '' : centsToDecimal(cents)}
            />
        </>
    );
}
