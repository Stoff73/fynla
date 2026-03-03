<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRetirementActionDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permissionService = app(\App\Services\Auth\PermissionService::class);

        return $this->user() && $permissionService->hasPermission($this->user(), \App\Models\Permission::ADMIN_ACCESS);
    }

    public function rules(): array
    {
        $uniqueKeyRule = $this->route('id')
            ? Rule::unique('retirement_action_definitions', 'key')->ignore($this->route('id'))
            : Rule::unique('retirement_action_definitions', 'key');

        return [
            'key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', $uniqueKeyRule],
            'source' => ['required', 'string', Rule::in(['agent', 'goal'])],
            'title_template' => ['required', 'string', 'max:255'],
            'description_template' => ['required', 'string'],
            'action_template' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:50'],
            'priority' => ['required', Rule::in(['critical', 'high', 'medium', 'low'])],
            'scope' => ['required', Rule::in(['account', 'portfolio'])],
            'what_if_impact_type' => ['required', Rule::in(['contribution', 'consolidation', 'tax_optimisation', 'default'])],
            'trigger_config' => ['required', 'array'],
            'trigger_config.condition' => ['required', 'string'],
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
