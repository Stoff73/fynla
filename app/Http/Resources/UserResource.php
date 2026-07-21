<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Tiers\TeaserGate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canViewDetailedExpenditure = $this->canViewDetailedExpenditure($request);

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
            'onboarding_fyn_step' => $this->onboarding_fyn_step,
            'onboarding_fyn_path' => $this->onboarding_fyn_path,
            'onboarding_fyn_selection' => $this->onboarding_fyn_selection,
            'onboarding_fyn_paused' => is_string(data_get($this->resource->onboarding_fyn_context, 'paused_at_step')),
            // Campaign re-entry marker — the /m onboardingActive gate needs it
            // so the verify pills + dock-resume work for a completed user mid
            // campaign re-entry (audit fix P3).
            'active_campaign' => $this->active_campaign,
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
            'food_groceries' => $this->when($canViewDetailedExpenditure, $this->food_groceries),
            'transport_fuel' => $this->when($canViewDetailedExpenditure, $this->transport_fuel),
            'healthcare_medical' => $this->when($canViewDetailedExpenditure, $this->healthcare_medical),
            'insurance' => $this->when($canViewDetailedExpenditure, $this->insurance),
            'mobile_phones' => $this->when($canViewDetailedExpenditure, $this->mobile_phones),
            'internet_tv' => $this->when($canViewDetailedExpenditure, $this->internet_tv),
            'subscriptions' => $this->when($canViewDetailedExpenditure, $this->subscriptions),
            'clothing_personal_care' => $this->when($canViewDetailedExpenditure, $this->clothing_personal_care),
            'entertainment_dining' => $this->when($canViewDetailedExpenditure, $this->entertainment_dining),
            'holidays_travel' => $this->when($canViewDetailedExpenditure, $this->holidays_travel),
            'pets' => $this->when($canViewDetailedExpenditure, $this->pets),
            'childcare' => $this->when($canViewDetailedExpenditure, $this->childcare),
            'school_fees' => $this->when($canViewDetailedExpenditure, $this->school_fees),
            'school_lunches' => $this->when($canViewDetailedExpenditure, $this->school_lunches),
            'school_extras' => $this->when($canViewDetailedExpenditure, $this->school_extras),
            'university_fees' => $this->when($canViewDetailedExpenditure, $this->university_fees),
            'children_activities' => $this->when($canViewDetailedExpenditure, $this->children_activities),
            'gifts_charity' => $this->when($canViewDetailedExpenditure, $this->gifts_charity),
            'regular_savings' => $this->when($canViewDetailedExpenditure, $this->regular_savings),
            'other_expenditure' => $this->when($canViewDetailedExpenditure, $this->other_expenditure),
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
            // Account deletion lifecycle
            'deletion_scheduled_for' => $this->deletion_scheduled_for,
            'deletion_reason' => $this->deletion_reason,
            'restored_at' => $this->restored_at,
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

    private function canViewDetailedExpenditure(Request $request): bool
    {
        $user = $request->user();

        if ($user === null || $user->is_admin || $user->is_preview_user) {
            return true;
        }

        try {
            return app(TeaserGate::class)->isFull($user, 'expenditure_detailed');
        } catch (ModelNotFoundException) {
            // Match AuthController's pre-seed degradation contract: when the
            // canonical tier rows are not installed yet, gated fields stay off.
            return false;
        }
    }
}
