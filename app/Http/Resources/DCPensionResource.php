<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DCPension;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DCPension
 */
class DCPensionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Created during the 2026-05-23 tech-debt audit (B52) — previously
     * RetirementController returned `'data' => $pension` after
     * `$pension->load('holdings')`, which exposed every model attribute
     * including any internally-added fields. Mirrors InvestmentAccountResource.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheme_name' => $this->scheme_name,
            'scheme_type' => $this->scheme_type,
            'provider' => $this->provider,
            'pension_type' => $this->pension_type,
            'member_number' => $this->member_number,
            'current_fund_value' => $this->current_fund_value,
            'annual_salary' => $this->annual_salary,
            'employee_contribution_percent' => $this->employee_contribution_percent,
            'employer_contribution_percent' => $this->employer_contribution_percent,
            'employer_matching_limit' => $this->employer_matching_limit,
            'monthly_contribution_amount' => $this->monthly_contribution_amount,
            'lump_sum_contribution' => $this->lump_sum_contribution,
            'investment_strategy' => $this->investment_strategy,
            'platform_fee_percent' => $this->platform_fee_percent,
            'platform_fee_type' => $this->platform_fee_type,
            'platform_fee_amount' => $this->platform_fee_amount,
            'platform_fee_frequency' => $this->platform_fee_frequency,
            'advisor_fee_percent' => $this->advisor_fee_percent,
            'retirement_age' => $this->retirement_age,
            'expected_return_percent' => $this->expected_return_percent,
            'projected_value_at_retirement' => $this->projected_value_at_retirement,
            'risk_preference' => $this->risk_preference,
            'has_custom_risk' => $this->has_custom_risk,
            'beneficiary_id' => $this->beneficiary_id,
            'beneficiary_name' => $this->beneficiary_name,
            'has_flexibly_accessed' => $this->has_flexibly_accessed,
            'flexible_access_date' => $this->flexible_access_date?->toDateString(),
            'salary_sacrifice' => $this->salary_sacrifice,
            'employer_ni_rebate_pct' => $this->employer_ni_rebate_pct,
            'holdings' => $this->whenLoaded('holdings'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
