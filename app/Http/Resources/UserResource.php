<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'surname' => $this->surname,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'is_preview_user' => $this->is_preview_user,
            'is_admin' => $this->is_admin,
            'is_advisor' => $this->is_advisor,
            'preview_persona_id' => $this->preview_persona_id,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'marital_status' => $this->marital_status,
            'life_stage' => $this->life_stage,
            'onboarding_completed' => $this->onboarding_completed,
            'onboarding_stage' => $this->onboarding_stage,
            'journey_state' => $this->journey_state,
            'has_spouse' => $this->has_spouse,
            'spouse_id' => $this->spouse_id,
            'mfa_enabled' => $this->mfa_enabled,
            'is_student' => $this->is_student,
            'student_loan_plan' => $this->student_loan_plan,
            // Expenditure fields (needed by ExpenditureForm view mode)
            'monthly_expenditure' => $this->monthly_expenditure,
            'annual_expenditure' => $this->annual_expenditure,
            'expenditure_entry_mode' => $this->expenditure_entry_mode,
            'expenditure_sharing_mode' => $this->expenditure_sharing_mode,
            // Expenditure categories
            'food_groceries' => $this->food_groceries,
            'transport_fuel' => $this->transport_fuel,
            'healthcare_medical' => $this->healthcare_medical,
            'insurance' => $this->insurance,
            'mobile_phones' => $this->mobile_phones,
            'internet_tv' => $this->internet_tv,
            'subscriptions' => $this->subscriptions,
            'clothing_personal_care' => $this->clothing_personal_care,
            'entertainment_dining' => $this->entertainment_dining,
            'holidays_travel' => $this->holidays_travel,
            'pets' => $this->pets,
            'childcare' => $this->childcare,
            'school_fees' => $this->school_fees,
            'school_lunches' => $this->school_lunches,
            'school_extras' => $this->school_extras,
            'university_fees' => $this->university_fees,
            'children_activities' => $this->children_activities,
            'gifts_charity' => $this->gifts_charity,
            'regular_savings' => $this->regular_savings,
            'other_expenditure' => $this->other_expenditure,
            // Income fields (needed by IncomeOccupation and tax calculations)
            'annual_employment_income' => $this->annual_employment_income,
            'annual_self_employment_income' => $this->annual_self_employment_income,
            'annual_rental_income' => $this->annual_rental_income,
            'annual_dividend_income' => $this->annual_dividend_income,
            'annual_interest_income' => $this->annual_interest_income,
            'annual_other_income' => $this->annual_other_income,
            'annual_trust_income' => $this->annual_trust_income,
            'annual_charitable_donations' => $this->annual_charitable_donations,
            'is_gift_aid' => $this->is_gift_aid,
            'is_registered_blind' => $this->is_registered_blind,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'spouse' => $this->when($this->relationLoaded('spouse'), function () {
                return $this->spouse ? [
                    'id' => $this->spouse->id,
                    'first_name' => $this->spouse->first_name,
                    'surname' => $this->spouse->surname,
                    'email' => $this->spouse->email,
                    'is_preview_user' => $this->spouse->is_preview_user,
                ] : null;
            }),
            'role' => $this->when($this->relationLoaded('role'), $this->role),
            'subscription' => $this->when($this->relationLoaded('subscription'), $this->subscription),
        ];
    }
}
