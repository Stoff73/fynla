<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * In-memory slider overrides applied during a /tax-strategy/calculate
 * recalculation. NEVER mutates the database — applied as deltas to the
 * user's stored state when the calculator runs.
 */
final class TaxStrategyOverridesDTO
{
    public function __construct(
        public readonly ?float $pensionContributionPercent = null,
        public readonly ?bool $salarySacrifice = null,
        public readonly ?float $isaAdditionalDeposit = null,
        public readonly ?bool $marriageAllowanceClaimed = null,
        public readonly ?float $assetShiftAmount = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            pensionContributionPercent: isset($data['pension_contribution_percent'])
                ? (float) $data['pension_contribution_percent'] : null,
            salarySacrifice: isset($data['salary_sacrifice'])
                ? (bool) $data['salary_sacrifice'] : null,
            isaAdditionalDeposit: isset($data['isa_additional_deposit'])
                ? (float) $data['isa_additional_deposit'] : null,
            marriageAllowanceClaimed: isset($data['marriage_allowance_claimed'])
                ? (bool) $data['marriage_allowance_claimed'] : null,
            assetShiftAmount: isset($data['asset_shift_amount'])
                ? (float) $data['asset_shift_amount'] : null,
        );
    }

    public function isEmpty(): bool
    {
        return $this->pensionContributionPercent === null
            && $this->salarySacrifice === null
            && $this->isaAdditionalDeposit === null
            && $this->marriageAllowanceClaimed === null
            && $this->assetShiftAmount === null;
    }
}
