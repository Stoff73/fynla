<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Bequest;
use App\Models\Estate\Gift;
use App\Models\Estate\IHTCalculation;
use App\Models\Estate\IHTProfile;
use App\Models\Estate\Will;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Goals\LifeEventService;
use App\Services\Investment\InvestmentProjectionService;
use App\Services\Settings\AssumptionsService;
use App\Services\Stores\PensionStore;
use App\Services\Stores\PropertyStore;
use App\Services\TaxConfigService;
use App\Traits\CalculatesOwnershipShare;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * IHT Calculation Service
 *
 * Handles all IHT calculations with caching and clear explanatory messages.
 * Uses sophisticated asset-specific projection methods:
 * - Cash: Income/expense surplus model
 * - Investments: Monte Carlo (80% confidence) or custom rate
 * - Properties: Configurable growth rate (default 3%)
 * - Liabilities: Amortisation to end date (default retirement age)
 */
class IHTCalculationService
{
    use CalculatesOwnershipShare;

    private const DEFAULT_PROPERTY_GROWTH_RATE = 3.0;

    public function __construct(
        private readonly EstateAssetAggregatorService $aggregator,
        private readonly TaxConfigService $taxConfig,
        private readonly AssumptionsService $assumptionsService,
        private readonly InvestmentProjectionService $investmentProjectionService,
        private readonly FutureValueCalculator $futureValueCalculator,
        private readonly LifeEventService $lifeEventService,
        private readonly PropertyStore $propertyStore,
        private readonly WillAnalysisService $willAnalysis,
        private readonly HouseholdCashFlowProjector $cashFlowProjector,
    ) {}

    /**
     * Calculate IHT liability with caching.
     *
     * Wave 2.4 (REVIEW §4 High #22): persistence is now opt-in. The default
     * is read-only — callers in read flows (dashboards, advice queries,
     * Fyn chat tool calls) do not write a row to the `iht_calculations`
     * audit table. Callers that genuinely want to capture a snapshot
     * (e.g. immediately after a trust create/update) pass `persist: true`.
     *
     * @param  User  $user  The primary user
     * @param  User|null  $spouse  The spouse (if married and linked)
     * @param  bool  $dataSharingEnabled  Whether spouse data sharing is enabled
     * @param  bool  $persist  Write the calculation result to the
     *                         `iht_calculations` audit table. Defaults to
     *                         false — opt in only when a caller has a
     *                         specific reason to capture the snapshot.
     * @return array IHT calculation results with all breakdown values
     */
    public function calculate(
        User $user,
        ?User $spouse = null,
        bool $dataSharingEnabled = false,
        bool $persist = false,
    ): array {
        // Eager load relationships to prevent N+1 queries
        $user->loadMissing(['investmentAccounts', 'mortgages', 'liabilities', 'savingsAccounts', 'properties']);
        if ($spouse) {
            $spouse->loadMissing(['investmentAccounts', 'mortgages', 'liabilities', 'savingsAccounts', 'properties']);
        }

        // 1. Check cache first
        $cached = $this->getCachedCalculation($user, $spouse, $dataSharingEnabled);
        if ($cached) {
            return $cached;
        }

        // 2. Get tax config
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $isMarried = in_array($user->marital_status, ['married']) && $spouse !== null;
        $isWidowed = $user->marital_status === 'widowed';

        // W-0154 F1/F3 — THE decision about whose records this calculation covers,
        // made once and used by every input below.
        //
        // It used to be made twice, differently, and that was the whole defect.
        // Assets and liabilities pooled on `$isMarried && $dataSharingEnabled`
        // (:107, :118) while the allowances doubled on `$isMarried` alone and every
        // per-person input — gifts, profiles, wills — read the logged-in user only.
        // Two consequences, both live:
        //   F1: the same household was quoted two different bills depending on who
        //       logged in, because one spouse's gifts reduced the pooled band in
        //       their view and by nothing in the other's.
        //   F3: married with sharing off gave £1,000,000 of allowances against one
        //       person's assets alone — a £0 bill on a £700,000 estate where the
        //       answer is £80,000.
        //
        // Everything that follows reads `$pooledMembers`. Adding a per-person input
        // to this service means adding it to that loop, not to `$user`.
        $pooledMembers = $this->pooledMembers($user, $spouse, $isMarried, $dataSharingEnabled);
        $poolsSpouse = count($pooledMembers) > 1;

        // Transferred allowances (widows/widowers) belong to whoever holds the
        // profile, so they are summed across the pooled members rather than read
        // from the logged-in user. Fetched once; `determineIHTRate()` is given the
        // same collection so it does not re-query.
        $profiles = IHTProfile::whereIn('user_id', array_map(fn (User $m): int => $m->id, $pooledMembers))
            ->get()
            ->keyBy('user_id');
        $ihtProfile = $profiles->get($user->id);
        $nrbTransferredPooled = (float) $profiles->sum('nrb_transferred_from_spouse');
        $rnrbTransferredPooled = (float) $profiles->sum('rnrb_transferred_from_spouse');

        // 3. Fetch and sum assets (exclude IHT-exempt assets like pensions)
        $userAssets = $this->aggregator->gatherUserAssets($user);
        $spouseAssets = ($isMarried && $dataSharingEnabled)
            ? $this->aggregator->gatherUserAssets($spouse)
            : collect();

        // Filter out IHT-exempt assets (DC pensions, etc.)
        $userTaxableAssets = $userAssets->reject(fn ($asset) => $asset->is_iht_exempt ?? false);
        $spouseTaxableAssets = $spouseAssets->reject(fn ($asset) => $asset->is_iht_exempt ?? false);

        $userGrossAssets = $userTaxableAssets->sum('current_value');
        $spouseGrossAssets = $spouseTaxableAssets->sum('current_value');
        $totalGrossAssets = $userGrossAssets + $spouseGrossAssets;

        // 4. Fetch and sum liabilities
        $userLiabilities = $this->aggregator->calculateUserLiabilities($user);
        $spouseLiabilities = ($isMarried && $dataSharingEnabled)
            ? $this->aggregator->calculateUserLiabilities($spouse)
            : 0;
        $totalLiabilities = $userLiabilities + $spouseLiabilities;

        // 5. Calculate net estate
        $userNetEstate = $userGrossAssets - $userLiabilities;
        $spouseNetEstate = $spouseGrossAssets - $spouseLiabilities;
        $totalNetEstate = $totalGrossAssets - $totalLiabilities;

        // 6. Calculate NRB with message (includes transferred NRB for widows)
        $nrbSingle = $ihtConfig['nil_rate_band']; // £325,000
        $nrbTransferred = $nrbTransferredPooled;

        // W-0154 F3: doubles on `$poolsSpouse`, not `$isMarried`. The allowances now
        // cover exactly the estates being taxed.
        //
        // W-0154 F2: `$nrbSpouseModelled` is reported separately from
        // `$nrbTransferred` and the two are NOT the same thing. There is no
        // transferable nil rate band while both spouses are alive — IHTA 1984 s8A
        // creates the claim on the survivor's death — so `nrb_transferred` is
        // legitimately 0 for a living couple. The doubling is this service's
        // second-death modelling assumption (see the projection docblock), and it is
        // now labelled as one instead of appearing as £175,000 the user could not
        // account for. Do NOT "fix" this by writing 325,000 into `nrb_transferred`.
        $nrbSpouseModelled = 0.0;

        if ($poolsSpouse) {
            $nrbSpouseModelled = $nrbSingle;
            $nrbGross = $nrbSingle + $nrbSpouseModelled;
        } elseif ($isWidowed && $nrbTransferred > 0) {
            $nrbGross = $nrbSingle + $nrbTransferred;
        } else {
            $nrbGross = $nrbSingle;
        }

        // 6b. Deduct each pooled member's gifts, capped at their OWN band.
        //
        // The comment that used to sit here said spouse nil rate band was "handled
        // separately by SpouseNRBTrackerService". **That service has never had a
        // caller** — verified repo-wide; the only hits were this comment, its twin
        // above the deduction method, and the class declaration. It described work
        // nothing did, and it is why the gap went unexamined. The service is left in
        // place, unwired, for a separate decision (W-0146); the claim is removed.
        $nrbDeduction = $this->calculateNRBDeductionForGifts($pooledMembers, $nrbSingle);
        $nrbAvailable = max(0, $nrbGross - $nrbDeduction['total_nrb_used']);

        // W-0134 acceptance 4. The message is built AFTER the deduction, so its
        // headline is the band actually applied. It used to be built before, then
        // have "Reduced by £150,000…" appended, which left the sentence opening
        // "Combined Nil Rate Band of £650,000 available" beneath a table whose rows
        // now itemise £500,000 — the last unexplained figure on a page fixed
        // specifically so a reader can add it up.
        $nrbMessage = $this->buildNrbMessage(
            $nrbGross,
            $nrbAvailable,
            $nrbSingle,
            $nrbTransferred,
            $nrbDeduction,
            $poolsSpouse,
            $isWidowed
        );

        // 7-8. Assess the residence band, the rate and the exemption.
        //
        // W-0136 — this assessment is now a single mechanism, `assessTaxPosition()`,
        // run against whatever estate it is given. The projection calls the SAME
        // method with the projected estate rather than reusing this answer, so the
        // £2,000,000 residence-band taper, the 10% charitable rate test and the
        // s23(1) exemption are all evaluated against the estate they apply to.
        //
        // The bug that made this necessary: every one of those three tests was run
        // once, against the CURRENT estate, and its answer was carried into a
        // projection roughly two and a half times larger. A household projected past
        // £2,000,000 was shown the full £350,000 residence band beneath a sentence
        // asserting it was below the taper threshold.
        //
        // W-0154 F3: the spouse is passed only when their estate is actually pooled.
        // `hasMainResidence()`, `hasDirectDescendants()` and `getMainResidenceNetValue()`
        // all consult whoever they are given, so an unpooled spouse could grant the
        // residence band, and raise its cap, using a property excluded from the estate
        // being taxed.
        $pooledSpouse = $poolsSpouse ? $spouse : null;

        $assessment = [
            'user' => $user,
            'spouse' => $pooledSpouse,
            'pooled_members' => $pooledMembers,
            'iht_config' => $ihtConfig,
            'pools_spouse' => $poolsSpouse,
            'is_widowed' => $isWidowed,
            'rnrb_transferred' => $rnrbTransferredPooled,
            'nrb_available' => $nrbAvailable,
            'profiles' => $profiles,
        ];

        $current = $this->assessTaxPosition(
            $totalNetEstate,
            $this->getMainResidenceNetValue($user, $pooledSpouse),
            $assessment
        );

        $rnrbData = $current['rnrb'];
        $ihtRateData = $current['rate'];
        $ihtRate = $ihtRateData['rate'];
        $charitableAmount = $current['charitable_deduction'];
        $totalAllowances = $current['total_allowances'];
        $taxableEstate = $current['taxable_estate'];
        $ihtLiability = $current['iht_liability'];
        $effectiveRate = $totalNetEstate > 0 ? ($ihtLiability / $totalNetEstate * 100) : 0;

        // 9. Calculate PROJECTED values at death using asset-specific methods
        $projectedData = $this->calculateProjectedValues(
            $user,
            $spouse,
            $isMarried,
            $assessment,
            $dataSharingEnabled
        );

        // 10. Build result array with CURRENT and PROJECTED values
        $result = [
            // Current values
            'user_gross_assets' => round($userGrossAssets, 2),
            'spouse_gross_assets' => round($spouseGrossAssets, 2),
            'total_gross_assets' => round($totalGrossAssets, 2),

            'user_total_liabilities' => round($userLiabilities, 2),
            'spouse_total_liabilities' => round($spouseLiabilities, 2),
            'total_liabilities' => round($totalLiabilities, 2),

            'user_net_estate' => round($userNetEstate, 2),
            'spouse_net_estate' => round($spouseNetEstate, 2),
            'total_net_estate' => round($totalNetEstate, 2),

            // W-0154 F2. These five now reconcile, and a user can check them:
            //   nrb_individual + nrb_spouse_modelled + nrb_transferred
            //     − nrb_gift_deduction = nrb_available
            // Before this, three of them were published (325,000 + 0 = 500,000) and
            // the £175,000 difference was two unlabelled effects netting out — a
            // +£325,000 modelled spouse band and a −£150,000 gift deduction that had
            // no field to appear in at all.
            'nrb_available' => round($nrbAvailable, 2),
            'nrb_individual' => round($nrbSingle, 2),
            'nrb_spouse_modelled' => round($nrbSpouseModelled, 2),
            'nrb_transferred' => round($nrbTransferred, 2),
            'nrb_gift_deduction' => round($nrbDeduction['total_nrb_used'], 2),
            'nrb_message' => $nrbMessage,

            'rnrb_available' => round($rnrbData['rnrb_available'], 2),
            'rnrb_individual' => round($rnrbData['rnrb_individual'] ?? 0, 2),
            'rnrb_transferred' => round($rnrbData['rnrb_transferred'] ?? 0, 2),
            'rnrb_status' => $rnrbData['rnrb_status'],
            'rnrb_message' => $rnrbData['rnrb_message'],

            'total_allowances' => round($totalAllowances, 2),
            'charitable_deduction' => round($charitableAmount, 2),
            'taxable_estate' => round($taxableEstate, 2),
            'iht_rate' => $ihtRate,
            'iht_rate_percent' => round($ihtRate * 100, 0),
            'iht_rate_type' => $ihtRateData['type'],
            'iht_rate_message' => $ihtRateData['message'],
            'charitable_giving_percent' => $ihtRateData['charitable_percent'],
            'charitable_baseline' => $ihtRateData['baseline'],
            'charitable_threshold' => $ihtRateData['threshold'],
            'iht_liability' => round($ihtLiability, 2),
            'effective_rate' => round($effectiveRate, 2),

            // Projected values at death (asset-specific)
            'projected_cash' => $projectedData['projected_cash'],
            'projected_cash_shortfall' => $projectedData['projected_cash_shortfall'],
            'projected_cash_assumptions' => $projectedData['projected_cash_assumptions'],
            'projected_investments' => $projectedData['projected_investments'],
            'projected_properties' => $projectedData['projected_properties'],
            'projected_gross_assets' => $projectedData['projected_gross_assets'],
            'projected_liabilities' => $projectedData['projected_liabilities'],
            'projected_net_estate' => $projectedData['projected_net_estate'],
            'projected_taxable_estate' => $projectedData['projected_taxable_estate'],
            'projected_iht_liability' => $projectedData['projected_iht_liability'],
            'years_to_death' => $projectedData['years_to_death'],
            'retirement_age' => $projectedData['retirement_age'],
            'estimated_age_at_death' => $projectedData['estimated_age_at_death'],
            'inflation_rate' => $projectedData['inflation_rate'],

            // W-0136 / W-0134 — the projected column's own allowance components.
            // The residence band tapers away as the estate grows, so the projected
            // allowances are NOT the current ones and a screen that prints one set
            // beside both columns cannot be added up. Every figure the projected
            // column needs to reconcile is published here.
            'projected_nrb_available' => $projectedData['projected_nrb_available'],
            'projected_rnrb_available' => $projectedData['projected_rnrb_available'],
            'projected_rnrb_individual' => $projectedData['projected_rnrb_individual'],
            'projected_rnrb_transferred' => $projectedData['projected_rnrb_transferred'],
            'projected_rnrb_status' => $projectedData['projected_rnrb_status'],
            'projected_rnrb_message' => $projectedData['projected_rnrb_message'],
            'projected_total_allowances' => $projectedData['projected_total_allowances'],
            'projected_charitable_deduction' => $projectedData['projected_charitable_deduction'],
            'projected_iht_rate' => $projectedData['projected_iht_rate'],
            'projected_iht_rate_percent' => $projectedData['projected_iht_rate_percent'],

            'is_married' => $isMarried,
            'is_widowed' => $isWidowed,
            'data_sharing_enabled' => $dataSharingEnabled,

            // NRB gift deduction breakdown
            'nrb_deduction' => $nrbDeduction,
        ];

        // 9b. Calculate 2027 pension Inheritance Tax dual-scenario projection
        $pensionAmendment = $this->calculatePensionAmendmentScenario($user, $spouse, $dataSharingEnabled, $result);
        $result['pension_amendment'] = $pensionAmendment;

        // 10. Save to database (opt-in only — see method docblock).
        if ($persist) {
            $this->saveCalculation($user, $result, $userAssets, $spouseAssets, $userLiabilities, $spouseLiabilities);
        }

        return $result;
    }

    /**
     * Calculate projected values at death using asset-specific methods
     *
     * For married couples, projects to SECOND DEATH (whoever lives longer)
     * to accurately calculate combined IHT liability.
     *
     * Asset-specific projection methods:
     * - Cash: Income/expense surplus model (switches at retirement)
     * - Investments: Monte Carlo (80% confidence) or custom rate
     * - Properties: Configurable growth rate (default 3%)
     * - Liabilities: Amortisation to end date (default retirement age)
     *
     * The tax position at death is NOT inherited from the current position. It is
     * re-assessed by `assessTaxPosition()` against the projected estate — see the
     * comment where that call is made.
     *
     * @param  array  $assessment  The assessment context built in `calculate()`.
     */
    private function calculateProjectedValues(
        User $user,
        ?User $spouse,
        bool $isMarried,
        array $assessment,
        bool $dataSharingEnabled = false
    ): array {
        // Get current age and key milestone ages
        $currentAge = $user->date_of_birth ? Carbon::parse($user->date_of_birth)->age : 50;
        $retirementAge = $this->cashFlowProjector->retirementAgeFor($user);

        // The horizon is a number of YEARS FROM NOW, and it is a property of the
        // household, not of whoever is signed in: the second death is the later of the
        // two life expectancies, and `max()` gives the same answer from either login.
        //
        // W-0188. The age at death used to be taken in whichever spouse dies second's
        // own age frame and then handed to a loop that started at the VIEWER's age. For
        // the second-dying spouse that is the true horizon; for the other it is short
        // by the age gap between them. One household projected two estates £103,206
        // apart, and every projection-derived figure — the taper test, life-cover
        // sizing, any gifting strategy quantified against the future estate —
        // inherited it.
        //
        // `$estimatedAgeAtDeath` is now purely a label: the viewer's own age at the
        // household horizon. The two logins therefore show DIFFERENT ages against the
        // SAME projection, which is correct — the spouses are not the same age.
        if ($isMarried && $spouse && $spouse->date_of_birth && $spouse->gender) {
            $yearsUntilDeath = max(
                $this->calculateLifeExpectancy($user),
                $this->calculateLifeExpectancy($spouse)
            );
        } else {
            $yearsUntilDeath = $this->calculateLifeExpectancy($user);
        }

        $estimatedAgeAtDeath = $currentAge + $yearsUntilDeath;

        // Get estate planning assumptions
        $assumptions = $this->assumptionsService->getEstateAssumptions($user);
        $inflationRate = ($assumptions['inflation_rate'] ?? 2.0) / 100;

        // Project investments using Monte Carlo p20 directly at death age
        // No rate extraction or recompounding — use the simulation result as-is
        $projectedInvestments = $this->projectInvestmentsMonteCarlo(
            $user,
            $spouse,
            $yearsUntilDeath,
            $dataSharingEnabled
        );

        // Project cash: one mechanism, shared with the year-by-year table the user
        // reads beneath the headline (Rule 20 — see HouseholdCashFlowProjector).
        $cashFlow = $this->cashFlowProjector->project(
            $user,
            $spouse,
            $dataSharingEnabled,
            $yearsUntilDeath,
            $inflationRate
        );
        $projectedCash = $cashFlow['final_cash'];

        $projectedProperties = $this->projectProperties(
            $user,
            $spouse,
            $yearsUntilDeath,
            $assumptions,
            $dataSharingEnabled
        );

        $projectedLiabilities = $this->projectLiabilities(
            $user,
            $spouse,
            $currentAge,
            $retirementAge,
            $estimatedAgeAtDeath,
            $dataSharingEnabled
        );

        // Get current chattel and business values (these don't appreciate - stay at current value)
        $userAssets = $this->aggregator->gatherUserAssets($user);
        $projectedChattels = $userAssets->where('asset_type', 'chattel')
            ->reject(fn ($a) => $a->is_iht_exempt)
            ->sum('current_value');
        $projectedBusiness = $userAssets->where('asset_type', 'business')
            ->reject(fn ($a) => $a->is_iht_exempt)
            ->sum('current_value');

        if ($dataSharingEnabled && $spouse) {
            $spouseAssets = $this->aggregator->gatherUserAssets($spouse);
            $projectedChattels += $spouseAssets->where('asset_type', 'chattel')
                ->reject(fn ($a) => $a->is_iht_exempt)
                ->sum('current_value');
            $projectedBusiness += $spouseAssets->where('asset_type', 'business')
                ->reject(fn ($a) => $a->is_iht_exempt)
                ->sum('current_value');
        }

        // Calculate totals (include chattels and business at current value)
        $projectedGrossAssets = $projectedCash + $projectedInvestments + $projectedProperties + $projectedChattels + $projectedBusiness;
        $projectedNetEstate = $projectedGrossAssets - $projectedLiabilities;

        // W-0136 and the charitable scaling defect, fixed together because fixing
        // either alone lands on a plausible wrong answer.
        //
        // This used to reuse the current assessment verbatim: the same allowances,
        // the same rate, and the current charitable exemption multiplied by
        // projected ÷ current. Three consequences, all live:
        //
        //   * The £2,000,000 residence-band taper never fired on a projection,
        //     however large. The arithmetic for it existed and was correct; it was
        //     simply never asked the question about this estate.
        //   * The 10% charitable rate test was likewise decided against today's
        //     estate. A household on 36% carried that rate to death even though the
        //     baseline roughly doubles while a fixed legacy does not.
        //   * A FIXED cash legacy was inflated in proportion to the estate. £20,000
        //     of `specific_amount` bequests became £50,891 on a £4.37m projection.
        //     A cash legacy is a fixed sum; only a `percentage` bequest grows.
        //
        // The fix is one call to the same assessment with the projected estate.
        // `getCharitableBequestTotal()` already distinguishes the two bequest types,
        // so re-asking it is exactly right where re-scaling its answer was not.
        //
        // The residence value handed to the cap (IHTA 1984 s8E(2)) is projected too.
        // Feeding it the current value would cap the projected band at a home price
        // decades out of date — it does not bite a household whose residence already
        // exceeds the band, and it silently under-caps every household whose does not.
        $projected = $this->assessTaxPosition(
            $projectedNetEstate,
            $this->projectMainResidenceNetValue(
                $user,
                $assessment['spouse'],
                $currentAge,
                $retirementAge,
                $yearsUntilDeath,
                $assumptions
            ),
            $assessment
        );

        return [
            'projected_cash' => round($projectedCash, 2),

            // W-0137. The projected expenditure the household's cash could not meet,
            // as a positive amount. It used to be folded INTO `projected_cash` as a
            // negative balance — a Cash ISA at minus £854,179 — which is not a value a
            // deposit account can hold, and which was then subtracted from the estate.
            // A shortfall is a planning output; a negative asset is a broken model.
            'projected_cash_shortfall' => round((float) $cashFlow['shortfall'], 2),

            // What the projection had to assume because a figure was absent. Published
            // so an unavailable number is never read as a real zero — a missing State
            // Pension forecast is a gap in the record, not an entitlement of nothing.
            'projected_cash_assumptions' => $cashFlow['assumptions'],

            'projected_investments' => round($projectedInvestments, 2),
            'projected_properties' => round($projectedProperties, 2),
            'projected_gross_assets' => round($projectedGrossAssets, 2),
            'projected_liabilities' => round($projectedLiabilities, 2),
            'projected_net_estate' => round($projectedNetEstate, 2),
            'projected_taxable_estate' => round($projected['taxable_estate'], 2),
            'projected_iht_liability' => round($projected['iht_liability'], 2),
            'projected_nrb_available' => round((float) $assessment['nrb_available'], 2),
            'projected_rnrb_available' => round((float) $projected['rnrb']['rnrb_available'], 2),
            'projected_rnrb_individual' => round((float) ($projected['rnrb']['rnrb_individual'] ?? 0), 2),
            'projected_rnrb_transferred' => round((float) ($projected['rnrb']['rnrb_transferred'] ?? 0), 2),
            'projected_rnrb_status' => $projected['rnrb']['rnrb_status'],
            'projected_rnrb_message' => $projected['rnrb']['rnrb_message'],
            'projected_total_allowances' => round($projected['total_allowances'], 2),
            'projected_charitable_deduction' => round($projected['charitable_deduction'], 2),
            'projected_iht_rate' => $projected['rate']['rate'],
            'projected_iht_rate_percent' => round($projected['rate']['rate'] * 100, 0),
            'years_to_death' => $yearsUntilDeath,
            'retirement_age' => $retirementAge,
            'estimated_age_at_death' => $estimatedAgeAtDeath,

            // Published so the year-by-year breakdown is driven by the same rate this
            // projection used. It was re-derived downstream before, and defaulted to a
            // different number.
            'inflation_rate' => $inflationRate,
        ];
    }

    /**
     * Assess one estate: residence band, rate, exemption, taxable estate, tax.
     *
     * The single mechanism behind both the current and the projected column. Every
     * test inside it — the £2,000,000 residence-band taper, the 10% charitable rate
     * test, the s23(1) exemption — is a function of the estate being assessed, so
     * running it twice with two estates is the only way both columns can be right.
     * Reusing one answer for both was W-0136 and its two siblings.
     *
     * The nil rate band is deliberately NOT re-derived here: it is a statutory
     * amount reduced by chargeable transfers already made, neither of which is a
     * function of the estate's size.
     *
     * @param  array  $ctx  user, spouse (pooled only), pooled_members, iht_config,
     *                      pools_spouse, is_widowed, rnrb_transferred, nrb_available,
     *                      profiles.
     */
    private function assessTaxPosition(float $netEstate, float $residenceNetValue, array $ctx): array
    {
        $rnrbData = $this->calculateRNRB(
            $netEstate,
            $residenceNetValue,
            $ctx['user'],
            $ctx['spouse'],
            $ctx['iht_config'],
            $ctx['pools_spouse'],
            $ctx['is_widowed'],
            $ctx['rnrb_transferred']
        );

        $rateData = $this->determineIHTRate(
            $ctx['user'],
            $ctx['pooled_members'],
            $netEstate,
            $ctx['nrb_available'],
            $ctx['iht_config'],
            $ctx['profiles']
        );

        // Charitable legacies are fully exempt (IHTA 1984 s23): the gift leaves the
        // taxable estate entirely, deducted alongside the NRB and RNRB before the
        // rate is applied. The 36% reduced rate is a separate effect that stacks on
        // top of the exemption.
        $charitableDeduction = (float) ($rateData['charitable_amount'] ?? 0);
        $totalAllowances = (float) $ctx['nrb_available'] + (float) $rnrbData['rnrb_available'];
        $taxableEstate = max(0, $netEstate - $totalAllowances - $charitableDeduction);

        return [
            'rnrb' => $rnrbData,
            'rate' => $rateData,
            'charitable_deduction' => $charitableDeduction,
            'total_allowances' => $totalAllowances,
            'taxable_estate' => $taxableEstate,
            'iht_liability' => $taxableEstate * $rateData['rate'],
        ];
    }

    /**
     * The net value of the main residence(s) AT DEATH.
     *
     * The projected counterpart of `sumMainResidenceNetShare()`, and it exists for
     * one reason: the residence cap in IHTA 1984 s8E(2) limits the residence band to
     * the net value of the home, so a projected band assessed against a current home
     * value caps a future allowance at a past price.
     *
     * Both halves reuse the mechanisms that produce the rest of the projection —
     * property growth via `FutureValueCalculator`, mortgage amortisation via
     * `projectSingleLiability()` — so the residence cannot be worth one thing here
     * and another in `projectProperties()` / `projectLiabilities()`.
     */
    private function projectMainResidenceNetValue(
        User $user,
        ?User $spouse,
        int $currentAge,
        int $retirementAge,
        int $yearsToProject,
        array $assumptions
    ): float {
        $growthRate = ($assumptions['property_growth_rate'] ?? self::DEFAULT_PROPERTY_GROWTH_RATE) / 100;
        $currentYear = now()->year;

        $projectFor = function (User $member) use ($growthRate, $currentAge, $retirementAge, $yearsToProject, $currentYear): float {
            return (float) $this->propertyStore
                ->forUserByType($member, 'main_residence')
                ->sum(function ($property) use ($member, $growthRate, $currentAge, $retirementAge, $yearsToProject, $currentYear) {
                    $valueShare = $this->futureValueCalculator->calculateFutureValue(
                        $this->calculateUserShare($property, $member->id),
                        $growthRate,
                        $yearsToProject
                    );

                    $mortgageShare = (float) $property->mortgages->sum(function ($mortgage) use ($member, $currentAge, $retirementAge, $yearsToProject, $currentYear) {
                        $endDate = $mortgage->end_date;

                        return $this->projectSingleLiability(
                            (float) $this->calculateUserMortgageShare($mortgage, $member->id),
                            $endDate instanceof \DateTimeInterface ? $endDate->format('Y-m-d') : $endDate,
                            $currentAge,
                            $retirementAge,
                            $yearsToProject,
                            $currentYear
                        );
                    });

                    return max(0.0, $valueShare - $mortgageShare);
                });
        };

        $value = $projectFor($user);

        if ($spouse) {
            $value += $projectFor($spouse);
        }

        return max(0.0, $value);
    }

    /**
     * Get fallback investment growth rate from AssumptionsService.
     * Falls back to 4.7% if no user-specific assumption is configured.
     */
    private function getFallbackGrowthRate(User $user): float
    {
        $assumptions = $this->assumptionsService->getEstateAssumptions($user);

        if (($assumptions['investment_growth_method'] ?? 'monte_carlo') === 'custom'
            && isset($assumptions['custom_investment_rate'])) {
            return (float) $assumptions['custom_investment_rate'] / 100;
        }

        return 0.047;
    }

    /**
     * Project investments using Monte Carlo (80% confidence) or custom rate
     */
    private function projectInvestments(
        User $user,
        ?User $spouse,
        int $yearsToProject,
        array $assumptions,
        bool $dataSharingEnabled
    ): float {
        if ($yearsToProject <= 0) {
            return $this->getCurrentInvestmentValue($user, $spouse, $dataSharingEnabled);
        }

        $method = $assumptions['investment_growth_method'] ?? 'monte_carlo';

        if ($method === 'monte_carlo') {
            return $this->projectInvestmentsMonteCarlo($user, $spouse, $yearsToProject, $dataSharingEnabled);
        }

        // Custom rate: simple compound growth
        $customRate = ($assumptions['custom_investment_rate'] ?? 5.0) / 100;
        $currentValue = $this->getCurrentInvestmentValue($user, $spouse, $dataSharingEnabled);

        return $this->futureValueCalculator->calculateFutureValue($currentValue, $customRate, $yearsToProject);
    }

    /**
     * Get current total investment value
     */
    private function getCurrentInvestmentValue(User $user, ?User $spouse, bool $dataSharingEnabled): float
    {
        $value = InvestmentAccount::where('user_id', $user->id)->sum('current_value');

        if ($dataSharingEnabled && $spouse) {
            $value += InvestmentAccount::where('user_id', $spouse->id)->sum('current_value');
        }

        return (float) $value;
    }

    /**
     * Project investments using Monte Carlo simulation (80% confidence / p20)
     */
    private function projectInvestmentsMonteCarlo(
        User $user,
        ?User $spouse,
        int $yearsToProject,
        bool $dataSharingEnabled
    ): float {
        $projectedValue = 0;

        $fallbackRate = $this->getFallbackGrowthRate($user);

        // Get user's investment projections
        try {
            $userProjections = $this->investmentProjectionService->getPortfolioProjections(
                $user,
                [$yearsToProject]
            );

            if (isset($userProjections['portfolio']['projections'][$yearsToProject]['percentiles']['p20'])) {
                $projectedValue += $userProjections['portfolio']['projections'][$yearsToProject]['percentiles']['p20'];
            } else {
                // Fallback: compound at fallback rate instead of zero growth
                $currentValue = (float) InvestmentAccount::where('user_id', $user->id)->sum('current_value');
                $projectedValue += $this->futureValueCalculator->calculateFutureValue($currentValue, $fallbackRate, $yearsToProject);
            }
        } catch (\Exception $e) {
            // Fallback: compound at fallback rate instead of zero growth
            $currentValue = (float) InvestmentAccount::where('user_id', $user->id)->sum('current_value');
            $projectedValue += $this->futureValueCalculator->calculateFutureValue($currentValue, $fallbackRate, $yearsToProject);
        }

        // Include spouse's investments
        if ($dataSharingEnabled && $spouse) {
            try {
                $spouseProjections = $this->investmentProjectionService->getPortfolioProjections(
                    $spouse,
                    [$yearsToProject]
                );

                if (isset($spouseProjections['portfolio']['projections'][$yearsToProject]['percentiles']['p20'])) {
                    $projectedValue += $spouseProjections['portfolio']['projections'][$yearsToProject]['percentiles']['p20'];
                } else {
                    $currentValue = (float) InvestmentAccount::where('user_id', $spouse->id)->sum('current_value');
                    $projectedValue += $this->futureValueCalculator->calculateFutureValue($currentValue, $fallbackRate, $yearsToProject);
                }
            } catch (\Exception $e) {
                $currentValue = (float) InvestmentAccount::where('user_id', $spouse->id)->sum('current_value');
                $projectedValue += $this->futureValueCalculator->calculateFutureValue($currentValue, $fallbackRate, $yearsToProject);
            }
        }

        return $projectedValue;
    }

    /**
     * Project properties using configurable growth rate (default 3%)
     */
    private function projectProperties(
        User $user,
        ?User $spouse,
        int $yearsToProject,
        array $assumptions,
        bool $dataSharingEnabled
    ): float {
        $propertyGrowthRate = ($assumptions['property_growth_rate'] ?? self::DEFAULT_PROPERTY_GROWTH_RATE) / 100;

        // PropertyStore::forUser is joint-aware (user_id = ? OR joint_owner_id = ?).
        // Filter to primary-owner-only here to preserve the pre-PR-5a single-count semantics:
        // a joint property A+B is summed under A (primary) ONCE — never double-counted under
        // both A and B in the data-sharing branch. Mirrors SavingsReadConsumerParityTest pattern.
        $currentPropertyValue = (float) $this->propertyStore
            ->forUser($user)
            ->where('user_id', $user->id)
            ->sum('current_value');

        // Include spouse properties if data sharing enabled
        if ($dataSharingEnabled && $spouse) {
            $currentPropertyValue += (float) $this->propertyStore
                ->forUser($spouse)
                ->where('user_id', $spouse->id)
                ->sum('current_value');
        }

        if ($yearsToProject <= 0) {
            return $currentPropertyValue;
        }

        return $this->futureValueCalculator->calculateFutureValue($currentPropertyValue, $propertyGrowthRate, $yearsToProject);
    }

    /**
     * Project liabilities with amortisation to end date
     *
     * If no end date specified, assumes liability cleared at retirement age
     */
    private function projectLiabilities(
        User $user,
        ?User $spouse,
        int $currentAge,
        int $retirementAge,
        int $deathAge,
        bool $dataSharingEnabled
    ): float {
        $projectedLiabilities = 0;
        $currentYear = now()->year;
        $yearsToProject = $deathAge - $currentAge;

        // Project mortgages
        foreach ($user->mortgages as $mortgage) {
            $endDate = $mortgage->end_date;
            $projectedLiabilities += $this->projectSingleLiability(
                (float) ($mortgage->outstanding_balance ?? 0),
                $endDate instanceof \DateTimeInterface ? $endDate->format('Y-m-d') : $endDate,
                $currentAge,
                $retirementAge,
                $yearsToProject,
                $currentYear
            );
        }

        // Project other liabilities
        foreach ($user->liabilities as $liability) {
            $endDate = $liability->maturity_date ?? $this->estimatePayoffDate($liability);
            $projectedLiabilities += $this->projectSingleLiability(
                (float) ($liability->current_balance ?? 0),
                $endDate instanceof \DateTimeInterface ? $endDate->format('Y-m-d') : $endDate,
                $currentAge,
                $retirementAge,
                $yearsToProject,
                $currentYear
            );
        }

        // Include spouse liabilities if data sharing enabled
        if ($dataSharingEnabled && $spouse) {
            foreach ($spouse->mortgages as $mortgage) {
                $endDate = $mortgage->end_date;
                $projectedLiabilities += $this->projectSingleLiability(
                    (float) ($mortgage->outstanding_balance ?? 0),
                    $endDate instanceof \DateTimeInterface ? $endDate->format('Y-m-d') : $endDate,
                    $currentAge,
                    $retirementAge,
                    $yearsToProject,
                    $currentYear
                );
            }

            foreach ($spouse->liabilities as $liability) {
                $endDate = $liability->maturity_date ?? $this->estimatePayoffDate($liability);
                $projectedLiabilities += $this->projectSingleLiability(
                    (float) ($liability->current_balance ?? 0),
                    $endDate instanceof \DateTimeInterface ? $endDate->format('Y-m-d') : $endDate,
                    $currentAge,
                    $retirementAge,
                    $yearsToProject,
                    $currentYear
                );
            }
        }

        return $projectedLiabilities;
    }

    /**
     * Project a single liability using linear amortisation
     */
    private function projectSingleLiability(
        float $currentBalance,
        ?string $endDate,
        int $currentAge,
        int $retirementAge,
        int $yearsToProject,
        int $currentYear
    ): float {
        if ($currentBalance <= 0) {
            return 0;
        }

        // Determine years until liability ends
        if ($endDate) {
            $endYear = Carbon::parse($endDate)->year;
            $yearsUntilEnd = max(0, $endYear - $currentYear);
        } else {
            // Default: assume liability cleared at retirement age
            $yearsUntilEnd = max(0, $retirementAge - $currentAge);
        }

        // If liability ends before death, it contributes £0 at death
        if ($yearsToProject >= $yearsUntilEnd) {
            return 0;
        }

        // Linear amortisation: remaining balance proportional to remaining term
        if ($yearsUntilEnd <= 0) {
            return $currentBalance; // Already past end date but still has balance
        }

        $remainingTerm = $yearsUntilEnd - $yearsToProject;
        $projectedBalance = $currentBalance * ($remainingTerm / $yearsUntilEnd);

        return max(0, $projectedBalance);
    }

    /**
     * Estimate payoff date from balance, monthly payment, and interest rate.
     */
    private function estimatePayoffDate($liability): ?string
    {
        $balance = (float) ($liability->current_balance ?? 0);
        $monthly = (float) ($liability->monthly_payment ?? 0);

        if ($balance <= 0 || $monthly <= 0) {
            return null;
        }

        $annualRate = (float) ($liability->interest_rate ?? 0);
        $monthlyRate = $annualRate / 100 / 12;

        if ($monthlyRate > 0 && $monthly <= $balance * $monthlyRate) {
            return null; // Payment doesn't cover interest
        }

        if ($monthlyRate > 0) {
            $months = (int) ceil(-log(1 - ($balance * $monthlyRate / $monthly)) / log(1 + $monthlyRate));
        } else {
            $months = (int) ceil($balance / $monthly);
        }

        return now()->addMonths($months)->format('Y-m-d');
    }

    /**
     * Calculate RNRB with full explanation message
     *
     * ALWAYS returns a value (even £0) with explanatory message.
     * For widows/widowers, includes transferred RNRB from deceased spouse.
     *
     * `$residenceNetValue` is a required argument rather than something this method
     * looks up, because it has to be the residence value of the estate being
     * assessed. Deriving it here made the s8E(2) cap permanently current-valued, so
     * the projected band was capped at today's house price (W-0136).
     */
    private function calculateRNRB(
        float $totalNetEstate,
        float $residenceNetValue,
        User $user,
        ?User $spouse,
        array $ihtConfig,
        bool $poolsSpouse,
        bool $isWidowed = false,
        float $rnrbTransferred = 0.0
    ): array {
        $rnrbSingle = $ihtConfig['residence_nil_rate_band']; // £175,000
        $taperThreshold = $ihtConfig['rnrb_taper_threshold']; // £2,000,000
        $taperRate = $ihtConfig['rnrb_taper_rate']; // 0.5 (£1 lost per £2 over threshold)

        // W-0154 F3: `$poolsSpouse` replaces `$isMarried` here, and the caller passes
        // `$spouse` as null when their estate is not pooled. `hasMainResidence()` and
        // `hasDirectDescendants()` below both consult whoever they are given, as does
        // whichever residence valuation the caller passed in, so a spouse excluded
        // from the estate could previously grant this allowance and raise its cap
        // with a property that was not being taxed. The transferred figure arrives
        // already summed across the
        // pooled members, for the same reason.

        // Check eligibility: the estate must own a main residence AND leave it to
        // direct descendants. IHTA 1984 s8E/s8K: the RNRB only applies where the
        // residence is "closely inherited" by direct descendants (children,
        // grandchildren, step-children). Both conditions are hard requirements —
        // owning a home is not enough without qualifying descendants.
        $hasMainResidence = $this->hasMainResidence($user, $spouse);
        $hasDirectDescendants = $this->hasDirectDescendants($user, $spouse);

        // Calculate potential max RNRB for messaging
        $potentialMax = $poolsSpouse ? ($rnrbSingle * 2) : ($isWidowed && $rnrbTransferred > 0 ? $rnrbSingle + $rnrbTransferred : $rnrbSingle);

        if (! $hasMainResidence) {
            return [
                'rnrb_available' => 0,
                'rnrb_individual' => 0,
                'rnrb_transferred' => 0,
                'rnrb_status' => 'none',
                'rnrb_message' => 'Residence Nil Rate Band not available. You need to own a main residence and leave it to direct descendants (children, grandchildren, step-children) to qualify for Residence Nil Rate Band of up to £'.number_format($potentialMax).'. Nieces, nephews, cousins, siblings, and other relatives are not direct descendants and do not qualify.',
            ];
        }

        if (! $hasDirectDescendants) {
            return [
                'rnrb_available' => 0,
                'rnrb_individual' => 0,
                'rnrb_transferred' => 0,
                'rnrb_status' => 'none',
                'rnrb_message' => 'Residence Nil Rate Band not available — you have no direct descendants recorded. The Residence Nil Rate Band of up to £'.number_format($potentialMax).' only applies when your main residence passes to direct descendants (children, grandchildren, step-children). Nieces, nephews, cousins, siblings, and other relatives do not qualify.',
            ];
        }

        // Calculate full RNRB (pooled couple gets double, widow with transfer gets own + transferred)
        if ($poolsSpouse) {
            $fullRNRB = $rnrbSingle * 2;
        } elseif ($isWidowed && $rnrbTransferred > 0) {
            $fullRNRB = $rnrbSingle + $rnrbTransferred;
        } else {
            $fullRNRB = $rnrbSingle;
        }

        // IHTA 1984 s8E(2): the RNRB is the LOWER of the maximum allowance and the
        // net value of the residence closely inherited by descendants. Cap the full
        // allowance at the net-of-mortgage, ownership-share-adjusted residence value
        // BEFORE the £2m taper is applied. A £200k home therefore limits the RNRB to
        // £200k even for a married couple whose combined maximum would be £350k.
        $cappedByResidence = $residenceNetValue < $fullRNRB;
        $fullRNRB = min($fullRNRB, $residenceNetValue);

        // Check for taper
        if ($totalNetEstate <= $taperThreshold) {
            // Build message based on status
            if ($cappedByResidence) {
                $rnrbMsg = 'Residence Nil Rate Band of £'.number_format($fullRNRB).' available — capped at the net value of your main residence (£'.number_format($residenceNetValue).'), which is lower than the maximum allowance of £'.number_format($potentialMax).'. Your estate is below the £'.number_format($taperThreshold).' taper threshold.';
            } elseif ($poolsSpouse) {
                $rnrbMsg = 'Full Residence Nil Rate Band of £'.number_format($fullRNRB).' available (£'.number_format($rnrbSingle).' each). Your combined estate is below the £'.number_format($taperThreshold).' taper threshold.';
            } elseif ($isWidowed && $rnrbTransferred > 0) {
                $rnrbMsg = 'Full Residence Nil Rate Band of £'.number_format($fullRNRB).' available (own £'.number_format($rnrbSingle).' + £'.number_format($rnrbTransferred).' transferred from late spouse\'s estate). Your estate is below the £'.number_format($taperThreshold).' taper threshold.';
            } else {
                $rnrbMsg = 'Full Residence Nil Rate Band of £'.number_format($fullRNRB).' available. Your estate is below the £'.number_format($taperThreshold).' taper threshold.';
            }

            return [
                'rnrb_available' => $fullRNRB,
                'rnrb_individual' => $rnrbSingle,
                'rnrb_transferred' => $rnrbTransferred,
                'rnrb_status' => $cappedByResidence ? 'residence_capped' : 'full',
                'rnrb_message' => $rnrbMsg,
            ];
        }

        // Apply taper
        $excess = $totalNetEstate - $taperThreshold;
        $reduction = $excess * $taperRate;
        $rnrbAvailable = max(0, $fullRNRB - $reduction);

        if ($rnrbAvailable > 0) {
            return [
                'rnrb_available' => $rnrbAvailable,
                'rnrb_individual' => $rnrbSingle,
                'rnrb_transferred' => $rnrbTransferred,
                'rnrb_status' => 'tapered',
                'rnrb_message' => 'Residence Nil Rate Band reduced to £'.number_format($rnrbAvailable).' due to estate taper. Your estate of £'.number_format($totalNetEstate).' exceeds £'.number_format($taperThreshold).' by £'.number_format($excess).', reducing RNRB by £'.number_format($reduction).' (£1 reduction per £2 over threshold).',
            ];
        }

        // Fully tapered away
        return [
            'rnrb_available' => 0,
            'rnrb_individual' => $rnrbSingle,
            'rnrb_transferred' => $rnrbTransferred,
            'rnrb_status' => 'tapered',
            'rnrb_message' => 'Residence Nil Rate Band fully tapered away. Your estate of £'.number_format($totalNetEstate).' exceeds the taper threshold of £'.number_format($taperThreshold).' by £'.number_format($excess).', eliminating all RNRB of £'.number_format($fullRNRB).'.',
        ];
    }

    /**
     * Determine IHT rate based on charitable giving
     *
     * If 10%+ of the "baseline" (net estate minus NRB, excluding RNRB) is left to charity,
     * the reduced rate of 36% applies instead of 40%.
     *
     * @param  User  $user  The primary user
     * @param  float  $netEstate  Total net estate value
     * @param  float  $nrbAvailable  Total NRB available (single or combined)
     * @param  array  $ihtConfig  Tax configuration for IHT
     * @return array Rate determination with type and message
     */
    private function determineIHTRate(
        User $user,
        array $pooledMembers,
        float $netEstate,
        float $nrbAvailable,
        array $ihtConfig,
        ?Collection $profiles = null
    ): array {
        $standardRate = $ihtConfig['standard_rate']; // 0.40 (40%)
        $reducedRate = $this->taxConfig->getCharitableReducedRate();

        // W-0154 F1 — the exemption and the rate test do NOT use the same set of
        // wills, and conflating them is the intuitive fix that is wrong.
        //
        // tax-compliance-reviewer's statutory ruling, 2026-08-21: there is no
        // household baseline in law. IHTA 1984 Sch 1A tests the estate of ONE
        // deceased person.
        //
        //   * s23(1) EXEMPTION — every pooled member's charitable legacies are paid
        //     and every one of them leaves the combined estate. Deducting only the
        //     logged-in user's understated the exemption on both accounts: a
        //     household where each spouse left £10,000 to a different charity had
        //     £10,000 deducted, never £20,000, whichever spouse logged in.
        //
        //   * 10% RATE TEST — only the SURVIVOR's will counts. This service models
        //     to the second death, so the estate being tested is the survivor's and
        //     the will operating on it is theirs. The first-to-die's legacy was
        //     tested on the first death against an estate that, under full spouse
        //     exemption, is nil — so no rate question arose there and their legacy
        //     cannot be added to the survivor's. **Summing both wills for the 10%
        //     test would over-qualify households for the 36% rate.**
        //
        // This is a modelling ruling — a statute mapped onto a construct the statute
        // does not have — and it carries a product sign-off requirement (W-0154).
        $survivor = $this->survivingMember($user, $pooledMembers[1] ?? null, count($pooledMembers) > 1);

        $profiles ??= IHTProfile::whereIn('user_id', array_map(fn (User $m): int => $m->id, $pooledMembers))
            ->get()
            ->keyBy('user_id');

        // The planning percentage is a property of the estate being rate-tested, so
        // it comes from the survivor rather than from whoever happens to be logged in.
        $charitablePercent = $profiles->get($survivor->id)?->charitable_giving_percent ?? 0;

        // Calculate baseline: Net Estate - NRB (RNRB is excluded from baseline calculation)
        $baseline = max(0, $netEstate - $nrbAvailable);

        // Threshold for reduced rate: 10% of baseline
        $threshold = $baseline * $this->taxConfig->getCharitableThresholdPercent();

        // Calculate charitable amount as percentage of net estate
        $charitableAmount = $netEstate * ($charitablePercent / 100);

        // W-0020: the recorded will wins. Until 2026-08-21 this rate was decided
        // solely by charitable_giving_percent — a planning figure the user types
        // on their Inheritance Tax profile — while the bequests actually
        // recorded in their will were never consulted. So a user could enter a
        // charitable legacy, see it in their generated will, and still be told
        // they had left nothing to charity. Two answers to "what is going to
        // charity", never speaking to each other (Rule 20).
        //
        // The will is the instrument HMRC reads, so recorded charitable
        // bequests take precedence. The profile percentage remains the answer
        // for a user who has recorded no bequests.
        // The exemption pools every member's legacies; the rate test uses the
        // survivor's alone. See the ruling in this method's opening comment.
        $bequestTotal = 0.0;
        foreach ($pooledMembers as $member) {
            $bequestTotal += $this->willAnalysis->getCharitableBequestTotal($member, $netEstate);
        }

        $survivorBequestTotal = count($pooledMembers) > 1
            ? $this->willAnalysis->getCharitableBequestTotal($survivor, $netEstate)
            : $bequestTotal;

        if ($bequestTotal > 0) {
            $charitableAmount = $bequestTotal;
            $rateTestAmount = $survivorBequestTotal;
            $charitablePercent = $netEstate > 0 ? ($survivorBequestTotal / $netEstate) * 100 : 0;
        }

        // `$charitableAmount` is the s23(1) exemption the caller deducts from the
        // estate — pooled. `$rateTestAmount` is what the 10% test compares — the
        // survivor's alone. They are equal for a single person and differ for a
        // household where both spouses left a legacy. The messages quote the
        // rate-test figure, because the comparison they describe is the rate test.
        $rateTestAmount ??= $charitableAmount;

        // Check if charitable giving meets the 10% of baseline threshold
        if ($charitablePercent > 0 && $rateTestAmount >= $threshold && $baseline > 0) {
            return [
                'rate' => $reducedRate,
                'type' => 'reduced',
                'message' => 'Reduced IHT rate of 36% applies. Your charitable giving of '.number_format($charitablePercent, 1).'% (£'.number_format($rateTestAmount).') meets the 10% threshold of £'.number_format($threshold).' (10% of baseline £'.number_format($baseline).').',
                'charitable_percent' => $charitablePercent,
                'charitable_amount' => round($charitableAmount, 2),
                'charitable_rate_test_amount' => round($rateTestAmount, 2),
                'baseline' => round($baseline, 2),
                'threshold' => round($threshold, 2),
            ];
        }

        // Standard rate applies
        if ($charitablePercent > 0 && $baseline > 0) {
            $shortfall = $threshold - $rateTestAmount;

            return [
                'rate' => $standardRate,
                'type' => 'standard',
                'message' => 'Standard IHT rate of 40% applies. Your charitable giving of '.number_format($charitablePercent, 1).'% (£'.number_format($rateTestAmount).') is below the 10% threshold of £'.number_format($threshold).'. Increase by £'.number_format($shortfall).' to qualify for 36% rate.',
                'charitable_percent' => $charitablePercent,
                'charitable_amount' => round($charitableAmount, 2),
                'charitable_rate_test_amount' => round($rateTestAmount, 2),
                'baseline' => round($baseline, 2),
                'threshold' => round($threshold, 2),
            ];
        }

        return [
            'rate' => $standardRate,
            'type' => 'standard',
            'message' => 'Standard IHT rate of 40% applies. Leave 10%+ of your baseline estate (£'.number_format($baseline).') to charity to qualify for the reduced 36% rate.',
            'charitable_percent' => 0,
            'charitable_amount' => round($charitableAmount, 2),
            'baseline' => round($baseline, 2),
            'threshold' => round($threshold, 2),
        ];
    }

    /**
     * How many more years this person is projected to live.
     *
     * Delegates to `FutureValueCalculator::getLifeExpectancy(User)`, which honours
     * `users.life_expectancy_override` before falling back to an interpolated lookup
     * from the Office for National Statistics actuarial life tables.
     *
     * **This used to call `getLifeExpectancyYears(int $age, string $gender)` instead**
     * — the one method on that calculator which never receives the user and therefore
     * *cannot* see the override, however it is written. So a household that told the
     * application when they expect to die was answered one way by retirement
     * (`RetirementAgent`), the same way by decumulation (`DecumulationController`),
     * and another way by their inheritance tax projection, with nothing anywhere
     * revealing the disagreement. The override moves the whole projection horizon, so
     * the two answers were not close.
     *
     * The missing-date-of-birth and missing-gender cases are handled inside the
     * calculator (30 years from an assumed age 85, and a default cohort respectively),
     * rather than short-circuited to a literal 25 here that no other module used.
     */
    private function calculateLifeExpectancy(User $user): int
    {
        return (int) round(
            (float) $this->futureValueCalculator->getLifeExpectancy($user)['years_remaining']
        );
    }

    /**
     * Check if user or spouse has main residence
     */
    private function hasMainResidence(User $user, ?User $spouse): bool
    {
        // PropertyStore::forUserByType is joint-aware. Filter to primary-owner-only so the
        // RNRB-eligibility check matches the pre-PR-5a semantics: a user qualifies only when
        // they are the primary owner of a main_residence record, not merely a joint owner.
        $userHasMainRes = $this->propertyStore
            ->forUserByType($user, 'main_residence')
            ->where('user_id', $user->id)
            ->isNotEmpty();

        if ($userHasMainRes) {
            return true;
        }

        if ($spouse) {
            return $this->propertyStore
                ->forUserByType($spouse, 'main_residence')
                ->where('user_id', $spouse->id)
                ->isNotEmpty();
        }

        return false;
    }

    /**
     * Determine whether the user (or spouse) has direct descendants who could
     * "closely inherit" the residence for RNRB purposes (IHTA 1984 s8K).
     *
     * Mirrors the direct-descendant lookup used in HouseholdPlanningService:
     * children, grandchildren, and step-children qualify. Other relatives
     * (nieces, nephews, cousins, siblings) do not.
     */
    private function hasDirectDescendants(User $user, ?User $spouse): bool
    {
        $directRelationships = ['child', 'grandchild', 'step_child'];

        if ($user->familyMembers()->whereIn('relationship', $directRelationships)->exists()) {
            return true;
        }

        if ($spouse) {
            return $spouse->familyMembers()->whereIn('relationship', $directRelationships)->exists();
        }

        return false;
    }

    /**
     * Net value of the main residence(s) closely inherited by descendants,
     * valued exactly as the estate values them so the RNRB cap aligns with
     * total_net_estate: ownership-share adjusted and net of the mortgage share.
     *
     * IHTA 1984 s8E(2): the RNRB is limited to this net residence value.
     */
    private function getMainResidenceNetValue(User $user, ?User $spouse): float
    {
        $value = $this->sumMainResidenceNetShare($user);

        if ($spouse) {
            $value += $this->sumMainResidenceNetShare($spouse);
        }

        return max(0.0, $value);
    }

    /**
     * Sum a single user's net share of their main residence(s): their ownership
     * share of the value less their share of any mortgage secured on it. Uses the
     * shared CalculatesOwnershipShare logic so the figure matches the property and
     * mortgage values that feed total_net_estate.
     */
    private function sumMainResidenceNetShare(User $user): float
    {
        return (float) $this->propertyStore
            ->forUserByType($user, 'main_residence')
            ->sum(function ($property) use ($user) {
                $valueShare = $this->calculateUserShare($property, $user->id);
                $mortgageShare = (float) $property->mortgages->sum(
                    fn ($mortgage) => $this->calculateUserMortgageShare($mortgage, $user->id)
                );

                return max(0.0, $valueShare - $mortgageShare);
            });
    }

    /**
     * Get cached calculation if valid
     */
    private function getCachedCalculation(User $user, ?User $spouse, bool $dataSharingEnabled): ?array
    {
        // Get latest calculation for this user
        $cached = IHTCalculation::where('user_id', $user->id)
            ->where('is_married', $spouse !== null)
            ->where('data_sharing_enabled', $dataSharingEnabled)
            ->latest('calculation_date')
            ->first();

        if (! $cached) {
            return null;
        }

        // Generate current hashes
        $currentHashes = $this->generateHashes($user, $spouse, $dataSharingEnabled);

        // Check if hashes match (data hasn't changed)
        if ($cached->assets_hash === $currentHashes['assets_hash'] &&
            $cached->liabilities_hash === $currentHashes['liabilities_hash'] &&
            $cached->result_json &&
            $this->isCurrentResultShape($cached->result_json)) {
            return $cached->result_json;
        }

        return null;
    }

    /**
     * Does a stored result carry every figure today's consumers read?
     *
     * The hashes fingerprint the DATA. They say nothing about the CODE that
     * produced the row, so a result persisted before W-0136 added the projected
     * allowance components would pass every hash check and then be served to a
     * controller that reads keys it does not contain.
     *
     * Recomputing is the right answer to a stale shape. Filling the gaps with
     * `?? 0` would publish a £0 allowance as though it were a finding — which is
     * precisely the failure mode this work item is full of.
     *
     * Nothing writes these rows today (`$persist` is false at every call site —
     * W-0131), so this guard is dormant. It is here so that fixing W-0131 does not
     * quietly resurrect a pre-fix answer.
     */
    private function isCurrentResultShape(array $result): bool
    {
        foreach (['projected_total_allowances', 'projected_charitable_deduction', 'projected_rnrb_status'] as $key) {
            if (! array_key_exists($key, $result)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate hashes for cache invalidation
     */
    /**
     * A fingerprint of the bequests that can move the Inheritance Tax rate.
     *
     * The cached calculation is keyed on hashes of assets and liabilities only.
     * That was sound while the rate depended solely on
     * IHTProfile.charitable_giving_percent — but W-0020 made the rate read the
     * user's recorded bequests, so without this a user could add a charitable
     * legacy, qualify for the reduced rate, and keep being served the previous
     * 40% figure from cache until their assets happened to change. That is the
     * exact journey W-0020 exists to fix, so the fingerprint belongs in the key.
     *
     * Sorted, so re-ordering bequests alone does not invalidate the cache.
     */
    private function charitableBequestFingerprint(User $user): string
    {
        $will = Will::where('user_id', $user->id)->with('bequests')->first();

        if (! $will) {
            return 'none';
        }

        return $will->bequests
            ->map(fn (Bequest $bequest) => implode(':', [
                $bequest->bequest_type,
                (string) $bequest->specific_amount,
                (string) $bequest->percentage_of_estate,
                $bequest->isCharitable() ? 'charity' : 'other',
            ]))
            ->sort()
            ->implode(',');
    }

    private function generateHashes(User $user, ?User $spouse, bool $dataSharingEnabled): array
    {
        $userAssets = $this->aggregator->gatherUserAssets($user);
        $spouseAssets = ($spouse && $dataSharingEnabled) ? $this->aggregator->gatherUserAssets($spouse) : collect();

        $assetsString = $userAssets->pluck('current_value')->join(',').'|'.$spouseAssets->pluck('current_value')->join(',')
            .'|'.$this->charitableBequestFingerprint($user);
        $assetsHash = hash('sha256', $assetsString);

        $userLiabilities = $this->aggregator->calculateUserLiabilities($user);
        $spouseLiabilities = ($spouse && $dataSharingEnabled) ? $this->aggregator->calculateUserLiabilities($spouse) : 0;

        $liabilitiesString = $userLiabilities.'|'.$spouseLiabilities;
        $liabilitiesHash = hash('sha256', $liabilitiesString);

        return [
            'assets_hash' => $assetsHash,
            'liabilities_hash' => $liabilitiesHash,
        ];
    }

    /**
     * Save calculation to database
     */
    private function saveCalculation(
        User $user,
        array $result,
        Collection $userAssets,
        Collection $spouseAssets,
        float $userLiabilities,
        float $spouseLiabilities
    ): void {
        // Generate hashes
        $assetsString = $userAssets->pluck('current_value')->join(',').'|'.$spouseAssets->pluck('current_value')->join(',')
            .'|'.$this->charitableBequestFingerprint($user);
        $liabilitiesString = $userLiabilities.'|'.$spouseLiabilities;

        IHTCalculation::create([
            'user_id' => $user->id,
            'user_gross_assets' => $result['user_gross_assets'],
            'spouse_gross_assets' => $result['spouse_gross_assets'],
            'total_gross_assets' => $result['total_gross_assets'],
            'user_total_liabilities' => $result['user_total_liabilities'],
            'spouse_total_liabilities' => $result['spouse_total_liabilities'],
            'total_liabilities' => $result['total_liabilities'],
            'user_net_estate' => $result['user_net_estate'],
            'spouse_net_estate' => $result['spouse_net_estate'],
            'total_net_estate' => $result['total_net_estate'],
            'nrb_available' => $result['nrb_available'],
            'nrb_message' => $result['nrb_message'],
            'rnrb_available' => $result['rnrb_available'],
            'rnrb_status' => $result['rnrb_status'],
            'rnrb_message' => $result['rnrb_message'],
            'total_allowances' => $result['total_allowances'],
            'taxable_estate' => $result['taxable_estate'],
            'iht_liability' => $result['iht_liability'],
            'effective_rate' => $result['effective_rate'],
            'projected_gross_assets' => $result['projected_gross_assets'],
            'projected_liabilities' => $result['projected_liabilities'],
            'projected_net_estate' => $result['projected_net_estate'],
            'projected_taxable_estate' => $result['projected_taxable_estate'],
            'projected_iht_liability' => $result['projected_iht_liability'],
            'projected_cash' => $result['projected_cash'] ?? null,
            'projected_investments' => $result['projected_investments'] ?? null,
            'projected_properties' => $result['projected_properties'] ?? null,
            'retirement_age' => $result['retirement_age'] ?? null,
            'result_json' => $result,
            'years_to_death' => $result['years_to_death'],
            'estimated_age_at_death' => $result['estimated_age_at_death'],
            'calculation_date' => now(),
            'is_married' => $result['is_married'],
            'data_sharing_enabled' => $result['data_sharing_enabled'],
            'assets_hash' => hash('sha256', $assetsString),
            'liabilities_hash' => hash('sha256', $liabilitiesString),
        ]);
    }

    /**
     * The nil-rate-band footnote, stated as the band actually applied.
     *
     * W-0134 acceptance 4. This sentence sits directly beneath the allowance rows
     * of `IHTCalculationTable.vue`, and it is the last place on that page a reader
     * meets a figure they cannot reconcile with the column above it. The rows
     * itemise £325,000 each, less the gift deduction, reaching the applied band;
     * the prose opened with the pre-deduction total and called it "available".
     *
     * Two rules govern the wording, both inherited from the rows rather than
     * invented here (Rule 20 — one vocabulary):
     *
     *   * the headline is the APPLIED band, with the gift deduction shown as the
     *     working that gets you there, not appended as an afterthought; and
     *   * a living spouse's £325,000 is described as modelled on second death,
     *     matching the `nrb-spouse-modelled` row note, because IHTA 1984 s8A
     *     creates the transferable band on the survivor's death and not before.
     *
     * @param  float  $nrbGross  The band before gifts are deducted
     * @param  float  $nrbAvailable  The band actually applied to the estate
     * @param  array{total_nrb_used: float, clts_7_to_14_years: float}  $deduction
     */
    private function buildNrbMessage(
        float $nrbGross,
        float $nrbAvailable,
        float $nrbSingle,
        float $nrbTransferred,
        array $deduction,
        bool $poolsSpouse,
        bool $isWidowed
    ): string {
        $giftsUsed = (float) $deduction['total_nrb_used'];

        if ($poolsSpouse) {
            $composition = '£'.number_format($nrbSingle).' each';
        } elseif ($isWidowed && $nrbTransferred > 0) {
            $composition = 'own £'.number_format($nrbSingle)
                .' plus £'.number_format($nrbTransferred).' transferred from your late spouse\'s estate';
        } else {
            $composition = null;
        }

        $heading = ($poolsSpouse || ($isWidowed && $nrbTransferred > 0))
            ? 'Combined Nil Rate Band'
            : 'Nil Rate Band';

        if ($giftsUsed > 0) {
            $working = $composition ?? '£'.number_format($nrbGross);
            $message = $heading.' of £'.number_format($nrbAvailable).' applied: '.$working
                .', less £'.number_format($giftsUsed).' of allowance used by gifts made within the last 7 years'
                .($deduction['clts_7_to_14_years'] > 0
                    ? ' (including the 14-year rule for historical Chargeable Lifetime Transfers)'
                    : '')
                .'.';
        } else {
            $message = $heading.' of £'.number_format($nrbAvailable).' applied'
                .($composition !== null ? ' ('.$composition.')' : '')
                .'.';
        }

        if ($poolsSpouse) {
            $message .= ' Your spouse\'s £'.number_format($nrbSingle)
                .' is modelled on second death — there is no transferable allowance while you are both alive.'
                .' Transfers between spouses are exempt from Inheritance Tax on the first death.';
        }

        return $message;
    }

    /**
     * Gift deduction (potentially exempt transfers and chargeable lifetime
     * transfers) across every pooled member, each capped at their own band.
     *
     * Implements the 14-year rule (Direction B): historical CLTs made 7-14 years
     * before death reduce the NRB available for PETs in the final 7 years.
     *
     * **W-0154.** This read the primary user only, and its docblock said spouse nil
     * rate band was "tracked separately by SpouseNRBTrackerService". **That service
     * has no callers** — verified repo-wide, twice. So one spouse's gifts reduced the
     * household band when they logged in and did nothing when the other did, and the
     * comment is why nobody looked. The claim is removed rather than reworded; see
     * W-0146 for whether the service is wired up or deleted.
     *
     * @param  list<User>  $members  The people whose records this calculation covers
     * @param  float  $nrbSingle  The individual NRB amount
     * @return array NRB deduction breakdown, summed across members
     */
    private function calculateNRBDeductionForGifts(array $members, float $nrbSingle): array
    {
        $totals = [
            'pets_in_7_years' => 0.0,
            'clts_in_7_years' => 0.0,
            'clts_7_to_14_years' => 0.0,
            'nrb_used_by_clts' => 0.0,
            'nrb_used_by_pets' => 0.0,
            'total_nrb_used' => 0.0,
        ];

        foreach ($members as $member) {
            $memberDeduction = $this->nrbDeductionForOneMember($member, $nrbSingle);

            foreach (array_keys($totals) as $key) {
                $totals[$key] += $memberDeduction[$key];
            }
        }

        return array_map(fn (float $value): float => round($value, 2), $totals)
            + ['fourteen_year_rule_applied' => $totals['clts_7_to_14_years'] > 0];
    }

    /**
     * One person's gift deduction, capped at their OWN nil rate band.
     *
     * **The cap is the point, and it is why this is per member.** The deduction used
     * to be summed and then taken off the POOLED band —
     * `max(0, $nrbAvailable - $total)` against £650,000 — so one spouse's chargeable
     * transfers could consume the other's band. IHTA 1984 s8A transfers the unused
     * **percentage** of the first-to-die's band and that percentage cannot go below
     * zero: a £400,000 transfer exhausts that person's own £325,000 and reaches no
     * further. The old code gave £250,000; s8A gives the survivor their full
     * £325,000.
     *
     * The per-person cap already existed for the chargeable-lifetime-transfer
     * subtotal (`min($nrbSingle, ...)` below) — it was simply never applied to the
     * household total. Capping here means the sum across members can never exceed
     * members × `$nrbSingle`, which is the invariant that was missing.
     *
     * @return array<string, float>
     */
    private function nrbDeductionForOneMember(User $member, float $nrbSingle): array
    {
        // PETs within 7 years of today (assumed death date for calculation)
        $petsIn7Years = Gift::where('user_id', $member->id)
            ->where('gift_type', 'pet')
            ->where('gift_date', '>', today()->subYears(7))
            ->sum('gift_value');

        // CLTs within 7 years
        $cltsIn7Years = Gift::where('user_id', $member->id)
            ->where('gift_type', 'clt')
            ->where('gift_date', '>', today()->subYears(7))
            ->sum('gift_value');

        // 14-year rule (Direction B): CLTs made 7-14 years before death
        // These CLTs don't incur IHT themselves (outside 7-year window),
        // but they DO reduce the NRB available for PETs in the final 7 years
        $clts7to14Years = Gift::where('user_id', $member->id)
            ->where('gift_type', 'clt')
            ->where('gift_date', '>', today()->subYears(14))
            ->where('gift_date', '<=', today()->subYears(7))
            ->sum('gift_value');

        // CLTs (both recent and historical) consume NRB first
        $nrbUsedByCLTs = min($nrbSingle, (float) $cltsIn7Years + (float) $clts7to14Years);

        // Remaining NRB available for PETs after CLT consumption
        $nrbRemainingForPETs = max(0, $nrbSingle - $nrbUsedByCLTs);
        $nrbUsedByPETs = min($nrbRemainingForPETs, (float) $petsIn7Years);

        return [
            'pets_in_7_years' => (float) $petsIn7Years,
            'clts_in_7_years' => (float) $cltsIn7Years,
            'clts_7_to_14_years' => (float) $clts7to14Years,
            'nrb_used_by_clts' => $nrbUsedByCLTs,
            'nrb_used_by_pets' => $nrbUsedByPETs,
            'total_nrb_used' => $nrbUsedByCLTs + $nrbUsedByPETs,
        ];
    }

    /**
     * The people whose records this calculation covers.
     *
     * ONE decision, used by assets, liabilities, gifts, transferred allowances and
     * wills alike (W-0154). The predicate matches the one the asset and liability
     * pooling already used, which is what makes the inputs consistent with the
     * estate they are being applied to.
     *
     * @return list<User>
     */
    private function pooledMembers(User $user, ?User $spouse, bool $isMarried, bool $dataSharingEnabled): array
    {
        return ($isMarried && $spouse !== null && $dataSharingEnabled) ? [$user, $spouse] : [$user];
    }

    /**
     * Whose estate the second-death model lands on.
     *
     * The service already models a married couple to the second death, so the estate
     * being taxed is the survivor's, and the charitable rate test must run against
     * that person's will.
     *
     * **Rule 20, stated rather than done:** `calculateProjectedValues()` holds an
     * equivalent comparison inline (the `max()` of the two life expectancies, and
     * whose age to project from). It is deliberately not refactored onto this helper
     * here — that method is `W-0137`'s territory and was under investigation by
     * another batch when this landed. **Converging the two belongs with W-0137**, and
     * the behaviour is identical today so nothing is broken by the wait.
     *
     * Falls back to the primary user whenever the spouse's life expectancy cannot be
     * computed, which is the same guard the projection applies.
     */
    private function survivingMember(User $user, ?User $spouse, bool $poolsSpouse): User
    {
        if (! $poolsSpouse || $spouse === null || ! $spouse->date_of_birth || ! $spouse->gender) {
            return $user;
        }

        return $this->calculateLifeExpectancy($spouse) > $this->calculateLifeExpectancy($user)
            ? $spouse
            : $user;
    }

    /**
     * Calculate the 2027 pension Inheritance Tax amendment dual-scenario projection.
     *
     * From April 2027, unused defined contribution pension pots will be included
     * in the taxable estate for Inheritance Tax purposes (Autumn Budget 2024).
     *
     * Returns both the current rules scenario and the post-2027 scenario,
     * allowing users to understand the potential impact.
     *
     * @param  User  $user  The primary user
     * @param  User|null  $spouse  The spouse
     * @param  bool  $dataSharingEnabled  Whether spouse data sharing is enabled
     * @param  array  $baseCalc  The base IHT calculation result
     * @return array Dual-scenario pension amendment data
     */
    private function calculatePensionAmendmentScenario(
        User $user,
        ?User $spouse,
        bool $dataSharingEnabled,
        array $baseCalc
    ): array {
        $pensionInclusion = $this->taxConfig->get('inheritance_tax.pension_iht_inclusion');

        // If pension IHT inclusion config not set, return no amendment
        if (! $pensionInclusion || ! isset($pensionInclusion['effective_date'])) {
            return [
                'amendment_warning' => false,
                'message' => 'No pension Inheritance Tax amendment configuration found.',
            ];
        }

        $effectiveDate = Carbon::parse($pensionInclusion['effective_date']);

        // Get total DC pension values
        $store = app(PensionStore::class);
        $userPensionValue = (float) $store->forUserByType($user, 'dc')->sum('current_fund_value');
        $spousePensionValue = 0;
        if ($dataSharingEnabled && $spouse) {
            $spousePensionValue = (float) $store->forUserByType($spouse, 'dc')->sum('current_fund_value');
        }
        $totalPensionValue = $userPensionValue + $spousePensionValue;

        // If no pension value, no impact
        if ($totalPensionValue <= 0) {
            return [
                'amendment_warning' => false,
                'message' => 'No defined contribution pension values to include.',
            ];
        }

        // Calculate the post-2027 scenario: pensions included in estate
        $currentNetEstate = $baseCalc['total_net_estate'] ?? 0;
        $postAmendmentNetEstate = $currentNetEstate + $totalPensionValue;
        $totalAllowances = $baseCalc['total_allowances'] ?? 0;
        $ihtRate = $baseCalc['iht_rate'] ?? (float) $this->taxConfig->getInheritanceTax()['standard_rate'];
        // Charitable legacies are exempt (IHTA 1984 s23) — deduct them here too so the
        // 2027 projection matches the current/death calculation for charitable donors.
        $charitableDeduction = (float) ($baseCalc['charitable_deduction'] ?? 0);

        $postAmendmentTaxableEstate = max(0, $postAmendmentNetEstate - $totalAllowances - $charitableDeduction);
        $postAmendmentIHTLiability = $postAmendmentTaxableEstate * $ihtRate;

        $currentIHTLiability = $baseCalc['iht_liability'] ?? 0;
        $additionalIHT = $postAmendmentIHTLiability - $currentIHTLiability;

        return [
            'amendment_warning' => true,
            'effective_date' => $effectiveDate->format('Y-m-d'),
            'announced' => $pensionInclusion['announced'] ?? 'Autumn Budget 2024',
            'current_rules' => [
                'net_estate' => round($currentNetEstate, 2),
                'iht_liability' => round($currentIHTLiability, 2),
                'pensions_included' => false,
                'description' => 'Under current rules, defined contribution pensions pass outside the estate and are not subject to Inheritance Tax.',
            ],
            'post_2027_rules' => [
                'net_estate' => round($postAmendmentNetEstate, 2),
                'pension_value_included' => round($totalPensionValue, 2),
                'user_pension_value' => round($userPensionValue, 2),
                'spouse_pension_value' => round($spousePensionValue, 2),
                'iht_liability' => round($postAmendmentIHTLiability, 2),
                'additional_iht' => round($additionalIHT, 2),
                'pensions_included' => true,
                'description' => 'From April 2027, unused defined contribution pension pots will be included in the taxable estate for Inheritance Tax purposes.',
            ],
            'impact_summary' => $additionalIHT > 0
                ? 'The 2027 pension amendment could increase your Inheritance Tax liability by £'.number_format($additionalIHT).' if your defined contribution pension pots (£'.number_format($totalPensionValue).') are included in your estate.'
                : 'The 2027 pension amendment would not increase your Inheritance Tax liability based on current pension values.',
        ];
    }

    /**
     * Invalidate cache when assets or liabilities change
     */
    public function invalidateCache(User $user): void
    {
        IHTCalculation::where('user_id', $user->id)->delete();
    }
}
