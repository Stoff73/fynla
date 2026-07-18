<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Native\StoreKit;

use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class StoreAppleTransactionRequest extends FormRequest
{
    private const MAX_SIGNED_TRANSACTION_BYTES = 65_536;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'signed_transaction' => [
                'bail',
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (
                        is_string($value)
                        && strlen($value) > self::MAX_SIGNED_TRANSACTION_BYTES
                    ) {
                        $fail('The signed transaction must not exceed 64 KB.');
                    }
                },
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_keys($this->all()) !== ['signed_transaction']) {
                $validator->errors()->add(
                    'request',
                    'Only a signed transaction may be submitted.',
                );
            }
        });
    }
}
