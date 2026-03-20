<?php

declare(strict_types=1);

namespace App\Http\Requests\Estate;

use Illuminate\Foundation\Http\FormRequest;

class UploadLpaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lpa_type' => 'sometimes|in:property_financial,health_welfare',
            'file' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'The uploaded file must be no larger than 10 MB.',
            'file.mimes' => 'The file must be a PDF or image (JPG, PNG).',
        ];
    }
}
