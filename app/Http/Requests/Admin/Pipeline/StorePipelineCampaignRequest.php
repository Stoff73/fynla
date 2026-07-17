<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Pipeline;

use App\Models\Pipeline\PipelineCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StorePipelineCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique(PipelineCampaign::class, 'slug'),
            ],
            'landing_url' => ['required', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:2000'],
            'active_from' => ['nullable', 'date'],
            'active_to' => ['nullable', 'date', 'after_or_equal:active_from'],
            'cta_heading' => ['nullable', 'string', 'max:120'],
            'cta_subheading' => ['nullable', 'string', 'max:500'],
            'cta_button_text' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug must be lowercase letters, digits, and hyphens only.',
        ];
    }
}
