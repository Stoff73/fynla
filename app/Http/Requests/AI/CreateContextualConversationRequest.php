<?php

declare(strict_types=1);

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CreateContextualConversationRequest extends FormRequest
{
    /** @var list<string> */
    public const OVERVIEW_RESOURCE_TYPES = [
        'personal_information',
        'savings',
        'investment',
        'retirement',
        'protection',
        'goals',
        'income',
        'expenditure',
        'net_worth',
        'estate',
        'tax_strategy',
    ];

    /** @var list<string> */
    public const ENTITY_RESOURCE_TYPES = [
        'savings_account',
        'investment_account',
        'dc_pension',
        'db_pension',
        'state_pension',
        'goal',
        'property',
        'mortgage',
        'liability',
        'life_insurance_policy',
        'critical_illness_policy',
        'income_protection_policy',
        'disability_policy',
        'sickness_illness_policy',
    ];

    /** @var list<string> */
    private const TOP_LEVEL_KEYS = [
        'action',
        'resource_type',
        'resource_id',
        'current_destination',
        'origin',
    ];

    /** @var list<string> */
    private const DESTINATION_PARAMETER_KEYS = [
        'account_id',
        'pension_id',
        'pension_type',
        'policy_id',
        'policy_type',
        'goal_id',
        'property_id',
        'mortgage_id',
        'liability_id',
        'income_owner',
        'income_source',
    ];

    /** @var list<string> */
    private const DESTINATION_SCREENS = [
        'dashboard',
        'personal_information',
        'savings',
        'savings_account_detail',
        'investment',
        'investment_account_detail',
        'retirement',
        'pension_detail',
        'protection',
        'protection_policy_detail',
        'goals',
        'goal_detail',
        'property_detail',
        'mortgage_detail',
        'liability_detail',
        'income_detail',
        'income',
        'expenditure',
        'net_worth',
        'estate',
        'tax_strategy',
    ];

    /** @var array<string, string> */
    private const PENSION_TYPES = [
        'dc_pension' => 'dc',
        'db_pension' => 'db',
        'state_pension' => 'state',
    ];

    /** @var array<string, string> */
    private const POLICY_TYPES = [
        'life_insurance_policy' => 'life',
        'critical_illness_policy' => 'criticalIllness',
        'income_protection_policy' => 'incomeProtection',
        'disability_policy' => 'disability',
        'sickness_illness_policy' => 'sicknessIllness',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['add', 'edit'])],
            'resource_type' => [
                'required',
                'string',
                Rule::in([...self::OVERVIEW_RESOURCE_TYPES, ...self::ENTITY_RESOURCE_TYPES]),
            ],
            'resource_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf(fn (): bool => in_array(
                    $this->input('resource_type'),
                    self::ENTITY_RESOURCE_TYPES,
                    true,
                )),
            ],
            'current_destination' => ['required', 'array:screen,params,fallback'],
            'current_destination.screen' => ['required', 'string', Rule::in(self::DESTINATION_SCREENS)],
            'current_destination.params' => ['present', 'array'],
            'current_destination.fallback' => ['required', 'string', Rule::in(self::DESTINATION_SCREENS)],
            'origin' => ['required', 'array:kind,recommendation_id'],
            'origin.kind' => ['required', 'string', Rule::in(['surface_action', 'recommendation'])],
            'origin.recommendation_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnexpectedKeys($validator);
            $this->validateIdentifierTypes($validator);
            $this->validateDestinationParameters($validator);
        });
    }

    private function validateIdentifierTypes(Validator $validator): void
    {
        foreach (['resource_id', 'origin.recommendation_id'] as $key) {
            $value = $this->input($key);
            if ($value !== null && ! is_int($value)) {
                $validator->errors()->add($key, 'Identifiers must be JSON integers.');
            }
        }
    }

    private function rejectUnexpectedKeys(Validator $validator): void
    {
        foreach (array_diff(array_keys($this->all()), self::TOP_LEVEL_KEYS) as $key) {
            $validator->errors()->add((string) $key, 'This field is not allowed.');
        }

        $params = $this->input('current_destination.params', []);
        if (! is_array($params)) {
            return;
        }

        foreach (array_diff(array_keys($params), self::DESTINATION_PARAMETER_KEYS) as $key) {
            $validator->errors()->add(
                'current_destination.params.'.$key,
                'Only identifier parameters are allowed.',
            );
        }
    }

    private function validateDestinationParameters(Validator $validator): void
    {
        $params = $this->input('current_destination.params', []);
        if (! is_array($params)) {
            return;
        }

        foreach ($params as $key => $value) {
            if (! in_array($key, self::DESTINATION_PARAMETER_KEYS, true)) {
                continue;
            }

            if (str_ends_with($key, '_id')) {
                if (! is_int($value)) {
                    $validator->errors()->add(
                        'current_destination.params.'.$key,
                        'Destination identifiers must be positive JSON integers.',
                    );
                } elseif ($value < 1) {
                    $validator->errors()->add(
                        'current_destination.params.'.$key,
                        'Destination identifiers must be positive JSON integers.',
                    );
                }
            } elseif (! is_string($value) || $value === '' || strlen($value) > 64) {
                $validator->errors()->add(
                    'current_destination.params.'.$key,
                    'Destination enum parameters must be short strings.',
                );
            }
        }

        $resourceType = $this->input('resource_type');
        if (! is_string($resourceType)) {
            return;
        }

        if (in_array($resourceType, self::OVERVIEW_RESOURCE_TYPES, true)) {
            $this->validateOverviewDestination($validator, $resourceType, $params);

            return;
        }

        if (! in_array($resourceType, self::ENTITY_RESOURCE_TYPES, true)) {
            return;
        }

        $resourceId = $this->input('resource_id');

        $idKey = match ($resourceType) {
            'savings_account', 'investment_account' => 'account_id',
            'dc_pension', 'db_pension', 'state_pension' => 'pension_id',
            'goal' => 'goal_id',
            'property' => 'property_id',
            'mortgage' => 'mortgage_id',
            'liability' => 'liability_id',
            default => 'policy_id',
        };

        if (! is_int($resourceId) || ! isset($params[$idKey]) || $params[$idKey] !== $resourceId) {
            $validator->errors()->add(
                'current_destination.params.'.$idKey,
                'The destination identifier must match the contextual resource.',
            );
        }

        $expectedScreen = match ($resourceType) {
            'savings_account' => 'savings_account_detail',
            'investment_account' => 'investment_account_detail',
            'dc_pension', 'db_pension', 'state_pension' => 'pension_detail',
            'goal' => 'goal_detail',
            'property' => 'property_detail',
            'mortgage' => 'mortgage_detail',
            'liability' => 'liability_detail',
            default => 'protection_policy_detail',
        };
        $expectedFallback = match ($resourceType) {
            'savings_account' => 'savings',
            'investment_account' => 'investment',
            'dc_pension', 'db_pension', 'state_pension' => 'retirement',
            'goal' => 'goals',
            'property', 'mortgage', 'liability' => 'net_worth',
            default => 'protection',
        };

        if ($this->input('current_destination.screen') !== $expectedScreen) {
            $validator->errors()->add(
                'current_destination.screen',
                'The destination screen must match the contextual resource.',
            );
        }
        if ($this->input('current_destination.fallback') !== $expectedFallback) {
            $validator->errors()->add(
                'current_destination.fallback',
                'The fallback must be the canonical resource overview.',
            );
        }

        $expectedParams = [$idKey];
        if (isset(self::PENSION_TYPES[$resourceType])) {
            $expectedParams[] = 'pension_type';
            if (($params['pension_type'] ?? null) !== self::PENSION_TYPES[$resourceType]) {
                $validator->errors()->add(
                    'current_destination.params.pension_type',
                    'The pension type must match the contextual resource.',
                );
            }
        } elseif (isset(self::POLICY_TYPES[$resourceType])) {
            $expectedParams[] = 'policy_type';
            if (($params['policy_type'] ?? null) !== self::POLICY_TYPES[$resourceType]) {
                $validator->errors()->add(
                    'current_destination.params.policy_type',
                    'The policy type must match the contextual resource.',
                );
            }
        }

        foreach (array_diff(array_keys($params), $expectedParams) as $unexpected) {
            $validator->errors()->add(
                'current_destination.params.'.$unexpected,
                'This identifier is not valid for the contextual resource.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function validateOverviewDestination(
        Validator $validator,
        string $resourceType,
        array $params,
    ): void {
        if ($this->input('resource_id') !== null) {
            $validator->errors()->add('resource_id', 'Overview context must not include a resource identifier.');
        }

        if ($resourceType === 'income' && $this->input('current_destination.screen') === 'income_detail') {
            $this->validateIncomeDetailDestination($validator, $params);

            return;
        }

        if ($params !== []) {
            $validator->errors()->add(
                'current_destination.params',
                'Overview context must not include entity identifiers.',
            );
        }
        if ($this->input('current_destination.screen') !== $resourceType) {
            $validator->errors()->add(
                'current_destination.screen',
                'The destination screen must match the overview resource.',
            );
        }
        if ($this->input('current_destination.fallback') !== 'dashboard') {
            $validator->errors()->add(
                'current_destination.fallback',
                'Overview context must fall back to the dashboard.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function validateIncomeDetailDestination(Validator $validator, array $params): void
    {
        if (! in_array($params['income_owner'] ?? null, ['user', 'spouse'], true)) {
            $validator->errors()->add(
                'current_destination.params.income_owner',
                'Income owner must be user or spouse.',
            );
        }

        if (! in_array($params['income_source'] ?? null, [
            'employment',
            'self_employment',
            'dividend',
            'interest',
            'other',
            'rental',
            'trust',
            'pension_income',
        ], true)) {
            $validator->errors()->add(
                'current_destination.params.income_source',
                'Income source is not allowlisted.',
            );
        }

        foreach (array_diff(array_keys($params), ['income_owner', 'income_source']) as $unexpected) {
            $validator->errors()->add(
                'current_destination.params.'.$unexpected,
                'This identifier is not valid for income detail.',
            );
        }

        if ($this->input('current_destination.fallback') !== 'income') {
            $validator->errors()->add(
                'current_destination.fallback',
                'Income detail must fall back to the income overview.',
            );
        }
    }
}
