<?php

declare(strict_types=1);

namespace App\Http\Resources\Protection;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProtectionProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'annual_income' => (float) $this->annual_income,
            'monthly_expenditure' => (float) $this->monthly_expenditure,
            'mortgage_balance' => (float) $this->mortgage_balance,
            'other_debts' => (float) $this->other_debts,
            'number_of_dependents' => $this->number_of_dependents,
            'dependents_ages' => $this->dependents_ages,
            'retirement_age' => $this->retirement_age,
            'occupation' => $this->occupation,
            'smoker_status' => (bool) $this->smoker_status,
            'health_status' => $this->health_status,
            'has_no_policies' => (bool) $this->has_no_policies,
            // Employer benefits: null means not provided; employer_benefits_recorded_at
            // null means the user has never answered.
            'employer_name' => $this->employer_name,
            'death_in_service_multiple' => $this->death_in_service_multiple !== null ? (float) $this->death_in_service_multiple : null,
            'group_ip_benefit_percent' => $this->group_ip_benefit_percent !== null ? (float) $this->group_ip_benefit_percent : null,
            'group_ip_benefit_months' => $this->group_ip_benefit_months,
            'group_ip_definition' => $this->group_ip_definition,
            'group_ci_amount' => $this->group_ci_amount !== null ? (float) $this->group_ci_amount : null,
            'has_employer_pmi' => (bool) $this->has_employer_pmi,
            'employer_benefits_recorded_at' => $this->employer_benefits_recorded_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
