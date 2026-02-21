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
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
