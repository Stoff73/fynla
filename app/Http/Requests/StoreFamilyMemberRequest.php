<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\Carbon;
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
            // Required for a spouse, because a spouse row is the household's
            // account link: `users.spouse_id`, both SpousePermission rows and
            // everything joint key off it. Without an email there is nothing to
            // link by, and the record that came back claimed a link it never
            // made (W-0051). The message for this rule was already here — the
            // rule itself never was.
            // Prohibited for anyone else, because only a spouse gets an
            // account. The form used to reveal this field for a partner too,
            // labelled "Used to create or link their account", validate what was
            // typed, return 201 — and then drop it, since `email` is not a
            // `family_members` column and only a spouse is routed to linking.
            // A field the code intends to discard is not a field to accept
            // (W-0111).
            'email' => ['required_if:relationship,spouse', 'prohibited_unless:relationship,spouse', 'nullable', 'email', 'max:255'],
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
                $dob = Carbon::parse($this->date_of_birth);
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
            'email.required_if' => 'Please enter your spouse\'s email address. We use it to create or link their account, which is what connects your finances.',
            'email.prohibited_unless' => 'Only a spouse can be given an email address — an account is created or linked for them, which does not happen for other family members.',
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
