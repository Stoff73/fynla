<?php

declare(strict_types=1);

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Models\User;
use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Coordination\PlanSources\ModuleStrategySource;
use App\Services\Coordination\StrategyPlanComposer;
use Illuminate\Support\Collection;

function fakeSource(array $recs, array $rows, array $availability): ModuleStrategySource
{
    return new class($recs, $rows, $availability) implements ModuleStrategySource
    {
        public function __construct(
            private array $recs,
            private array $rows,
            private array $availability,
        ) {}

        public function moduleKey(): string
        {
            return 'retirement';
        }

        public function recommendations(User $user): array
        {
            return $this->recs;
        }

        public function metadataRows(): Collection
        {
            return collect($this->rows);
        }

        public function availability(User $user): array
        {
            return $this->availability;
        }
    };
}

it('composes a module plan from a source and derives locked from unmet required_data', function (): void {
    $rec = new StrategyRecommendation(
        type: 'increase_pension_contribution',
        category: StrategyCategory::Lifecycle,
        priority: 'high',
        title: 'Increase pension contribution',
        description: 'Use unused annual allowance.',
        estimatedAnnualTaxSaved: 800.0,
    );

    // Two catalogue rows: one fired (above), one locked behind missing data.
    $firedRow = (object) [
        'strategy_type' => 'increase_pension_contribution',
        'claim_tier' => 'mechanical',
        'sequencing' => ['do_before' => [], 'conflicts_with' => []],
        'required_data' => ['pension_contributions'],
    ];
    $lockedRow = (object) [
        'strategy_type' => 'carry_forward_unused_allowance',
        'claim_tier' => 'judgement',
        'sequencing' => ['do_before' => [], 'conflicts_with' => []],
        'required_data' => ['pension_input_history'],
    ];

    $service = new ComposedModulePlanService(new StrategyPlanComposer);
    $source = fakeSource(
        recs: [$rec],
        rows: [$firedRow, $lockedRow],
        availability: ['pension_contributions' => true, 'pension_input_history' => false],
    );

    $plan = $service->forSource($source, new User);

    expect($plan['items'])->toHaveCount(1)
        ->and($plan['items'][0]['type'])->toBe('increase_pension_contribution')
        ->and($plan['locked'])->toBe([
            ['strategy_type' => 'carry_forward_unused_allowance', 'missing' => ['pension_input_history']],
        ]);
});

it('extractStrategyIds and planDigest behave identically to the tax facade', function (): void {
    $plan = [
        'items' => [['type' => 'a'], ['type' => '']],
        'combined_annual_saving' => 0.0,
        'locked' => [['strategy_type' => 'b', 'missing' => ['x']]],
    ];

    expect(ComposedModulePlanService::extractStrategyIds($plan))
        ->toBe(ComposedTaxPlanService::extractStrategyIds($plan))
        ->and(ComposedModulePlanService::planDigest($plan))
        ->toBe(ComposedTaxPlanService::planDigest($plan));
});
