<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\TaxDefaults;
use App\Models\User;
use App\Services\Stores\TaxConfigStore;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Tax Configuration Service
 *
 * Provides centralized access to the active UK tax configuration.
 * Request-scoped singleton - loads active config once per request and caches in memory.
 *
 * Reads route through TaxConfigStore (SP1 P2 R1.4) — the service no longer
 * touches the TaxConfiguration model directly. Public API is unchanged;
 * every consumer in app/Constants and app/Agents continues to call
 * TaxConfigService::getIncomeTax() etc.
 *
 * Usage:
 *   $taxConfig = app(TaxConfigService::class);
 *   $personalAllowance = $taxConfig->get('income_tax.personal_allowance');
 *   $incomeTax = $taxConfig->getIncomeTax();
 */
class TaxConfigService
{
    /**
     * Cached active tax configuration as a canonical array (request-scoped).
     */
    private ?array $config = null;

    public function __construct(
        private readonly TaxConfigStore $store = new TaxConfigStore,
    ) {}

    /**
     * Get the full active tax configuration
     *
     * @throws RuntimeException if no active tax year found
     */
    public function getAll(): array
    {
        return $this->loadActiveConfig();
    }

    /**
     * Get a specific tax configuration value using dot notation
     *
     * @param  string  $key  Dot notation key (e.g., 'income_tax.personal_allowance')
     * @param  mixed  $default  Default value if key doesn't exist
     *
     * @throws RuntimeException if no active tax year found
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $config = $this->loadActiveConfig();

        return Arr::get($config, $key, $default);
    }

    /**
     * Check if a configuration key exists
     *
     * @param  string  $key  Dot notation key
     */
    public function has(string $key): bool
    {
        $config = $this->loadActiveConfig();

        return Arr::has($config, $key);
    }

    /**
     * Get the active tax year string
     *
     * @return string e.g., '2025/26'
     *
     * @throws RuntimeException if no active tax year found
     */
    public function getTaxYear(): string
    {
        return $this->get('tax_year', '');
    }

    /**
     * Get the effective from date
     *
     * @return string e.g., '2025-04-06'
     *
     * @throws RuntimeException if no active tax year found
     */
    public function getEffectiveFrom(): string
    {
        return $this->get('effective_from', '');
    }

    /**
     * Get the effective to date
     *
     * @return string e.g., '2026-04-05'
     *
     * @throws RuntimeException if no active tax year found
     */
    public function getEffectiveTo(): string
    {
        return $this->get('effective_to', '');
    }

    /**
     * Check if a date falls within the current tax year
     *
     * @param  Carbon|string  $date
     */
    public function isInCurrentTaxYear($date): bool
    {
        $effectiveFrom = $this->getEffectiveFrom();
        $effectiveTo = $this->getEffectiveTo();

        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return $date->isBetween($effectiveFrom, $effectiveTo, true);
    }

    // =========================================================================
    // Module-Specific Helper Methods
    // =========================================================================

    /**
     * Get Income Tax configuration
     *
     * @return array Contains personal_allowance, bands, etc.
     */
    public function getIncomeTax(): array
    {
        return $this->get('income_tax', []);
    }

    /**
     * Get National Insurance configuration
     *
     * @return array Contains class_1, class_2, class_4 rates
     */
    public function getNationalInsurance(): array
    {
        return $this->get('national_insurance', []);
    }

    /**
     * Get ISA allowances configuration
     *
     * @return array Contains annual_allowance, lifetime_isa, junior_isa
     */
    public function getISAAllowances(): array
    {
        return $this->get('isa', []);
    }

    /**
     * Get Pension allowances configuration
     *
     * @return array Contains annual_allowance, MPAA, tapered_allowance, state_pension,
     *               lump_sum_allowance (LSA), lump_sum_and_death_benefit_allowance (LSDBA),
     *               pcls_rate
     */
    public function getPensionAllowances(): array
    {
        return $this->get('pension', []);
    }

    /**
     * Calculate the tax-free Pension Commencement Lump Sum (PCLS) for a crystallisation.
     *
     * Caps at the Lump Sum Allowance (LSA, £268,275 since LTA abolition April 2024).
     *
     * @param  float  $crystallisedAmount  Total crystallised pension value
     * @param  float  $lsaUsed  LSA already consumed by prior crystallisations.
     *                          Defaults to 0; pass actual tracked value when
     *                          per-user lsa_used tracking is implemented.
     * @return float Tax-free PCLS amount (≤ LSA remaining)
     */
    public function calculatePCLS(float $crystallisedAmount, float $lsaUsed = 0.0): float
    {
        $pension = $this->getPensionAllowances();
        $pclsRate = (float) ($pension['pcls_rate'] ?? 0.25);
        $lsa = (float) ($pension['lump_sum_allowance'] ?? 268275);
        $lsaRemaining = max(0.0, $lsa - max(0.0, $lsaUsed));

        return min(max(0.0, $crystallisedAmount) * $pclsRate, $lsaRemaining);
    }

    /**
     * Get Inheritance Tax configuration
     *
     * @return array Contains NRB, RNRB, rates, PETs, CLTs
     */
    public function getInheritanceTax(): array
    {
        return $this->get('inheritance_tax', []);
    }

    /**
     * Get Capital Gains Tax configuration
     *
     * @return array Contains annual_exempt_amount, rates
     */
    public function getCapitalGainsTax(): array
    {
        return $this->get('capital_gains_tax', []);
    }

    /**
     * Get Dividend Tax configuration
     *
     * @return array Contains allowance, rates
     */
    public function getDividendTax(): array
    {
        return $this->get('dividend_tax', []);
    }

    /**
     * Get Stamp Duty Land Tax configuration
     *
     * @return array Contains residential and non_residential bands
     */
    public function getStampDuty(): array
    {
        return $this->get('stamp_duty', []);
    }

    /**
     * Get Gifting Exemptions configuration
     *
     * @return array Contains annual_exemption, small_gifts, wedding_gifts, etc.
     */
    public function getGiftingExemptions(): array
    {
        return $this->get('gifting_exemptions', []);
    }

    /**
     * Get Trusts configuration
     *
     * @return array Contains entry_charge, exit_charge, periodic_charge
     */
    public function getTrusts(): array
    {
        return $this->get('trusts', []);
    }

    /**
     * Get PET (Potentially Exempt Transfer) rules
     *
     * @return array Contains years_to_exemption, taper_relief, failed_pet_rules
     */
    public function getPETRules(): array
    {
        return $this->get('inheritance_tax.potentially_exempt_transfers', []);
    }

    /**
     * Get CLT (Chargeable Lifetime Transfer) rules
     *
     * @return array Contains lookback_period, lifetime_rate, death_rate, taper_relief
     */
    public function getCLTRules(): array
    {
        return $this->get('inheritance_tax.chargeable_lifetime_transfers', []);
    }

    /**
     * CLT lifetime rate — the IHT rate charged on chargeable lifetime
     * transfers above the nil-rate band when the trust pays (default 0.20).
     *
     * Centralised here during the 2026-05-23 tech-debt audit (B44) after the
     * same `?? 0.20` lookup was found duplicated across
     * PersonalizedTrustStrategyService (×2) and GiftingStrategyOptimizer.
     */
    public function getCLTLifetimeRate(): float
    {
        return (float) ($this->get('inheritance_tax.chargeable_lifetime_transfers.lifetime_rate') ?? 0.20);
    }

    /**
     * The reduced Inheritance Tax rate for a qualifying charitable estate
     * (IHTA 1984 s.7(1A)).
     *
     * Centralised following the same precedent as getCLTLifetimeRate(): the
     * `?? 0.36` lookup was duplicated across WillAnalysisService (×2),
     * IHTCalculationService, EstateAgent, GiftingStrategy and
     * TaxSettingsController, alongside a seventh consumer reading
     * TaxDefaults::IHT_CHARITABLE_RATE — two fallback conventions, seven sites.
     *
     * **THIS NOTE NO LONGER ASSERTS A COUNT, BECAUSE EVERY COUNT OF IT HAS BEEN
     * WRONG (W-0451).**
     *
     * The paragraph above claimed the consolidation was complete; it was not.
     * The first correction named ONE survivor; there were two. The
     * tax-compliance gate named TWO; `grep -rn '?? 0.36' app/` then found
     * **four** — `WillAnalysisService`, `GiftingStrategy`, `EstateAgent:694`,
     * and `TaxSettingsController:330`, the last inside the admin screen that
     * displays the tax settings themselves, hardcoding "10%+" in the same
     * sentence.
     *
     * **Three successive statements of the number, by three different authors,
     * each confident and each wrong.** The number is not the durable thing. The
     * check is:
     *
     *     grep -rn '?? 0\.36' app/ | grep -v ':\s*\*\|// '   # the literal fallback
     *     grep -rn 'reduced_rate_charity' app/               # every direct array read
     *
     * A direct array read is a survivor only when it falls back to a LITERAL.
     * Falling back to `TaxDefaults::IHT_CHARITABLE_RATE` is the sanctioned
     * convention — `EstatePlanService:508` and `TaxConfigSnapshotService:90` do
     * that and must not be "fixed".
     *
     * **THE FILTER IS NOT COSMETIC.** The unfiltered grep now returns five hits
     * and four of them are the COMMENTS written to explain the fixes — including
     * two lines of this docblock. **A grep-based check degrades as the fix it
     * checks for gets documented**, which is a fourth way for a completion claim
     * to stop the next reader looking, and it took one cycle to appear.
     *
     * **WHAT THE LAST PASS ACTUALLY DID, because naming four and fixing three is
     * how this note went wrong the previous three times (W-0451 gate, C4):**
     *
     *     WillAnalysisService   routed
     *     GiftingStrategy       routed
     *     EstateAgent:705       routed
     *     TaxSettingsController:330   **NAMED AND LEFT STANDING** — still there
     *
     * It is admin-facing, so lower severity than the user-facing three, but it is
     * the **Tax Settings screen**: the one place a hardcoded rate contradicts the
     * very configuration it is rendering, and its sentence hardcodes the 10%
     * threshold as well. Filed as **W-0461**.
     *
     * **A completion note is load-bearing: if a consolidation leaves a survivor,
     * the note is the thing that hides it** — a reader checking whether the
     * duplication was dealt with finds a docblock saying it was, and stops.
     * **So this one records the command, the exclusions, and the one it did not
     * fix.** A conclusion goes stale; a command plus a known survivor does not.
     */
    public function getCharitableReducedRate(): float
    {
        return (float) ($this->get('inheritance_tax.reduced_rate_charity') ?? TaxDefaults::IHT_CHARITABLE_RATE);
    }

    /**
     * The proportion of the baseline amount that must pass to charity for the
     * reduced rate to apply (IHTA 1984 Sch 1A) — 10%.
     *
     * Read from configuration because the key is seeded AND rendered in the
     * admin Tax Settings screen as though it governs the calculation. Until
     * this accessor existed nothing read it, so the admin control was inert.
     */
    public function getCharitableThresholdPercent(): float
    {
        return (float) ($this->get('inheritance_tax.charity_threshold_percent') ?? TaxDefaults::IHT_CHARITY_THRESHOLD);
    }

    /**
     * The fourteen-year rule, DERIVED from the two transfer blocks that state it.
     *
     * **There is no fourteen-year window in the legislation.** There are two
     * independent seven-year ones: a chargeable transfer within seven years of
     * death is charged by reference to the transfers in the seven years ending
     * with THAT transfer (IHTA 1984 s7(1)(b)), so a gift up to fourteen years old
     * can still matter. Fourteen is the SUM, never an input — which is why it is
     * computed here rather than configured.
     *
     * **W-0526.** It used to be configured, in an `inheritance_tax.fourteen_year_rule`
     * block carrying its own `lookback_for_failed_pets`, `lookback_for_clts` and
     * `maximum_window: 14` — and nothing read any of them. `FailedGiftTaxCalculator`
     * composed the same windows from `potentially_exempt_transfers` and
     * `chargeable_lifetime_transfers` instead. So one rule had two configured homes:
     * an admin moving `maximum_window` to 10 changed nothing, moving the CLT block
     * changed the answer silently, and the two could contradict each other because
     * a stored 14 does not follow a lookback edited to 5.
     *
     * The narrative keys (`description`, `calculation_steps`) are still read from
     * that block, because prose is the one thing it can own without going stale
     * against arithmetic it does not perform.
     *
     * @return array{lookback_for_failed_pets: int, lookback_for_clts: int, maximum_window: int, description: string, calculation_steps: list<string>}
     */
    public function getFourteenYearRule(): array
    {
        $clts = $this->getCLTRules();

        // BOTH windows come from the chargeable-lifetime-transfer block, because
        // both are properties of a CLT: `cumulation_period` is how far back from
        // DEATH a chargeable transfer is caught, `lookback_period` is how far back
        // from THAT TRANSFER its own cumulation reaches.
        //
        // Deliberately NOT `potentially_exempt_transfers.years_to_exemption`, even
        // though it holds 7 as well. It answers a different question — when a PET
        // becomes exempt — and substituting it here would be a silent change of
        // meaning that no test could catch while both keys happen to agree.
        $lookbackForClts = (int) ($clts['cumulation_period'] ?? 7);
        $lookbackForFailedPets = (int) ($clts['lookback_period'] ?? 7);
        $narrative = $this->get('inheritance_tax.fourteen_year_rule', []);

        return [
            'lookback_for_failed_pets' => $lookbackForFailedPets,
            'lookback_for_clts' => $lookbackForClts,
            // The outer search bound. Not a cumulation band in its own right.
            'maximum_window' => $lookbackForFailedPets + $lookbackForClts,
            'description' => (string) ($narrative['description'] ?? ''),
            'calculation_steps' => (array) ($narrative['calculation_steps'] ?? []),
        ];
    }

    /**
     * Get Trust IHT charges configuration
     *
     * @return array Contains entry, periodic, and exit charge rules
     */
    public function getTrustCharges(): array
    {
        return $this->get('inheritance_tax.trust_charges', []);
    }

    /**
     * Get taper relief rates for PETs/CLTs
     *
     * @param  string  $type  'pet' or 'clt'
     * @return array Taper relief schedule
     */
    public function getTaperRelief(string $type = 'pet'): array
    {
        if ($type === 'clt') {
            return $this->get('inheritance_tax.chargeable_lifetime_transfers.taper_relief', []);
        }

        return $this->get('inheritance_tax.potentially_exempt_transfers.taper_relief', []);
    }

    /**
     * Get the tax rate for a gift based on years survived
     *
     * @param  int|float  $yearsSurvived  Years between gift and death
     * @param  string  $type  'pet' or 'clt'
     * @return float Tax rate (0.0 to 0.40)
     */
    public function getGiftTaxRate(int|float $yearsSurvived, string $type = 'pet'): float
    {
        // The two schedules are shaped differently and only one of them worked.
        //
        // Potentially-exempt-transfer bands carry `tax_rate` outright (0.32 = "80%
        // of 40%"). Chargeable-lifetime-transfer bands carry `tax_percent` — the
        // PERCENTAGE OF THE DEATH RATE still payable — and no `tax_rate` at all, so
        // `$band['tax_rate'] ?? 0.40` matched nothing and returned the hardcoded
        // default: **every chargeable lifetime transfer was rated at the full 40%
        // however long the donor had survived.** Measured before the fix: 0.40 at
        // every year from 0 to 8.
        //
        // s7(4) charges "the following PERCENTAGE OF THE RATE OR RATES REFERRED TO
        // IN SUBSECTION (1)", and s7(1) is the death rate — so `death_rate ×
        // tax_percent / 100` is a transcription of the statute rather than a
        // convenience.
        //
        // Handled HERE rather than in a caller: `FailedGiftTaxCalculator` briefly
        // carried its own band walk to work around this, which left the canonical
        // accessor still broken, still public, and still answering 40% to whoever
        // called it next — two mechanisms for one question, which is the thing
        // Rule 20 exists to stop.
        $deathRate = $type === 'clt'
            ? (float) ($this->get('inheritance_tax.chargeable_lifetime_transfers.death_rate')
                ?? $this->get('inheritance_tax.standard_rate', TaxDefaults::IHT_RATE))
            : (float) $this->get('inheritance_tax.standard_rate', TaxDefaults::IHT_RATE);

        foreach ($this->getTaperRelief($type) as $band) {
            $minYears = (float) ($band['min_years'] ?? 0);
            $maxYears = ($band['max_years'] ?? null) === null ? INF : (float) $band['max_years'];

            if ($yearsSurvived >= $minYears && $yearsSurvived < $maxYears) {
                if (array_key_exists('tax_rate', $band)) {
                    return (float) $band['tax_rate'];
                }

                // Rounded: 0.40 × 20/100 lands on 0.08000000000000002, and this rate
                // is published, printed as a percentage and compared against others.
                return round($deathRate * ((float) ($band['tax_percent'] ?? 100) / 100), 6);
            }
        }

        // No band matched — the full rate for the type, from configuration.
        return $deathRate;
    }

    /**
     * Get Business Relief configuration
     *
     * @return array Contains rates, min_ownership_years, excluded_businesses
     */
    public function getBusinessRelief(): array
    {
        return $this->get('inheritance_tax.business_relief', []);
    }

    /**
     * Get Agricultural Relief configuration
     *
     * @return array Contains rates, ownership requirements, caps
     */
    public function getAgriculturalRelief(): array
    {
        return $this->get('inheritance_tax.agricultural_relief', []);
    }

    /**
     * Get Quick Succession Relief configuration
     *
     * @return array Contains relief rates by years
     */
    public function getQuickSuccessionRelief(): array
    {
        return $this->get('inheritance_tax.quick_succession_relief', []);
    }

    /**
     * Get Normal Expenditure from Income exemption rules
     *
     * @return array Contains conditions and evidence requirements
     */
    /**
     * The IHTA 1984 s21 exemption — regular gifts out of surplus income.
     *
     * **W-0525.** This accessor had zero callers while TWO services computed the
     * exemption anyway: `PersonalizedGiftingStrategyService` and
     * `GiftingStrategyOptimizer` each hardcoded `surplus * 0.5` with a `>= 1000`
     * floor. So one exemption had two mechanisms and no configuration — moving
     * the admin setting did nothing, and editing either service let the two
     * answers drift apart with nothing comparing them.
     *
     * The two numbers are surfaced explicitly because they are the ones the
     * strategies act on, and neither is in the legislation: s21 sets no cap at
     * all. `safe_surplus_fraction` is a deliberate conservatism — the third
     * statutory test is that the donor keeps their usual standard of living, so
     * suggesting the whole surplus would advise up to the edge of failing it.
     * `minimum_annual_gift` is the point below which a standing order is not
     * worth the record-keeping the exemption demands.
     *
     * @return array{limit: null|float, immediately_exempt: bool, safe_surplus_fraction: float, minimum_annual_gift: float, conditions: array<string, bool>, evidence_required: list<string>}
     */
    public function getNormalExpenditureFromIncome(): array
    {
        $rules = $this->get('gifting_exemptions.normal_expenditure_from_income', []);

        return $rules + [
            'limit' => null,
            'immediately_exempt' => true,
            'safe_surplus_fraction' => 0.5,
            'minimum_annual_gift' => 1000.0,
            'conditions' => [],
            'evidence_required' => [],
        ];
    }

    /**
     * Get Investment/Financial Planning Assumptions
     *
     * @return array Contains investment_growth, inflation, salary_growth, growth_by_risk
     */
    public function getAssumptions(): array
    {
        return $this->get('assumptions', []);
    }

    /**
     * Get Personal Savings Allowance by tax band
     *
     * @param  string|null  $taxBand  'basic', 'higher', or 'additional'. Null returns all bands.
     * @return int|array Returns the PSA amount for the band, or all bands if null
     */
    public function getPersonalSavingsAllowance(?string $taxBand = null): int|array
    {
        $psa = $this->get('income_tax.personal_savings_allowance', [
            'basic' => 1000,
            'higher' => 500,
            'additional' => 0,
        ]);

        if ($taxBand === null) {
            return $psa;
        }

        return $psa[$taxBand] ?? 0;
    }

    /**
     * Get the Blind Person's Allowance for the active tax year.
     *
     * W-0511 — the `?? 2870` fallback that used to sit here was a stale year's figure,
     * so an unconfigured year silently granted the wrong allowance rather than showing
     * a gap. It was also a hardcoded tax value (Rule 2). Zero is the honest answer to
     * "this year does not configure one": it under-grants visibly instead of
     * over-granting invisibly, and every seeded year sets the key.
     */
    public function getBlindPersonsAllowance(): float
    {
        return (float) ($this->get('income_tax.blind_persons_allowance') ?? 0);
    }

    /**
     * The Blind Person's Allowance this user is entitled to — W-0511.
     *
     * The one place the entitlement question is answered, so the five services that
     * compute somebody's income tax cannot drift apart on it. ITA 2007 s38 gives the
     * allowance to a person registered as severely sight impaired; `is_registered_blind`
     * is what the app records, captured at onboarding and editable on the profile.
     *
     * **Surplus is not transferred to a spouse or civil partner.** ITA 2007 s39 allows
     * it where the claimant's own income cannot absorb the allowance, and this does not
     * model it — a deliberate omission stated at the line rather than left to be
     * discovered. It under-relieves only the household whose registered-blind member has
     * income below the allowance, and modelling it needs a spouse's computation this
     * method has no access to.
     */
    public function blindPersonsAllowanceFor(?User $user): float
    {
        return $user?->is_registered_blind ? $this->getBlindPersonsAllowance() : 0.0;
    }

    /**
     * Get Savings-specific configuration (FSCS, Premium Bonds, etc.)
     *
     * @param  string|null  $key  Optional dot-notation sub-key (e.g., 'fscs_deposit_protection')
     * @return mixed Full savings config array, or specific value if key provided
     */
    public function getSavingsConfig(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->get('savings', []);
        }

        return $this->get("savings.{$key}");
    }

    /**
     * Get Protection module configuration
     *
     * @return array Contains income_multipliers, affordability, premium_factors, etc.
     */
    public function getProtectionConfig(): array
    {
        return $this->get('protection', []);
    }

    /**
     * Get Retirement module configuration
     *
     * @return array Contains withdrawal_rates, target_income_percent, annuity_rate_estimates, etc.
     */
    public function getRetirementConfig(): array
    {
        return $this->get('retirement', []);
    }

    /**
     * Get Investment module configuration
     *
     * @return array Contains fee_benchmarks, waterfall limits, venture_capital, safety thresholds
     */
    public function getInvestmentConfig(): array
    {
        return $this->get('investment', []);
    }

    /**
     * Get Estate planning configuration
     *
     * @return array Contains onboarding_estimates, insurance_premium_estimates
     */
    public function getEstateConfig(): array
    {
        return $this->get('estate', []);
    }

    /**
     * Get Benefits configuration (SSP, ESA, UC, PIP, bereavement)
     *
     * @param  string|null  $key  Optional sub-key (e.g., 'ssp', 'universal_credit')
     * @return mixed Full benefits config or specific benefit section
     */
    public function getBenefits(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->get('benefits', []);
        }

        return $this->get("benefits.{$key}", []);
    }

    /**
     * Get Domicile rules
     *
     * @return array Contains uk_domiciled, non_uk_domiciled rules
     */
    public function getDomicile(): array
    {
        return $this->get('domicile', []);
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Load active tax configuration (with request-scoped caching)
     *
     * @throws RuntimeException if no active tax year found
     */
    private function loadActiveConfig(): array
    {
        // Return cached config if already loaded
        if ($this->config !== null) {
            return $this->config;
        }

        // Load active tax configuration via the store (spec §14.1 boundary).
        $active = $this->store->activeConfig();

        if ($active === null) {
            throw new RuntimeException(
                'No active tax configuration found. Please run TaxConfigurationSeeder or activate a tax year.'
            );
        }

        // The store returns the full row (including is_active, notes, etc.);
        // the public service contract is the config_data array, so extract it.
        $this->config = $active['config_data'] ?? [];

        // Log which tax year is being used (helpful for debugging)
        logger()->debug('Tax Configuration Service loaded', [
            'tax_year' => $this->config['tax_year'] ?? ($active['tax_year'] ?? 'unknown'),
            'effective_from' => $this->config['effective_from'] ?? ($active['effective_from'] ?? 'unknown'),
        ]);

        return $this->config;
    }

    /**
     * Clear cached configuration (mainly for testing)
     */
    public function clearCache(): void
    {
        $this->config = null;
        $this->store->forgetActive();
    }

    /**
     * Get property ownership information including joint ownership types and leasehold reform
     *
     * @return array Contains joint_ownership_types, leasehold_reform, tenure_types
     */
    public function getPropertyOwnership(): array
    {
        return $this->get('property_ownership', []);
    }

    /**
     * Get joint ownership type information
     *
     * @param  string|null  $type  Optional specific type ('joint_tenancy' or 'tenants_in_common')
     */
    public function getJointOwnershipType(?string $type = null): ?array
    {
        $types = $this->get('property_ownership.joint_ownership_types', []);

        if ($type !== null) {
            return $types[$type] ?? null;
        }

        return $types;
    }

    /**
     * Get leasehold reform information
     *
     * @return array Contains ground_rent_abolished_date, valuation_thresholds, etc.
     */
    public function getLeaseholdReform(): array
    {
        return $this->get('property_ownership.leasehold_reform', []);
    }

    /**
     * Check if a leasehold property is approaching problematic remaining lease term
     *
     * @return array Returns warnings and thresholds
     */
    public function getLeaseholdValuationWarnings(int $remainingYears): array
    {
        $reform = $this->getLeaseholdReform();
        $thresholds = $reform['valuation_thresholds'] ?? ['difficult_to_mortgage' => 80, 'significant_value_loss' => 60];

        $warnings = [];

        if ($remainingYears < $thresholds['difficult_to_mortgage']) {
            $warnings[] = [
                'level' => 'warning',
                'message' => 'Properties with less than '.$thresholds['difficult_to_mortgage'].' years remaining may be difficult to mortgage',
            ];
        }

        if ($remainingYears < $thresholds['significant_value_loss']) {
            $warnings[] = [
                'level' => 'danger',
                'message' => 'Properties with less than '.$thresholds['significant_value_loss'].' years remaining may significantly lose value',
            ];
        }

        return [
            'has_warnings' => count($warnings) > 0,
            'warnings' => $warnings,
            'thresholds' => $thresholds,
            'remaining_years' => $remainingYears,
        ];
    }

    /**
     * Check if joint tenancy has survivorship rights (for IHT calculations)
     *
     * **W-0498 — deliberately without callers, and this note is the point.**
     *
     * This and `allowsWillOverride()` below answer a FIRST-death question: what happens
     * to a jointly-held asset when one owner dies. The estate model does not ask it.
     * `EstateAssetAggregatorService` produces a SECOND-death estate, where there is no
     * survivor left for a joint tenancy to pass to, so survivorship must not be
     * consulted there — W-0375 rewrote that service's docblock to say so, and a guard
     * in `JointOwnershipConfigReachesTheUserTest` fails if either method appears in it.
     *
     * They are kept rather than deleted because the configured data they read is real
     * and is now shown to users through `TaxConfigSnapshotService`; a first-death
     * treatment would compose from here rather than re-deriving it. The absence of a
     * caller is a decision, recorded, instead of looking like dead code somebody
     * forgot to wire.
     */
    public function hasSurvivorshipRights(string $jointOwnershipType): bool
    {
        $typeInfo = $this->getJointOwnershipType($jointOwnershipType);

        return $typeInfo['survivorship'] ?? false;
    }

    /**
     * Check if joint ownership type allows will override
     */
    public function allowsWillOverride(string $jointOwnershipType): bool
    {
        $typeInfo = $this->getJointOwnershipType($jointOwnershipType);

        return $typeInfo['will_override'] ?? false;
    }

    /**
     * Get Child Benefit configuration
     *
     * @return array Contains weekly/annual rates and HICBC thresholds
     */
    public function getChildBenefit(): array
    {
        return $this->get('benefits.child_benefit', [
            'eldest_child_weekly' => 26.05,
            'additional_child_weekly' => 17.25,
            'eldest_child_annual' => 1354.60,
            'additional_child_annual' => 897.00,
            'high_income_charge_threshold' => 60000,
            'high_income_full_clawback' => 80000,
            'clawback_increment' => 200,
        ]);
    }

    /**
     * Get Tax-Free Childcare configuration.
     *
     * @return array Contains top-up rates, limits, income thresholds, and warnings
     */
    public function getTaxFreeChildcare(): array
    {
        return $this->get('benefits.tax_free_childcare', [
            'government_top_up_rate' => 0.25,
            'max_government_contribution' => 2000,
            'max_disabled_contribution' => 4000,
            'child_age_limit' => 11,
            'max_income_threshold' => 100000,
        ]);
    }

    /**
     * Get Early Years Funding configuration.
     *
     * @return array Contains funded hours entitlements, age ranges, income thresholds, and warnings
     */
    public function getEarlyYearsFunding(): array
    {
        return $this->get('benefits.early_years_funding', [
            'universal_15hrs' => ['hours_per_week' => 15, 'weeks_per_year' => 38, 'income_test' => false],
            'working_parents_30hrs' => ['hours_per_week' => 30, 'weeks_per_year' => 38, 'max_income_threshold' => 100000],
        ]);
    }
}
