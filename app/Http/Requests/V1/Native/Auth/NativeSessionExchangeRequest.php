<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Native\Auth;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class NativeSessionExchangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $deviceLabel = $this->input('device_label');

        if (is_string($deviceLabel)) {
            $this->merge(['device_label' => trim($deviceLabel)]);
        }
    }

    public function rules(): array
    {
        return [
            'device_label' => [
                'required',
                'string',
                'max:80',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $originalValue = $this->originalDeviceLabel($value);

                    if (is_string($originalValue) && preg_match('/\p{Cc}/u', $originalValue) === 1) {
                        $fail('The device label must not contain control characters.');
                    }
                },
            ],
        ];
    }

    private function originalDeviceLabel(mixed $fallback): mixed
    {
        return $this->attributes->get('native_original_device_label', $fallback);
    }
}
