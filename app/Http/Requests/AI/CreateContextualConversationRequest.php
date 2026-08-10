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
        'income',
        'expenditure',
        'net_worth',
        'estate',
        'tax_strategy',
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
            $this->validateDestinationParameters($validator);
        });
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
                if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
                    $validator->errors()->add(
                        'current_destination.params.'.$key,
                        'Destination identifiers must be positive integers.',
                    );
                } elseif ((int) $value < 1) {
                    $validator->errors()->add(
                        'current_destination.params.'.$key,
                        'Destination identifiers must be positive integers.',
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
        $resourceId = $this->integer('resource_id');
        if (! is_string($resourceType) || ! in_array($resourceType, self::ENTITY_RESOURCE_TYPES, true)) {
            return;
        }

        $idKey = match ($resourceType) {
            'savings_account', 'investment_account' => 'account_id',
            'dc_pension', 'db_pension', 'state_pension' => 'pension_id',
            'goal' => 'goal_id',
            default => 'policy_id',
        };

        if (! isset($params[$idKey]) || (int) $params[$idKey] !== $resourceId) {
            $validator->errors()->add(
                'current_destination.params.'.$idKey,
                'The destination identifier must match the contextual resource.',
            );
        }
    }
}
