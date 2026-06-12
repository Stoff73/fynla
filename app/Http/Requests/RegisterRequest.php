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
            'funnel_answers.employment' => ['nullable', 'string', 'max:40'],
            'funnel_answers.income' => ['nullable', 'string', 'max:40'],
            'funnel_answers.spouse' => ['nullable', 'string', 'max:10'],
            'funnel_answers.spouseIncome' => ['nullable', 'string', 'max:40'],
            'funnel_answers.assets' => ['nullable', 'array'],
            'funnel_answers.assets.*' => ['string', 'max:40'],
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
