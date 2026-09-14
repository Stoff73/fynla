<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Http\Requests\UpdateIncomeOccupationRequest;
use App\Http\Requests\UpdatePersonalInfoRequest;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * MB-33 — the wizard's step endpoint validated only that `data` was an array;
 * `OnboardingService::processPersonalInfo()` and `processIncomeInfo()` then
 * assigned its values straight to `users.*` (date of birth, marital status,
 * incomes, retirement age). The only validation was in the Vue form.
 *
 * One rule set, not a second copy (Rule 20): the two user-column steps reuse
 * the rules the profile endpoints already enforce for the same columns,
 * applied under the `data.` prefix. Every other step keeps its array contract
 * — those write module rows through their own stores.
 */
class SaveStepProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // The wizard sends '' (or null) for optional fields the user left
        // alone; the profile requests strip those before their nullable rules
        // run, so the same value cannot fail here and pass there.
        $data = $this->input('data');
        if (! is_array($data)) {
            return;
        }
        foreach ($data as $field => $value) {
            if ($value === '' || $value === null) {
                unset($data[$field]);
            }
        }
        $this->merge(['data' => $data]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'step_name' => ['required', 'string', 'max:64'],
            'data' => ['required', 'array'],
        ];

        foreach ($this->profileRulesForStep((string) $this->input('step_name')) as $field => $rule) {
            $rules['data.'.$field] = $rule;
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function profileRulesForStep(string $step): array
    {
        // Only the rules for columns the step writes. The wizard also posts
        // fields the step ignores (name, email), and the profile request's
        // email uniqueness rule cannot run under the data. prefix — Laravel
        // reads the attribute name as the column (live 2026-09-14: 500,
        // "Unknown column 'data.email'").
        return match ($step) {
            'personal_info' => array_intersect_key(
                $this->rulesOf(new UpdatePersonalInfoRequest),
                array_flip(OnboardingService::PERSONAL_INFO_FIELDS),
            ),
            'income' => array_intersect_key(
                $this->rulesOf(new UpdateIncomeOccupationRequest),
                array_flip(OnboardingService::INCOME_FIELDS),
            ),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesOf(FormRequest $request): array
    {
        // The personal-info rules read the request user (the email uniqueness
        // ignore); hand them ours.
        $request->setUserResolver(fn () => $this->user());

        return $request->rules();
    }
}
