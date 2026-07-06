<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
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
        if (! is_array($funnel) || ! array_key_exists('campaign', $funnel)) {
            return;
        }

        $known = array_keys((array) config('onboarding.campaign_map', []));
        if (! is_string($funnel['campaign']) || ! in_array($funnel['campaign'], $known, true)) {
            unset($funnel['campaign']);
            $this->merge(['funnel_answers' => $funnel]);
        }
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
            'funnel_answers.employment' => ['nullable', 'string', 'max:40'],
            'funnel_answers.income' => ['nullable', 'string', 'max:40'],
            'funnel_answers.spouse' => ['nullable', 'string', 'max:10'],
            'funnel_answers.spouseIncome' => ['nullable', 'string', 'max:40'],
            'funnel_answers.assets' => ['nullable', 'array'],
            'funnel_answers.assets.*' => ['string', 'max:40'],
            // pensioncheck funnel-specific keys (absent from savetax)
            'funnel_answers.age' => ['nullable', 'string', 'max:20'],
            'funnel_answers.pensions' => ['nullable', 'array'],
            'funnel_answers.pensions.*' => ['string', 'max:30'],
            'funnel_answers.pot' => ['nullable', 'string', 'max:20'],
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
