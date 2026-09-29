<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Services\Onboarding\FunnelIncomeBand;
use App\Services\Stores\SavingsMarketRateStore;
use App\Services\Tax\Strategies\PensionTaxReliefStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use LogicException;

/**
 * SaveTaxEstimateService — drives the dynamic numbers on the /savetax funnel
 * result page (savetax-plan).
 *
 * Given the funnel answers (income band, spouse, spouse income band, assets) it
 * returns two things:
 *   - "savings": the estimated annual tax saving, broken into line items.
 *   - "allowances": the tax-free / tax-relievable allowances available, with a total.
 *
 * Every tax value is read from TaxConfigService (Rule #2 — never hard-coded).
 *
 * Income is collected as a BAND, not a precise figure; per CSJ we assume the
 * UPPER bound of each band. Pension relief is computed with an exact income-tax
 * engine (Personal Allowance taper + basic/higher band extension + 20% relief at
 * source), so the £100k–£125,140 "60% trap" emerges naturally.
 *
 * The math model is documented in June/June8Updates/savetax-math-spec.md.
 */
class SaveTaxEstimateService
{
    /** Stable funnel keys; their monetary boundaries come from TaxConfigService. */
    private const BAND_KEYS = [
        'zero',
        'upto_50270',
        '50271_100000',
        '100001_125140',
        'over_125140',
    ];

    /** The income band that sits inside the £100k Personal Allowance taper. */
    private const TRAP_BAND = '100001_125140';

    /** Employment answers whose income is presumed not to be relevant earnings. */
    private const NON_EARNING_EMPLOYMENT = ['retired', 'not-employed'];

    /** Saving lines that belong to the partner rather than the user. */
    private const PARTNER_SAVING_KEYS = ['spouse_tax_trap_60'];

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly TaxStrategyMath $math,
    ) {}

    /**
     * @param  array{employment?:?string,income?:string,spouse?:string,spouseIncome?:?string,assets?:array<int,string>}  $answers
     * @return array<string,mixed>
     */
    public function estimate(array $answers): array
    {
        $incomeBand = $this->normaliseBand($answers['income'] ?? null, 'upto_50270');
        $married = ($answers['spouse'] ?? null) === 'yes';
        $spouseBand = $married ? $this->normaliseBand($answers['spouseIncome'] ?? null, null) : null;
        $assets = array_values(array_filter((array) ($answers['assets'] ?? [])));

        $income = $this->incomeForBand($incomeBand);
        $rate = $this->marginalRate($income);

        $has = fn (string ...$keys): bool => (bool) array_intersect($keys, $assets);
        $hasFinancial = $has('isa', 'savings', 'investments', 'bank');

        $savings = [];

        // --- Pension / 60% tax trap -----------------------------------------
        // Shown whether or not the user already has a pension (CSJ 2026-09-26):
        // holding a pension says nothing about how much Annual Allowance is
        // left. Sized by the plan engine's own rules and priced by its tax
        // engine (TaxStrategyMath), so the funnel promises what the plan finds.
        $pensionLine = $this->pensionLine($income, $this->hasNoEarnings($answers['employment'] ?? null, $incomeBand));
        if ($pensionLine !== null) {
            $savings[] = $pensionLine;
        }

        // --- ISA -------------------------------------------------------------
        // An ISA saves the tax on the interest, not the money moved (audit
        // 2026-09-27): interest at the admin-managed easy-access benchmark, less
        // the Personal Savings Allowance that already covers it (ITA 2007
        // s12B), at the marginal rate. No line when nothing is taxed.
        if ($hasFinancial) {
            $isaAssumed = (int) round($income * 0.10);
            $store = app(SavingsMarketRateStore::class);
            $benchmark = (float) ($store->findByKeyAndTaxYear('easy_access', (string) $store->latestTaxYear())?->rate ?? 0);
            $taxedInterest = max(0.0, $isaAssumed * $benchmark - $this->personalSavingsAllowance($income));
            $isaSaving = (int) floor($taxedInterest * $rate);
            if ($isaSaving > 0) {
                $savings[] = [
                    'key' => 'isa',
                    'label' => 'ISA allowance',
                    'amount' => $isaSaving,
                    'reason' => 'Interest on '.$this->money($isaAssumed).' of savings held in an ISA is tax-free.',
                ];
            }
        }

        // Own Personal Savings, Dividend and Capital Gains Tax allowances are
        // given automatically, so they are not savings the user can make
        // (CSJ 2026-09-27; plan ruling 2026-09-25). They stay in 'allowances'.

        // --- Marriage Allowance ----------------------------------------------
        // Salary cannot be moved to a spouse (it is taxed on the employee,
        // ITEPA 2003 s62), so the funnel no longer prices a spouse's Personal
        // Allowance, Savings Allowance or starting rate as if income could be
        // moved into them; the plan engine never produces those figures.
        // Marriage Allowance is the transfer the law allows.
        $marriage = $married && $spouseBand !== null ? $this->marriageAllowanceLine($income, $spouseBand) : null;
        if ($marriage !== null) {
            $savings[] = $marriage;
        }

        // --- Partner in the 60% tax trap ------------------------------------
        if ($married && $spouseBand === self::TRAP_BAND) {
            $spouseIncome = $this->incomeForBand($spouseBand);
            $contribution = $this->taperRescueContribution($spouseIncome);
            if ($contribution > 0) {
                $savings[] = [
                    'key' => 'spouse_tax_trap_60',
                    'label' => "Your partner's ".$this->trapLabel(),
                    'amount' => $this->pensionSaving($spouseIncome, $contribution),
                    'reason' => 'At '.$this->money($spouseIncome).', the top of the band you chose for your partner, paying '.$this->money($contribution).' into their pension reclaims their Personal Allowance, if that income is from work. Income between '.$this->money($this->taperThreshold()).' and '.$this->money($this->taperEnd()).' is taxed at '.$this->pct($this->trapRate()).'.',
                ];
            }
        }

        $savingsTotal = array_sum(array_column($savings, 'amount'));

        return [
            'tax_year' => $this->taxConfig->getTaxYear(),
            'assumed_income' => $income,
            'marginal_rate' => $rate,
            'savings' => $savings,
            'savings_total' => $savingsTotal,
            // What the headline has to say about itself: which income it
            // assumes, and how much of it comes from the partner's side.
            // 'band_top' for a bounded band, 'example' for the open top band
            // (onboarding.savetax_over_band_assumed_income), 'none' for no income.
            'assumed_income_basis' => match ($incomeBand) {
                'zero' => 'none',
                'over_125140' => 'example',
                default => 'band_top',
            },
            'partner_savings_total' => array_sum(array_column(
                array_filter($savings, fn (array $line): bool => in_array($line['key'], self::PARTNER_SAVING_KEYS, true)),
                'amount'
            )),
            'allowances' => $this->allowances($income, $married, $spouseBand, $assets),
        ];
    }

    /**
     * The allowances available (capacity, not saving). Doubles per-person
     * allowances when married and the spouse qualifies.
     *
     * @param  array<int,string>  $assets
     * @return array<string,mixed>
     */
    private function allowances(int $income, bool $married, ?string $spouseBand, array $assets): array
    {
        $has = fn (string ...$keys): bool => (bool) array_intersect($keys, $assets);
        $isa = $this->taxInt('isa.annual_allowance');
        $items = [];

        // Personal Allowance — always shown. In the £100k–£125,140 taper band it
        // carries the 60% tax trap explanation in its note (the standalone trap
        // card is removed; the saving callout attaches to this card in the JS).
        $items[] = $this->personalAllowanceItem('personal_allowance', 'Personal Allowance', $income);

        $items[] = $this->allowanceItem(
            'isa',
            'ISA Allowance',
            $isa,
            'available',
            'Available to shelter eligible savings and investments from tax on income and growth.'
        );
        $items[] = $this->pensionAaItem('pension_aa', 'Pension Annual Allowance', $income);

        $psa = $this->personalSavingsAllowance($income);
        $psaAvailable = $has('savings', 'bank') && $psa > 0;
        $items[] = $this->allowanceItem(
            'psa',
            'Personal Savings Allowance',
            $psa,
            $psaAvailable ? 'available' : 'not_applicable',
            $psaAvailable
                ? 'Available for eligible savings interest within the allowance.'
                : ($psa === 0
                    ? 'Not applicable at the assumed additional-rate income band.'
                    : 'Not applicable because no bank or savings account was selected.')
        );

        $divAllowance = $this->taxInt('dividend_tax.allowance');
        $hasInvestments = $has('investments');
        $items[] = $this->allowanceItem(
            'dividend',
            'Dividend Allowance',
            $divAllowance,
            $hasInvestments ? 'available' : 'not_applicable',
            $hasInvestments
                ? 'Available for eligible dividend income within the allowance.'
                : 'Not applicable because no investments were selected.'
        );

        $cgt = $this->taxInt('capital_gains_tax.annual_exempt_amount');
        $hasChargeableAssets = $has('investments', 'property');
        $items[] = $this->allowanceItem(
            'cgt',
            'Capital Gains Tax Allowance',
            $cgt,
            $hasChargeableAssets ? 'available' : 'not_applicable',
            $hasChargeableAssets
                ? 'Available against eligible gains realised during the tax year.'
                : 'Not applicable because no investments or property were selected.'
        );

        if ($married && $spouseBand !== null) {
            $marriage = $this->taxInt('income_tax.marriage_allowance.amount');
            // Eligible only when a partner with no income pairs with a
            // basic-rate taxpayer, either way round. Shown greyed otherwise.
            $marriageEligible = $this->marriageAllowanceRecipient($income, $spouseBand) !== null;
            $items[] = $this->allowanceItem(
                'marriage_allowance',
                'Marriage Allowance',
                $marriage,
                $marriageEligible ? 'available' : 'not_applicable',
                $marriageEligible
                    ? 'Available because the assumed circumstances pair a partner with no income with a basic-rate taxpayer.'
                    : 'Not applicable to the income bands supplied for this estimate.'
            );

            // Spouse's own per-person allowances (household view).
            $spouseIncome = $this->incomeForBand($spouseBand);
            $items[] = $this->personalAllowanceItem('spouse_pa', "Spouse's Personal Allowance", $spouseIncome, isSpouse: true);

            // Starting Rate for Savings — a non-earning spouse can receive up to
            // £5,000 of savings interest tax-free. Greyed unless the spouse has
            // no income.
            $startingRate = $this->taxInt('income_tax.starting_rate_for_savings.band');
            $spouseNoIncome = $spouseBand === 'zero';
            $items[] = $this->allowanceItem(
                'spouse_starting_rate',
                "Spouse's Starting Rate for Savings",
                $startingRate,
                $spouseNoIncome ? 'available' : 'not_applicable',
                $spouseNoIncome
                    ? 'With no income, your spouse can receive up to '.$this->money($startingRate).' of savings interest tax-free.'
                    : 'Not applicable because the supplied spouse income is above the starting-rate conditions.'
            );

            // Personal Savings Allowance — per person. A non-earning spouse still
            // gets the basic-rate amount; an earning spouse gets their band amount
            // (the same rule as your own card). Greyed without household savings.
            $spousePsa = $this->personalSavingsAllowance($spouseIncome);
            $spousePsaAvailable = $has('savings', 'bank') && $spousePsa > 0;
            $items[] = $this->allowanceItem(
                'spouse_psa',
                "Spouse's Personal Savings Allowance",
                $spousePsa,
                $spousePsaAvailable ? 'available' : 'not_applicable',
                $spousePsaAvailable
                    ? ($spouseNoIncome
                        ? 'A non-earning spouse still gets the basic-rate '.$this->money($spousePsa).' of savings interest tax-free.'
                        : 'Available for your spouse\'s eligible savings interest within the allowance.')
                    : ($spousePsa === 0
                        ? 'Not applicable at the spouse\'s assumed additional-rate income band.'
                        : 'Not applicable because no bank or savings account was selected.')
            );

            $items[] = $this->allowanceItem(
                'spouse_isa',
                "Spouse's ISA Allowance",
                $isa,
                'available',
                'Available in your spouse\'s own individual ISA for eligible savings and investments.'
            );
            $items[] = $this->pensionAaItem('spouse_pension_aa', "Spouse's Pension Annual Allowance", $spouseIncome);

            // Dividend and CGT allowances are also per person — held in the
            // spouse's name they double the household's tax-free headroom. Same
            // gating as your own cards.
            $items[] = $this->allowanceItem(
                'spouse_dividend',
                "Spouse's Dividend Allowance",
                $divAllowance,
                $hasInvestments ? 'available' : 'not_applicable',
                $hasInvestments
                    ? 'Available for eligible dividend income held in your spouse\'s name.'
                    : 'Not applicable because no investments were selected.'
            );
            $items[] = $this->allowanceItem(
                'spouse_cgt',
                "Spouse's Capital Gains Tax Allowance",
                $cgt,
                $hasChargeableAssets ? 'available' : 'not_applicable',
                $hasChargeableAssets
                    ? 'Available against eligible gains realised on assets held in your spouse\'s name.'
                    : 'Not applicable because no investments or property were selected.'
            );
        }

        $total = 0;
        $available = 0;
        foreach ($items as $item) {
            if ($item['state'] === 'available') {
                $total += $item['amount'];
                $available++;
            }
        }

        // `total` adds allowances with different tax meanings together and is
        // not shown any more (Azlan, 2026-09-18: "don't understand the
        // allowances"); the page shows the count. Kept for the tests and any
        // caller reading it.
        return ['items' => $items, 'total' => $total, 'available_count' => $available, 'count' => count($items)];
    }

    /**
     * Build a Personal Allowance allowance-row, flagging the £100k taper so the
     * page can show "tapered to £X" instead of a bare figure.
     *
     * @return array<string,mixed>
     */
    private function personalAllowanceItem(string $key, string $label, int $income, bool $isSpouse = false): array
    {
        $amount = (int) round($this->personalAllowance($income));
        $taper = $this->taperThreshold();
        $taperEnd = (int) round($taper + ($this->personalAllowanceBase() / $this->taxNumber('income_tax.personal_allowance_taper_rate')));
        $inTrap = $income > $taper && $income <= $taperEnd;

        if ($inTrap) {
            // At the exact taper boundary the allowance is £0 today, but it is
            // still actionable because an eligible pension contribution can
            // restore it.
            $state = $amount > 0 ? 'used_automatically' : 'available';
            $explanation = $amount > 0
                ? 'The remaining allowance is used automatically against income. Income over '.$this->money($taper).' reduces it at £1 for every £2; an eligible pension contribution may restore some or all of the tapered amount.'
                : 'The allowance is currently tapered away. An eligible pension contribution may restore some or all of it, subject to pension contribution and allowance rules.';
        } elseif ($income > $taperEnd) {
            $state = 'not_applicable';
            $explanation = 'Not applicable because income over '.$this->money($taperEnd).' has tapered the Personal Allowance away entirely.';
        } elseif ($income > 0) {
            $state = 'used_automatically';
            $explanation = $isSpouse
                ? 'Used automatically against the spouse income assumed for this estimate.'
                : 'Automatically used against your income.';
        } else {
            $state = 'available';
            $explanation = 'Not yet used — available to set against eligible income or savings.';
        }

        return $this->allowanceItem(
            $key,
            $income > $taper ? $label.' (tapered)' : $label,
            $amount,
            $state,
            $explanation
        );
    }

    /**
     * Build a Pension Annual Allowance allowance-row. The Pension Annual
     * Allowance is for pensions, not income: a working earner gets the full
     * £60,000 and it is always shown. A non-earner's figure is capped at the
     * £3,600 relevant-earnings minimum (they can only contribute up to that).
     *
     * @return array<string,mixed>
     */
    private function pensionAaItem(string $key, string $label, int $income): array
    {
        $pension = $this->taxConfig->getPensionAllowances();
        $aa = $this->requiredArrayInt($pension, 'annual_allowance', 'pension.annual_allowance');
        $nonEarnerLimit = $this->requiredArrayInt($pension, 'relevant_earnings_minimum', 'pension.relevant_earnings_minimum');
        $isNonEarner = $income === 0;

        if ($isNonEarner) {
            $net = (int) round($nonEarnerLimit * (1 - $this->bandRates()['basic']));
            $explanation = 'The Pension Annual Allowance is '.$this->money($aa).'. With no earnings, contributions receiving tax relief are normally limited to '.$this->money($nonEarnerLimit).' gross ('.$this->money($net).' net after basic-rate relief); the outcome also depends on allowance rules and personal circumstances.';
        } else {
            $explanation = 'You can open or contribute to a pension. Tax relief is limited by earnings, allowance rules and personal circumstances.';
        }

        return $this->allowanceItem(
            $key,
            $label,
            $aa,
            'available',
            $explanation
        );
    }

    /**
     * @return array{key:string,label:string,amount:int,state:string,explanation:string,on:bool,note:string}
     */
    private function allowanceItem(string $key, string $label, int $amount, string $state, string $explanation): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'amount' => $amount,
            'state' => $state,
            'explanation' => $explanation,
            // Transitional response compatibility for consumers outside the
            // public page. New rendering is driven only by the semantic state.
            'on' => $state === 'available',
            'note' => $explanation,
        ];
    }

    // --- Lines priced by the plan engine ----------------------------------

    /**
     * The pension line, priced by the plan engine's tax maths. The trap and
     * additional-rate bands are sized as the engine sizes them; the basic and
     * higher bands use a tenth of income, which in the higher band is less
     * than the engine's whole higher-rate slice (agreed 29 Sep 2026).
     *
     * @return array{key:string,label:string,amount:int,reason:string}|null
     */
    private function pensionLine(int $income, bool $noEarnings): ?array
    {
        if ($noEarnings) {
            // Relief only on contributions up to the greater of relevant
            // earnings and the basic amount (FA 2004 s190), and none after the
            // age in pension.relief_max_age (s188(3)(a)). Basic-rate relief is
            // added at source whether or not tax is paid (s192); the band
            // extension adds any higher-rate relief on top.
            $nonEarner = $this->math->nonEarnerPensionContribution();
            $gross = (int) round($nonEarner['gross']);
            $relief = (int) round($nonEarner['relief']);
            $saving = (int) round($this->math->reliefAtSourceSavingOn($this->incomeParts($income), (float) $gross));
            // Anything above the relief at source is higher-rate relief, given
            // only on a claim (s192(4)), e.g. through Self Assessment.
            $claim = $saving > $relief
                ? ' The other '.$this->money($saving - $relief).' you claim back through Self Assessment.'
                : '';

            return [
                'key' => $this->isTrap($income) ? 'tax_trap_60' : 'pension',
                'label' => $this->isTrap($income) ? $this->trapLabel() : 'Pension contribution',
                'amount' => $saving,
                'reason' => 'Without earnings from work, tax relief is limited to '.$this->money($gross).' a year of pension contributions: pay in '.$this->money((int) round($nonEarner['net'])).' and it is topped up to '.$this->money($gross).', if you are under '.$this->reliefMaxAge().'.'.$claim,
            ];
        }

        if ($income > $this->taxInt('income_tax.additional_rate_threshold')) {
            $contribution = (int) $this->math->additionalRateAvoidanceContribution(
                (float) $income,
                (float) $this->annualAllowance(),
                $this->math->bandThresholds(),
            )['contribution'];
            $reason = 'At '.$this->money($income).', paying '.$this->money($contribution).' into a pension moves income out of the '.$this->pct($this->bandRates()['additional']).' band and reclaims your Personal Allowance.';
        } elseif ($income > $this->taperThreshold()) {
            $contribution = $this->taperRescueContribution($income);
            $reason = "You're in the ".$this->pct($this->trapRate()).' tax trap. At '.$this->money($income).', the top of the band you chose, paying '.$this->money($contribution).' into a pension reclaims your Personal Allowance. Income between '.$this->money($this->taperThreshold()).' and '.$this->money($this->taperEnd()).' is taxed at '.$this->pct($this->trapRate()).'.';
        } else {
            $contribution = (int) (floor($income * PensionTaxReliefStrategy::BASIC_RATE_SHARE_OF_EARNINGS / 100) * 100);
            $reason = 'A pension contribution of '.$this->money($contribution).' attracts '.$this->pct($this->marginalRate($income)).' tax relief.';
        }

        if ($contribution <= 0) {
            return null;
        }

        return [
            'key' => $this->isTrap($income) ? 'tax_trap_60' : 'pension',
            'label' => $this->isTrap($income) ? $this->trapLabel() : 'Pension contribution',
            'amount' => $this->pensionSaving($income, $contribution),
            'reason' => $reason,
        ];
    }

    /**
     * Marriage Allowance, either way round, priced as the plan engine prices
     * it: the transfer reduces the recipient's tax by up to the allowance at
     * the basic rate, never more than the tax they pay (ITA 2007 s55B, s55C;
     * TaxStrategyMath::marriageAllowance). The partner with no income loses
     * nothing by giving it.
     *
     * @return array{key:string,label:string,amount:int,reason:string}|null
     */
    private function marriageAllowanceLine(int $income, string $spouseBand): ?array
    {
        $recipient = $this->marriageAllowanceRecipient($income, $spouseBand);
        if ($recipient === null) {
            return null;
        }

        $marriage = $this->taxInt('income_tax.marriage_allowance.amount');
        $recipientIncome = $recipient === 'user' ? $income : $this->incomeForBand($spouseBand);
        $saving = (int) floor(min(
            $marriage * $this->bandRates()['basic'],
            $this->math->incomeTaxOn($this->incomeParts($recipientIncome)),
        ));
        if ($saving < 1) {
            return null;
        }

        return [
            'key' => 'marriage_allowance',
            'label' => 'Marriage Allowance',
            'amount' => $saving,
            'reason' => ($recipient === 'user'
                ? 'As a basic-rate taxpayer you qualify: your partner can transfer '.$this->money($marriage).' of their Personal Allowance to you.'
                : 'Your partner is a basic-rate taxpayer, so you can transfer '.$this->money($marriage).' of your Personal Allowance to them.')
                .$this->scottishMarriageAllowanceCaveat($recipient, $recipientIncome),
        ];
    }

    /**
     * The funnel prices England, Wales and Northern Ireland rates, as the plan
     * engine does. A Scottish recipient may pay no more than the Scottish
     * intermediate rate (ITA 2007 s55B(2)(b)), so above
     * income_tax.marriage_allowance.scottish_recipient_upper_limit the line
     * carries the same warning as the engine's how-to (ActionHowToFacts,
     * action-how-to/tax.md) (CSJ 2026-09-29).
     */
    private function scottishMarriageAllowanceCaveat(string $recipient, int $recipientIncome): string
    {
        $limit = $this->taxConfig->get('income_tax.marriage_allowance.scottish_recipient_upper_limit');
        if (! is_numeric($limit) || $recipientIncome <= (float) $limit) {
            return '';
        }

        return ($recipient === 'user'
            ? ' If you live in Scotland, this does not apply to you: '
            : ' If your partner lives in Scotland, this does not apply: ')
            .'there the person receiving it must pay no more than the Scottish intermediate rate, which usually means income up to '
            .$this->money((int) round((float) $limit)).'.';
    }

    /**
     * Who would receive a Marriage Allowance transfer: 'user', 'spouse' or
     * null. The giver has no income; the receiver pays tax, but at no more
     * than the basic rate (ITA 2007 s55B(2)).
     */
    private function marriageAllowanceRecipient(int $income, ?string $spouseBand): ?string
    {
        if ($spouseBand === null) {
            return null;
        }
        $spouseIncome = $this->incomeForBand($spouseBand);
        $pays = fn (int $amount): bool => $amount > $this->personalAllowanceBase() && $this->isBasicRate($amount);

        if ($spouseIncome === 0 && $pays($income)) {
            return 'user';
        }
        if ($income === 0 && $pays($spouseIncome)) {
            return 'spouse';
        }

        return null;
    }

    /** Income tax saved by a gross pension contribution, from the plan's tax engine. */
    private function pensionSaving(int $income, int $contribution): int
    {
        return (int) round($this->math->pensionContributionSavingOn($this->incomeParts($income), (float) $contribution));
    }

    private function taperRescueContribution(int $income): int
    {
        return (int) $this->math->taperRescueContribution((float) $income, (float) $this->annualAllowance());
    }

    /** @return array{non_savings: float, interest: float, dividends: float, trust: float, net_pay: float} */
    private function incomeParts(int $income): array
    {
        return ['non_savings' => (float) $income, 'interest' => 0.0, 'dividends' => 0.0, 'trust' => 0.0, 'net_pay' => 0.0];
    }

    /**
     * No relevant earnings: the "no income" band, or an employment answer
     * whose income is a pension, rent or savings rather than pay.
     */
    private function hasNoEarnings(?string $employment, string $incomeBand): bool
    {
        return $incomeBand === 'zero' || in_array($employment, self::NON_EARNING_EMPLOYMENT, true);
    }

    // --- Lookups -------------------------------------------------------------

    private function personalAllowanceBase(): int
    {
        return $this->taxInt('income_tax.personal_allowance');
    }

    private function taperThreshold(): int
    {
        return $this->taxInt('income_tax.personal_allowance_taper_threshold');
    }

    /** Where the Personal Allowance is fully tapered away. */
    private function taperEnd(): int
    {
        return (int) round($this->taperThreshold() + $this->personalAllowanceBase() / $this->taxNumber('income_tax.personal_allowance_taper_rate'));
    }

    /** Income inside the Personal Allowance taper band. */
    private function isTrap(int $income): bool
    {
        return $income > $this->taperThreshold() && $income <= $this->taperEnd();
    }

    private function trapLabel(): string
    {
        return $this->pct($this->trapRate()).' Tax Trap';
    }

    /**
     * Effective rate across the taper band: the higher rate on the pound
     * earned plus the higher rate on the allowance it withdraws (ITA 2007 s35).
     */
    private function trapRate(): float
    {
        return $this->bandRates()['higher'] * (1 + $this->taxNumber('income_tax.personal_allowance_taper_rate'));
    }

    private function annualAllowance(): int
    {
        return $this->requiredArrayInt($this->taxConfig->getPensionAllowances(), 'annual_allowance', 'pension.annual_allowance');
    }

    private function reliefMaxAge(): int
    {
        return $this->requiredArrayInt($this->taxConfig->getPensionAllowances(), 'relief_max_age', 'pension.relief_max_age');
    }

    /** Personal Allowance after the £1-per-£2 taper above £100k. */
    private function personalAllowance(int $income): float
    {
        $base = $this->personalAllowanceBase();
        $threshold = $this->taperThreshold();
        $taperRate = $this->taxNumber('income_tax.personal_allowance_taper_rate');

        if ($income <= $threshold) {
            return (float) $base;
        }

        return max(0.0, $base - ($income - $threshold) * $taperRate);
    }

    /** @return array{basic:float,higher:float,additional:float} */
    private function bandRates(): array
    {
        $bands = $this->taxConfig->getIncomeTax()['bands'] ?? [];
        $rate = function (string $name) use ($bands): float {
            foreach ($bands as $b) {
                if (stripos((string) ($b['name'] ?? ''), $name) !== false && is_numeric($b['rate'] ?? null)) {
                    return (float) $b['rate'];
                }
            }

            throw new LogicException("Missing required tax configuration: income_tax.bands.{$name}.rate");
        };

        return [
            'basic' => $rate('Basic'),
            'higher' => $rate('Higher'),
            'additional' => $rate('Additional'),
        ];
    }

    /** True when the assumed income falls in the basic-rate band. */
    private function isBasicRate(int $income): bool
    {
        return $income <= $this->taxInt('income_tax.higher_rate_threshold');
    }

    /** Marginal income-tax rate at the assumed income. */
    private function marginalRate(int $income): float
    {
        $rates = $this->bandRates();
        $higherStart = $this->taxInt('income_tax.higher_rate_threshold');
        $additionalStart = $this->taxInt('income_tax.additional_rate_threshold');

        if ($income > $additionalStart) {
            return $rates['additional'];
        }
        if ($income > $higherStart) {
            return $rates['higher'];
        }

        return $rates['basic'];
    }

    private function personalSavingsAllowance(int $income): int
    {
        $psa = $this->taxConfig->get('income_tax.personal_savings_allowance');
        if (! is_array($psa)) {
            throw new LogicException('Missing required tax configuration: income_tax.personal_savings_allowance');
        }
        $higherStart = $this->taxInt('income_tax.higher_rate_threshold');
        $additionalStart = $this->taxInt('income_tax.additional_rate_threshold');

        if ($income > $additionalStart) {
            return $this->requiredArrayInt($psa, 'additional', 'income_tax.personal_savings_allowance.additional');
        }
        if ($income > $higherStart) {
            return $this->requiredArrayInt($psa, 'higher', 'income_tax.personal_savings_allowance.higher');
        }

        return $this->requiredArrayInt($psa, 'basic', 'income_tax.personal_savings_allowance.basic');
    }

    private function normaliseBand(?string $band, ?string $default): ?string
    {
        return in_array($band, self::BAND_KEYS, true) ? $band : $default;
    }

    private function incomeForBand(string $band): int
    {
        return FunnelIncomeBand::assumedIncome(
            $band,
            config('onboarding.savetax_over_band_assumed_income')
        );
    }

    private function taxNumber(string $path): float
    {
        $value = $this->taxConfig->get($path);
        if (! is_numeric($value)) {
            throw new LogicException("Missing required tax configuration: {$path}");
        }

        return (float) $value;
    }

    private function taxInt(string $path): int
    {
        return (int) round($this->taxNumber($path));
    }

    private function requiredArrayInt(array $values, string $key, string $path): int
    {
        if (! is_numeric($values[$key] ?? null)) {
            throw new LogicException("Missing required tax configuration: {$path}");
        }

        return (int) round((float) $values[$key]);
    }

    private function money(int $n): string
    {
        return '£'.number_format($n);
    }

    private function pct(float $rate): string
    {
        return rtrim(rtrim(number_format($rate * 100, 1), '0'), '.').'%';
    }
}
