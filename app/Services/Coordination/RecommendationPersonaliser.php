<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\Constants\TaxDefaults;
use App\Models\User;
use App\Services\TaxConfigService;
use App\Traits\FormatsCurrency;

/**
 * Enriches raw recommendations with personalised context based on user circumstances.
 *
 * Adds a 'personalised_context' field to recommendations without modifying the original text.
 * Context is generated for Protection, Estate, and Investment modules.
 */
class RecommendationPersonaliser
{
    use FormatsCurrency;

    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Personalise a single recommendation by adding contextual information.
     *
     * @param  array  $recommendation  Raw recommendation array
     * @param  User  $user  The user to personalise for
     * @return array Recommendation with 'personalised_context' added when applicable
     */
    public function personaliseRecommendation(array $recommendation, User $user): array
    {
        $context = [];

        $category = $recommendation['category'] ?? '';
        $module = $recommendation['module'] ?? $this->inferModule($category, $recommendation);

        $context = match ($module) {
            'protection' => $this->personaliseProtection($recommendation, $user),
            'estate' => $this->personaliseEstate($recommendation, $user),
            'investment' => $this->personaliseInvestment($recommendation, $user),
            default => [],
        };

        if (! empty($context)) {
            $recommendation['personalised_context'] = $context;
        }

        return $recommendation;
    }

    /**
     * Personalise a batch of recommendations.
     *
     * @param  array  $recommendations  Array of recommendation arrays
     * @param  User  $user  The user to personalise for
     * @return array Personalised recommendations
     */
    public function personaliseRecommendations(array $recommendations, User $user): array
    {
        return array_map(
            fn (array $rec) => $this->personaliseRecommendation($rec, $user),
            $recommendations
        );
    }

    /**
     * Generate personalised context for protection recommendations.
     */
    private function personaliseProtection(array $recommendation, User $user): array
    {
        $context = [];
        $category = $recommendation['category'] ?? '';
        $action = strtolower($recommendation['action'] ?? '');

        // Load family data
        $children = $user->familyMembers()
            ->where('relationship', 'child')
            ->whereNotNull('date_of_birth')
            ->get();

        $spouse = $user->spouse;

        // Life insurance / life cover recommendations
        if ($this->isLifeCoverRecommendation($category, $action)) {
            $context = array_merge($context, $this->buildFamilyContext($children, $user));

            if ($spouse) {
                $context = array_merge($context, $this->buildPartnerIncomeContext($spouse));
            }

            // Check for employer death-in-service benefit via DC pensions
            $deathInServiceContext = $this->buildDeathInServiceContext($user);
            if (! empty($deathInServiceContext)) {
                $context = array_merge($context, $deathInServiceContext);
            }
        }

        // Income protection recommendations
        if ($this->isIncomeProtectionRecommendation($category, $action)) {
            $context = array_merge($context, $this->buildEmploymentContext($user));
        }

        // Critical illness recommendations
        if ($category === 'Critical Illness') {
            if ($children->isNotEmpty()) {
                $context[] = sprintf(
                    'With %d %s depending on you, a critical illness diagnosis could significantly impact your family\'s financial stability.',
                    $children->count(),
                    $children->count() === 1 ? 'child' : 'children'
                );
            }
        }

        return $context;
    }

    /**
     * Generate personalised context for estate recommendations.
     */
    private function personaliseEstate(array $recommendation, User $user): array
    {
        $context = [];
        $category = $recommendation['category'] ?? '';
        $title = strtolower($recommendation['title'] ?? $recommendation['strategy_name'] ?? '');
        $description = strtolower($recommendation['description'] ?? '');

        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrb = (float) $ihtConfig['nil_rate_band'];
        $rnrb = (float) $ihtConfig['residence_nil_rate_band'];

        $spouse = $user->spouse;
        $children = $user->familyMembers()
            ->where('relationship', 'child')
            ->get();
        $hasMainResidence = $user->properties()
            ->where('property_type', 'main_residence')
            ->exists();

        // IHT-related recommendations
        if ($this->isIHTRecommendation($category, $title, $description)) {
            // Estate value context
            $netEstate = $recommendation['net_estate'] ?? null;
            if ($netEstate !== null && $netEstate > $nrb) {
                $excess = $netEstate - $nrb;
                $context[] = sprintf(
                    'Your estate is currently valued at %s, which is %s above the nil-rate band of %s.',
                    $this->formatCurrency($netEstate),
                    $this->formatCurrency($excess),
                    $this->formatCurrency($nrb)
                );
            }

            // RNRB eligibility for parents with a main residence
            if ($children->isNotEmpty() && $hasMainResidence) {
                $context[] = sprintf(
                    'The residence nil-rate band of %s is available because you are leaving your main residence to direct descendants.',
                    $this->formatCurrency($rnrb)
                );
            }

            // Married couples can combine bands
            if ($spouse) {
                $combinedAllowance = ($nrb + $rnrb) * 2;
                $context[] = sprintf(
                    'You and your spouse can combine nil-rate bands for up to %s tax-free.',
                    $this->formatCurrency($combinedAllowance)
                );
            }
        }

        // Trust recommendations
        if ($this->isTrustRecommendation($category, $title)) {
            $minorChildren = $children->filter(function ($child) {
                return $child->date_of_birth && $child->date_of_birth->diffInYears(now()) < 18;
            });

            if ($minorChildren->isNotEmpty()) {
                $ages = $minorChildren->map(fn ($c) => (int) $c->date_of_birth->diffInYears(now()));
                $context[] = sprintf(
                    'A discretionary trust would protect assets until your %s %s %s.',
                    $minorChildren->count() === 1 ? 'child' : 'children',
                    $minorChildren->count() === 1 ? 'reaches' : 'reach',
                    $minorChildren->count() === 1
                        ? 'adulthood (currently aged '.$ages->first().')'
                        : 'adulthood (currently aged '.$ages->implode(' and ').')'
                );
            }
        }

        // Gifting recommendations
        if ($this->isGiftingRecommendation($category, $title)) {
            if ($spouse) {
                $giftingConfig = $this->taxConfig->getGiftingExemptions();
                $annualExemption = (float) $giftingConfig['annual_exemption'];
                $context[] = sprintf(
                    'Both you and your spouse can each use the %s annual gift exemption, totalling %s per year.',
                    $this->formatCurrency($annualExemption),
                    $this->formatCurrency($annualExemption * 2)
                );
            }

            $currentAge = $user->date_of_birth
                ? (int) $user->date_of_birth->diffInYears(now())
                : null;
            if ($currentAge !== null && $currentAge < 65) {
                $yearsToGift = 85 - $currentAge;
                $context[] = sprintf(
                    'At age %d, you have approximately %d years of gifting opportunity ahead.',
                    $currentAge,
                    $yearsToGift
                );
            }
        }

        return $context;
    }

    /**
     * Generate personalised context for investment recommendations.
     */
    private function personaliseInvestment(array $recommendation, User $user): array
    {
        $context = [];
        $category = $recommendation['category'] ?? '';
        $action = strtolower($recommendation['action'] ?? $recommendation['title'] ?? '');
        $description = strtolower($recommendation['description'] ?? '');

        // Asset allocation recommendations
        if ($this->isAssetAllocationRecommendation($category, $action, $description)) {
            // Property exposure context
            $propertyValue = (float) $user->properties()->sum('current_value');
            if ($propertyValue > 0) {
                $context[] = sprintf(
                    'Your property holdings of %s already provide significant real asset exposure. Consider this when setting your equity and alternatives allocation.',
                    $this->formatCurrency($propertyValue)
                );
            }

            // Employment sector concentration risk
            $employerHoldings = $this->getEmployerHoldings($user);
            if (! empty($employerHoldings)) {
                $context[] = 'You hold investments linked to your employer. This creates concentration risk as both your income and investments are exposed to the same company.';
            }
        }

        // Rebalancing recommendations
        if ($this->isRebalancingRecommendation($category, $action, $description)) {
            // Include specific drift amounts if available
            $driftData = $recommendation['drift_metrics'] ?? $recommendation['allocation_deviation'] ?? null;
            if ($driftData && isset($driftData['drifts_by_asset'])) {
                $significantDrifts = [];
                foreach ($driftData['drifts_by_asset'] as $asset => $drift) {
                    if (abs($drift['drift'] ?? 0) >= 5) {
                        $direction = ($drift['drift'] ?? 0) > 0 ? 'above' : 'below';
                        $significantDrifts[] = sprintf(
                            '%s is %.1f%% %s target (%.1f%% vs %.1f%%)',
                            ucfirst($asset),
                            abs($drift['drift']),
                            $direction,
                            $drift['current'] ?? 0,
                            $drift['target'] ?? 0
                        );
                    }
                }
                if (! empty($significantDrifts)) {
                    $context[] = 'Specific drift details: '.implode('; ', $significantDrifts).'.';
                }
            }
        }

        // ISA / tax wrapper recommendations
        if ($this->isISARecommendation($category, $action, $description)) {
            $isaAllowance = $this->taxConfig->getISAAllowances()['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE;
            $context[] = sprintf(
                'The current ISA allowance is %s per tax year. Contributions are tax-free and sheltered from Capital Gains Tax.',
                $this->formatCurrency($isaAllowance)
            );
        }

        return $context;
    }

    /**
     * Infer the module from recommendation category and content.
     */
    private function inferModule(string $category, array $recommendation): string
    {
        $protectionCategories = ['Life Insurance', 'Critical Illness', 'Income Protection', 'Trust Planning', 'Policy Optimisation'];
        if (in_array($category, $protectionCategories)) {
            return 'protection';
        }

        $estateCategories = ['charitable_bequest', 'liquidity', 'life_cover', 'trust_planning', 'annual_gifting', 'new_life_cover', 'pet_gifting', 'clt_trust', 'will_trust_setup', 'planning'];
        if (in_array($category, $estateCategories)) {
            return 'estate';
        }

        $investmentCategories = ['rebalancing', 'asset_allocation', 'tax_efficiency', 'isa_optimisation', 'fee_reduction', 'diversification'];
        if (in_array($category, $investmentCategories)) {
            return 'investment';
        }

        // Check for estate strategy structure
        if (isset($recommendation['strategy_name']) || isset($recommendation['iht_saved'])) {
            return 'estate';
        }

        // Check for investment structure
        if (isset($recommendation['drift_metrics']) || isset($recommendation['allocation_deviation'])) {
            return 'investment';
        }

        return 'unknown';
    }

    /**
     * Build family composition context for life cover recommendations.
     */
    private function buildFamilyContext(mixed $children, User $user): array
    {
        $context = [];

        if ($children->isEmpty()) {
            return $context;
        }

        $childDetails = $children->map(function ($child) {
            $age = $child->date_of_birth ? (int) $child->date_of_birth->diffInYears(now()) : null;
            $yearsOfSupport = $age !== null ? max(0, 18 - $age) : null;

            return [
                'name' => $child->first_name ?? $child->name ?? 'Child',
                'age' => $age,
                'years_of_support' => $yearsOfSupport,
            ];
        })->filter(fn ($c) => $c['age'] !== null);

        if ($childDetails->isNotEmpty()) {
            $descriptions = $childDetails->map(function ($c) {
                if ($c['years_of_support'] !== null && $c['years_of_support'] > 0) {
                    return sprintf('aged %d (needing support for %d more %s)', $c['age'], $c['years_of_support'], $c['years_of_support'] === 1 ? 'year' : 'years');
                }

                return sprintf('aged %d', $c['age']);
            });

            $context[] = sprintf(
                'You have %d %s %s.',
                $childDetails->count(),
                $childDetails->count() === 1 ? 'child' : 'children',
                $descriptions->implode(' and ')
            );
        }

        return $context;
    }

    /**
     * Build partner income context.
     */
    private function buildPartnerIncomeContext(User $spouse): array
    {
        $context = [];

        $spouseIncome = ($spouse->annual_employment_income ?? 0)
                      + ($spouse->annual_self_employment_income ?? 0);

        if ($spouseIncome > 0) {
            $context[] = sprintf(
                'If your partner continues earning %s per year, this would help offset the financial impact.',
                $this->formatCurrency($spouseIncome)
            );
        }

        return $context;
    }

    /**
     * Build workplace pension context that may indicate employer benefits.
     */
    private function buildDeathInServiceContext(User $user): array
    {
        $context = [];

        $dcPensions = $user->dcPensions ?? collect();
        if ($dcPensions->isEmpty() && method_exists($user, 'dcPensions')) {
            $dcPensions = $user->dcPensions()->get();
        }

        // Workplace pensions with employer contributions suggest employer benefits package
        $workplacePension = $dcPensions->first(fn ($p) => ($p->employer_contribution_percent ?? 0) > 0);
        if ($workplacePension) {
            $context[] = sprintf(
                'Your workplace pension has %s%% employer contributions. Check with your employer whether your benefits package includes death-in-service cover, which would reduce the life cover gap.',
                number_format((float) $workplacePension->employer_contribution_percent, 1)
            );
        }

        return $context;
    }

    /**
     * Build employment type context for income protection.
     */
    private function buildEmploymentContext(User $user): array
    {
        $context = [];

        $selfEmploymentIncome = $user->annual_self_employment_income ?? 0;
        $employmentIncome = $user->annual_employment_income ?? 0;

        if ($selfEmploymentIncome > 0 && (float) $employmentIncome === 0.0) {
            $context[] = 'As a self-employed professional, income protection is particularly important as you have no employer sick pay or statutory sick pay entitlement.';
        } elseif ($selfEmploymentIncome > 0 && $employmentIncome > 0) {
            $context[] = sprintf(
                'You have both employed and self-employed income. Your employed income (%s) may have some employer sick pay coverage, but your self-employed income (%s) has no such protection.',
                $this->formatCurrency($employmentIncome),
                $this->formatCurrency($selfEmploymentIncome)
            );
        }

        return $context;
    }

    /**
     * Get holdings that are linked to the user's employer.
     */
    private function getEmployerHoldings(User $user): array
    {
        $accounts = $user->investmentAccounts ?? collect();
        if ($accounts->isEmpty() && method_exists($user, 'investmentAccounts')) {
            $accounts = $user->investmentAccounts()->where('employer_is_listed', true)->get();
        } else {
            $accounts = $accounts->filter(fn ($a) => $a->employer_is_listed ?? false);
        }

        return $accounts->toArray();
    }

    // --- Category detection helpers ---

    private function isLifeCoverRecommendation(string $category, string $action): bool
    {
        return $category === 'Life Insurance'
            || str_contains($action, 'life')
            || str_contains($action, 'cover')
            || str_contains($action, 'family income');
    }

    private function isIncomeProtectionRecommendation(string $category, string $action): bool
    {
        return $category === 'Income Protection'
            || str_contains($action, 'income protection');
    }

    private function isIHTRecommendation(string $category, string $title, string $description): bool
    {
        return in_array($category, ['charitable_bequest', 'liquidity', 'life_cover', 'new_life_cover', 'planning'])
            || str_contains($title, 'iht')
            || str_contains($title, 'inheritance')
            || str_contains($description, 'iht')
            || str_contains($description, 'inheritance tax');
    }

    private function isTrustRecommendation(string $category, string $title): bool
    {
        return in_array($category, ['trust_planning', 'clt_trust', 'will_trust_setup'])
            || str_contains($title, 'trust')
            || str_contains($title, 'discretionary');
    }

    private function isGiftingRecommendation(string $category, string $title): bool
    {
        return in_array($category, ['annual_gifting', 'pet_gifting'])
            || str_contains($title, 'gift');
    }

    private function isAssetAllocationRecommendation(string $category, string $action, string $description): bool
    {
        return $category === 'asset_allocation'
            || str_contains($action, 'allocation')
            || str_contains($action, 'diversif')
            || str_contains($description, 'allocation');
    }

    private function isRebalancingRecommendation(string $category, string $action, string $description): bool
    {
        return $category === 'rebalancing'
            || str_contains($action, 'rebalanc')
            || str_contains($description, 'rebalanc')
            || str_contains($description, 'drift');
    }

    private function isISARecommendation(string $category, string $action, string $description): bool
    {
        return $category === 'isa_optimisation'
            || str_contains($action, 'isa')
            || str_contains($description, 'isa allowance');
    }
}
