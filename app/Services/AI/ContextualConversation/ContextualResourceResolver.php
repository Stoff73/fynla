<?php

declare(strict_types=1);

namespace App\Services\AI\ContextualConversation;

use App\Http\Requests\AI\CreateContextualConversationRequest;
use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\DisabilityPolicy;
use App\Models\Goal;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\SavingsAccount;
use App\Models\SicknessIllnessPolicy;
use App\Models\StatePension;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ContextualResourceResolver
{
    public function resolve(User $user, string $resourceType, ?int $resourceId): ContextualResource
    {
        if (in_array($resourceType, CreateContextualConversationRequest::OVERVIEW_RESOURCE_TYPES, true)) {
            return $this->resolveOverview($user, $resourceType);
        }

        if ($resourceId === null) {
            throw (new ModelNotFoundException)->setModel($resourceType);
        }

        return $this->resolveEntity($user, $resourceType, $resourceId);
    }

    private function resolveOverview(User $user, string $resourceType): ContextualResource
    {
        $definition = match ($resourceType) {
            'personal_information' => ['Personal Information', 'personal_information'],
            'savings' => ['Bank Accounts', 'savings'],
            'investment' => ['Investments', 'investment'],
            'retirement' => ['Retirement', 'retirement'],
            'protection' => ['Protection', 'protection'],
            'goals' => ['Goals', 'goals'],
            'income' => ['Income', 'income'],
            'expenditure' => ['Expenditure', 'expenditure'],
            'net_worth' => ['Net Worth', 'net_worth'],
            'estate' => ['Estate Planning', 'estate'],
            'tax_strategy' => ['Tax Strategy', 'tax_strategy'],
            default => throw (new ModelNotFoundException)->setModel($resourceType),
        };

        return new ContextualResource(
            resourceType: $resourceType,
            resourceId: null,
            label: $definition[0],
            overviewScreen: $definition[1],
            canonicalFacts: ['user_id' => $user->id],
        );
    }

    private function resolveEntity(User $user, string $resourceType, int $resourceId): ContextualResource
    {
        [$modelClass, $labelFields, $overviewScreen, $factFields] = match ($resourceType) {
            'savings_account' => [
                SavingsAccount::class,
                ['account_name', 'institution'],
                'savings',
                ['account_name', 'account_type', 'institution', 'current_balance', 'interest_rate', 'is_isa', 'isa_type', 'ownership_type'],
            ],
            'investment_account' => [
                InvestmentAccount::class,
                ['account_name', 'provider'],
                'investment',
                ['account_name', 'account_type', 'provider', 'current_value', 'contributions_ytd', 'ownership_type'],
            ],
            'dc_pension' => [
                DCPension::class,
                ['scheme_name', 'provider'],
                'retirement',
                ['scheme_name', 'scheme_type', 'provider', 'current_fund_value', 'monthly_contribution_amount', 'retirement_age'],
            ],
            'db_pension' => [
                DBPension::class,
                ['scheme_name'],
                'retirement',
                ['scheme_name', 'scheme_type', 'accrued_annual_pension', 'normal_retirement_age'],
            ],
            'state_pension' => [
                StatePension::class,
                [],
                'retirement',
                ['ni_years_completed', 'ni_years_required', 'state_pension_forecast_annual', 'state_pension_age'],
            ],
            'goal' => [
                Goal::class,
                ['goal_name'],
                'goals',
                ['goal_name', 'goal_type', 'description', 'target_amount', 'current_amount', 'target_date', 'status'],
            ],
            'life_insurance_policy' => [
                LifeInsurancePolicy::class,
                ['provider'],
                'protection',
                ['provider', 'policy_type', 'sum_assured', 'premium_amount', 'premium_frequency', 'policy_end_date'],
            ],
            'critical_illness_policy' => [
                CriticalIllnessPolicy::class,
                ['provider'],
                'protection',
                ['provider', 'policy_type', 'sum_assured', 'premium_amount', 'premium_frequency'],
            ],
            'income_protection_policy' => [
                IncomeProtectionPolicy::class,
                ['provider'],
                'protection',
                ['provider', 'benefit_amount', 'benefit_frequency', 'premium_amount', 'premium_frequency'],
            ],
            'disability_policy' => [
                DisabilityPolicy::class,
                ['provider'],
                'protection',
                ['provider', 'benefit_amount', 'benefit_frequency', 'premium_amount', 'premium_frequency'],
            ],
            'sickness_illness_policy' => [
                SicknessIllnessPolicy::class,
                ['provider'],
                'protection',
                ['provider', 'benefit_amount', 'benefit_frequency', 'premium_amount', 'premium_frequency'],
            ],
            default => throw (new ModelNotFoundException)->setModel($resourceType, [$resourceId]),
        };

        /** @var Model $model */
        $model = $modelClass::query()
            ->where('user_id', $user->id)
            ->findOrFail($resourceId);

        return new ContextualResource(
            resourceType: $resourceType,
            resourceId: $resourceId,
            label: $this->labelFor($model, $labelFields, $resourceType),
            overviewScreen: $overviewScreen,
            canonicalFacts: $model->only($factFields),
        );
    }

    /**
     * @param  list<string>  $fields
     */
    private function labelFor(Model $model, array $fields, string $resourceType): string
    {
        foreach ($fields as $field) {
            $value = $model->getAttribute($field);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return match ($resourceType) {
            'state_pension' => 'State Pension',
            'goal' => 'Goal',
            default => str($resourceType)->replace('_', ' ')->title()->toString(),
        };
    }
}
