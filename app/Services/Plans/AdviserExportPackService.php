<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Models\CriticalIllnessPolicy;
use App\Models\DisabilityPolicy;
use App\Models\Goal;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeEvent;
use App\Models\LifeInsurancePolicy;
use App\Models\SicknessIllnessPolicy;
use App\Models\User;
use App\Services\Risk\RiskPreferenceService;
use App\Services\Settings\AssumptionsService;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\Stores\LiabilityStore;
use App\Services\UserProfile\UserProfileService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdviserExportPackService
{
    public function __construct(
        private readonly UserProfileService $profileService,
        private readonly CrossModuleAssetAggregator $assetAggregator,
        private readonly LiabilityStore $liabilityStore,
        private readonly RiskPreferenceService $riskService,
        private readonly AssumptionsService $assumptionsService,
        private readonly InvestmentPlanService $investmentPlanService,
        private readonly ProtectionPlanService $protectionPlanService,
        private readonly RetirementPlanService $retirementPlanService,
        private readonly EstatePlanService $estatePlanService,
        private readonly PlanConfigService $planConfig,
    ) {}

    /**
     * Build a point-in-time adviser handover pack from canonical application services.
     *
     * @return array<string, mixed>
     */
    public function buildPack(User $user): array
    {
        $profile = $this->profileService->getCompleteProfile($user);
        $profile['personal_info']['name'] = trim(implode(' ', array_filter([
            $user->first_name,
            $user->surname,
        ]))) ?: $profile['personal_info']['name'];

        if (empty($profile['expenditure']['annual_expenditure'])
            && ! empty($profile['expenditure']['monthly_expenditure'])) {
            $profile['expenditure']['annual_expenditure'] = (float) $profile['expenditure']['monthly_expenditure'] * 12;
        }
        $investmentNames = InvestmentAccount::forUserOrJoint($user->id)
            ->pluck('account_name', 'id');

        $assets = $this->assetAggregator->getAllAssets($user->id)
            ->map(fn (object $asset): array => [
                'type' => $asset->asset_type,
                'name' => $asset->source_model === 'InvestmentAccount'
                    ? ($investmentNames->get($asset->source_id) ?: $asset->asset_name)
                    : $asset->asset_name,
                'value' => (float) $asset->current_value,
                'full_value' => (float) $asset->full_value,
                'ownership_type' => $asset->ownership_type,
                'ownership_percentage' => (float) $asset->ownership_percentage,
            ])
            ->values()
            ->all();

        $liabilities = $this->liabilityStore->forUser($user)
            ->map(fn ($liability): array => [
                'type' => $liability->liability_type,
                'name' => $liability->liability_name ?: str($liability->liability_type)->replace('_', ' ')->title()->toString(),
                'balance' => (float) $liability->current_balance,
                'monthly_payment' => $liability->monthly_payment === null ? null : (float) $liability->monthly_payment,
                'interest_rate' => $liability->interest_rate === null ? null : (float) $liability->interest_rate,
                'ownership_type' => $liability->ownership_type,
                'ownership_percentage' => (float) $liability->ownership_percentage,
            ])
            ->values()
            ->all();

        $goals = Goal::forUserOrJoint($user->id)
            ->orderBy('target_date')
            ->get()
            ->map(fn (Goal $goal): array => [
                'name' => $goal->goal_name,
                'target_amount' => (float) $goal->target_amount,
                'current_amount' => (float) $goal->current_amount,
                'target_date' => $goal->target_date?->toDateString(),
                'status' => $goal->status,
            ])
            ->values()
            ->all();

        $lifeEvents = LifeEvent::forUserOrJoint($user->id)
            ->orderBy('expected_date')
            ->get()
            ->map(fn (LifeEvent $event): array => [
                'name' => $event->event_name,
                'amount' => (float) $event->amount,
                'impact_type' => $event->impact_type,
                'expected_date' => $event->expected_date?->toDateString(),
                'status' => $event->status,
            ])
            ->values()
            ->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'data_as_at' => now()->toDateString(),
            'profile' => $profile['personal_info'],
            'assets' => $assets,
            'liabilities' => $liabilities,
            'income_expenditure' => [
                'income' => $profile['income_occupation'],
                'expenditure' => $profile['expenditure'],
            ],
            'risk_profile' => $this->riskService->getRiskProfile($user->id),
            'goals' => $goals,
            'life_events' => $lifeEvents,
            'assumptions' => $this->assumptionsService->getAssumptions($user->id),
            'plan_summaries' => $this->buildPlanSummaries($user),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildPlanSummaries(User $user): array
    {
        $services = [
            'Investment' => $this->investmentPlanService,
            'Protection' => $this->protectionPlanService,
            'Retirement' => $this->retirementPlanService,
            'Estate planning' => $this->estatePlanService,
        ];
        $available = [
            'Investment' => $user->investmentAccounts->isNotEmpty() || $user->cashAccounts->isNotEmpty(),
            'Protection' => LifeInsurancePolicy::query()->where('user_id', $user->id)->exists()
                || CriticalIllnessPolicy::query()->where('user_id', $user->id)->exists()
                || IncomeProtectionPolicy::query()->where('user_id', $user->id)->exists()
                || DisabilityPolicy::query()->where('user_id', $user->id)->exists()
                || SicknessIllnessPolicy::query()->where('user_id', $user->id)->exists(),
            'Retirement' => $user->dcPensions->isNotEmpty()
                || $user->dbPensions->isNotEmpty()
                || $user->statePension !== null,
            'Estate planning' => $user->properties->isNotEmpty()
                || $user->liabilities->isNotEmpty()
                || $user->businessInterests->isNotEmpty()
                || $user->chattels->isNotEmpty(),
        ];
        $summaries = [];

        foreach ($services as $label => $service) {
            if (! $available[$label]) {
                continue;
            }

            try {
                $type = str($label)->before(' ')->lower()->toString();
                $plan = Cache::remember(
                    "plan_{$type}_{$user->id}",
                    $this->planConfig->getPlanCacheTTL(),
                    fn (): array => $service->generatePlan($user->id),
                );

                if (! empty($plan['executive_summary'])) {
                    $summaries[$label] = $plan['executive_summary'];
                }
            } catch (Throwable $exception) {
                Log::warning('Adviser export omitted an unavailable plan summary', [
                    'user_id' => $user->id,
                    'module' => $label,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $summaries;
    }
}
