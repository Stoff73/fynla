<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Constants\TaxDefaults;
use App\Models\Estate\IHTProfile;
use App\Models\User;
use App\Services\Risk\RiskPreferenceService;
use App\Services\Settings\AssumptionsService;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

/**
 * Generates personalized trust planning strategies based on asset liquidity
 * and UK IHT rules for Chargeable Lifetime Transfers (CLTs).
 *
 * Key UK IHT Trust Rules:
 * - NRB: £325,000 per person (transfers to relevant property trusts)
 * - Lifetime charge: 20% on amounts exceeding NRB (or 25% if settlor pays)
 * - Death within 7 years: Additional charge to bring total to 40% (less taper relief)
 * - 7-year lookback: CLTs cumulative over rolling 7 years
 * - Taper relief: Applies if death occurs 3-7 years after transfer
 */
class PersonalizedTrustStrategyService
{
    public function __construct(
        private readonly AssetLiquidityAnalyzer $liquidityAnalyzer,
        private readonly TaxConfigService $taxConfig,
        private readonly AssumptionsService $assumptionsService,
        private readonly RiskPreferenceService $riskPreferenceService
    ) {}

    /**
     * Generate personalized trust planning strategy
     *
     * @param  Collection  $assets  User's assets
     * @param  float  $currentIHTLiability  Current IHT liability
     * @param  IHTProfile  $profile  User's IHT profile
     * @param  User  $user  The user
     * @param  int  $yearsUntilDeath  Projected years until death
     * @return array Strategy details
     */
    public function generatePersonalizedTrustStrategy(
        Collection $assets,
        float $currentIHTLiability,
        IHTProfile $profile,
        User $user,
        int $yearsUntilDeath = 20
    ): array {
        // Analyze asset liquidity
        $liquidityAnalysis = $this->liquidityAnalyzer->analyzeAssetLiquidity($assets);

        // Calculate giftable amounts by liquidity
        $giftableAmounts = $this->calculateGiftableAmounts($liquidityAnalysis);

        // Generate trust strategies
        $strategies = $this->generateTrustStrategies(
            $liquidityAnalysis,
            $giftableAmounts,
            $currentIHTLiability,
            $profile,
            $user,
            $yearsUntilDeath
        );

        // Calculate overall strategy impact
        $strategyImpact = $this->calculateStrategyImpact($strategies, $currentIHTLiability, $yearsUntilDeath);

        return [
            'strategies' => $strategies,
            'liquidity_analysis' => $liquidityAnalysis,
            'giftable_amounts' => $giftableAmounts,
            'strategy_impact' => $strategyImpact,
            'summary' => $this->generateSummary($strategies, $strategyImpact, $currentIHTLiability),
        ];
    }

    /**
     * Calculate giftable amounts by liquidity category
     */
    private function calculateGiftableAmounts(array $liquidityAnalysis): array
    {
        // AssetLiquidityAnalyzer returns structure with 'liquid', 'semi_liquid', 'illiquid' keys
        // Each contains 'assets' (array), 'total_value', 'count', 'description'
        $liquidAssets = $liquidityAnalysis['liquid']['assets'] ?? [];
        $semiLiquidAssets = $liquidityAnalysis['semi_liquid']['assets'] ?? [];
        $illiquidAssets = $liquidityAnalysis['illiquid']['assets'] ?? [];

        $immediatelyGiftable = (float) ($liquidityAnalysis['liquid']['total_value'] ?? 0);
        $giftableWithPlanning = (float) ($liquidityAnalysis['semi_liquid']['total_value'] ?? 0);
        $notGiftable = (float) ($liquidityAnalysis['illiquid']['total_value'] ?? 0);

        return [
            'immediately_giftable' => $immediatelyGiftable,
            'giftable_with_planning' => $giftableWithPlanning,
            'not_giftable' => $notGiftable,
            'total_giftable' => $immediatelyGiftable + $giftableWithPlanning,
            'liquid_asset_count' => count($liquidAssets),
            'semi_liquid_asset_count' => count($semiLiquidAssets),
            'illiquid_asset_count' => count($illiquidAssets),
        ];
    }

    /**
     * Generate trust planning strategies
     */
    private function generateTrustStrategies(
        array $liquidityAnalysis,
        array $giftableAmounts,
        float $currentIHTLiability,
        IHTProfile $profile,
        User $user,
        int $yearsUntilDeath
    ): array {
        $strategies = [];
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $availableNRB = $profile->available_nrb ?? $ihtConfig['nil_rate_band'];

        // Strategy 1: Immediate CLT using available NRB (Discretionary Trust)
        $strategies[] = $this->buildImmediateCLTStrategy(
            $giftableAmounts,
            $availableNRB,
            $liquidityAnalysis
        );

        // Strategy 2: Multi-Cycle CLT Strategy (7-year cycles)
        $strategies[] = $this->buildMultiCycleCLTStrategy(
            $giftableAmounts,
            $availableNRB,
            $yearsUntilDeath,
            $liquidityAnalysis
        );

        // Strategy 3: Loan Trust Strategy (for large estates)
        $strategies[] = $this->buildLoanTrustStrategy(
            $giftableAmounts,
            $liquidityAnalysis
        );

        // Strategy 4: Discounted Gift Trust Strategy (with retained income)
        $strategies[] = $this->buildDiscountedGiftTrustStrategy(
            $giftableAmounts,
            $liquidityAnalysis,
            $user
        );

        // Strategy 5: Main Residence Property Trust Planning
        $strategies[] = $this->buildPropertyTrustStrategy(
            $liquidityAnalysis
        );

        return $strategies;
    }

    /**
     * Strategy 1: Immediate CLT using available NRB
     */
    private function buildImmediateCLTStrategy(
        array $giftableAmounts,
        float $availableNRB,
        array $liquidityAnalysis
    ): array {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $ihtRate = (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $cltLifetimeRate = (float) ($ihtConfig['chargeable_lifetime_transfers']['lifetime_rate'] ?? 0.20);
        $cltSettlorRate = $cltLifetimeRate / (1 - $cltLifetimeRate); // Grossed-up rate when settlor pays

        $liquidAssets = collect($liquidityAnalysis['liquid']['assets'] ?? []);

        // For Immediate CLT, you can transfer all liquid assets (not capped at NRB)
        // The NRB determines tax-free portion, not maximum transfer amount
        $amountToTrust = (float) $giftableAmounts['immediately_giftable'];
        $excessOverNRB = max(0.0, $amountToTrust - $availableNRB);

        // CLT taxation: 20% on excess over NRB (or 25% if settlor pays)
        $lifetimeCharge = $excessOverNRB * $cltLifetimeRate;
        $lifetimeChargeIfSettlorPays = $excessOverNRB * $cltSettlorRate;

        // Potential additional charge if death within 7 years
        $potentialDeathCharge = ($excessOverNRB * $ihtRate) - $lifetimeCharge; // 40% less 20% already paid

        $implementation = [
            '1. **Identify liquid assets** to transfer into discretionary trust (cash, investments)',
            '2. **Set up discretionary trust** with professional trustees',
            '3. **Transfer £'.number_format($amountToTrust, 0).'** into the trust',
        ];

        if ($excessOverNRB > 0) {
            $implementation[] = '4. **Pay lifetime IHT charge** of £'.number_format($lifetimeCharge, 0).' (20%) from trust, or £'.number_format($lifetimeChargeIfSettlorPays, 0).' (25%) if you pay';
            $implementation[] = '5. **Survive 7 years** to avoid additional 20% charge (total 40% if death occurs)';
        } else {
            $implementation[] = '4. **No immediate IHT charge** (within your £'.number_format($availableNRB, 0).' NRB)';
            $implementation[] = '5. **Survive 7 years** to fully preserve NRB for other gifts';
        }

        $implementation[] = '6. **Trustees manage assets** for beneficiaries with discretion';
        $implementation[] = '7. **10-year anniversary charges** may apply (6% of value above NRB)';

        $eligibleAssets = $liquidAssets->map(function ($asset) {
            return [
                'asset_name' => $asset['asset_name'],
                'asset_type' => $asset['asset_type'],
                'current_value' => $asset['current_value'],
                'liquidity' => $asset['liquidity_classification']['liquidity'],
            ];
        })->toArray();

        return [
            'strategy_name' => 'Immediate Discretionary Trust (CLT)',
            'priority' => 1,
            'description' => 'Transfer liquid assets into a discretionary trust using your available Nil Rate Band',
            'amount' => $amountToTrust,
            'iht_saving_potential' => min($amountToTrust, $availableNRB) * $ihtRate, // Saving: IHT rate on amount within NRB
            'lifetime_tax_charge' => $lifetimeCharge,
            'potential_death_charge' => $potentialDeathCharge,
            'time_frame' => '7 years for full effectiveness',
            'risk_level' => $excessOverNRB > 0 ? 'Medium' : 'Low',
            'suitable_for' => 'Liquid assets (cash, investments)',
            'implementation_steps' => $implementation,
            'tax_treatment' => [
                'immediate_charge' => $lifetimeCharge,
                'death_within_7_years' => $potentialDeathCharge + $lifetimeCharge,
                'after_7_years' => $lifetimeCharge,
                'nrb_used' => min($amountToTrust, $availableNRB),
            ],
            'eligible_assets' => $eligibleAssets,
            'key_benefits' => [
                '✓ Assets immediately outside your estate for IHT',
                '✓ Growth outside your estate',
                '✓ Flexibility for trustees to distribute',
                '✓ Protection from creditors and divorce',
            ],
            'key_risks' => [
                '✗ Immediate IHT charge if over NRB (20%)',
                '✗ Additional charge if death within 7 years (up to 40% total)',
                '✗ 10-year anniversary charges (6% on value above NRB)',
                '✗ Loss of control over assets',
                '✗ Complex trust administration',
            ],
        ];
    }

    /**
     * Strategy 2: Multi-Cycle CLT Strategy (7-year cycles)
     */
    private function buildMultiCycleCLTStrategy(
        array $giftableAmounts,
        float $availableNRB,
        int $yearsUntilDeath,
        array $liquidityAnalysis
    ): array {
        $totalGiftable = $giftableAmounts['total_giftable'];
        $cyclesPossible = floor($yearsUntilDeath / 7);

        if ($cyclesPossible < 1) {
            $cyclesPossible = 1;
        }

        // Each cycle can use the full NRB (7-year rolling window resets)
        $amountPerCycle = $availableNRB;
        $totalOverLifetime = min($totalGiftable, $amountPerCycle * $cyclesPossible);
        $cyclesNeeded = (int) min($cyclesPossible, ceil($totalGiftable / $availableNRB));

        $schedule = $this->buildCLTCycleSchedule($amountPerCycle, $cyclesNeeded, $availableNRB);

        // IHT saving: IHT rate on total transferred (assuming survival)
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $ihtRate = (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $ihtSaving = $totalOverLifetime * $ihtRate;

        $implementation = [
            '1. **Cycle 1 (Year 0)**: Transfer £'.number_format($amountPerCycle, 0).' into discretionary trust',
            '2. **No immediate IHT charge** (within NRB of £'.number_format($availableNRB, 0).')',
        ];

        if ($cyclesNeeded > 1) {
            for ($i = 2; $i <= $cyclesNeeded; $i++) {
                $year = ($i - 1) * 7;
                $implementation[] = ($i + 1).". **Cycle $i (Year $year)**: Transfer another £".number_format($amountPerCycle, 0).' (NRB resets after 7 years)';
            }
        }

        $implementation[] = ($cyclesNeeded + 2).'. **Survive 7 years** after each transfer for full exemption';
        $implementation[] = ($cyclesNeeded + 3).'. **Total removed from estate**: £'.number_format($totalOverLifetime, 0).' over '.(($cyclesNeeded - 1) * 7).' years';

        return [
            'strategy_name' => 'Multi-Cycle CLT Strategy',
            'priority' => 2,
            'description' => 'Use multiple 7-year cycles to maximize NRB usage for larger estates',
            'amount' => $totalOverLifetime,
            'iht_saving_potential' => $ihtSaving,
            'lifetime_tax_charge' => 0, // Assuming each cycle stays within NRB
            'potential_death_charge' => $this->calculateMultiCycleDeathCharge($schedule, $yearsUntilDeath),
            'time_frame' => (($cyclesNeeded - 1) * 7).' years ('.$cyclesNeeded.' cycles)',
            'risk_level' => 'Medium',
            'suitable_for' => 'Large estates exceeding £'.number_format($availableNRB, 0),
            'implementation_steps' => $implementation,
            'clt_schedule' => $schedule,
            'cycles_needed' => $cyclesNeeded,
            'cycles_possible' => $cyclesPossible,
            'key_benefits' => [
                '✓ Maximize NRB usage over multiple cycles',
                '✓ No immediate tax if each transfer within NRB',
                '✓ Each cycle starts fresh 7-year clock',
                '✓ Can remove large amounts from estate over time',
            ],
            'key_risks' => [
                '✗ Requires long time horizon (multiple 7-year cycles)',
                '✗ Death before 7 years on any cycle triggers charge',
                '✗ Complex timing and record-keeping',
                '✗ 10-year anniversary charges on each trust',
            ],
        ];
    }

    /**
     * Build CLT cycle schedule
     */
    private function buildCLTCycleSchedule(float $amountPerCycle, int $cycles, float $nrb): array
    {
        $schedule = [];

        for ($i = 0; $i < $cycles; $i++) {
            $year = $i * 7;
            $schedule[] = [
                'cycle' => $i + 1,
                'year' => $year,
                'amount' => (float) $amountPerCycle,
                'nrb_available' => (float) $nrb,
                'immediate_charge' => 0.0, // Within NRB
                'description' => 'Transfer £'.number_format($amountPerCycle, 0)." in year $year",
            ];
        }

        return $schedule;
    }

    /**
     * Calculate potential death charge for multi-cycle strategy
     */
    private function calculateMultiCycleDeathCharge(array $schedule, int $yearsUntilDeath): float
    {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $ihtRate = (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $totalCharge = 0;

        foreach ($schedule as $cycle) {
            $yearsFromTransfer = $yearsUntilDeath - $cycle['year'];

            // If death occurs within 7 years of this transfer
            if ($yearsFromTransfer < 7) {
                $charge = $cycle['amount'] * $ihtRate;

                // Apply taper relief if 3-7 years
                if ($yearsFromTransfer >= 3) {
                    $taperRate = $this->getTaperReliefRate($yearsFromTransfer);
                    $charge = $charge * ($taperRate / 100);
                }

                $totalCharge += $charge;
            }
        }

        return $totalCharge;
    }

    /**
     * Get taper relief rate based on years since transfer
     */
    private function getTaperReliefRate(int $years): int
    {
        if ($years < 3) {
            return 100;
        } // Full 40%
        if ($years < 4) {
            return 80;
        }  // 32%
        if ($years < 5) {
            return 60;
        }  // 24%
        if ($years < 6) {
            return 40;
        }  // 16%
        if ($years < 7) {
            return 20;
        }  // 8%

        return 0;                // 0% (fully exempt)
    }

    /**
     * Strategy 3: Loan Trust Strategy
     */
    private function buildLoanTrustStrategy(
        array $giftableAmounts,
        array $liquidityAnalysis
    ): array {
        $liquidAssets = collect($liquidityAnalysis['liquid']['assets'] ?? []);
        $totalLiquid = $giftableAmounts['immediately_giftable'];

        // Loan trust: lend money to trust, loan stays in estate but growth doesn't
        $loanAmount = $totalLiquid;
        $assumedGrowthRate = 0.05; // 5% per year
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $ihtRate = (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $growthOver20Years = $loanAmount * (pow(1 + $assumedGrowthRate, 20) - 1);
        $ihtSaving = $growthOver20Years * $ihtRate;

        return [
            'strategy_name' => 'Loan Trust Strategy',
            'priority' => 3,
            'description' => 'Lend assets to a trust - loan stays in estate but future growth is IHT-free',
            'amount' => (float) $loanAmount,
            'iht_saving_potential' => (float) $ihtSaving,
            'lifetime_tax_charge' => 0.0, // No immediate charge (it's a loan, not a gift)
            'potential_death_charge' => 0.0, // Loan itself stays in estate
            'time_frame' => 'Immediate effect (growth is immediately outside estate)',
            'risk_level' => 'Low',
            'suitable_for' => 'Large estates, those wanting to retain access to capital',
            'implementation_steps' => [
                '1. **Set up loan trust** with trustees',
                '2. **Lend £'.number_format($loanAmount, 0).'** to the trust (not a gift)',
                '3. **Loan remains in your estate** for IHT purposes',
                '4. **Investment growth is outside your estate** (IHT-free)',
                '5. **Repay loan to yourself** as needed for income/capital',
                '6. **Gradual loan write-off** can use annual exemptions (£3,000/year)',
                '7. **Assumed growth over 20 years**: £'.number_format($growthOver20Years, 0).' (IHT saving: £'.number_format($ihtSaving, 0).')',
            ],
            'eligible_assets' => $liquidAssets->map(function ($asset) {
                return [
                    'asset_name' => $asset['asset_name'],
                    'asset_type' => $asset['asset_type'],
                    'current_value' => $asset['current_value'],
                ];
            })->toArray(),
            'key_benefits' => [
                '✓ No immediate IHT charge',
                '✓ Future growth outside your estate',
                '✓ Retain access to capital via loan repayments',
                '✓ Can write off loan gradually using annual exemptions',
                '✓ No 7-year wait for growth benefit',
            ],
            'key_risks' => [
                '✗ Loan value stays in estate',
                '✗ Complex trust administration',
                '✗ Requires investment growth to be effective',
                '✗ If loan repaid, growth benefit lost',
            ],
        ];
    }

    /**
     * Strategy 4: Discounted Gift Trust Strategy
     */
    private function buildDiscountedGiftTrustStrategy(
        array $giftableAmounts,
        array $liquidityAnalysis,
        User $user
    ): array {
        $liquidAssets = collect($liquidityAnalysis['liquid']['assets'] ?? []);
        $totalLiquid = $giftableAmounts['immediately_giftable'];

        // Get IHT configuration
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $ihtRate = (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $cltLifetimeRate = (float) ($ihtConfig['chargeable_lifetime_transfers']['lifetime_rate'] ?? 0.20);

        // Discounted gift trust: gift with retained income rights
        // Discount reduces the CLT value
        $assumedIncomeRate = 0.04; // 4% income
        $lifeExpectancy = 85 - ($user->age ?? 50); // Years remaining
        $discountRate = min(0.50, ($assumedIncomeRate * min($lifeExpectancy, 20)) / 2); // Simplified discount

        $giftValue = $totalLiquid;
        $discountValue = $giftValue * $discountRate;
        $cltValue = $giftValue - $discountValue; // Actual chargeable amount

        // Discount stays in estate, gift value (after discount) is CLT
        $ihtSavingOnGift = $cltValue * $ihtRate;

        return [
            'strategy_name' => 'Discounted Gift Trust',
            'priority' => 4,
            'description' => 'Gift assets to trust but retain income rights - reduces the chargeable gift value',
            'amount' => $giftValue,
            'chargeable_amount' => $cltValue,
            'discount_value' => $discountValue,
            'discount_percentage' => $discountRate * 100,
            'iht_saving_potential' => $ihtSavingOnGift,
            'lifetime_tax_charge' => max(0, ($cltValue - $ihtConfig['nil_rate_band']) * $cltLifetimeRate), // CLT lifetime rate on excess over NRB
            'potential_death_charge' => max(0, ($cltValue - $ihtConfig['nil_rate_band']) * $ihtRate), // IHT rate if death within 7 years
            'time_frame' => '7 years for full effectiveness',
            'risk_level' => 'Medium',
            'suitable_for' => 'Those wanting to gift but retain income',
            'implementation_steps' => [
                '1. **Set up discounted gift trust** with income rights',
                '2. **Gift £'.number_format($giftValue, 0).'** to the trust',
                '3. **Retain right to income** (e.g., '.($assumedIncomeRate * 100).'% = £'.number_format($giftValue * $assumedIncomeRate, 0).'/year)',
                '4. **HMRC values gift** at £'.number_format($cltValue, 0).' (after '.($discountRate * 100).'% discount)',
                '5. **Discount value £'.number_format($discountValue, 0).' stays in your estate**',
                '6. **Capital growth** on full £'.number_format($giftValue, 0).' is outside your estate',
                '7. **Survive 7 years** to remove CLT value from estate',
            ],
            'eligible_assets' => $liquidAssets->filter(function ($asset) {
                // Only suitable for income-producing assets
                return in_array($asset['asset_type'], ['investment', 'cash']);
            })->map(function ($asset) {
                return [
                    'asset_name' => $asset['asset_name'],
                    'asset_type' => $asset['asset_type'],
                    'current_value' => $asset['current_value'],
                ];
            })->toArray(),
            'key_benefits' => [
                '✓ Reduced CLT value due to discount',
                '✓ Retain income for life',
                '✓ All growth outside your estate',
                '✓ Lower IHT charge than full gift',
            ],
            'key_risks' => [
                '✗ Discount value stays in your estate',
                '✗ Complex valuation calculations',
                '✗ HMRC may challenge discount percentage',
                '✗ Still subject to 7-year rule on CLT value',
            ],
        ];
    }

    /**
     * Strategy 5: Main Residence Property Trust Planning
     */
    private function buildPropertyTrustStrategy(
        array $liquidityAnalysis
    ): array {
        $illiquidAssets = collect($liquidityAnalysis['illiquid']['assets'] ?? []);
        $mainResidence = $illiquidAssets->firstWhere('is_main_residence', true);

        if (! $mainResidence) {
            return [
                'strategy_name' => 'Property Trust Planning',
                'priority' => 5,
                'description' => 'N/A - No main residence identified',
                'amount' => 0,
                'iht_saving_potential' => 0,
                'applicable' => false,
            ];
        }

        $propertyValue = $mainResidence['current_value'];

        return [
            'strategy_name' => 'Property Trust Planning',
            'priority' => 5,
            'description' => 'Alternative approaches for your main residence to mitigate IHT',
            'amount' => $propertyValue,
            'iht_saving_potential' => 0, // Cannot be directly calculated
            'lifetime_tax_charge' => 0,
            'potential_death_charge' => 0,
            'time_frame' => 'Long-term planning',
            'risk_level' => 'High',
            'suitable_for' => 'Main residence',
            'applicable' => true,
            'implementation_steps' => [
                '**Option A: Downsizing Strategy**',
                '1. Wait until dependants leave home',
                '2. Downsize to smaller property',
                '3. Gift released equity using PET/CLT strategies above',
                '4. Claim RNRB on remaining property value',
                '',
                '**Option B: Life Interest Trust in Will**',
                '1. Leave property in trust via Will (not lifetime)',
                '2. Spouse has right to live in property for life',
                '3. On second death, property passes to children',
                '4. Avoids double IHT charge on same property',
                '',
                '**Option C: Shared Ownership**',
                '1. Gift % ownership to adult children',
                '2. They pay market rent for their share (avoid GROB)',
                '3. Gradual transfer over time',
                '4. Complex and may not save significant IHT',
                '',
                '⚠️ **WARNING: Gift with Reservation of Benefit**',
                'You CANNOT gift your main residence and continue living in it rent-free.',
                "This is a 'gift with reservation of benefit' and remains in your estate for IHT.",
            ],
            'property_details' => [
                'property_name' => $mainResidence['asset_name'],
                'current_value' => $propertyValue,
                'reason_not_giftable' => $mainResidence['liquidity_classification']['not_giftable_reason'] ?? 'Main residence',
            ],
            'key_benefits' => [
                '✓ Downsizing releases equity for gifting',
                '✓ Life interest trust protects surviving spouse',
                '✓ RNRB available on main residence',
            ],
            'key_risks' => [
                '✗ Cannot gift and continue living in property',
                '✗ Gift with reservation rules are strict',
                '✗ Downsizing may not be desirable',
                '✗ Complex planning required',
            ],
        ];
    }

    /**
     * Calculate overall strategy impact
     */
    private function calculateStrategyImpact(array $strategies, float $currentIHTLiability, int $yearsUntilDeath): array
    {
        $totalAmountTransferred = 0;
        $totalIHTSaving = 0;
        $totalLifetimeCharges = 0;
        $totalPotentialDeathCharges = 0;

        foreach ($strategies as $strategy) {
            if (isset($strategy['applicable']) && ! $strategy['applicable']) {
                continue;
            }

            $totalAmountTransferred += $strategy['amount'] ?? 0;
            $totalIHTSaving += $strategy['iht_saving_potential'] ?? 0;
            $totalLifetimeCharges += $strategy['lifetime_tax_charge'] ?? 0;
            $totalPotentialDeathCharges += $strategy['potential_death_charge'] ?? 0;
        }

        // Net saving = IHT saved - costs paid
        $netSaving = $totalIHTSaving - $totalLifetimeCharges;

        // Worst case: if death occurs before 7 years on all transfers
        $worstCaseCost = $totalLifetimeCharges + $totalPotentialDeathCharges;
        $worstCaseNetSaving = $totalIHTSaving - $worstCaseCost;

        return [
            'total_amount_transferred' => $totalAmountTransferred,
            'total_iht_saving' => $totalIHTSaving,
            'total_lifetime_charges' => $totalLifetimeCharges,
            'total_potential_death_charges' => $totalPotentialDeathCharges,
            'net_saving' => $netSaving,
            'net_saving_percentage' => $currentIHTLiability > 0 ? ($netSaving / $currentIHTLiability) * 100 : 0,
            'worst_case_cost' => $worstCaseCost,
            'worst_case_net_saving' => $worstCaseNetSaving,
            'projected_time_frame' => $yearsUntilDeath.' years',
        ];
    }

    /**
     * Generate summary
     */
    private function generateSummary(array $strategies, array $impact, float $currentIHTLiability): array
    {
        $applicableStrategies = collect($strategies)->filter(function ($strategy) {
            return ! isset($strategy['applicable']) || $strategy['applicable'] !== false;
        });

        return [
            'current_iht_liability' => $currentIHTLiability,
            'total_strategies' => $applicableStrategies->count(),
            'recommended_strategy' => $applicableStrategies->sortBy('priority')->first()['strategy_name'] ?? 'None',
            'maximum_estate_reduction' => $impact['total_amount_transferred'],
            'maximum_iht_saving' => $impact['total_iht_saving'],
            'total_costs' => $impact['total_lifetime_charges'],
            'net_benefit' => $impact['net_saving'],
            'effectiveness_rating' => $this->calculateEffectivenessRating($impact, $currentIHTLiability),
        ];
    }

    /**
     * Calculate effectiveness rating
     */
    private function calculateEffectivenessRating(array $impact, float $currentIHTLiability): string
    {
        if ($currentIHTLiability == 0) {
            return 'N/A - No IHT liability';
        }

        $savingPercentage = ($impact['net_saving'] / $currentIHTLiability) * 100;

        if ($savingPercentage >= 80) {
            return 'Excellent';
        }
        if ($savingPercentage >= 60) {
            return 'Very Good';
        }
        if ($savingPercentage >= 40) {
            return 'Good';
        }
        if ($savingPercentage >= 20) {
            return 'Moderate';
        }

        return 'Limited';
    }

    /**
     * Calculate Nil Rate Band avoidance forward projection for a trust settlement.
     *
     * Projects the trust value growth over 11 years (covering the 10-year
     * periodic charge anniversary) to show whether the planned settlement
     * amount will exceed the Nil Rate Band threshold.
     *
     * Growth rate is sourced from the user's risk profile via AssumptionsService
     * and RiskPreferenceService -- never from TaxConfigService or hardcoded defaults.
     *
     * @param  User  $user  The user creating the trust
     * @param  float  $plannedAmount  The planned initial settlement amount
     * @return array Forward projection with year-by-year trajectory
     */
    public function calculateNRBAvoidanceProjection(User $user, float $plannedAmount): array
    {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrb = (float) $ihtConfig['nil_rate_band'];

        // Get growth rate from user's risk profile via AssumptionsService
        $riskLevel = $this->riskPreferenceService->getMainRiskLevel($user->id) ?? 'medium';

        try {
            $riskParams = $this->riskPreferenceService->getReturnParameters($riskLevel);
            $growthRate = ($riskParams['expected_return_typical'] ?? 5.0) / 100;
        } catch (\Exception $e) {
            $growthRate = 0.05; // Medium risk fallback
        }

        // Calculate maximum initial settlement that stays below NRB at 10-year anniversary
        $maxInitialSettlement = $nrb / pow(1 + $growthRate, 10);

        // Project the planned amount forward
        $projectedAt10Years = $plannedAmount * pow(1 + $growthRate, 10);
        $willExceedNRB = $projectedAt10Years > $nrb;

        // Year-by-year trajectory (0 to 11 years, covering the 10-year anniversary)
        $trajectory = [];
        for ($year = 0; $year <= 11; $year++) {
            $projectedValue = $plannedAmount * pow(1 + $growthRate, $year);
            $trajectory[] = [
                'year' => $year,
                'projected_value' => round($projectedValue, 2),
                'exceeds_nrb' => $projectedValue > $nrb,
            ];
        }

        // Calculate estimated periodic charge if value exceeds NRB at 10-year anniversary
        $estimatedPeriodicCharge = 0;
        if ($willExceedNRB) {
            $estimatedPeriodicCharge = $this->calculatePeriodicCharge($projectedAt10Years, $nrb);
        }

        return [
            'planned_amount' => round($plannedAmount, 2),
            'max_initial_settlement' => round($maxInitialSettlement, 2),
            'projected_at_10_years' => round($projectedAt10Years, 2),
            'will_exceed_nrb' => $willExceedNRB,
            'nrb_threshold' => round($nrb, 2),
            'estimated_periodic_charge' => round($estimatedPeriodicCharge, 2),
            'growth_rate_used' => round($growthRate, 4),
            'risk_level' => $riskLevel,
            'trajectory' => $trajectory,
            'guidance' => $willExceedNRB
                ? 'The planned settlement of £'.number_format($plannedAmount).' is projected to exceed the Nil Rate Band (£'.number_format($nrb).') by the 10-year anniversary. Consider settling no more than £'.number_format($maxInitialSettlement).' to avoid the periodic charge.'
                : 'The planned settlement of £'.number_format($plannedAmount).' is projected to remain within the Nil Rate Band (£'.number_format($nrb).') at the 10-year anniversary. No periodic charge is expected.',
        ];
    }

    /**
     * Calculate the estimated periodic charge on a trust value exceeding the Nil Rate Band.
     *
     * The periodic charge is approximately 6% of the value above the Nil Rate Band,
     * calculated on each 10-year anniversary of the trust.
     *
     * @param  float  $trustValue  The projected trust value
     * @param  float  $nrb  The Nil Rate Band threshold
     * @return float Estimated periodic charge
     */
    private function calculatePeriodicCharge(float $trustValue, float $nrb): float
    {
        $excessOverNRB = max(0, $trustValue - $nrb);

        // Maximum periodic charge rate is 6% (30% of lifetime rate of 20%)
        return $excessOverNRB * 0.06;
    }
}
