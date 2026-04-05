<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\IHTProfile;
use App\Models\User;
use App\Services\TaxConfigService;
use App\Traits\ResolvesExpenditure;
use App\Traits\ResolvesIncome;
use Illuminate\Support\Collection;

/**
 * Generates personalized gifting strategies based on user's actual assets and liquidity
 *
 * Unlike generic gifting advice, this service analyzes the user's specific assets
 * and provides tailored recommendations based on what they can actually gift.
 */
class PersonalizedGiftingStrategyService
{
    use ResolvesExpenditure;
    use ResolvesIncome;

    public function __construct(
        private readonly AssetLiquidityAnalyzer $liquidityAnalyzer,
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Generate personalized gifting strategy based on user's assets
     *
     * @param  Collection  $assets  User's Estate Assets
     * @param  float  $currentIHTLiability  Current IHT liability to reduce
     * @param  IHTProfile  $profile  User's IHT profile
     * @param  User  $user  User for income/expenditure data
     * @param  int  $yearsUntilDeath  Life expectancy in years
     * @return array Personalized gifting strategies
     */
    public function generatePersonalizedStrategy(
        Collection $assets,
        float $currentIHTLiability,
        IHTProfile $profile,
        User $user,
        int $yearsUntilDeath = 20
    ): array {
        // Analyze asset liquidity
        $liquidityAnalysis = $this->liquidityAnalyzer->analyzeAssetLiquidity($assets);
        $giftableAmounts = $this->liquidityAnalyzer->calculateMaximumGiftableAmount($assets);

        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $giftingConfig = $this->taxConfig->getGiftingExemptions();
        $ihtRate = $ihtConfig['standard_rate']; // 0.40
        $annualExemption = $giftingConfig['annual_exemption']; // £3,000

        $strategies = [];
        $remainingIHTLiability = $currentIHTLiability;

        // If no IHT liability, no strategies needed
        if ($currentIHTLiability <= 0) {
            return [
                'strategies' => [],
                'liquidity_analysis' => $liquidityAnalysis,
                'giftable_amounts' => $giftableAmounts,
                'summary' => [
                    'message' => 'No IHT liability - no gifting strategies needed',
                    'original_iht_liability' => 0,
                    'total_iht_saved' => 0,
                    'remaining_iht_liability' => 0,
                    'total_gifted' => 0,
                    'reduction_percentage' => 0,
                    'implementation_timeframe' => $yearsUntilDeath.' years',
                ],
            ];
        }

        // 1. Annual Exemption (always available)
        $annualExemptionStrategy = $this->buildAnnualExemptionStrategy($yearsUntilDeath, $annualExemption, $ihtRate);
        $strategies[] = $annualExemptionStrategy;
        $remainingIHTLiability -= $annualExemptionStrategy['iht_saved'];

        // 2. Asset-specific gifting strategies based on liquidity
        if ($giftableAmounts['immediately_giftable'] > 0) {
            $liquidAssetStrategy = $this->buildLiquidAssetGiftingStrategy(
                $liquidityAnalysis['liquid'],
                $remainingIHTLiability,
                $ihtRate,
                $yearsUntilDeath
            );

            if ($liquidAssetStrategy) {
                $strategies[] = $liquidAssetStrategy;
                $remainingIHTLiability -= $liquidAssetStrategy['iht_saved'];
            }
        }

        // 3. Semi-liquid asset strategies (properties)
        if ($giftableAmounts['giftable_with_planning'] > 0 && $remainingIHTLiability > 0) {
            $propertyStrategy = $this->buildPropertyGiftingStrategy(
                $liquidityAnalysis['semi_liquid'],
                $remainingIHTLiability,
                $ihtRate
            );

            if ($propertyStrategy) {
                $strategies[] = $propertyStrategy;
                $remainingIHTLiability -= $propertyStrategy['iht_saved'];
            }
        }

        // 4. Illiquid asset strategies (main residence)
        if ($liquidityAnalysis['illiquid']['count'] > 0) {
            $mainResidenceStrategy = $this->buildMainResidenceStrategy(
                $liquidityAnalysis['illiquid'],
                $user
            );

            if ($mainResidenceStrategy) {
                $strategies[] = $mainResidenceStrategy;
            }
        }

        // Gifting from income strategy removed - creates unrealistic priority 5 with inflated figures

        // Calculate totals
        $totalIHTSaved = array_sum(array_column(array_filter($strategies, fn ($s) => isset($s['iht_saved'])), 'iht_saved'));
        $totalGifted = array_sum(array_column(array_filter($strategies, fn ($s) => isset($s['total_gifted'])), 'total_gifted'));

        return [
            'strategies' => $strategies,
            'liquidity_analysis' => $liquidityAnalysis,
            'giftable_amounts' => $giftableAmounts,
            'summary' => [
                'original_iht_liability' => round($currentIHTLiability, 2),
                'total_iht_saved' => round($totalIHTSaved, 2),
                'remaining_iht_liability' => round(max(0, $currentIHTLiability - $totalIHTSaved), 2),
                'total_gifted' => round($totalGifted, 2),
                'reduction_percentage' => $currentIHTLiability > 0 ?
                    round(($totalIHTSaved / $currentIHTLiability) * 100, 1) : 0,
                'implementation_timeframe' => $yearsUntilDeath.' years',
            ],
        ];
    }

    /**
     * Build annual exemption strategy
     */
    private function buildAnnualExemptionStrategy(int $years, float $annualExemption, float $ihtRate): array
    {
        $totalGifted = $annualExemption * $years;
        $ihtSaved = $totalGifted * $ihtRate;

        return [
            'strategy_name' => 'Annual Exemption',
            'priority' => 1,
            'category' => 'immediate_exemption',
            'description' => 'Gift £'.number_format($annualExemption, 0).' per year using annual exemption',
            'total_gifted' => round($totalGifted, 2),
            'annual_amount' => round($annualExemption, 2),
            'years' => $years,
            'iht_saved' => round($ihtSaved, 2),
            'risk_level' => 'Low',
            'implementation_steps' => [
                'Set up standing order for £'.number_format($annualExemption, 0).' per year to beneficiaries',
                'Immediately exempt - no 7-year wait',
                'Can carry forward unused allowance from previous year (one year only)',
                'Both spouses can gift £3,000 each (£6,000 total per year)',
                'Keep records of all gifts',
            ],
        ];
    }

    /**
     * Build liquid asset gifting strategy (cash, investments)
     */
    private function buildLiquidAssetGiftingStrategy(
        array $liquidAssets,
        float $remainingIHTLiability,
        float $ihtRate,
        int $yearsUntilDeath
    ): ?array {
        if ($liquidAssets['count'] === 0 || $remainingIHTLiability <= 0) {
            return null;
        }

        // Calculate target gift amount to reduce IHT
        $targetGiftAmount = min($remainingIHTLiability / $ihtRate, $liquidAssets['total_value']);

        // If user has 7+ years, can do PET strategy
        $complete7YearCycles = floor($yearsUntilDeath / 7);

        if ($complete7YearCycles > 0) {
            // PET strategy - gift in 7-year cycles
            $amountPerCycle = min($targetGiftAmount / $complete7YearCycles, $liquidAssets['total_value']);
            $totalGifted = $amountPerCycle * $complete7YearCycles;
            $ihtSaved = $totalGifted * $ihtRate;

            $assetNames = implode(', ', array_column($liquidAssets['assets'], 'name'));

            return [
                'strategy_name' => 'Gift Liquid Assets (Investments/Cash)',
                'priority' => 2,
                'category' => 'liquid_assets',
                'description' => 'Gift liquid assets (investments, cash) using Potentially Exempt Transfers (PETs)',
                'available_assets' => $assetNames,
                'total_available' => round($liquidAssets['total_value'], 2),
                'recommended_gift_amount' => round($totalGifted, 2),
                'total_gifted' => round($totalGifted, 2),
                'gift_schedule' => $this->buildPETSchedule($amountPerCycle, $complete7YearCycles),
                'iht_saved' => round($ihtSaved, 2),
                'risk_level' => 'Medium',
                'implementation_steps' => [
                    'Gift £'.number_format($amountPerCycle, 0).' every 7 years from liquid assets',
                    'You have '.$complete7YearCycles.' complete 7-year cycle(s) before expected life expectancy',
                    'Gifts become fully exempt after 7 years',
                    'Taper relief applies from year 3 if you die within 7 years',
                    'Consider Capital Gains Tax (CGT) on investments before gifting',
                    'ISAs lose tax-free status when gifted',
                    'Specific assets to consider: '.$assetNames,
                ],
                'asset_details' => $liquidAssets['assets'],
            ];
        } else {
            // Not enough time for PET - immediate gift with risk
            $ihtSaved = $targetGiftAmount * $ihtRate;

            return [
                'strategy_name' => 'Immediate Gift of Liquid Assets',
                'priority' => 2,
                'category' => 'liquid_assets',
                'description' => 'Gift liquid assets now (becomes PET - may not become fully exempt)',
                'total_available' => round($liquidAssets['total_value'], 2),
                'recommended_gift_amount' => round($targetGiftAmount, 2),
                'total_gifted' => round($targetGiftAmount, 2),
                'iht_saved' => round($ihtSaved, 2),
                'risk_level' => 'High',
                'implementation_steps' => [
                    'Gift £'.number_format($targetGiftAmount, 0).' immediately from liquid assets',
                    'WARNING: May not survive 7 years for full exemption',
                    'Taper relief may apply if you survive 3+ years',
                    'Consider gifting smaller amounts over time instead',
                ],
                'asset_details' => $liquidAssets['assets'],
            ];
        }
    }

    /**
     * Build property gifting strategy
     */
    private function buildPropertyGiftingStrategy(
        array $semiLiquidAssets,
        float $remainingIHTLiability,
        float $ihtRate
    ): ?array {
        if ($semiLiquidAssets['count'] === 0 || $remainingIHTLiability <= 0) {
            return null;
        }

        $propertyAssets = array_filter($semiLiquidAssets['assets'], fn ($a) => ($a['type'] ?? null) === 'property');

        if (empty($propertyAssets)) {
            return null;
        }

        $totalPropertyValue = array_sum(array_column($propertyAssets, 'value'));
        $targetGiftAmount = min($remainingIHTLiability / $ihtRate, $totalPropertyValue);
        $ihtSaved = $targetGiftAmount * $ihtRate;

        $propertyNames = implode(', ', array_column($propertyAssets, 'name'));

        return [
            'strategy_name' => 'Gift Rental/Second Properties',
            'priority' => 3,
            'category' => 'property',
            'description' => 'Gift rental or second properties (not main residence)',
            'available_properties' => $propertyNames,
            'total_property_value' => round($totalPropertyValue, 2),
            'recommended_gift_amount' => round($targetGiftAmount, 2),
            'total_gifted' => round($targetGiftAmount, 2),
            'iht_saved' => round($ihtSaved, 2),
            'risk_level' => 'Medium',
            'implementation_steps' => [
                'Properties can be gifted outright or in stages (e.g., 10% per year)',
                'Consider Capital Gains Tax (CGT) on disposal (24% for residential property)',
                'Stamp Duty Land Tax (SDLT) may apply to recipient',
                'Gift becomes PET - exempt after 7 years',
                'Consider setting up a trust for flexibility',
                'Rental income should continue to recipient after gift',
                'Professional legal and tax advice essential',
                'Properties available: '.$propertyNames,
            ],
            'tax_considerations' => [
                'cgt_rate' => '24% on gains (residential property)',
                'sdlt' => 'May apply to recipient (varies by property value)',
                'iht_treatment' => 'PET - exempt after 7 years',
            ],
            'property_details' => $propertyAssets,
        ];
    }

    /**
     * Build main residence strategy (downsizing)
     */
    private function buildMainResidenceStrategy(array $illiquidAssets, User $user): ?array
    {
        $mainResidenceAssets = array_filter($illiquidAssets['assets'], fn ($a) => ! $a['is_giftable']);

        if (empty($mainResidenceAssets)) {
            return null;
        }

        $mainResidence = $mainResidenceAssets[0]; // Assume first is main residence

        return [
            'strategy_name' => 'Main Residence Strategy - Downsize to Release Equity',
            'priority' => 4,
            'category' => 'main_residence',
            'description' => 'Your main residence cannot be gifted while you live in it - consider downsizing',
            'main_residence' => $mainResidence['asset_name'],
            'current_value' => round($mainResidence['current_value'], 2),
            'total_gifted' => 0, // Not directly gifted
            'iht_saved' => 0, // Variable depending on downsizing
            'risk_level' => 'Low',
            'implementation_steps' => [
                'Cannot gift main residence while living in it (gift with reservation of benefit)',
                'Strategy: Downsize once dependants leave home',
                'Example: Sell £'.number_format($mainResidence['current_value'], 0).' home, buy £'.number_format($mainResidence['current_value'] * 0.6, 0).' property',
                'Released equity (£'.number_format($mainResidence['current_value'] * 0.4, 0).') can then be gifted',
                'Downsizing benefits: Lower running costs, easier maintenance, equity for gifting',
                'Timing: Consider when children leave home or retirement',
            ],
            'alternative_strategies' => [
                'Move out and pay market rent to continue living there (complex, requires professional advice)',
                'Leave via will to take advantage of Residence Nil Rate Band (£'.number_format((float) $this->taxConfig->getInheritanceTax()['residence_nil_rate_band'], 0).')',
                'Consider equity release schemes (but reduces estate value for beneficiaries)',
            ],
            'not_giftable_reason' => $mainResidence['not_giftable_reason'],
        ];
    }

    /**
     * Build gifting from income strategy
     */
    private function buildGiftingFromIncomeStrategy(User $user, int $years, float $ihtRate): ?array
    {
        // Cast all database values to float to prevent type errors
        $totalIncome = (float) ($user->annual_employment_income ?? 0) +
                       (float) ($user->annual_self_employment_income ?? 0) +
                       (float) ($user->annual_rental_income ?? 0) +
                       (float) ($user->annual_dividend_income ?? 0) +
                       (float) ($user->annual_other_income ?? 0);

        $annualExpenditure = (float) ($user->annual_expenditure ?? 0);

        if ($totalIncome <= 0 || $annualExpenditure <= 0) {
            return null;
        }

        $surplusIncome = max(0, $totalIncome - $annualExpenditure);
        $safeGiftingAmount = $surplusIncome * 0.5; // Conservative 50% of surplus

        $canAfford = $surplusIncome > 0 && $safeGiftingAmount >= 1000;

        if (! $canAfford) {
            return null;
        }

        $totalGifted = $safeGiftingAmount * $years;
        $ihtSaved = $totalGifted * $ihtRate;

        return [
            'strategy_name' => 'Normal Expenditure Out of Income',
            'priority' => 5,
            'category' => 'income',
            'description' => 'Make regular gifts from surplus income (unlimited and immediately exempt)',
            'can_afford' => $canAfford,
            'total_income' => round($totalIncome, 2),
            'annual_expenditure' => round($annualExpenditure, 2),
            'surplus_income' => round($surplusIncome, 2),
            'annual_amount' => round($safeGiftingAmount, 2),
            'total_gifted' => round($totalGifted, 2),
            'years' => $years,
            'iht_saved' => round($ihtSaved, 2),
            'risk_level' => 'Low',
            'implementation_steps' => [
                'Set up regular standing order for £'.number_format($safeGiftingAmount, 0).' per year',
                'Must be made from income, not capital',
                'Must not affect your standard of living',
                'Immediately exempt - no 7-year wait',
                'Keep detailed records showing regularity (3+ years of pattern)',
                'Document income sources and expenditure',
                'Your surplus income: £'.number_format($surplusIncome, 0).' per year',
            ],
        ];
    }

    /**
     * Build PET schedule for 7-year cycles
     */
    private function buildPETSchedule(float $amountPerCycle, float $cycles): array
    {
        $schedule = [];
        $cyclesInt = (int) $cycles;

        for ($i = 0; $i < $cyclesInt; $i++) {
            $schedule[] = [
                'year' => $i * 7,
                'amount' => round($amountPerCycle, 2),
                'becomes_exempt' => ($i * 7) + 7,
            ];
        }

        return $schedule;
    }
}
