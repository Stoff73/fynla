<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    private const EMPLOYMENT_VALUES = ['not-employed', 'part-time', 'full-time', 'self-employed', 'retired'];

    private const INCOME_VALUES = ['upto_50270', '50271_100000', '100001_125140', 'over_125140'];

    private const SPOUSE_VALUES = ['yes', 'no'];

    private const SPOUSE_INCOME_VALUES = ['zero', ...self::INCOME_VALUES];

    private const ASSET_VALUES = ['bank', 'savings', 'pension', 'property', 'isa', 'investments'];

    private const AGE_VALUES = ['under_30', '30s', '40s', '50s', '60_plus'];

    private const PENSION_VALUES = ['workplace', 'personal_sipp', 'final_salary', 'none'];

    private const POT_VALUES = ['none', 'under_25k', '25k_100k', '100k_250k', 'over_250k'];

    /**
     * Marketing-channel allowlist for signup_source. Mirror of
     * resources/js/utils/sourceCapture.js — keep both lists in sync
     * when adding a new social platform.
     */
    public const ALLOWED_SIGNUP_SOURCES = [
        'linkedin',
        'facebook',
        'instagram',
        'tiktok',
        'x',
        'youtube',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitise funnel_answers.campaign against the campaign_map keys. An
     * unknown campaign is STRIPPED, not rejected — a 422 here would block a
     * real registration over a stale funnel client, whereas a missing stamp
     * just falls back to the legacy savetax default downstream
     * (AiChatController::startOnboarding funnel fallback).
     */
    protected function prepareForValidation(): void
    {
        $funnel = $this->input('funnel_answers');
        if (! is_array($funnel)) {
            return;
        }

        $known = array_keys((array) config('onboarding.campaign_map', []));
        if (array_key_exists('campaign', $funnel)
            && (! is_string($funnel['campaign']) || ! in_array($funnel['campaign'], $known, true))) {
            unset($funnel['campaign']);
        }

        foreach ([
            'employment' => self::EMPLOYMENT_VALUES,
            'income' => self::INCOME_VALUES,
            'spouse' => self::SPOUSE_VALUES,
            'spouseIncome' => self::SPOUSE_INCOME_VALUES,
            'age' => self::AGE_VALUES,
            'pot' => self::POT_VALUES,
        ] as $field => $allowed) {
            if (array_key_exists($field, $funnel)
                && (! is_string($funnel[$field]) || ! in_array($funnel[$field], $allowed, true))) {
                unset($funnel[$field]);
            }
        }

        foreach (['assets' => self::ASSET_VALUES, 'pensions' => self::PENSION_VALUES] as $field => $allowed) {
            if (! array_key_exists($field, $funnel)) {
                continue;
            }

            $funnel[$field] = is_array($funnel[$field])
                ? array_values(array_unique(array_filter(
                    $funnel[$field],
                    static fn (mixed $value): bool => is_string($value) && in_array($value, $allowed, true)
                )))
                : [];
        }

        $this->merge(['funnel_answers' => $funnel]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/',
            ],
            'signup_source' => ['nullable', 'string', Rule::in(self::ALLOWED_SIGNUP_SOURCES)],
            // /savetax acquisition-funnel answers (coarse hints carried from the
            // public funnel via localStorage). Validated loosely at the boundary.
            'funnel_answers' => ['nullable', 'array'],
            'funnel_answers.campaign' => ['nullable', 'string', 'max:40'],
            'funnel_answers.employment' => ['nullable', 'string', Rule::in(self::EMPLOYMENT_VALUES)],
            'funnel_answers.income' => ['nullable', 'string', Rule::in(self::INCOME_VALUES)],
            'funnel_answers.spouse' => ['nullable', 'string', Rule::in(self::SPOUSE_VALUES)],
            'funnel_answers.spouseIncome' => ['nullable', 'string', Rule::in(self::SPOUSE_INCOME_VALUES)],
            'funnel_answers.assets' => ['nullable', 'array', 'max:12'],
            'funnel_answers.assets.*' => ['string', Rule::in(self::ASSET_VALUES)],
            // pensioncheck funnel-specific keys (absent from savetax)
            'funnel_answers.age' => ['nullable', 'string', Rule::in(self::AGE_VALUES)],
            'funnel_answers.pensions' => ['nullable', 'array', 'max:12'],
            'funnel_answers.pensions.*' => ['string', Rule::in(self::PENSION_VALUES)],
            'funnel_answers.pot' => ['nullable', 'string', Rule::in(self::POT_VALUES)],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'surname.required' => 'Last name is required.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
        ];
    }
}
