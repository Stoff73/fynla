<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFamilyMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $maxAge105 = now()->subYears(105)->format('Y-m-d');

        return [
            'relationship' => ['sometimes', Rule::in(['spouse', 'partner', 'child', 'step_child', 'parent', 'other_dependent'])],
            'email' => ['nullable', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'], // Optional - constructed from name parts
            'first_name' => ['sometimes', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today', 'after:'.$maxAge105],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'national_insurance_number' => ['nullable', 'string', 'regex:/^$|^[A-Z]{2}[0-9]{6}[A-Z]{1}$/'],
            'annual_income' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'is_dependent' => ['sometimes', 'boolean'],
            'education_status' => ['nullable', Rule::in(['pre_school', 'primary', 'secondary', 'further_education', 'higher_education', 'graduated', 'not_applicable'])],
            'receives_child_benefit' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->date_of_birth || $validator->errors()->has('date_of_birth')) {
                return;
            }

            try {
                $dob = \Carbon\Carbon::parse($this->date_of_birth);
            } catch (\Exception $e) {
                $validator->errors()->add('date_of_birth', 'Please provide a valid date.');

                return;
            }
            $age = $dob->diffInYears(now());

            // Spouse validation - must be 16+
            if ($this->relationship === 'spouse' && $age < 16) {
                $validator->errors()->add('date_of_birth', 'Spouse must be at least 16 years old.');
            }

            // Child validation
            if (in_array($this->relationship, ['child', 'step_child'])) {
                $educationStatuses = ['pre_school', 'primary', 'secondary', 'further_education', 'higher_education'];
                $isInEducation = in_array($this->education_status, $educationStatuses);
                $maxAge = $isInEducation ? 22 : 18;

                if ($age > $maxAge) {
                    $message = $isInEducation
                        ? 'Child in education must be 22 years old or younger.'
                        : 'Child not in education must be 18 years old or younger.';
                    $validator->errors()->add('date_of_birth', $message);
                }
            }
        });
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'relationship.required' => 'Please select a relationship type.',
            'email.required_if' => 'Email address is required for spouse.',
            'email.email' => 'Please enter a valid email address.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'date_of_birth.before_or_equal' => 'Date of birth cannot be in the future.',
            'date_of_birth.after' => 'Date of birth cannot be more than 105 years ago.',
            'national_insurance_number.regex' => 'National Insurance number must be in format: AB123456C',
            'annual_income.min' => 'Income cannot be negative.',
        ];
    }
}
