<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Insights;

use Illuminate\Foundation\Http\FormRequest;

class UploadInsightImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:10240'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:255'],
        ];
    }
}
