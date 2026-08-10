<?php

declare(strict_types=1);

namespace App\Services\AI\ContextualConversation;

use App\Http\Requests\AI\CreateContextualConversationRequest;
use App\Models\CriticalIllnessPolicy;
use App\Models\DisabilityPolicy;
use App\Models\Goal;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\SicknessIllnessPolicy;
use App\Models\User;
use App\Services\Stores\PensionStore;
use App\Services\Stores\SavingsStore;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ContextualResourceResolver
{
    public function __construct(
        private readonly SavingsStore $savingsStore,
        private readonly PensionStore $pensionStore,
    ) {}

    public function referenceKey(string $resourceType, ?int $resourceId): string
    {
        return $resourceType.':'.($resourceId ?? 'overview');
    }

    public function overviewScreenFor(string $resourceType): string
    {
        return match ($resourceType) {
            'personal_information' => 'personal_information',
            'savings', 'savings_account' => 'savings',
            'investment', 'investment_account' => 'investment',
            'retirement', 'dc_pension', 'db_pension', 'state_pension' => 'retirement',
            'protection',
            'life_insurance_policy',
            'critical_illness_policy',
            'income_protection_policy',
            'disability_policy',
            'sickness_illness_policy' => 'protection',
            'goals', 'goal' => 'goals',
            'income' => 'income',
            'expenditure' => 'expenditure',
            'net_worth' => 'net_worth',
            'estate' => 'estate',
            'tax_strategy' => 'tax_strategy',
            default => 'dashboard',
        };
    }

    public function displayNameFor(string $resourceType): string
    {
        return match ($resourceType) {
            'personal_information' => 'Personal Information',
            'savings' => 'Bank Accounts',
            'savings_account' => 'Bank Account',
            'investment' => 'Investments',
            'investment_account' => 'Investment Account',
            'retirement' => 'Retirement',
            'dc_pension' => 'Defined Contribution Pension',
            'db_pension' => 'Defined Benefit Pension',
            'state_pension' => 'State Pension',
            'protection' => 'Protection',
            'life_insurance_policy' => 'Life Insurance Policy',
            'critical_illness_policy' => 'Critical Illness Policy',
            'income_protection_policy' => 'Income Protection Policy',
            'disability_policy' => 'Disability Policy',
            'sickness_illness_policy' => 'Sickness and Illness Policy',
            'goals' => 'Goals',
            'goal' => 'Goal',
            'income' => 'Income',
            'expenditure' => 'Expenditure',
            'net_worth' => 'Net Worth',
            'estate' => 'Estate Planning',
            'tax_strategy' => 'Tax Strategy',
            default => 'Fyn Conversation',
        };
    }

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

    /**
     * Resolve history references in one ownership-scoped query per entity type.
     * Missing, malformed, and foreign references are deliberately omitted.
     *
     * @param  list<array{resource_type: string, resource_id: int|null}>  $references
     * @return array<string, ContextualResource>
     */
    public function resolveMany(User $user, array $references): array
    {
        $resolved = [];
        $idsByType = [];

        foreach ($references as $reference) {
            $resourceType = $reference['resource_type'];
            $resourceId = $reference['resource_id'];

            if (in_array($resourceType, CreateContextualConversationRequest::OVERVIEW_RESOURCE_TYPES, true)) {
                $resolved[$this->referenceKey($resourceType, null)] = $this->resolveOverview($user, $resourceType);

                continue;
            }

            if ($resourceId !== null) {
                $idsByType[$resourceType][] = $resourceId;
            }
        }

        foreach ($idsByType as $resourceType => $ids) {
            try {
                $models = $this->modelsFor(
                    $user,
                    $resourceType,
                    array_values(array_unique($ids)),
                );
            } catch (ModelNotFoundException) {
                continue;
            }

            foreach ($models as $model) {
                $resource = $this->resourceFromModel($model, $resourceType);
                $resolved[$this->referenceKey($resourceType, $resource->resourceId)] = $resource;
            }
        }

        return $resolved;
    }

    private function resolveOverview(User $user, string $resourceType): ContextualResource
    {
        if (! in_array($resourceType, CreateContextualConversationRequest::OVERVIEW_RESOURCE_TYPES, true)) {
            throw (new ModelNotFoundException)->setModel($resourceType);
        }

        return new ContextualResource(
            resourceType: $resourceType,
            resourceId: null,
            label: $this->displayNameFor($resourceType),
            overviewScreen: $this->overviewScreenFor($resourceType),
            canonicalFacts: ['user_id' => $user->id],
        );
    }

    private function resolveEntity(User $user, string $resourceType, int $resourceId): ContextualResource
    {
        /** @var Model $model */
        $model = $this->modelsFor($user, $resourceType, [$resourceId])->first();

        if (! $model instanceof Model) {
            throw (new ModelNotFoundException)->setModel($resourceType, [$resourceId]);
        }

        return $this->resourceFromModel($model, $resourceType);
    }

    /**
     * Resolve entities through their canonical read stores where a store
     * boundary exists, retaining one ownership-scoped query per resource type.
     *
     * @param  list<int>  $resourceIds
     * @return Collection<int, Model>
     */
    private function modelsFor(User $user, string $resourceType, array $resourceIds): Collection
    {
        if ($resourceType === 'savings_account') {
            return $this->savingsStore->findManyPrimary($resourceIds, $user);
        }

        if (in_array($resourceType, ['dc_pension', 'db_pension', 'state_pension'], true)) {
            return $this->pensionStore->findMany(
                $resourceIds,
                match ($resourceType) {
                    'dc_pension' => 'dc',
                    'db_pension' => 'db',
                    'state_pension' => 'state',
                },
                $user,
            );
        }

        [$modelClass] = $this->entityDefinition($resourceType);

        if ($modelClass === null) {
            throw (new ModelNotFoundException)->setModel($resourceType);
        }

        return $modelClass::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $resourceIds)
            ->get();
    }

    /**
     * @return array{class-string<Model>|null, list<string>, string, list<string>}
     */
    private function entityDefinition(string $resourceType): array
    {
        return match ($resourceType) {
            'savings_account' => [
                null,
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
                null,
                ['scheme_name', 'provider'],
                'retirement',
                ['scheme_name', 'scheme_type', 'provider', 'current_fund_value', 'monthly_contribution_amount', 'retirement_age'],
            ],
            'db_pension' => [
                null,
                ['scheme_name'],
                'retirement',
                ['scheme_name', 'scheme_type', 'accrued_annual_pension', 'normal_retirement_age'],
            ],
            'state_pension' => [
                null,
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
            default => throw (new ModelNotFoundException)->setModel($resourceType),
        };
    }

    private function resourceFromModel(Model $model, string $resourceType): ContextualResource
    {
        [, $labelFields, $overviewScreen, $factFields] = $this->entityDefinition($resourceType);
        $resourceId = (int) $model->getKey();

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
