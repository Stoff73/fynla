<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Retirement\PensionContributionRule;
use App\Services\Savings\ISATracker;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\Stores\PensionStore;
use App\Services\Stores\SavingsStore;
use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;
use App\Support\SavingsInterestRate;
use App\Traits\CalculatesOwnershipShare;
use Carbon\Carbon;

/**
 * Stateless math/lookup helpers shared across every TaxStrategy class.
 *
 * Every public method is deterministic given (User, ?Overrides, TaxConfig).
 * Methods that hit the database (estimateAnnualInterest,
 * estimateIsaSubscriptionsThisYear, estimatePensionContributionThisYear)
 * issue a single query each — keep an eye on N+1 if a strategy class calls
 * them inside a loop.
 */
final class TaxStrategyMath
{
    use CalculatesOwnershipShare;

    /**
     * Per-instance memo keyed by user id for taxableIncomeFor(), which fires
     * a SavingsAccount query via estimateAnnualInterest. Strategies that call
     * the helper repeatedly (or via composed paths after M11) would otherwise
     * issue one query each — benchmarked to flake the 50ms calculator budget.
     *
     * @var array<int, float>
     */
    private array $taxableIncomeCache = [];

    /** @var array<int, array<string, mixed>> */
    private array $incomeDefinitionsCache = [];

    /** @var array<int, bool> */
    private array $mpaaAppliesCache = [];

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly IncomeDefinitionsService $incomeDefinitions,
    ) {}

    /**
     * Personal Savings Allowance amount for a given band, sourced from
     * TaxConfigService['income_tax']['personal_savings_allowance'].
     */
    public function psaForBand(string $band): float
    {
        $psa = $this->taxConfig->getIncomeTax()['personal_savings_allowance'] ?? [];

        return (float) ($psa[$band] ?? 0);
    }

    /**
     * Tax-band thresholds sourced from TaxConfigService — basic/higher/additional
     * boundaries. Returns the lower bounds: basic = 0, higher band lower_limit,
     * additional band lower_limit.
     *
     * @return array{higher: float, additional: float}
     */
    public function bandThresholds(): array
    {
        $bands = $this->taxConfig->getIncomeTax()['bands'] ?? [];
        $higher = 0.0;
        $additional = 0.0;
        foreach ($bands as $band) {
            $name = strtolower((string) ($band['name'] ?? ''));
            if (str_contains($name, 'higher')) {
                $higher = (float) ($band['lower_limit'] ?? 0);
            }
            if (str_contains($name, 'additional')) {
                $additional = (float) ($band['lower_limit'] ?? 0);
            }
        }

        return ['higher' => $higher, 'additional' => $additional];
    }

    /**
     * The band limits as they apply to THIS user: extended by the grossed-up Gift
     * Aid, the same extension `UKTaxCalculator` applies (ITA 2007 s414); the
     * Personal Allowance taper is not modelled here because it only bites above
     * £100,000 where both limits are already exceeded. Without this the strategy
     * engine valued a slice at 45% that the calculator taxed at 40% (2026-09-17).
     *
     * @return array{higher: float, additional: float}
     */
    public function bandThresholdsFor(User $user): array
    {
        // Gift Aid (ITA 2007 s414) and relief-at-source pension contributions
        // (FA 2004 s192(4)) both raise the basic and higher rate limits. The
        // Blind Person's Allowance comes off net income with the Personal
        // Allowance (ITA 2007 s23 Step 3, s38), so in net-income terms each
        // rate starts that much higher too.
        $deductions = $this->incomeDefinitionsFor($user)['deductions'] ?? [];
        $extension = (float) ($deductions['gift_aid_gross'] ?? 0) + (float) ($deductions['relief_at_source_gross'] ?? 0)
            + $this->taxConfig->blindPersonsAllowanceFor($user);
        $raw = $this->bandThresholds();

        return [
            'higher' => $raw['higher'] > 0 ? $raw['higher'] + $extension : 0.0,
            'additional' => $raw['additional'] > 0 ? $raw['additional'] + $extension : 0.0,
        ];
    }

    /**
     * Raw (non-Gift-Aid-aware) band lookup for an arbitrary income figure.
     * Stays raw deliberately for `QuerySchemas` and for `CoordinatingAgent`'s two
     * spouse-income calls — none of those callers have a `User` model in hand to
     * look up Gift Aid for, so `bandFromIncomeFor()` is not available to them.
     * Every caller that DOES hold a `User` should use `bandFromIncomeFor()` instead.
     */
    public function bandFromIncome(float $income): string
    {
        $thresholds = $this->bandThresholds();

        return match (true) {
            $income > $thresholds['additional'] && $thresholds['additional'] > 0 => 'additional',
            $income > $thresholds['higher'] && $thresholds['higher'] > 0 => 'higher',
            default => 'basic',
        };
    }

    public function bandFromIncomeFor(User $user, float $income): string
    {
        $thresholds = $this->bandThresholdsFor($user);

        return match (true) {
            $income > $thresholds['additional'] && $thresholds['additional'] > 0 => 'additional',
            $income > $thresholds['higher'] && $thresholds['higher'] > 0 => 'higher',
            default => 'basic',
        };
    }

    /**
     * The user's Income Tax band as the Tax plan prices it: 'none' when net
     * income is within the Personal Allowance after its taper (ITA 2007 s35)
     * and any Blind Person's Allowance (s38), otherwise the band from
     * bandFromIncomeFor() on taxable income. Fyn names this band (audit item
     * 44), so it never works out a second one.
     */
    public function incomeTaxBandFor(User $user): string
    {
        $taxable = $this->taxableIncomeFor($user);
        $allowances = $this->personalAllowanceFor($user) + $this->taxConfig->blindPersonsAllowanceFor($user);

        return $taxable <= $allowances ? 'none' : $this->bandFromIncomeFor($user, $taxable);
    }

    /**
     * Marginal income-tax rate for the user, derived from their HMRC band on
     * TOTAL taxable income (employment + dividends + savings interest), not
     * employment alone. This is the right basis for the marginal rate on
     * savings interest, AA charges, and pension tax relief — all of which
     * stack on top of the user's other income at HMRC.
     */
    public function bandRateFor(User $user): float
    {
        return $this->bandRateForBand($this->bandFromIncomeFor($user, $this->taxableIncomeFor($user)));
    }

    /**
     * Marginal income-tax rate for an arbitrary gross income figure, without
     * needing a User model. Used by HouseholdFinancialContext to price a
     * dual-earner spouse's rate from the household input's spouse_annual_income.
     */
    public function bandRateFromIncome(float $income): float
    {
        return $this->bandRateForBand($this->bandFromIncome($income));
    }

    /**
     * Marginal income-tax rate for a given band ('basic' / 'higher' /
     * 'additional'), sourced from TaxConfigService['income_tax']['bands'].
     * Falls back to HMRC 2025/26 defaults only if the band can't be matched
     * (defensive — config seeder always populates all three bands).
     */
    public function bandRateForBand(string $band): float
    {
        $bands = $this->taxConfig->getIncomeTax()['bands'] ?? [];
        $needle = strtolower($band);

        foreach ($bands as $row) {
            $name = strtolower((string) ($row['name'] ?? ''));
            if (str_contains($name, $needle)) {
                return (float) ($row['rate'] ?? 0);
            }
        }

        // Rule 2: a rate missing from tax config is a configuration fault, not
        // a reason to fall back to an old hardcoded figure.
        throw new \RuntimeException("Income tax band '{$needle}' is missing from the tax configuration.");
    }

    public function personalSavingsAllowanceFor(float $income): float
    {
        return $this->psaForBand($this->bandFromIncome($income));
    }

    /**
     * Personal Savings Allowance for THIS user, banded on their Gift-Aid-extended
     * thresholds via `bandFromIncomeFor()`. Use this over `personalSavingsAllowanceFor()`
     * whenever a `User` is in hand; the float-only variant stays for the spouse grids,
     * which price off a household income figure rather than a `User` model.
     */
    public function personalSavingsAllowanceForUser(User $user): float
    {
        return $this->psaForBand($this->bandFromIncomeFor($user, $this->taxableIncomeFor($user)));
    }

    /**
     * Net income (ITA 2007 s23 Step 2): every captured source, less net-pay
     * pension contributions taken from pay (FA 2004 s193(2)). This is the
     * income the tax bands are applied to; relief-at-source contributions do
     * not reduce it and instead extend the bands (bandThresholdsFor). When the
     * user has no explicit annual-interest figure, use the interest implied by
     * captured savings balances and rates rather than silently treating it as
     * zero.
     */
    public function taxableIncomeFor(User $user): float
    {
        $compute = function () use ($user): float {
            $definitions = $this->incomeDefinitionsFor($user);

            return max(0.0, (float) ($definitions['net_income'] ?? 0) + $this->interestAdjustment($user, $definitions));
        };

        // Only a saved user is cached: every unsaved model has id null.
        if (! $user->exists) {
            return $compute();
        }

        return $this->taxableIncomeCache[(int) $user->id] ??= $compute();
    }

    public function adjustedNetIncomeFor(User $user): float
    {
        $definitions = $this->incomeDefinitionsFor($user);

        return max(
            0.0,
            (float) ($definitions['adjusted_net_income'] ?? 0) + $this->interestAdjustment($user, $definitions),
        );
    }

    public function nonSavingsIncomeFor(User $user): float
    {
        $definitions = $this->incomeDefinitionsFor($user);
        $components = is_array($definitions['components'] ?? null) ? $definitions['components'] : [];

        return max(
            0.0,
            $this->taxableIncomeFor($user)
                - $this->resolvedInterest($user, $definitions)
                - (float) ($components['dividend'] ?? 0),
        );
    }

    public function personalAllowanceFor(User $user): float
    {
        return $this->personalAllowanceForIncome($this->adjustedNetIncomeFor($user));
    }

    public function personalAllowanceForIncome(float $adjustedNetIncome): float
    {
        // The one home for the ITA 2007 s35 taper (CSJ 2026-10-01; audit item 43).
        return IncomeTaxBands::taperedPersonalAllowance($this->taxConfig->getIncomeTax(), $adjustedNetIncome);
    }

    public function moneyPurchaseAnnualAllowanceApplies(User $user): bool
    {
        $key = (int) $user->id;

        return $this->mpaaAppliesCache[$key] ??= app(PensionStore::class)
            ->forUserByType($user, 'dc')
            ->contains(fn ($pension) => (bool) $pension->has_flexibly_accessed);
    }

    public function effectiveAnnualAllowanceFor(User $user): float
    {
        $pension = $this->taxConfig->getPensionAllowances();
        // The one home for the FA 2004 s228ZA taper (CSJ 2026-10-01; audit item 42).
        $allowance = AnnualAllowanceTaper::allowance($pension, $this->thresholdIncomeFor($user), $this->adjustedIncomeFor($user));

        if ($this->moneyPurchaseAnnualAllowanceApplies($user)) {
            $allowance = min(
                $allowance,
                (float) ($pension['money_purchase_annual_allowance'] ?? $pension['mpaa'] ?? 0),
            );
        }

        return max(0.0, $allowance);
    }

    /**
     * Remaining Pension Annual Allowance for the current tax year, after the
     * user's existing contributions and any in-flight slider override.
     * Floored at 0 and constrained by the tapered Annual Allowance or Money
     * Purchase Annual Allowance where either applies. Carry-forward is handled
     * separately by PensionAACarryForwardStrategy.
     */
    /**
     * Carry forward only helps someone paying in more than this year's
     * allowance, so it applies only when both hold (CSJ 2026-09-28):
     * - earnings above this year's allowance: relief is capped at relevant
     *   UK earnings (FA 2004 s190, https://www.legislation.gov.uk/ukpga/2004/12/section/190);
     * - cash savings above what they can still pay in this year: carry
     *   forward starts once this year's allowance is used (FA 2004 s228A,
     *   https://www.legislation.gov.uk/ukpga/2004/12/section/228A).
     * Anyone else is never asked for past pension payments.
     */
    public function carryForwardCouldApply(User $user): bool
    {
        $earnings = (float) ($user->annual_employment_income ?? 0) + (float) ($user->annual_self_employment_income ?? 0);
        if ($earnings <= $this->effectiveAnnualAllowanceFor($user)) {
            return false;
        }

        $cash = app(SavingsStore::class)->forUser($user)
            ->sum(fn ($account): float => $this->calculateUserShare($account, (int) $user->id));

        return $cash > $this->availableAnnualAllowance($user, null);
    }

    public function availableAnnualAllowance(User $user, ?TaxStrategyOverridesDTO $overrides): float
    {
        $aa = $this->effectiveAnnualAllowanceFor($user);
        $used = $this->estimatePensionContributionThisYear($user, $overrides);

        return max(0, $aa - $used);
    }

    public function estimateAnnualInterest(User $user): float
    {
        // forUser() is joint-aware (primary or joint owner). HMRC splits
        // joint-account interest by beneficial share (50/50 default between
        // spouses), so each account contributes the user's ownership share —
        // never the full balance. Issue log 2026-07-23 #21; mirrors the rule
        // net worth already applies via CalculatesOwnershipShare.
        return (float) app(SavingsStore::class)->forUser($user)
            ->where('is_isa', false)
            ->sum(fn ($acc) => $this->calculateUserShare($acc, $user->id) * $this->normalisedInterestRate($acc));
    }

    /**
     * The other owner's share of the user's shared non-ISA accounts — the
     * interest HMRC attributes to the spouse. The SaveTax campaign stores the
     * spouse as household input (no User row, joint_owner_id null), so the
     * spouse grids can only derive this from the primary user's records.
     */
    public function estimateSpouseJointInterest(User $user): float
    {
        return (float) app(SavingsStore::class)->forUser($user)
            ->where('is_isa', false)
            ->filter(fn ($acc) => $this->isSharedOwnership($acc))
            ->sum(function ($acc) use ($user) {
                $fullInterest = (float) $acc->current_balance * $this->normalisedInterestRate($acc);

                return $fullInterest - ($this->calculateUserShare($acc, $user->id) * $this->normalisedInterestRate($acc));
            });
    }

    /** The account's rate as a fraction (SavingsInterestRate: the column holds percentages). */
    private function normalisedInterestRate(object $acc): float
    {
        return SavingsInterestRate::fraction($acc->interest_rate);
    }

    public function estimateIsaSubscriptionsThisYear(User $user): float
    {
        // The one ISA-used rule, the same the Savings page, the ISA tracker and
        // every other engine read (ISATracker::usedThisTaxYear; CSJ 2026-10-01).
        // This used to count `isa_subscription_current_year` whatever its year,
        // and otherwise guess from the balances of ISAs opened this tax year,
        // which also counted transfers in, and transfers do not use the allowance.
        return (float) app(ISATracker::class)->usedThisTaxYear($user)['total_used'];
    }

    public function estimatePensionContributionThisYear(User $user, ?TaxStrategyOverridesDTO $overrides): float
    {
        if ($overrides?->pensionContributionPercent !== null) {
            return (float) ($user->annual_employment_income ?? 0) * ($overrides->pensionContributionPercent / 100);
        }

        // The pension input amount (FA 2004 s233(1)), published once by the
        // income definitions.
        return (float) $this->incomeDefinitionsFor($user)['pension_input_amount'];
    }

    /**
     * Threshold income for tapered AA — sum of all taxable income fields on
     * the User row, with no pension-contribution deduction. V1 simplification:
     * does not handle salary-sacrifice anti-forestalling addback (HMRC rule
     * for sacrifices on/after 9 July 2015). Acceptable today; revisit if a
     * persona-driven false-negative appears.
     */
    public function thresholdIncomeFor(User $user): float
    {
        $definitions = $this->incomeDefinitionsFor($user);

        return max(
            0.0,
            (float) ($definitions['threshold_income'] ?? 0) + $this->interestAdjustment($user, $definitions),
        );
    }

    /**
     * Adjusted income for tapered AA — threshold income plus employer
     * pension contributions added back. Used as the £260k gate for the
     * tapered Annual Allowance.
     */
    public function adjustedIncomeFor(User $user): float
    {
        $definitions = $this->incomeDefinitionsFor($user);

        return max(
            0.0,
            (float) ($definitions['adjusted_income'] ?? 0) + $this->interestAdjustment($user, $definitions),
        );
    }

    /**
     * The user's own pension contributions this year as the gross amount that
     * earns relief: workplace (net pay) contributions as paid, personal pension
     * and SIPP payments grossed up at the basic rate (relief at source).
     * Salary-sacrificed pensions are excluded: that pay never reaches them.
     */
    public function grossEmployeePensionContributions(User $user): float
    {
        $salary = (float) ($user->annual_employment_income ?? 0);
        $basicRelief = (float) ($this->taxConfig->getPensionAllowances()['tax_relief']['basic_rate'] ?? 0);

        return (float) app(PensionStore::class)->forUserByType($user, 'dc')
            ->reject(fn ($p) => ! empty($p->salary_sacrifice))
            ->sum(function ($p) use ($salary, $basicRelief) {
                $paid = PensionContributionRule::monthlyEmployee($p, $salary) * 12;

                return PensionContributionRule::isWorkplace($p) || $basicRelief >= 1
                    ? $paid
                    : $paid / (1 - $basicRelief);
            });
    }

    /** Tax treats spouses and civil partners alike; unmarried partners get neither transfer. */
    public function isMarriedOrCivilPartner(User $user): bool
    {
        return in_array((string) ($user->marital_status ?? ''), ['married', 'civil_partnership'], true);
    }

    public function marriageAllowanceAmount(): float
    {
        return (float) ($this->taxConfig->getIncomeTax()['marriage_allowance']['amount'] ?? 0);
    }

    /**
     * The Marriage Allowance position for a couple, in either direction, from
     * ITA 2007 Part 3 Chapter 3A
     * (https://www.legislation.gov.uk/ukpga/2007/3/part/3/chapter/3A):
     * - s55C(1)(a): the couple must be married or civil partners.
     * - s55C(1)(c),(ca): once their Personal Allowance is reduced under
     *   s55B(6), the transferor may be liable only at the basic, savings,
     *   dividend-ordinary and nil rates, dividends counted in full. Their
     *   taxable income is then net income less (allowance − transferable
     *   amount), so the test is net income + transferable amount inside the
     *   basic-rate band (CSJ 2026-09-30: "widen to law"). GOV.UK's "income
     *   below your Personal Allowance" (https://www.gov.uk/marriage-allowance)
     *   is s55C(2), which binds only a non-resident qualifying under s56(3)
     *   (s55C(1)(d)); the app models UK residents. The band test counts
     *   interest in full, so a giver whose only income above the band would
     *   be interest inside the Personal Savings Allowance (the savings nil
     *   rate, which s55C(1)(c) allows) is not offered it: the gate can only
     *   err towards not showing the action.
     * - s55B(2)(b),(ba): the recipient may be liable only at the basic,
     *   savings, dividend-ordinary and nil rates, i.e. their total income
     *   stays inside the basic-rate band.
     * - s55B(1),(3): the reduction is the basic rate × the transferable
     *   amount (income_tax.marriage_allowance.amount).
     * - s23 Step 6 and s26: the reduction comes off the tax calculated at
     *   Step 5, so it can never exceed the recipient's tax.
     * - s55B(6): the transferor's Personal Allowance falls by the transferable
     *   amount, so any extra tax they then pay comes off the household saving.
     *   Income the smaller allowance no longer covers can fall at a nil rate
     *   (starting rate for savings s12, Personal Savings Allowance s12B,
     *   dividend allowance s13A), so a transferor above the allowance can
     *   still save the household tax.
     *
     * The spouse's income must be known: a non-earner (single_earner_couple)
     * or captured income (dual_earner). Returns null when nothing is saved.
     *
     * @return array{saving: float, direction: 'to_user'|'to_spouse', user_income: float, spouse_income: float, transferor_extra_tax: float}|null
     */
    public function marriageAllowance(User $user, string $mode, ?TaxStrategyHouseholdInput $household): ?array
    {
        if (! $this->isMarriedOrCivilPartner($user)) {
            return null;
        }

        $partner = $this->partnerTaxPosition($user, $mode, $household);
        if ($partner === null) {
            return null;
        }
        $linked = $partner['linked'];
        $spouse = $partner['parts'];
        $spouseExtension = $partner['band_extension'];
        $amount = $this->marriageAllowanceAmount();
        if ($linked !== null) {
            $spouseBandAt = fn (float $extra): string => $this->bandFromIncomeFor($linked, $this->taxableIncomeFor($linked) + $extra);
        } else {
            $spouseNetGiven = $spouse['non_savings'] + $spouse['interest'] + $spouse['dividends'];
            $spouseBandAt = fn (float $extra): string => $this->bandFromIncome($spouseNetGiven + $extra);
        }

        $user_ = $this->incomePartsFor($user);
        // Tax is priced on the band the gate reads: Gift Aid and relief-at-source
        // contributions extend it (ITA 2007 s414, FA 2004 s192(4)). Priced
        // without that, a donor's £1,260 would be taxed as if above a band they
        // are inside (tax compliance review of #1031).
        $userExtension = $this->bandExtensionFor($user);
        $maxReduction = $amount * $this->bandRateForBand('basic');
        $userNet = $user_['non_savings'] + $user_['interest'] + $user_['dividends'] + $user_['trust'] - $user_['net_pay'];
        $spouseNet = $spouse['non_savings'] + $spouse['interest'] + $spouse['dividends'] + $spouse['trust'] - $spouse['net_pay'];
        $userBandAt = fn (float $extra): string => $this->bandFromIncomeFor($user, $this->taxableIncomeFor($user) + $extra);

        // Each way round: the recipient pays no rate above the basic rate
        // (s55B(2)(b),(ba)), nor does the transferor once their allowance is
        // reduced by the transferable amount (s55C(1)(c),(ca)).
        $options = [];
        $extraTax = [];
        if ($spouseBandAt($amount) === 'basic' && $userBandAt(0.0) === 'basic') {
            $extraTax['to_user'] = $this->extraTaxFromLosingAllowance($spouse, $amount, $spouseExtension);
            $options['to_user'] = min($maxReduction, $this->incomeTaxWithBandExtension($user_, $userExtension)) - $extraTax['to_user'];
        }
        if (($mode === 'dual_earner' || $linked !== null)
            && $userBandAt($amount) === 'basic' && $spouseBandAt(0.0) === 'basic') {
            $extraTax['to_spouse'] = $this->extraTaxFromLosingAllowance($user_, $amount, $userExtension);
            $options['to_spouse'] = min($maxReduction, $this->incomeTaxWithBandExtension($spouse, $spouseExtension)) - $extraTax['to_spouse'];
        }

        $options = array_filter($options, fn (float $saving): bool => $saving >= 0.01);
        if ($options === []) {
            return null;
        }
        arsort($options);
        $direction = (string) key($options);

        return [
            'saving' => round((float) reset($options), 2),
            'direction' => $direction,
            'user_income' => round($userNet, 2),
            'spouse_income' => round($spouseNet, 2),
            'transferor_extra_tax' => round($extraTax[$direction], 2),
        ];
    }

    /**
     * The linked spouse, when their own records hold income. Their records
     * win over the figures the user gave (2026-09-28); when they hold none,
     * nothing is known from them, and the income the user gave for them is
     * the figure (csjones 2026-09-30: an empty record read as £0 offered
     * Marriage Allowance to a £32,000 / £72,000 couple).
     */
    public function linkedSpouseWithIncome(User $user): ?User
    {
        $linked = $user->liveSpouse();
        if ($linked === null) {
            return null;
        }
        $parts = $this->incomePartsFor($linked);

        return ($parts['non_savings'] + $parts['interest'] + $parts['dividends'] + $parts['trust']) > 0 ? $linked : null;
    }

    /**
     * The transferable amount when the user RECEIVES a Marriage Allowance that
     * saves tax, else 0. The spouse's Personal Allowance is then reduced by it
     * (s55B(6)) and cannot also shelter gifted interest.
     */
    public function marriageAllowanceTransfer(User $user, string $mode, ?TaxStrategyHouseholdInput $household): float
    {
        return ($this->marriageAllowance($user, $mode, $household)['direction'] ?? null) === 'to_user'
            ? $this->marriageAllowanceAmount()
            : 0.0;
    }

    /**
     * The spouse's income as the tax engine stacks it, from the one place both
     * Marriage Allowance and a savings move read it. A linked spouse's own
     * records beat the onboarding answers: "does not work" is not "has no
     * income", and a spouse with a pension or rent at or above the Personal
     * Allowance cannot give any of it away. Records that hold no income are
     * not an answer: the income given is used. Not working is not the same as
     * no income either, so only a captured figure counts (CSJ 2026-09-28).
     *
     * `savings_known` says whether their own savings interest is known: from
     * their records, an interest figure given for them, or savings given as
     * £0 (CSJ 2026-10-01, D1/D2). When it is not, their interest is counted as
     * their share of joint accounts only.
     *
     * @return array{parts: array{non_savings: float, interest: float, dividends: float, trust: float, net_pay: float}, band_extension: float, linked: ?User, savings_known: bool}|null
     */
    public function partnerTaxPosition(User $user, string $mode, ?TaxStrategyHouseholdInput $household): ?array
    {
        $linked = $this->linkedSpouseWithIncome($user);
        if ($linked !== null) {
            return [
                'parts' => $this->incomePartsFor($linked),
                'band_extension' => $this->bandExtensionFor($linked),
                'linked' => $linked,
                'savings_known' => true,
            ];
        }
        if (! in_array($mode, ['single_earner_couple', 'dual_earner'], true) || $household?->spouse_annual_income === null) {
            return null;
        }

        $ownInterest = $household->spouse_annual_savings_interest;
        $savingsGivenAsNone = $household->spouse_existing_savings_balance !== null
            && (float) $household->spouse_existing_savings_balance === 0.0;

        return [
            'parts' => [
                'non_savings' => (float) $household->spouse_annual_income,
                'interest' => $this->estimateSpouseJointInterest($user) + (float) ($ownInterest ?? 0),
                'dividends' => (float) ($household->spouse_annual_dividends ?? 0),
                'trust' => 0.0,
                'net_pay' => 0.0,
            ],
            'band_extension' => 0.0,
            'linked' => null,
            'savings_known' => $ownInterest !== null || $savingsGivenAsNone,
        ];
    }

    /**
     * The user's savings priced for a gift to their spouse: sole-name and
     * outside an ISA. Interest on a joint account is already taxed half each
     * between spouses living together (ITA 2007 s836) unless they declare
     * unequal shares (s837), so joint accounts are left out by choice;
     * ownership_type decides, because the campaign's joint accounts carry a
     * null co-owner. Interest from each
     * account's own rate. `accounts` lists each balance and its rate, highest
     * rate first: a gift moves the most interest per pound from there.
     *
     * @return array{balance: float, interest: float, accounts: list<array{balance: float, rate: float}>}
     */
    public function soleNonIsaSavings(User $user): array
    {
        $accounts = app(SavingsStore::class)->forUser($user)
            ->where('user_id', $user->id)
            ->reject(fn ($acc) => $this->isSharedOwnership($acc))
            ->where('is_isa', false);

        return [
            'balance' => (float) $accounts->sum('current_balance'),
            'interest' => (float) $accounts->sum(fn ($acc) => (float) $acc->current_balance * SavingsInterestRate::fraction($acc->interest_rate)),
            'accounts' => $accounts
                ->map(fn ($acc): array => ['balance' => (float) $acc->current_balance, 'rate' => SavingsInterestRate::fraction($acc->interest_rate)])
                ->sortByDesc('rate')
                ->values()
                ->all(),
        ];
    }

    /**
     * The savings interest the user can move to their spouse that saves the
     * household the most tax this year, and what it saves (TODO item 4; spec
     * docs/superpowers/specs/2026-10-01-savings-to-lower-tax-partner-design.md).
     *
     * Interest on savings given outright to a spouse is theirs for tax (ITTOIA
     * 2005 s626), so moving £X of interest lowers the user's tax and raises
     * the spouse's. Both sides are priced by the one tax engine on each
     * person's whole income, so each one's Personal Allowance, starting rate
     * for savings (ITA 2007 s12), savings nil rate (s12A) and Personal
     * Savings Allowance (s12B) are
     * applied as HMRC would. The best amount is found in £10 steps: moving
     * more than it shifts interest the user paid little on, or the spouse pays
     * more on, and a band change can make the curve jump (s12B), so every
     * step is priced rather than assuming a shape.
     *
     * $pensionPaid and $interestSheltered are what other items in the same
     * plan already do to the user's income, as interestRemovalSaving takes
     * them. With $exactInterest, that amount is priced instead of searched
     * (a card rounds the amount, then shows what that amount saves). Null
     * when the spouse's income or savings are not known, or when nothing
     * moved saves at least £1.
     *
     * @return array{interest_moved: float, saving: float, user_tax_saved: float, partner_extra_tax: float}|null
     */
    public function savingsMoveToPartner(
        User $user,
        string $mode,
        ?TaxStrategyHouseholdInput $household,
        float $movableInterest,
        float $pensionPaid = 0.0,
        float $interestSheltered = 0.0,
        ?float $exactInterest = null,
    ): ?array {
        $partner = $this->partnerTaxPosition($user, $mode, $household);
        if ($partner === null || ! $partner['savings_known'] || $movableInterest < 10) {
            return null;
        }

        $userParts = $this->pricingPartsFor($user, $interestSheltered);
        $userParts['net_pay'] += $pensionPaid;
        $movableInterest = min($movableInterest, $userParts['interest']);
        $partnerParts = $partner['linked'] !== null ? $this->pricingPartsFor($partner['linked'], 0.0) : $partner['parts'];
        // A Marriage Allowance transfer to the user takes that slice of the
        // spouse's Personal Allowance (s55B(6)); it cannot also cover interest.
        if ($this->marriageAllowanceTransfer($user, $mode, $household) > 0) {
            $partnerParts['non_savings'] += $this->marriageAllowanceAmount();
        }

        $userBefore = $this->incomeTaxOn($userParts);
        $partnerBefore = $this->incomeTaxOn($partnerParts);
        $price = function (float $x) use ($userParts, $partnerParts, $userBefore, $partnerBefore): array {
            $userAfter = $userParts;
            $userAfter['interest'] -= $x;
            $partnerAfter = $partnerParts;
            $partnerAfter['interest'] += $x;
            $userSaved = $userBefore - $this->incomeTaxOn($userAfter);
            $partnerExtra = $this->incomeTaxOn($partnerAfter) - $partnerBefore;

            return ['interest_moved' => $x, 'saving' => $userSaved - $partnerExtra, 'user_tax_saved' => $userSaved, 'partner_extra_tax' => $partnerExtra];
        };

        $best = null;
        if ($exactInterest !== null) {
            $best = $price(min($exactInterest, $movableInterest));
        } else {
            $step = max(10.0, ceil($movableInterest / 1000 / 10) * 10);
            $x = 0.0;
            while ($x < $movableInterest) {
                $x = min($x + $step, $movableInterest);
                $priced = $price($x);
                if ($best === null || $priced['saving'] > $best['saving'] + 0.005) {
                    $best = $priced;
                }
            }
        }
        if ($best === null || $best['saving'] < 1) {
            return null;
        }

        return array_map(fn (float $v): float => round($v, 2), $best);
    }

    /**
     * The user's income split the way the tax engine stacks it (ITA 2007 s16).
     * The total comes from IncomeDefinitionsService, so salary sacrifice is
     * already resolved. Net-pay contributions are the workplace contributions
     * taken from pay.
     *
     * @return array{non_savings: float, interest: float, dividends: float, trust: float, net_pay: float}
     */
    public function incomePartsFor(User $user): array
    {
        $definitions = $this->incomeDefinitionsFor($user);
        $components = is_array($definitions['components'] ?? null) ? $definitions['components'] : [];
        $interest = (float) ($components['interest'] ?? 0);
        $dividends = (float) ($components['dividend'] ?? 0);
        $trust = (float) ($components['trust'] ?? 0);
        $salary = (float) ($user->annual_employment_income ?? 0);

        $netPay = (float) app(PensionStore::class)->forUserByType($user, 'dc')
            ->filter(fn ($p) => empty($p->salary_sacrifice) && PensionContributionRule::isWorkplace($p))
            ->sum(fn ($p) => PensionContributionRule::monthlyEmployee($p, $salary) * 12);

        return [
            'non_savings' => max(0.0, (float) ($definitions['total_income'] ?? 0) - $interest - $dividends - $trust),
            'interest' => $this->resolvedInterest($user, $definitions),
            'dividends' => $dividends,
            'trust' => $trust,
            'net_pay' => $netPay,
        ];
    }

    /**
     * Income tax at ITA 2007 s23 Step 5 (before tax reductions), from the
     * app's one tax engine, UKTaxCalculator. Non-savings income goes in the
     * employment slot: only the income tax total is read, and non-savings
     * income is stacked first whatever its source (ITA 2007 s16).
     *
     * @param  array{non_savings: float, interest: float, dividends: float, trust?: float, net_pay?: float}  $parts
     */
    public function incomeTaxOn(array $parts): float
    {
        $result = app(UKTaxCalculator::class)->calculateDetailedNetIncome(
            employmentIncome: $parts['non_savings'],
            trustIncome: (float) ($parts['trust'] ?? 0),
            interestIncome: $parts['interest'],
            dividendIncome: $parts['dividends'],
            pensionContributions: (float) ($parts['net_pay'] ?? 0),
            blindPersonsAllowance: (float) ($parts['blind_persons_allowance'] ?? 0),
        );

        return (float) $result['summary']['total_income_tax_before_credits'];
    }

    /** The user's Income Tax for the year as things stand, from the one tax engine. */
    public function incomeTaxNow(User $user): float
    {
        return $this->incomeTaxOn($this->pricingPartsFor($user, 0.0));
    }

    /**
     * Income tax saved by a further gross pension contribution of $gross: the
     * tax on the user's income now less the tax once it is paid, both from the
     * one tax engine (incomeTaxOn), so the Personal Allowance taper, the
     * Personal Savings Allowance and the dividend rates are all priced as
     * HMRC would. Relief-at-source contributions and Gift Aid already made
     * reduce adjusted net income and move the bands just as a net-pay
     * contribution does (FA 2004 s192(4), ITA 2007 s414, s58), so they sit
     * in the same deduction. $interestSheltered is interest another item in
     * the same plan already moves into an ISA.
     */
    public function pensionContributionSaving(User $user, float $gross, float $interestSheltered = 0.0): float
    {
        return $this->pensionContributionSavingOn($this->pricingPartsFor($user, $interestSheltered), $gross);
    }

    /**
     * The same saving for income parts with no User behind them. The public
     * /savetax funnel prices its pension lines here, so the figure it promises
     * and the figure the plan delivers come from one calculation.
     *
     * @param  array{non_savings: float, interest: float, dividends: float, trust?: float, net_pay?: float}  $parts
     */
    public function pensionContributionSavingOn(array $parts, float $gross): float
    {
        $after = $parts;
        $after['net_pay'] = (float) ($after['net_pay'] ?? 0) + $gross;

        return max(0.0, $this->incomeTaxOn($parts) - $this->incomeTaxOn($after));
    }

    /**
     * Saving on a relief-at-source contribution of $gross by someone with no
     * relevant earnings. Basic-rate relief is added at source whether or not
     * they pay tax (FA 2004 s192(1),
     * https://www.legislation.gov.uk/ukpga/2004/12/section/192); a taxpayer's
     * band extension on a claim (s192(4)) and the allowance won back through
     * adjusted net income (ITA 2007 s58) can only add to it. Counted in the
     * headline (29 Sep 2026: the user's own top-up counts, as the spouse's
     * does under the CSJ ruling of 2026-09-25).
     *
     * @param  array{non_savings: float, interest: float, dividends: float, trust?: float, net_pay?: float}  $parts
     */
    public function reliefAtSourceSavingOn(array $parts, float $gross): float
    {
        $atSource = $gross * (float) $this->taxConfig->getPensionAllowances()['tax_relief']['basic_rate'];

        return max($atSource, $this->pensionContributionSavingOn($parts, $gross));
    }

    public function reliefAtSourceSaving(User $user, float $gross, float $interestSheltered = 0.0): float
    {
        return $this->reliefAtSourceSavingOn($this->pricingPartsFor($user, $interestSheltered), $gross);
    }

    /**
     * Gross contributions that can get tax relief: the greater of relevant UK
     * earnings and the basic amount (FA 2004 s190,
     * https://www.legislation.gov.uk/ukpga/2004/12/section/190), from
     * pension.relevant_earnings_minimum.
     */
    public function pensionReliefLimit(float $relevantEarnings): float
    {
        return max($relevantEarnings, (float) $this->taxConfig->getPensionAllowances()['relevant_earnings_minimum']);
    }

    /**
     * Earnings from work the profile captures: pay and self-employment profit
     * (FA 2004 s189(2), https://www.legislation.gov.uk/ukpga/2004/12/section/189).
     */
    public function relevantEarningsFor(User $user): float
    {
        return (float) ($user->annual_employment_income ?? 0) + (float) ($user->annual_self_employment_income ?? 0);
    }

    /**
     * Someone who has told us they do not work (retired or not employed) and
     * has no earnings. £0 of pay on a profile with no employment status is
     * "not asked yet", not "none".
     */
    public function isDeclaredNonEarner(User $user): bool
    {
        return $this->relevantEarningsFor($user) <= 0
            && in_array($user->employment_status, ['retired', 'unemployed'], true);
    }

    /**
     * Contribution that takes adjusted net income back down to the Personal
     * Allowance taper threshold (ITA 2007 s35), then $belowTaper more of
     * income relieved at the higher rate beneath it, capped by the Annual
     * Allowance left and rounded down to the nearest £100 — rounding up would
     * relieve tax that is not paid. The /savetax funnel passes no $belowTaper
     * (CSJ 2026-10-01: its trap line stays at the taper slice).
     */
    public function taperRescueContribution(float $adjustedNetIncome, float $availableAA, float $belowTaper = 0.0): float
    {
        $taperThreshold = (float) $this->taxConfig->getIncomeTax()['personal_allowance_taper_threshold'];

        return floor(max(0.0, min($adjustedNetIncome - $taperThreshold + max(0.0, $belowTaper), $availableAA)) / 100) * 100;
    }

    /**
     * Income actually taxed at the higher rate: non-savings income above the
     * limit, plus interest above it that the Personal Savings Allowance does
     * not cover. The allowance is a nil rate on the first slice of savings
     * income (ITA 2007 s12B, https://www.legislation.gov.uk/ukpga/2007/3/section/12B),
     * and savings income sits above non-savings income (s16). Dividends are
     * taxed at the dividend rates (s8), never the higher rate, so they are
     * left out: the slice can only understate, never overstate, the relief.
     * Both pension cards that relieve at the higher rate size from here
     * (PensionTaxReliefStrategy, IncomeBandStrategy).
     */
    public function higherRateSlice(User $user, float $taxable, float $limit): float
    {
        $parts = $this->incomePartsFor($user);
        $interest = $parts['interest'];
        $nonSavings = max(0.0, $taxable - $interest - $parts['dividends']);
        $interestAbove = max(0.0, min($interest, $nonSavings + $interest - $limit));
        $allowanceLeft = max(0.0, $this->psaForBand('higher') - ($interest - $interestAbove));

        return max(0.0, $nonSavings - $limit) + max(0.0, $interestAbove - $allowanceLeft);
    }

    /**
     * Contribution for an additional-rate taxpayer: the slice above the
     * additional-rate threshold, then the taper band, then the higher-rate band
     * down to its threshold (never below it: relief there is only the basic
     * rate), capped by the Annual Allowance left and rounded down to £100.
     *
     * @param  array{higher: float, additional: float}  $thresholds
     * @return array{contribution: float, additional_slice: float, taper_slice: float}
     */
    public function additionalRateAvoidanceContribution(float $taxableIncome, float $availableAA, array $thresholds): array
    {
        $taperThreshold = (float) $this->taxConfig->getIncomeTax()['personal_allowance_taper_threshold'];
        $additionalSlice = min(max(0.0, $taxableIncome - $thresholds['additional']), $availableAA);
        $remaining = max(0, $availableAA - $additionalSlice);
        $taperSlice = min($remaining, $thresholds['additional'] - $taperThreshold);
        $remainingAfterTaper = max(0, $remaining - $taperSlice);
        $belowTaperSlice = min($remainingAfterTaper, max(0, $taperThreshold - $thresholds['higher']));

        return [
            'contribution' => floor(($additionalSlice + $taperSlice + $belowTaperSlice) / 100) * 100,
            'additional_slice' => $additionalSlice,
            'taper_slice' => $taperSlice,
        ];
    }

    /**
     * Income tax saved when $interest of the user's interest stops being
     * theirs (wrapped in an ISA, or given to a spouse), priced by the tax
     * engine: the starting rate for savings (ITA 2007 s12), the Personal
     * Savings Allowance (s12B) and every band the interest spans are applied
     * as HMRC would, not a flat marginal rate. $pensionPaid is a gross pension
     * contribution the same plan already makes, priced before the interest
     * move (FA 2004 s192(4), ITA 2007 s58).
     */
    public function interestRemovalSaving(User $user, float $interest, float $interestSheltered = 0.0, float $pensionPaid = 0.0): float
    {
        $parts = $this->pricingPartsFor($user, $interestSheltered);
        $parts['net_pay'] += $pensionPaid;
        $after = $parts;
        $after['interest'] = max(0.0, $after['interest'] - $interest);

        return max(0.0, $this->incomeTaxOn($parts) - $this->incomeTaxOn($after));
    }

    /**
     * What the donor reclaims on their Gift Aid: the tax the grossed-up gift
     * saves them (band extension, ITA 2007 s414, and the Personal Allowance it
     * wins back through adjusted net income, s58) less the basic-rate tax the
     * charity already claimed. Priced by the tax engine, so the Personal
     * Savings Allowance and any taper are applied as HMRC would.
     */
    public function giftAidDonorReclaim(User $user): float
    {
        $gross = (float) ($this->incomeDefinitionsFor($user)['deductions']['gift_aid_gross'] ?? 0);
        if ($gross <= 0) {
            return 0.0;
        }
        $with = $this->pricingPartsFor($user, 0.0);
        $without = $with;
        $without['net_pay'] = max(0.0, $without['net_pay'] - $gross);

        return max(0.0, $this->incomeTaxOn($without) - $this->incomeTaxOn($with) - $gross * $this->bandRateForBand('basic'));
    }

    /**
     * What a donor who does not yet use Gift Aid would reclaim once they do:
     * the net gifts grossed up at the basic rate (ITA 2007 s414) extend their
     * bands and win back Personal Allowance (s58), less the basic-rate tax the
     * charity claims. 0 for a basic-rate donor. Priced by the tax engine.
     */
    public function giftAidReclaimIfDeclared(User $user, float $netDonations): float
    {
        $gross = $netDonations / (1 - $this->bandRateForBand('basic'));
        $without = $this->pricingPartsFor($user, 0.0);
        $with = $without;
        $with['net_pay'] += $gross;

        return max(0.0, $this->incomeTaxOn($without) - $this->incomeTaxOn($with) - $gross * $this->bandRateForBand('basic'));
    }

    /**
     * The user's income parts for pricing a change: interest another item in
     * the same plan already moves is taken out, and relief-at-source
     * contributions and Gift Aid already made sit with the net-pay deduction
     * because they reduce adjusted net income and move the bands the same way
     * (FA 2004 s192(4), ITA 2007 s414, s58).
     *
     * @return array{non_savings: float, interest: float, dividends: float, trust: float, net_pay: float}
     */
    private function pricingPartsFor(User $user, float $interestSheltered): array
    {
        $parts = $this->incomePartsFor($user);
        $parts['interest'] = max(0.0, $parts['interest'] - $interestSheltered);
        $deductions = $this->incomeDefinitionsFor($user)['deductions'] ?? [];
        $parts['net_pay'] += (float) ($deductions['relief_at_source_gross'] ?? 0) + (float) ($deductions['gift_aid_gross'] ?? 0);
        // Priced as the tax calculator prices it (ITA 2007 s38).
        $parts['blind_persons_allowance'] = $this->taxConfig->blindPersonsAllowanceFor($user);

        return $parts;
    }

    /**
     * Extra tax a transferor pays once their Personal Allowance falls by
     * $amount (s55B(6)). A smaller allowance taxes exactly the income that
     * $amount of extra non-savings income would, because non-savings income
     * is stacked first (ITA 2007 s16) and the transferor is far below the
     * taper threshold.
     *
     * @param  array{non_savings: float, interest: float, dividends: float, trust?: float, net_pay?: float}  $parts
     */
    private function extraTaxFromLosingAllowance(array $parts, float $amount, float $bandExtension = 0.0): float
    {
        $reduced = $parts;
        $reduced['non_savings'] += $amount;

        return max(0.0, $this->incomeTaxWithBandExtension($reduced, $bandExtension) - $this->incomeTaxWithBandExtension($parts, $bandExtension));
    }

    /**
     * Gross Gift Aid and relief-at-source contributions: both extend the basic
     * and higher rate limits (ITA 2007 s414, FA 2004 s192(4)), as
     * bandThresholdsFor reads them.
     */
    private function bandExtensionFor(User $user): float
    {
        $deductions = $this->incomeDefinitionsFor($user)['deductions'] ?? [];

        return (float) ($deductions['gift_aid_gross'] ?? 0) + (float) ($deductions['relief_at_source_gross'] ?? 0);
    }

    /**
     * Income tax from the one tax engine, UKTaxCalculator, with the band
     * extended by $bandExtension and adjusted net income reduced by it (ITA
     * 2007 s414, s58; FA 2004 s192(4)). Net-pay contributions come off pay as
     * in incomeTaxOn.
     *
     * @param  array{non_savings: float, interest: float, dividends: float, trust?: float, net_pay?: float}  $parts
     */
    private function incomeTaxWithBandExtension(array $parts, float $bandExtension): float
    {
        return (float) app(UKTaxCalculator::class)->calculateNetIncome(
            employmentIncome: $parts['non_savings'],
            dividendIncome: $parts['dividends'],
            interestIncome: $parts['interest'],
            otherIncome: (float) ($parts['trust'] ?? 0),
            pensionContributions: (float) ($parts['net_pay'] ?? 0),
            giftAidGross: $bandExtension,
            blindPersonsAllowance: (float) ($parts['blind_persons_allowance'] ?? 0),
        )['income_tax'];
    }

    /**
     * The Income Tax the user is liable to for the year as things stand, for a
     * figure shown as "Income Tax": Gift Aid and relief-at-source payments
     * extend the bands rather than coming off income (ITA 2007 s414, FA 2004
     * s192(4)), since their basic-rate relief is given at source; the Blind
     * Person's Allowance applies (s38). The plan's own pricing
     * (pricingPartsFor) still treats them as deductions; see TODO item 2.
     */
    public function incomeTaxLiability(User $user): float
    {
        $parts = $this->incomePartsFor($user);
        $parts['blind_persons_allowance'] = $this->taxConfig->blindPersonsAllowanceFor($user);

        return round($this->incomeTaxWithBandExtension($parts, $this->bandExtensionFor($user)), 2);
    }

    /**
     * Relief-at-source figures for a contribution by or for someone with no
     * relevant earnings. Gross is the configured limit, relief is basic-rate
     * relief on it, and net is what the payer actually hands over.
     *
     * @return array{gross: float, net: float, relief: float}
     */
    /**
     * The most a declared non-earner can pay into a pension from what they
     * hold: their share of recorded cash savings is the net payment, grossed
     * up by the basic-rate relief the provider adds (FA 2004 s192). CSJ
     * 2026-09-29: suggest the basic amount only when savings cover it,
     * otherwise what they can afford.
     */
    public function nonEarnerFundableGross(User $user): float
    {
        $cash = app(CrossModuleAssetAggregator::class)->calculateCashTotal($user->id);
        $relief = (float) $this->taxConfig->getPensionAllowances()['tax_relief']['basic_rate'];

        return $relief < 1 ? round($cash / (1 - $relief), 2) : 0.0;
    }

    public function nonEarnerPensionContribution(): array
    {
        $pension = $this->taxConfig->getPensionAllowances();
        $gross = (float) ($pension['relevant_earnings_minimum'] ?? 0);
        $relief = round($gross * (float) ($pension['tax_relief']['basic_rate'] ?? 0), 2);

        return ['gross' => $gross, 'net' => round($gross - $relief, 2), 'relief' => $relief];
    }

    /**
     * Share of a net Gift Aid donation a higher- or additional-rate taxpayer
     * reclaims through Self Assessment: the grossed-up gift (net ÷ (1 − basic))
     * times the gap between their rate and the basic rate. 0 at basic rate.
     */
    public function giftAidReclaimFactor(string $band): float
    {
        if (! in_array($band, ['higher', 'additional'], true)) {
            return 0.0;
        }

        $basic = $this->bandRateForBand('basic');

        return $basic < 1 ? round(($this->bandRateForBand($band) - $basic) / (1 - $basic), 4) : 0.0;
    }

    /**
     * Dividend tax rate for a given band, sourced from
     * TaxConfigService['dividend_tax']. Centralises the match block previously
     * duplicated across DividendAllowanceHarvestStrategy, AssetShiftingBundle-
     * Strategy, and CrossSpouseBundleStrategy.
     */
    public function dividendRateForBand(string $band): float
    {
        $div = $this->taxConfig->getDividendTax();

        return match (strtolower($band)) {
            'higher' => (float) $div['higher_rate'],
            'additional' => (float) $div['additional_rate'],
            default => (float) $div['basic_rate'],
        };
    }

    /**
     * Age in whole years from a date_of_birth, or null when DOB is unknown.
     * Mirrors FamilyMember::getAgeAttribute.
     */
    public function ageOf(mixed $dateOfBirth): ?int
    {
        if ($dateOfBirth === null) {
            return null;
        }

        $dob = $dateOfBirth instanceof \DateTimeInterface
            ? Carbon::instance($dateOfBirth)
            : Carbon::parse((string) $dateOfBirth);

        return (int) $dob->diffInYears(now());
    }

    /** @return array<string, mixed> */
    /**
     * The user's income definitions (IncomeDefinitionsService), cached per user:
     * the Income page's figures, and the one home for "this user's income" that
     * Fyn reads too (audit item 44).
     */
    public function incomeDefinitionsFor(User $user): array
    {
        // From the model in hand (calculateFor), so an unsaved model works too;
        // only a saved user is cached, by id.
        if (! $user->exists) {
            return $this->incomeDefinitions->calculateFor($user);
        }

        $key = (int) $user->id;
        if (! isset($this->incomeDefinitionsCache[$key])) {
            $this->incomeDefinitionsCache[$key] = $this->incomeDefinitions->calculateFor($user);
        }

        return $this->incomeDefinitionsCache[$key];
    }

    /** @param array<string, mixed> $definitions */
    private function interestAdjustment(User $user, array $definitions): float
    {
        $components = is_array($definitions['components'] ?? null) ? $definitions['components'] : [];

        return $this->resolvedInterest($user, $definitions) - (float) ($components['interest'] ?? 0);
    }

    /** @param array<string, mixed> $definitions */
    private function resolvedInterest(User $user, array $definitions): float
    {
        $components = is_array($definitions['components'] ?? null) ? $definitions['components'] : [];
        $captured = (float) ($components['interest'] ?? 0);

        // An unsaved model holds no savings accounts to estimate interest from.
        if ($captured > 0 || ! $user->exists) {
            return $captured;
        }

        return $this->estimateAnnualInterest($user);
    }
}
