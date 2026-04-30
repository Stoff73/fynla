<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies\Contract;

use App\DataTransferObjects\StrategyRecommendation;
use App\Services\Tax\Strategies\TaxStrategyContext;

interface TaxStrategy
{
    /**
     * Return zero or more recommendations for this context. Returning an empty
     * array means the strategy's preconditions weren't met for this user.
     *
     * @return list<StrategyRecommendation>
     */
    public function generate(TaxStrategyContext $context): array;
}
