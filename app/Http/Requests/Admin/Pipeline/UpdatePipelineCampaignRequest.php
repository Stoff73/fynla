<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Pipeline;

use App\Models\Pipeline\PipelineCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePipelineCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $campaignId = $this->route('campaign')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'slug' => [
                'sometimes',
                'string',
                'max:80',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique(PipelineCampaign::class, 'slug')->ignore($campaignId),
            ],
            'landing_url' => ['sometimes', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:2000'],
            'active_from' => ['nullable', 'date'],
            'active_to' => ['nullable', 'date', 'after_or_equal:active_from'],
        ];
    }
}
