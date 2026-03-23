<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProtectionActionDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permissionService = app(\App\Services\Auth\PermissionService::class);

        return $this->user() && $permissionService->hasPermission($this->user(), \App\Models\Permission::ADMIN_ACCESS);
    }

    public function rules(): array
    {
        $uniqueKeyRule = $this->route('id')
            ? Rule::unique('protection_action_definitions', 'key')->ignore($this->route('id'))
            : Rule::unique('protection_action_definitions', 'key');

        return [
            'key' => ['sometimes', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', $uniqueKeyRule],
            'source' => ['sometimes', 'string', Rule::in(['agent', 'gap'])],
            'title_template' => ['sometimes', 'string', 'max:255'],
            'description_template' => ['sometimes', 'string', 'max:2000'],
            'action_template' => ['nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:50'],
            'priority' => ['sometimes', Rule::in(['critical', 'high', 'medium', 'low'])],
            'scope' => ['sometimes', Rule::in(['account', 'portfolio'])],
            'what_if_impact_type' => ['sometimes', Rule::in(['coverage_increase', 'gap_reduction', 'default'])],
            'trigger_config' => ['sometimes', 'array'],
            'trigger_config.condition' => ['sometimes', 'string'],
            'is_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'Key must contain only lowercase letters, numbers, and underscores.',
            'key.unique' => 'This action key is already in use.',
            'trigger_config.condition.required' => 'Trigger configuration must include a condition.',
        ];
    }
}
