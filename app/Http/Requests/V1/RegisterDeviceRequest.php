<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_token' => ['sometimes', 'string', 'max:500'],
            'device_id' => ['sometimes', 'string', 'max:255'],
            'platform' => ['sometimes', Rule::in(['ios', 'android'])],
            'device_name' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'os_version' => ['nullable', 'string', 'max:50'],
        ];
    }
}
