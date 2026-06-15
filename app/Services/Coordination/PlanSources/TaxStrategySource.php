<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources;

use App\DataTransferObjects\StrategyRecommendation;
use App\Models\TaxActionDefinition;
use App\Models\User;
use App\Services\Coordination\HouseholdFinancialContext;
use App\Services\Tax\TaxStrategyCalculator;
use Illuminate\Support\Collection;

/** Tax module's plan source — wraps the calculator + tax catalogue + household availability. */
final class TaxStrategySource implements ModuleStrategySource
{
    public function __construct(
        private readonly TaxStrategyCalculator $calculator,
        private readonly HouseholdFinancialContext $context,
    ) {}

    public function moduleKey(): string
    {
        return 'tax';
    }

    public function recommendations(User $user): array
    {
        $output = $this->calculator->calculate($user);

        return array_map(
            fn (array $r) => StrategyRecommendation::fromArray((string) $r['category'], $r),
            $output->recommendations
        );
    }

    public function metadataRows(): Collection
    {
        return TaxActionDefinition::where('source', 'strategy')->where('is_enabled', true)->get();
    }

    public function availability(User $user): array
    {
        return $this->context->availability($user);
    }
}
