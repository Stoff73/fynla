<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Insights;

use Illuminate\Foundation\Http\FormRequest;

class StoreInsightTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'article_id' => ['required', 'exists:insight_articles,id'],
            'name' => ['required', 'string', 'max:255', 'unique:insight_templates,name'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
