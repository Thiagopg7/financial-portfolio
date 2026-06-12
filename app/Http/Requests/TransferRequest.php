<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000000'],
            'recipient_email' => [
                'required',
                'email',
                'exists:users,email',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (strcasecmp((string) $value, $this->user()->email) === 0) {
                        $fail('Você não pode transferir para si mesmo.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Informe um valor.',
            'amount.numeric' => 'O valor deve ser um número.',
            'amount.decimal' => 'O valor pode ter no máximo duas casas decimais.',
            'amount.min' => 'O valor deve ser maior que zero.',
            'recipient_email.required' => 'Informe o e-mail do destinatário.',
            'recipient_email.email' => 'Informe um e-mail válido.',
            'recipient_email.exists' => 'Nenhum usuário encontrado com esse e-mail.',
            'description.max' => 'O campo de descrição não deve ter mais de 255 caracteres.',
        ];
    }

    public function amountInCents(): int
    {
        return (int) round(((float) $this->validated('amount')) * 100);
    }
}
