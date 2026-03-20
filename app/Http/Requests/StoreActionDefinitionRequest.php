<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActionDefinitionRequest extends FormRequest
{
    private const MODULE_TABLES = [
        'protection' => 'protection_action_definitions',
        'savings' => 'savings_action_definitions',
        'investment' => 'investment_action_definitions',
        'retirement' => 'retirement_action_definitions',
        'estate' => 'estate_action_definitions',
        'tax' => 'tax_action_definitions',
    ];

    private const MODULE_SOURCES = [
        'protection' => ['agent', 'gap'],
        'savings' => ['agent', 'goal'],
        'investment' => ['agent', 'goal'],
        'retirement' => ['agent', 'goal'],
        'estate' => ['agent', 'goal'],
        'tax' => ['agent', 'goal'],
    ];

    private const MODULE_IMPACT_TYPES = [
        'protection' => ['coverage_increase', 'gap_reduction', 'default'],
        'savings' => ['savings_increase', 'rate_improvement', 'default'],
        'investment' => ['fee_reduction', 'savings_increase', 'contribution', 'tax_optimisation', 'default'],
        'retirement' => ['contribution', 'consolidation', 'tax_optimisation', 'default'],
        'estate' => ['iht_reduction', 'estate_protection', 'default'],
        'tax' => ['tax_optimisation', 'allowance_utilisation', 'default'],
    ];

    public function authorize(): bool
    {
        $permissionService = app(\App\Services\Auth\PermissionService::class);

        return $this->user() && $permissionService->hasPermission($this->user(), Permission::ADMIN_ACCESS);
    }

    public function rules(): array
    {
        $module = $this->route('module');
        $table = self::MODULE_TABLES[$module] ?? 'protection_action_definitions';
        $sources = self::MODULE_SOURCES[$module] ?? ['agent'];
        $impacts = self::MODULE_IMPACT_TYPES[$module] ?? ['default'];

        $uniqueKeyRule = $this->route('id')
            ? Rule::unique($table, 'key')->ignore($this->route('id'))
            : Rule::unique($table, 'key');

        return [
            'key' => ['sometimes', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', $uniqueKeyRule],
            'source' => ['sometimes', 'string', Rule::in($sources)],
            'title_template' => ['sometimes', 'string', 'max:255'],
            'description_template' => ['sometimes', 'string', 'max:2000'],
            'action_template' => ['nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:50'],
            'priority' => ['sometimes', Rule::in(['critical', 'high', 'medium', 'low'])],
            'scope' => ['sometimes', Rule::in(['account', 'portfolio'])],
            'what_if_impact_type' => ['sometimes', Rule::in($impacts)],
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
