<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Asset;
use App\Models\Estate\Bequest;
use App\Models\Estate\IHTCalculation;
use App\Models\Estate\IHTProfile;
use App\Models\Estate\Will;
use App\Models\LifeEvent;
use App\Models\User;
use App\Services\Settings\AssumptionsService;
use App\Services\Stores\PensionStore;
use App\Services\Stores\PropertyStore;
use App\Services\TaxConfigService;
use App\Support\HouseholdPooling;
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
 *
 * WHOSE RECORDS, AND HOW MUCH OF EACH — one answer, used by the headline and the
 * projection alike (W-0331).
 *
 * This service reports two estates in one response: the estate as it stands and
 * the estate projected to the second death. They must be the same household seen
 * at two moments, and they used to be assembled by two different rules.
 *
 * The rule, declared by the headline at `calculate()` step 3 and now read by the
 * projection too: **the union of the pooled members' records, each record counted
 * ONCE, at the share each member actually owns.** `gatherUserAssets()` and
 * `CrossModuleAssetAggregator` both express it as `forUserOrJoint` reach times
 * `calculateUserShare` fraction, per member.
 *
 * Two consequences worth stating because the wrong shapes look plausible:
 *   - Summing each member's records at 100% is NOT equivalent. It agrees only
 *     when every shared record is shared between the two members themselves. A
 *     record shared with a THIRD PARTY (`tenants_in_common`, `joint_owner_id`
 *     NULL) carries a stranger's share into the household's estate, and a
 *     stranger's money is not taxable on this death.
 *   - A member's own total already includes their share of records the OTHER
 *     member records, so the two members' totals add to the household exactly
 *     once. Adding a joint record again "for the other side" would double it.
 *
 * **WHICH PEOPLE are in the household is asked once, by `HouseholdPooling`
 * (W-0474, W-0340) — with one stated exception, the projection HORIZON in
 * `calculateProjectedValues()`, which is a fact about the household rather than
 * about permission and carries no sharing term. That exception is commented at the
 * line; everything else reads the one rule.** It used to be asked twice, differently: the headline pooled
 * on `$isMarried && $dataSharingEnabled`, every projection branch on
 * `$dataSharingEnabled && $spouse` alone, and neither `liveSpouse()` nor
 * `hasAcceptedSpousePermission()` consults `marital_status`. A civil partnership
 * was therefore assessed on two projected estates against one person's allowances,
 * and an unmarried linked couple got a headline taxing one estate and a projection
 * pooling two. Both halves read the one predicate now; a new branch that pools the
 * spouse calls it rather than re-deriving it.
 */
class IHTCalculationService
{
    /**
     * Words that mean agricultural land, for the W-0466 caveat trigger.
     *
     * Prefix-matched at a word boundary, so "farm" covers "farmland", "farmhouse"
     * and "farm buildings" without covering "pharmacy". Kept as a constant rather
     * than inlined so the one list is testable and has one home (Rule 20).
     */
    /**
     * Marital statuses that pool two people's records into one estate.
     *
     * One home for the list, because nine sibling services carry their own copy of
     * it and this service was the one that drifted (W-0474, Rule 20).
     */
    private const POOLING_MARITAL_STATUSES = HouseholdPooling::POOLING_MARITAL_STATUSES;

    private const AGRICULTURAL_ASSET_TERMS = [
        'farm', 'farmland', 'agricultur', 'arable', 'pasture', 'grazing',
        'smallholding', 'croft', 'orchard', 'paddock',
        // Added on round-five review: the two most defensible additions for land
        // a user describes by size or field name rather than by "farm".
        'acre', 'meadow',
    ];

    use CalculatesOwnershipShare;

    public function __construct(
        private readonly EstateAssetAggregatorService $aggregator,
        private readonly TaxConfigService $taxConfig,
        private readonly AssumptionsService $assumptionsService,
        private readonly FutureValueCalculator $futureValueCalculator,
        private readonly PropertyStore $propertyStore,
        private readonly PensionStore $pensionStore,
        private readonly WillAnalysisService $willAnalysis,
        private readonly HouseholdCashFlowProjector $cashFlowProjector,
        private readonly UndividedShareDiscount $undividedShareDiscount,
        private readonly FailedGiftTaxCalculator $failedGiftTax,
        // W-0527 — IHTA 1984 s141. The relief reaches BOTH columns because
        // `assessTaxPosition()` is the one mechanism that produces a liability
        // (:435 current, :973 projected), so it cannot be applied to one and not
        // the other — the disagreement W-0465 records.
        private readonly QuickSuccessionReliefCalculator $quickSuccessionRelief,
        // What the household owns and owes at the modelled date of death. The five
        // projected terms are asked for here and never re-derived; the growth,
        // amortisation and drawdown that decide them all live in one place (Rule 20).
        private readonly EstateProjectionService $estateProjection,
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
        $isMarried = $this->hasSpousalStatus($user) && $spouse !== null;
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
        $pooledMembers = $this->pooledMembers($user, $spouse, $dataSharingEnabled);
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
        $spouseAssets = $this->poolsSpouse($user, $spouse, $dataSharingEnabled)
            ? $this->aggregator->gatherUserAssets($spouse)
            : collect();

        // Filter out IHT-exempt assets (DC pensions, etc.)
        $userTaxableAssets = $userAssets->reject(fn ($asset) => $asset->is_iht_exempt ?? false);
        $spouseTaxableAssets = $spouseAssets->reject(fn ($asset) => $asset->is_iht_exempt ?? false);

        $userGrossAssets = $userTaxableAssets->sum('current_value');
        $spouseGrossAssets = $spouseTaxableAssets->sum('current_value');
        $totalGrossAssets = $userGrossAssets + $spouseGrossAssets;

        // W-0091 / W-0463 — PARTIAL Business Property Relief, which a boolean could
        // not express. A wholly relieved business is already gone via
        // `is_iht_exempt` above; one above the £2,500,000 cap stays in the estate at
        // its full value carrying 50% relief, and that relief has to come off
        // somewhere. It is published as its own deduction rather than netted into
        // the asset value so the estate still reconciles on screen: a user can see
        // the business at what it is worth and the relief as a line against it.
        $businessReliefDeduction = (float) $userTaxableAssets->sum(fn ($asset) => (float) ($asset->iht_relief_amount ?? 0))
            + (float) $spouseTaxableAssets->sum(fn ($asset) => (float) ($asset->iht_relief_amount ?? 0));

        // W-0466 — an estate holding farmland or AIM shares is shown a figure that
        // models NEITHER, and the two errors run in OPPOSITE directions:
        //   - Agricultural property has no asset type in the schema, so farmland is
        //     an ordinary asset carrying no relief. OVERSTATES tax, by up to ~40%
        //     of the land value.
        //   - AIM shares from 6 April 2026 take 50% relief OUTSIDE the allowance
        //     (IHTM25570). Recorded as a business interest they take 100% to the
        //     cap and UNDERSTATE it; held in an investment account they get nothing
        //     and it is overstated again.
        //
        // Registered in `UNIMPLEMENTED_RULES`, which tells the test suite and tells
        // no user. The reviewer's verdict: defensible as a documented exclusion,
        // not as a silent gap.
        //
        // **The trigger is the half we can actually identify.** A business interest
        // is a row; agricultural property is not expressible at all — `assets.
        // asset_type` is enum('property','pension','investment','business','other')
        // and `properties.property_type` is the three canonical residences. So a
        // farmer holding land and no company sees nothing, and that residual closes
        // with the schema change W-0466 records, not here. Widening the trigger to
        // every estate would breach the "only where it applies" condition and
        // desensitise the households it IS for.
        //
        // Read off the FULL collections, not the taxable ones: a wholly relieved
        // business is `is_iht_exempt` and has already been rejected above — which
        // is precisely an estate the caveat is for.
        // Tested separately rather than merged: `gatherUserAssets()` returns an
        // ELOQUENT collection carrying plain objects, and `merge()` on one keys the
        // items by `getKey()` — which a stdClass does not have. It throws.
        $isBusiness = fn ($asset): bool => ($asset->asset_type ?? null) === 'business';
        $holdsBusinessInterest = $userAssets->contains($isBusiness)
            || $spouseAssets->contains($isBusiness);

        // W-0466 — CSJ, 2026-08-24: **trigger on farmland specifically, not on the
        // whole `other` bucket.** `compliance-lead` was right that the sentence is
        // addressed to "if your estate holds farmland" and was shown only to estates
        // holding a company — the cohort whose figure is most wrong never saw it. But
        // widening to every `asset_type = 'other'` row would show it to someone whose
        // "other" asset is a bicycle or some Bitcoin, which is how a caveat becomes
        // wallpaper on the one screen it matters.
        //
        // There is no agricultural asset type in the schema (the registered dead end),
        // and `assets` carries no description — `asset_name` is the only field that can
        // carry the user's intent. So this is a NAME HEURISTIC and is labelled as one:
        // it will miss land the user named something else, and that is the failure
        // direction to prefer, because a missed caveat leaves the existing behaviour
        // where a false one actively misleads.
        //
        // **The durable fix is an agricultural asset type**, which is also what
        // Agricultural Property Relief itself needs; when that lands this goes.
        $holdsAgriculturalAsset = $userAssets->contains($this->looksAgricultural(...))
            || $spouseAssets->contains($this->looksAgricultural(...));

        $unmodelledReliefCaveat = ($holdsBusinessInterest || $holdsAgriculturalAsset)
            // Wording revised 2026-08-24 on `compliance-lead`'s findings A and B.
            // CSJ approved the substance; two rules bind the words.
            //
            // **Rule 9, as amended by CSJ 2026-08-24** — an acronym may be used
            // once it has been spelled out to that user. `compliance-lead` flagged
            // the tension honestly: an investor may recognise these shares only as
            // "AIM", so the spelled-out form alone is less identifiable. CSJ made
            // the amendment rather than the string making it: expanded on first
            // use, abbreviated on the second, both inside the one sentence the
            // reader has in front of them.
            //
            // **Rule 3** — a household told its figure could be wrong by up to ~40%
            // of its land value had been informed and not equipped. The signpost is
            // rule 1's own canonical phrasing.
            ? 'This figure does not include Agricultural Property Relief, and does not apply the special treatment of shares listed on the Alternative Investment Market (AIM). If your estate holds farmland or shares listed on AIM, your actual liability could be higher or lower than shown — it is worth discussing with a regulated financial adviser or a specialist solicitor.'
            : null;

        // 4. Fetch and sum liabilities
        $userLiabilities = $this->aggregator->calculateUserLiabilities($user);
        $spouseLiabilities = $this->poolsSpouse($user, $spouse, $dataSharingEnabled)
            ? $this->aggregator->calculateUserLiabilities($spouse)
            : 0;
        $totalLiabilities = $userLiabilities + $spouseLiabilities;

        // 5. Calculate net estate
        $userNetEstate = $userGrossAssets - $userLiabilities;
        $spouseNetEstate = $spouseGrossAssets - $spouseLiabilities;
        // Relief reduces the estate that is CHARGEABLE, exactly as the charitable
        // exemption does, so it belongs here beside the liabilities rather than
        // hidden inside an asset's value.
        $totalNetEstate = $totalGrossAssets - $totalLiabilities - $businessReliefDeduction;

        // F2 — the residence-band taper is measured on a DIFFERENT estate, and one
        // variable was doing both jobs.
        //
        // IHTM46023 on s8D(5)(d): "The taper threshold applies to the value of the
        // estate after liabilities, but BEFORE taking into account any exemptions or
        // reliefs." So E includes relieved business value at full worth, while the
        // Schedule 1A baseline is correctly struck AFTER reliefs (IHTM45031's worked
        // example opens "Estate £1,000,000 / Less agricultural relief −£200,000").
        //
        // Subtracting relief from the taper base let a business-owning estate keep a
        // residence band the taper should have removed — it UNDERSTATED tax, the one
        // direction a compliance surface must not lean. Wholly relieved assets are
        // dropped from gross at the filter above, so they are added back here too.
        // R2 — only the WHOLLY relieved assets need adding back.
        //
        // `$totalGrossAssets` already sums every non-exempt asset at full value, and
        // a PARTLY relieved business is not exempt (`is_iht_exempt` is set only when
        // relief covers the whole value) — so its full value is in there already.
        // Adding its relief amount as well counted the relief twice, and those two
        // terms were character-for-character the definition of
        // `$businessReliefDeduction`, so the overstatement equalled it exactly:
        // a £6m business with £4.25m of relief produced an E of £10.25m.
        //
        // Wholly relieved assets DO leave the gross filter, so they must come back.
        // Guarded on `iht_relief_qualifies` so genuinely exempt property — defined
        // contribution pensions — is not dragged into the taper base with them.
        $estateForTaper = $totalGrossAssets
            + (float) $userAssets->filter(fn ($a) => ($a->is_iht_exempt ?? false) && ($a->iht_relief_qualifies ?? false))->sum('current_value')
            + (float) $spouseAssets->filter(fn ($a) => ($a->is_iht_exempt ?? false) && ($a->iht_relief_qualifies ?? false))->sum('current_value')
            - $totalLiabilities;

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
        // F19 / F15's twin — the nil rate band was in exactly the state the residence
        // band was in, and for the same two reasons.
        //
        // UNCAPPED: `IHTController:266` validates `nrb_transferred_from_spouse` as
        // `numeric|min:0` with no ceiling, `:159` SUMS it across pooled members, and
        // it was added below with no `min()`. A widowed user entering £500,000 was
        // given an £825,000 band. s8A(3)-(5) transfers the unused PERCENTAGE of the
        // first-to-die's band and caps the increase at 100% of the maximum, so the
        // survivor's ceiling is 2 × £325,000 however many predeceased spouses there
        // are. Capped in the CALCULATION because the pooled sum can breach the
        // ceiling from individually valid rows.
        $nrbTransferred = min($nrbTransferred, $nrbSingle);

        // IDENTITY BREAK: when pooling, `$nrbSpouseModelled` was set to the whole
        // band and `$nrbTransferred` was published from the pooled sum but never
        // entered the total — the same £325,000-you-cannot-account-for that was just
        // fixed on the residence side. A real brought-forward band DISPLACES the
        // modelled one rather than stacking on top of it.
        $nrbSpouseModelled = $poolsSpouse ? max(0.0, $nrbSingle - $nrbTransferred) : 0.0;

        if ($poolsSpouse) {
            $nrbGross = $nrbSingle + $nrbTransferred + $nrbSpouseModelled;
        } elseif ($isWidowed && $nrbTransferred > 0) {
            $nrbGross = $nrbSingle + $nrbTransferred;
        } else {
            $nrbGross = $nrbSingle;
        }

        // 6b. Deduct each pooled member's gifts, capped at their OWN band.
        //
        // The comment that used to sit here said spouse nil rate band was "handled
        // separately by SpouseNRBTrackerService". **That service never had a caller**
        // — verified repo-wide; the only hits were this comment, its twin above the
        // deduction method, and the class declaration. It described work nothing did,
        // and it is why the gap went unexamined. **W-0146 deleted the class**, so the
        // deduction below is the only answer to this question and cannot acquire a
        // second one. Every pooled member's gifts are deducted here, capped at their
        // own band; nothing is handled elsewhere.
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
            // W-0361 — the projection re-strikes the band against its own date of
            // death, and needs the gross figure to do it. Published rather than
            // recomputed so there is one definition of the gross band.
            'nrb_gross' => $nrbGross,
            'profiles' => $profiles,
            'estate_for_taper' => $estateForTaper,
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
            // W-0154 F2. Without these the residence row read 175,000 + 0 = 350,000
            // and a reader could not account for the other half. Same reasoning as
            // the nil rate band above: the doubling is a modelled second-death
            // assumption, not a transfer that exists today.
            //   individual + spouse_modelled + transferred
            //     − residence_cap_reduction − taper_reduction = available
            'rnrb_spouse_modelled' => round($rnrbData['rnrb_spouse_modelled'] ?? 0, 2),
            'rnrb_transferred' => round($rnrbData['rnrb_transferred'] ?? 0, 2),
            'rnrb_residence_cap_reduction' => round($rnrbData['rnrb_residence_cap_reduction'] ?? 0, 2),
            'rnrb_taper_reduction' => round($rnrbData['rnrb_taper_reduction'] ?? 0, 2),
            'rnrb_status' => $rnrbData['rnrb_status'],
            'rnrb_message' => $rnrbData['rnrb_message'],

            'total_allowances' => round($totalAllowances, 2),
            // Zero for every estate holding no qualifying business, which is most
            // of them — published unconditionally so a screen that shows it does
            // not have to decide whether the key exists.
            'business_relief_deduction' => round($businessReliefDeduction, 2),
            // W-0466 — the caveat, and the words, live HERE because `/m` computes
            // nothing (CSJ 2026-08-23) and the two frontends share no constants.
            // A copy of this sentence in each bundle is a Rule 20 violation waiting
            // to drift, so both surfaces render what this publishes.
            'unmodelled_relief_caveat' => $unmodelledReliefCaveat,
            // Tax on gifts the seven-year window did not save, after taper relief.
            //
            // Deliberately NOT added to `iht_liability`. That figure is the ESTATE's
            // bill; tax on a failed gift is the recipient's, falling back on the
            // estate only if it goes unpaid for twelve months. Folding it in would
            // silently move the headline number for every user holding a large
            // gift, and quoting one figure that is really two liabilities owed by
            // two different people is the kind of unexplainable total this module
            // has just spent a cycle removing.
            'failed_gift_tax' => round((float) ($nrbDeduction['failed_gift_tax'] ?? 0), 2),
            'failed_gift_taper_saving' => round((float) ($nrbDeduction['failed_gift_taper_saving'] ?? 0), 2),
            'failed_gifts' => $nrbDeduction['failed_gifts'] ?? [],
            'charitable_deduction' => round($charitableAmount, 2),
            'taxable_estate' => round($taxableEstate, 2),
            'iht_rate' => $ihtRate,
            'iht_rate_percent' => round($ihtRate * 100, 0),
            'iht_rate_type' => $ihtRateData['type'],
            'iht_rate_message' => $ihtRateData['message'],
            'charitable_giving_percent' => $ihtRateData['charitable_percent'],
            // The two charitable figures answer DIFFERENT questions and are not
            // interchangeable (tax-compliance-reviewer, 2026-08-21, ruling quoted
            // in determineIHTRate()):
            //   charitable_deduction        — the s23(1) exemption. POOLED across
            //                                 the household, because every member's
            //                                 legacy leaves the combined estate.
            //   charitable_rate_test_amount — what Sch 1A's 10% test compares. The
            //                                 SURVIVOR's will alone; summing both
            //                                 would over-qualify households for 36%.
            // They coincide for a single person and differ whenever both spouses
            // left a legacy, which is precisely when publishing only one of them
            // misleads. W-0399.
            'charitable_rate_test_amount' => round($current['charitable_rate_test_amount'] ?? $charitableAmount, 2),
            'charitable_baseline' => $ihtRateData['baseline'],
            'charitable_threshold' => $ihtRateData['threshold'],

            // W-0451 / W-0452 — the household's charitable rate position, settled
            // once in `determineIHTRate()` / `assessTaxPosition()` and published
            // whole, so that no second reader has to re-derive any part of it
            // from a different estate.
            //
            // It used to be re-derived. `EstateAgent` handed
            // `WillAnalysisService` the INDIVIDUAL's net estate with the
            // HOUSEHOLD's available nil rate band — one person's assets against
            // two people's band — and that mongrel baseline reached
            // `/plans/estate` as "Current Charitable Rate 4.2%" on a page whose
            // own Net Estate row read £1,728,780, while `/estate` said 0.8% for
            // the same household in the same session. Same numerator, two
            // denominators, five times apart.
            //
            // Every consumer now reads these keys. Nothing downstream computes a
            // baseline, a threshold, a shortfall or a saving of its own.
            'charitable_shortfall' => $ihtRateData['shortfall'],
            'charitable_rate_qualifies' => $ihtRateData['qualifies'],
            // W-0451 C1. Whose will the 10% test reads. Published beside the
            // amount it reads, so no sentence has to guess whose position it is
            // describing — see the note in `determineIHTRate()`.
            'charitable_rate_test_member_id' => $ihtRateData['rate_test_member_id'],
            'charitable_rate_test_member_first_name' => $ihtRateData['rate_test_member_first_name'],
            'charitable_rate_test_is_requesting_user' => $ihtRateData['rate_test_member_is_requesting_user'],
            'charitable_has_unvalued_gifts' => $ihtRateData['has_unvalued_charitable_gifts'],
            'charitable_taxable_estate_if_qualifying' => round($current['charitable_taxable_estate_if_qualifying'], 2),
            'charitable_tax_at_standard_rate' => round($current['charitable_tax_at_standard_rate'], 2),
            'charitable_tax_at_reduced_rate' => round($current['charitable_tax_at_reduced_rate'], 2),
            'charitable_rate_saving' => round($current['charitable_rate_saving'], 2),
            'charitable_residue_effect' => round((float) ($current['charitable_residue_effect'] ?? 0), 2),
            'charitable_break_even_shortfall' => round((float) ($current['charitable_break_even_shortfall'] ?? 0), 2),
            'iht_liability' => round($ihtLiability, 2),
            // W-0527 — published beside the bill it reduced. A relief that moves
            // a figure without appearing next to it is the audit gap W-0171 names:
            // the reader cannot reconcile the taxable estate and the rate against
            // a liability that is quietly lower than their product.
            'quick_succession_relief' => round((float) ($current['quick_succession_relief'] ?? 0.0), 2),
            'effective_rate' => round($effectiveRate, 2),

            // Projected values at death (asset-specific)
            'projected_cash' => $projectedData['projected_cash'],
            'projected_cash_shortfall' => $projectedData['projected_cash_shortfall'],
            'projected_cash_assumptions' => $projectedData['projected_cash_assumptions'],
            // W-0482 — the unused defined contribution fund at the modelled death date,
            // published beside the other projected terms so a surface can show the row.
            'projected_unused_pension' => $projectedData['projected_unused_pension'],
            'projected_unused_pension_basis' => $projectedData['projected_unused_pension_basis'],
            // W-0482 — the W-0363 caveat went with its cause, and these arrived with the
            // fix. `05-perimeter.md` §4: where the picture is incomplete, it is said at
            // the point the affected figure is shown. One sentence, from the engine, for
            // both surfaces (Rule 20).
            'projected_pension_inclusion_caveat' => $projectedData['projected_pension_inclusion_caveat'],
            'projected_investments' => $projectedData['projected_investments'],
            'projected_properties' => $projectedData['projected_properties'],
            'projected_gross_assets' => $projectedData['projected_gross_assets'],
            'projected_liabilities' => $projectedData['projected_liabilities'],
            // W-0465. This block ENUMERATES the projected keys rather than
            // spreading them, so a figure `calculateProjectedValues()` returns is
            // absent from the result until it is named here — the same shape as the
            // dropped-field defects on the frontend mapping (W-0134, W-0399).
            'projected_business_relief_deduction' => $projectedData['projected_business_relief_deduction'],
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
            // W-0361 — published beside the band it reduces, so the projected column
            // reconciles the same way the current one does. A figure a service
            // computes and does not publish is a figure nobody can check.
            'projected_nrb_gift_deduction' => $projectedData['projected_nrb_gift_deduction'],
            'projected_rnrb_available' => $projectedData['projected_rnrb_available'],
            'projected_rnrb_individual' => $projectedData['projected_rnrb_individual'],
            'projected_rnrb_spouse_modelled' => $projectedData['projected_rnrb_spouse_modelled'],
            'projected_rnrb_transferred' => $projectedData['projected_rnrb_transferred'],
            'projected_rnrb_residence_cap_reduction' => $projectedData['projected_rnrb_residence_cap_reduction'],
            'projected_rnrb_taper_reduction' => $projectedData['projected_rnrb_taper_reduction'],
            'projected_rnrb_status' => $projectedData['projected_rnrb_status'],
            'projected_rnrb_message' => $projectedData['projected_rnrb_message'],
            'projected_total_allowances' => $projectedData['projected_total_allowances'],
            'projected_charitable_deduction' => $projectedData['projected_charitable_deduction'],
            'projected_iht_rate' => $projectedData['projected_iht_rate'],
            'projected_iht_rate_percent' => $projectedData['projected_iht_rate_percent'],

            'is_married' => $isMarried,
            // W-0467 — the marital status the calculation actually used, published
            // so a consumer can tell "married, partner has no linked account" from
            // "single". `is_married` cannot: it requires `$spouse !== null`, so both
            // states arrive as false and a headline written for the second is shown
            // to the first (compliance-lead, second pass, §11).
            //
            // Published rather than re-derived at the consumer on purpose. The
            // detector reads its pooling answer back off this calculation precisely
            // so a second predicate cannot drift from the one the figure was
            // computed under; asking `$user->marital_status` there would reintroduce
            // exactly that.
            'marital_status' => $user->marital_status,
            'is_widowed' => $isWidowed,
            'data_sharing_enabled' => $dataSharingEnabled,

            // NRB gift deduction breakdown
            'nrb_deduction' => $nrbDeduction,
        ];

        // 9b. Calculate 2027 pension Inheritance Tax dual-scenario projection
        $pensionAmendment = $this->calculatePensionAmendmentScenario($user, $spouse, $dataSharingEnabled, $result, $assessment);
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
        // W-0474 F3 — the ONE branch that deliberately does not ask `poolsSpouse()`,
        // and it is deliberate: it has no `$dataSharingEnabled` term. **How long the
        // household lasts is a fact about the household; whose records are in the
        // estate is a question about permission.** A couple who have not switched
        // sharing on are still a couple, and the second death is still when this
        // estate is taxed.
        //
        // Consequence, named rather than left to be discovered: a civil partnership
        // with sharing OFF now gets the longer horizon it did not get before, so more
        // compounding, a larger projected estate and MORE projected tax against
        // unchanged single allowances. That is the same treatment a marriage in that
        // position already had, which is the point of the change — but it is a figure
        // that moved in the opposite direction to the headline, and it is not in the
        // before/after on the board item, which measures sharing ON only.
        //
        // Note `survivingMember()` keys on `$poolsSpouse` while this keys on
        // `$isMarried`, so one household can select its horizon and its survivor by
        // two different rules. Left as is: the survivor only matters for an estate
        // that pools, and the horizon matters whether it pools or not.
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

        // W-0520 — the user's CONFIGURED growth method, not Monte Carlo regardless.
        //
        // This called `projectInvestmentsMonteCarlo()` directly, straight past
        // `projectInvestments()`, which is the method that reads
        // `estate_planning.investment_growth_method` and branches on it. So a user who
        // set "custom" in Settings → Assumptions and typed their own rate had it ignored
        // by the projected estate, and therefore by their projected Inheritance Tax. The
        // dispatcher was written in `37b9b7b1` and never called; `LifeCoverCalculator`
        // honours the same setting, so the estate was the one place that did not.
        //
        // The custom rate was not entirely ignored, which is what made this hard to see:
        // `getFallbackGrowthRate()` reads it, but only as the fallback for when the
        // simulation FAILS. A user's explicit choice was reachable solely by the
        // simulation erroring — exactly backwards.
        $projectedInvestments = $this->estateProjection->projectInvestments(
            $user,
            $spouse,
            $yearsUntilDeath,
            $assumptions,
            $dataSharingEnabled
        );

        // Project cash: one mechanism, shared with the year-by-year table the user
        // reads beneath the headline (Rule 20 — see HouseholdCashFlowProjector).
        $cashFlow = $this->cashFlowProjector->project(
            $user,
            $spouse,
            // W-0474 F1 (tax-compliance-reviewer, 2026-08-24) — the SEVENTH pooling
            // branch, and the one the first pass missed. Passing the raw sharing flag
            // left `HouseholdCashFlowProjector` running the pre-fix predicate, so a
            // `single`, `divorced` or `widowed` user with a linked, sharing partner
            // still had that partner's savings, income and expenditure in their
            // projected estate while everything else had correctly left it.
            // OVERSTATED projected tax. The decision is taken here, by the one rule,
            // rather than inside the projector — which also drives the year-by-year
            // table, whose pooling is the same question and must give the same answer.
            $this->poolsSpouse($user, $spouse, $dataSharingEnabled),
            $yearsUntilDeath,
            $inflationRate
        );
        $projectedCash = $cashFlow['final_cash'];

        $projectedProperties = $this->estateProjection->projectProperties(
            $user,
            $spouse,
            $yearsUntilDeath,
            $assumptions,
            $dataSharingEnabled
        );

        $projectedLiabilities = $this->estateProjection->projectLiabilities(
            $user,
            $spouse,
            $currentAge,
            $retirementAge,
            $estimatedAgeAtDeath,
            $dataSharingEnabled
        );

        // Get current chattel and business values (these don't appreciate - stay at current value)
        $userAssets = $this->aggregator->gatherUserAssets($user);
        $spouseAssets = $this->poolsSpouse($user, $spouse, $dataSharingEnabled)
            ? $this->aggregator->gatherUserAssets($spouse)
            : collect();

        $chattelValue = fn ($assets): float => (float) $assets->where('asset_type', 'chattel')
            ->reject(fn ($a) => $a->is_iht_exempt)
            ->sum('current_value');
        $businessValue = fn ($assets): float => (float) $assets->where('asset_type', 'business')
            ->reject(fn ($a) => $a->is_iht_exempt)
            ->sum('current_value');

        $projectedChattels = $chattelValue($userAssets) + $chattelValue($spouseAssets);
        $projectedBusiness = $businessValue($userAssets) + $businessValue($spouseAssets);

        // W-0475 — everything the five projected terms cannot see.
        //
        // The current column is built from `gatherUserAssets()`; the projection is
        // built from SOURCE TABLES — `properties` via `PropertyStore`,
        // `investment_accounts` via `calculateInvestmentTotal()`, savings via the cash
        // flow projector. Only chattels and business read the collection. So a row in
        // the `assets` table was counted today and gone at death, taking the taper
        // base down with it: UNDERSTATED projected tax.
        //
        // **Not `other`-only, and not filtered by `asset_type`.** The board item
        // named the `other` bucket; measured against the code it is four of the five
        // types a user can create — `CoordinatingAgent:4055` lets Fyn record
        // `property`, `pension`, `investment`, `business` or `other`, and only
        // `business` survives, because that term filters the collection rather than a
        // table. A row typed `property` is "covered" by NAME and invisible to
        // `PropertyStore`, so excluding by type would drop it again.
        //
        // Keyed on PROVENANCE instead: an `Estate\Asset` row is one the projection's
        // sources never see. `business` is excluded because the filter above already
        // counts it from both provenances. **A new member of the enum falls in here
        // automatically rather than vanishing** — which is what a guard against the
        // enum and the projection drifting apart actually needs.
        //
        // Carried at current value, like chattels and business, because nothing in
        // the app models growth for an arbitrary asset. That is a stated choice, not
        // an oversight: unmodelled growth understates by less than a missing asset.
        $estateAssetResidual = fn ($assets): float => (float) $assets
            ->filter(fn ($a) => $a instanceof Asset && ($a->asset_type ?? null) !== 'business')
            ->reject(fn ($a) => $a->is_iht_exempt ?? false)
            ->sum('current_value');

        $projectedOtherAssets = $estateAssetResidual($userAssets) + $estateAssetResidual($spouseAssets);

        // W-0465 — the projection applied NO business relief at all, so a £6,000,000
        // trading business showed £4,250,000 of relief in the current column and
        // nothing in the projected one: the two halves of a table whose entire
        // purpose is to compare them disagreed by the whole relief.
        //
        // **Rule 20 — this is not a second allocation.** The capped, pro-rata
        // allowance (s124D(7)) is worked out exactly once, by
        // `EstateAssetAggregatorService::applyBusinessPropertyRelief()`, and stamped
        // onto `iht_relief_amount` by the same `gatherUserAssets()` call the values
        // above are read from. This only READS what that allocation decided.
        //
        // **Why reading today's relief is the right pairing rather than a shortcut**
        // (acceptance 4): business values are deliberately NOT projected forward —
        // the comment above says so and the arithmetic agrees, they enter the
        // projection at present-day worth. Relief struck on present-day values is
        // therefore relief on the values actually being taxed here.
        //
        // **If business projection is ever added, this breaks and must move.** The
        // £2,500,000 allowance does not grow with the business, so relief allocated
        // on today's value against a grown value would UNDERSTATE the charge. The
        // fix then is to re-run the allocator over the projected values, never to
        // scale this figure.
        $businessRelief = fn ($assets): float => (float) $assets->where('asset_type', 'business')
            ->reject(fn ($a) => $a->is_iht_exempt)
            ->sum(fn ($a) => (float) ($a->iht_relief_amount ?? 0));

        $projectedBusinessRelief = $businessRelief($userAssets) + $businessRelief($spouseAssets);

        // W-0482 — the unused defined contribution fund at the modelled death date.
        //
        // **Not the pot, and not today's value.** `HouseholdCashFlowProjector` already
        // turns this pension into income and carries it in `$projectedCash`; adding the
        // fund on top would count the same money twice, once as the income it becomes
        // and once as the fund it came from. What belongs here is what is LEFT after
        // that drawdown, which `RetirementProjectionService::unusedDcFundAtAge()`
        // models — the one place drawdown is modelled at all.
        //
        // Gated on the date, from configuration (Rule 2): the pot forms part of the
        // estate only for a death on or after `pension_iht_inclusion.effective_date`.
        // A household modelled to die before it adds nothing, and is told nothing.
        $projectedUnusedPension = $this->estateProjection->projectedUnusedPensionFund(
            $user,
            $spouse,
            $yearsUntilDeath,
            $this->poolsSpouse($user, $spouse, $dataSharingEnabled),
            // The SAME rate the cash flow above was projected at. The residual is the
            // complement of the income that projection credited, so a second rate here
            // would make the two halves fail to reconcile.
            $inflationRate
        );

        // Calculate totals (include chattels and business at current value)
        $projectedGrossAssets = $projectedCash + $projectedInvestments + $projectedProperties + $projectedChattels + $projectedBusiness + $projectedOtherAssets + $projectedUnusedPension['amount'];
        // Relief reduces the CHARGEABLE estate, in the projected column for the same
        // reason and in the same place as the current one (see `$totalNetEstate`).
        $projectedNetEstate = $projectedGrossAssets - $projectedLiabilities - $projectedBusinessRelief;

        // W-0465 acceptance 3 — the taper base can no longer be assumed relief-free.
        //
        // `assessTaxPosition()` used to be handed `$projectedNetEstate` as its own
        // taper base on the stated reasoning that "the projection does not model
        // business relief separately, so its net estate is already relief-free."
        // That was true, and true only because the projection was WRONG about
        // relief. Fixing the relief invalidates the reasoning, so the base is now
        // struck explicitly, mirroring `$estateForTaper` in the current column:
        // gross before reliefs, less liabilities (IHTM46023 on s8D(5)(d)).
        //
        // `$projectedBusiness` excludes wholly relieved businesses — `is_iht_exempt`
        // is set only when relief covers the whole value — so they are added back
        // here exactly as they are in the current column. A PARTLY relieved business
        // is already in at full value, and adding its relief on top would be the
        // R2 double-count.
        $whollyRelieved = fn ($assets): float => (float) $assets
            ->filter(fn ($a) => ($a->is_iht_exempt ?? false) && ($a->iht_relief_qualifies ?? false))
            ->sum('current_value');

        $projectedEstateForTaper = $projectedGrossAssets
            + $whollyRelieved($userAssets)
            + $whollyRelieved($spouseAssets)
            - $projectedLiabilities;

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
        // F2 — the projection must not inherit the CURRENT estate's taper base.
        // Passing its own value explicitly stops `$ctx['estate_for_taper']` leaking
        // today's figure into a projection decades out. W-0465 changed WHAT is
        // passed: see `$projectedEstateForTaper` above.
        // W-0361 — re-struck against the modelled date of death, not today.
        $projectedDeathDate = today()->addYears(max(0, $yearsUntilDeath));
        $projectedNrbDeduction = $this->calculateNRBDeductionForGifts(
            $assessment['pooled_members'],
            (float) ($assessment['iht_config']['nil_rate_band'] ?? 0),
            $projectedDeathDate,
        );
        $projectedNrbAvailable = max(
            0,
            (float) $assessment['nrb_gross'] - (float) $projectedNrbDeduction['total_nrb_used'],
        );

        $projected = $this->assessTaxPosition(
            $projectedNetEstate,
            $this->estateProjection->projectMainResidenceNetValue(
                $user,
                $assessment['spouse'],
                $yearsUntilDeath,
                $assumptions
            ),
            // W-0361 — the projected column's OWN nil rate band. It reused the
            // current one, whose gift deduction is measured from today: a chargeable
            // transfer made in 2020 still consumed £150,000 of the band at a death
            // modelled in 2062, THIRTY YEARS after IHTA 1984 s7(1) drops it out of
            // cumulation. Measured £500,000 where £650,000 is correct — £60,000 of
            // overstated projected tax.
            //
            // The docblock defending the reuse said the band is "a statutory amount
            // reduced by chargeable transfers already made, neither of which is a
            // function of the estate's size". True, and beside the point: it IS a
            // function of the DATE OF DEATH, and the two columns have different ones.
            // The same calculator is asked about the projected date rather than a
            // second rule being written for it.
            ['estate_for_taper' => $projectedEstateForTaper, 'nrb_available' => $projectedNrbAvailable] + $assessment
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

            // W-0482 — published rather than folded silently into the gross figure, so
            // both surfaces can show the row and say which basis it was modelled on.
            'projected_unused_pension' => round($projectedUnusedPension['amount'], 2),
            'projected_unused_pension_basis' => $projectedUnusedPension['basis'],
            'projected_pension_inclusion_caveat' => $projectedUnusedPension['caveat'],
            'projected_investments' => round($projectedInvestments, 2),
            'projected_properties' => round($projectedProperties, 2),
            'projected_gross_assets' => round($projectedGrossAssets, 2),
            'projected_liabilities' => round($projectedLiabilities, 2),
            // W-0465 — published so the projected column can show the relief row the
            // current column already has. `IHTPlanning.vue` read this key with a
            // fallback to the CURRENT deduction; that fallback was only ever right
            // by accident and is removed with this change.
            'projected_business_relief_deduction' => round($projectedBusinessRelief, 2),
            'projected_net_estate' => round($projectedNetEstate, 2),
            'projected_taxable_estate' => round($projected['taxable_estate'], 2),
            'projected_iht_liability' => round($projected['iht_liability'], 2),
            'projected_nrb_available' => round($projectedNrbAvailable, 2),
            'projected_nrb_gift_deduction' => round((float) $projectedNrbDeduction['total_nrb_used'], 2),
            'projected_rnrb_available' => round((float) $projected['rnrb']['rnrb_available'], 2),
            'projected_rnrb_individual' => round((float) ($projected['rnrb']['rnrb_individual'] ?? 0), 2),
            'projected_rnrb_spouse_modelled' => round((float) ($projected['rnrb']['rnrb_spouse_modelled'] ?? 0), 2),
            'projected_rnrb_transferred' => round((float) ($projected['rnrb']['rnrb_transferred'] ?? 0), 2),
            'projected_rnrb_residence_cap_reduction' => round((float) ($projected['rnrb']['rnrb_residence_cap_reduction'] ?? 0), 2),
            'projected_rnrb_taper_reduction' => round((float) ($projected['rnrb']['rnrb_taper_reduction'] ?? 0), 2),
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
        // F2 — the taper is measured on the estate BEFORE reliefs (IHTM46023), the
        // Schedule 1A baseline on the estate AFTER them (IHTM45031). Passed as its
        // own value rather than derived here, because only the caller knows what was
        // relieved. Falls back to `$netEstate` when the caller supplies nothing,
        // which is correct for any estate holding no relievable property — i.e. all
        // of them until a business interest exists.
        $rnrbData = $this->calculateRNRB(
            $ctx['estate_for_taper'] ?? $netEstate,
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

        // W-0451 — THE ONE DEFINITION OF WHAT THE REDUCED CHARITABLE RATE SAVES.
        //
        // Three mechanisms answered this and no two agreed. `EstateAgent` printed
        // the differential on the taxable estate; `WillAnalysisService` published
        // the differential on the BASELINE — a quantity that over-includes by
        // exactly the charitable gift plus the residence band. So a decision
        // trace whose whole purpose is auditability read:
        //
        //   "On the taxable estate of £858,780: at 40% = £343,512,
        //    at 36% = £309,161 — saving £19,580."
        //
        // £343,512 − £309,161 = £34,351. The sentence published £19,580 — a 43%
        // error a reader can check on a calculator. Neither figure answered the
        // question the sentence asks.
        //
        // **The question is what the ACTION saves**, so the answer is the
        // difference between two Inheritance Tax bills:
        //
        //   * at the standard rate on the estate as the will stands today; and
        //   * at the reduced rate on the estate once the gift is increased to
        //     the Schedule 1A threshold — the gift itself leaves the estate
        //     under the s23(1) exemption, so the chargeable estate falls by the
        //     shortfall as the rate falls.
        //
        // The threshold does not move when the gift grows: Sch 1A para 5 adds the
        // donated amount back into the baseline, so `baseline = netEstate − NRB`
        // is independent of what is given. That is what makes one subtraction
        // the whole answer.
        //
        // For an estate ALREADY qualifying the shortfall is zero, the two bills
        // sit on the same chargeable estate, and the difference collapses to the
        // rate differential on that estate — which is what "the reduced rate is
        // worth" means for someone who already has it. **One formula, both
        // branches**, and in both of them one of the two bills is the actual
        // `iht_liability` and the other is the counterfactual.
        //
        // The error it replaces changed SIGN with the household — the baseline
        // exceeded the taxable estate on one path and fell below it on another —
        // so it could never be defended as conservative.
        // Read exactly as `determineIHTRate()` reads it three lines above, from the
        // same array, so the two bills cannot be struck at different rates. No
        // `??` fallback for the standard rate: that method reads the key
        // unguarded, so a missing key has already fataled before this line.
        $standardRate = (float) $ctx['iht_config']['standard_rate'];
        $reducedRate = $this->taxConfig->getCharitableReducedRate();
        $charitableShortfall = (float) ($rateData['shortfall'] ?? 0);

        $taxableEstateIfQualifying = max(0, $taxableEstate - $charitableShortfall);

        $taxAtStandardRate = $taxableEstate * $standardRate;
        $taxAtReducedRate = $taxableEstateIfQualifying * $reducedRate;

        // W-0527 — IHTA 1984 s141. Never negative and never larger than the tax
        // borne on the earlier death, both guarded in the calculator. A household
        // with no inheritance life event, or one that has not stated that tax,
        // gets 0.0 and is completely unaffected.
        $quickSuccession = $this->quickSuccessionReliefFor($ctx['pooled_members'] ?? []);

        return $this->suppressRateOnNilLiability([
            'rnrb' => $rnrbData,
            'rate' => $rateData,
            'charitable_deduction' => $charitableDeduction,
            // Published rather than left to be subtracted downstream, so that a
            // sentence saying "36% of £X" can print the £X it multiplied. A
            // reader who cannot see the second base cannot check the second bill,
            // which is the whole failure this item exists to close.
            'charitable_taxable_estate_if_qualifying' => $taxableEstateIfQualifying,
            'charitable_tax_at_standard_rate' => $taxAtStandardRate,
            'charitable_tax_at_reduced_rate' => $taxAtReducedRate,
            'charitable_rate_saving' => max(0, $taxAtStandardRate - $taxAtReducedRate),
            // W-0462 — the OTHER half of the same recommendation, published from
            // the same home so no surface has to compose it (Rule 20).
            //
            // "Save £74,987" is true and incomplete: the estate really does pay
            // that much less tax, and on the peak_earners household the family
            // really does receive £37,891 LESS, because the gift that buys the
            // reduced rate leaves the estate too. Only one of those was on the
            // page.
            //
            //     Δresidue = (r_s − r_r)·E − S·(1 − r_r)
            //
            // Negative means the beneficiaries are worse off. The break-even is
            // S = E·(r_s − r_r)/(1 − r_r) — 6.25% of the chargeable estate at
            // 40/36, and ONLY at 40/36, which is why it is derived from the
            // configured rates and never written as a literal (Rule 2).
            'charitable_residue_effect' => round(
                (($standardRate - $reducedRate) * $taxableEstate) - ($charitableShortfall * (1 - $reducedRate)),
                2
            ),
            'charitable_break_even_shortfall' => round(
                $reducedRate >= 1.0
                    ? 0.0
                    : ($taxableEstate * ($standardRate - $reducedRate)) / (1 - $reducedRate),
                2
            ),
            // W-0399. determineIHTRate() separates the pooled s23(1) exemption
            // from the survivor-only Sch 1A rate-test amount — the distinction
            // tax-compliance-reviewer ruled on — and then the rate-test figure
            // was read by NOTHING. It died in that method, so the only charitable
            // figure reaching a screen was the pooled exemption, rendered under
            // the words "Your will leaves …". Each will leaves half of it.
            'charitable_rate_test_amount' => (float) ($rateData['charitable_rate_test_amount'] ?? $charitableDeduction),
            'total_allowances' => $totalAllowances,
            'taxable_estate' => $taxableEstate,
            // W-0527 — s141 reduces the TAX, not the estate, so it is subtracted
            // here and not from `$taxableEstate`. Floored at zero: the relief can
            // never exceed the tax borne on the earlier death, but the bill it is
            // being set against can be smaller than that.
            'iht_liability' => max(0.0, ($taxableEstate * $rateData['rate']) - $quickSuccession),
            'quick_succession_relief' => round($quickSuccession, 2),
        ]);
    }

    /**
     * A rate only "applies" if there is something for it to apply to.
     *
     * W-0154 F4. `determineIHTRate()` answers the Sch 1A 10% test, and it has to
     * run before the taxable estate exists because the charitable exemption it
     * identifies is one of the deductions that produces it. The consequence was
     * that its answer was published verbatim: an estate covered entirely by its
     * allowances was told *"Reduced Inheritance Tax rate of 36% applies"* beneath
     * a bill of £0. Meaningless at best, and at worst it reads as though 36% of
     * something is being charged.
     *
     * Applied HERE, in `assessTaxPosition()`, because that is the one mechanism
     * both the current and the projected figures go through (Rule 20) — a guard
     * added at a display site would have to be added again at the next one, which
     * is how `EstatePlanService:478` came to carry an unguarded copy.
     *
     * What is deliberately NOT changed: `charitable_rate_qualifies`,
     * `charitable_giving_percent`, `charitable_shortfall` and the two "what if"
     * tax figures. Whether the will passes the 10% test is a true fact about the
     * will whatever the bill is, and the guidance that tells a user how close they
     * are is built from it.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>
     */
    private function suppressRateOnNilLiability(array $position): array
    {
        if (round((float) $position['iht_liability'], 2) > 0.0) {
            return $position;
        }

        $position['rate'] = [
            ...$position['rate'],
            'rate' => 0.0,
            'type' => 'none',
            'message' => 'No Inheritance Tax is due. Your allowances of £'
                .number_format((float) $position['total_allowances'])
                .' cover the estate in full, so no Inheritance Tax rate is applied.',
        ];

        return $position;
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
        float $estateForTaper,
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
                'rnrb_spouse_modelled' => 0,
                'rnrb_transferred' => 0,
                'rnrb_residence_cap_reduction' => 0,
                'rnrb_taper_reduction' => 0,
                'rnrb_status' => 'none',
                'rnrb_message' => 'Residence Nil Rate Band not available. You need to own a main residence and leave it to direct descendants (children, grandchildren, step-children) to qualify for Residence Nil Rate Band of up to £'.number_format($potentialMax).'. Nieces, nephews, cousins, siblings, and other relatives are not direct descendants and do not qualify.',
            ];
        }

        if (! $hasDirectDescendants) {
            return [
                'rnrb_available' => 0,
                'rnrb_individual' => 0,
                'rnrb_spouse_modelled' => 0,
                'rnrb_transferred' => 0,
                'rnrb_residence_cap_reduction' => 0,
                'rnrb_taper_reduction' => 0,
                'rnrb_status' => 'none',
                'rnrb_message' => 'Residence Nil Rate Band not available — you have no direct descendants recorded. The Residence Nil Rate Band of up to £'.number_format($potentialMax).' only applies when your main residence passes to direct descendants (children, grandchildren, step-children). Nieces, nephews, cousins, siblings, and other relatives do not qualify.',
            ];
        }

        // W-0154 F2, the residence half of it. `$rnrbSpouseModelled` is reported
        // separately from `$rnrbTransferred` for exactly the reason the nil rate
        // band does it (see the block in `calculate()`): there is no transferable
        // residence band while both spouses are alive — IHTA 1984 s8G creates the
        // claim on the survivor's death — so `rnrb_transferred` is legitimately 0
        // for a living couple, and the doubling is this service's second-death
        // modelling assumption.
        //
        // Without this field the user was shown `rnrb_individual` £175,000,
        // `rnrb_transferred` £0 and `rnrb_available` £350,000, and the £175,000
        // between them was unattributable — the same defect that was fixed for the
        // nil rate band and left standing here. Do NOT "fix" it by writing
        // £175,000 into `rnrb_transferred`.
        // F19 (tax-compliance-reviewer, 2026-08-23) — a transferred band is capped
        // at ONE extra band. `IHTController:266` validates
        // `nrb_transferred_from_spouse` as `numeric|min:0` with NO upper bound and
        // omits `rnrb_transferred_from_spouse` from the list entirely; the column
        // carries no constraint and `:159` SUMS it across pooled members, so a
        // widowed user entering £400,000 was given a £575,000 residence band against
        // a statutory maximum of £350,000. Capped in the CALCULATION, not only in
        // request validation, because the pooled sum can exceed the cap even from
        // individually valid rows.
        //
        // s8G(3)(d): where the total brought-forward percentage exceeds 100%, the
        // brought-forward allowance IS the residential enhancement — one extra band,
        // however many predeceased spouses there are.
        $rnrbTransferred = min($rnrbTransferred, $rnrbSingle);

        // F15 — for a REMARRIED widow(er) the real brought-forward band DISPLACES the
        // modelled one rather than stacking on top of it. £175,000 own + £175,000
        // transferred + £175,000 modelled would be 200% brought forward, which
        // s8G(3)(d) forbids. Same £350,000 total in every case; the components now
        // say which half is a crystallised claim and which is this service's
        // second-death assumption, and the identity closes for a household where both
        // are present.
        $rnrbSpouseModelled = $poolsSpouse ? max(0.0, $rnrbSingle - $rnrbTransferred) : 0.0;

        if ($poolsSpouse) {
            $fullRNRB = $rnrbSingle + $rnrbTransferred + $rnrbSpouseModelled;
        } elseif ($isWidowed && $rnrbTransferred > 0) {
            $fullRNRB = $rnrbSingle + $rnrbTransferred;
        } else {
            $fullRNRB = $rnrbSingle;
        }

        // The maximum before the two reductions below. Both are published so the
        // arithmetic closes on screen:
        //   individual + spouse_modelled + transferred
        //     − taper_reduction − residence_cap_reduction = available
        $rnrbGross = $fullRNRB;

        // F1 — THE TAPER RUNS FIRST, THEN THE RESIDENCE CAP. This was the other way
        // round, and the comment that used to sit here asserted the wrong order as
        // though it were s8E(2).
        //
        // s8D(5)(g) defines the ADJUSTED allowance as the default allowance less
        // (E − TT)/2 — the taper reduces the ALLOWANCE. s8E(4)-(5) then compare the
        // closely-inherited residence value against that ADJUSTED figure. Capping
        // first and tapering the capped figure is `min(D,R) − T`, which is never
        // greater than the correct `min(D − T, R)` — so the old order could only ever
        // OVERSTATE the tax, never under-state it.
        //
        // Worked (reviewer's example, verified): default £350,000, closely-inherited
        // residence £300,000, estate £2,200,000. Correct: taper £100,000 → adjusted
        // £250,000, cap does not bite → £250,000. Old order: cap to £300,000, then
        // taper £100,000 → £200,000. £50,000 of allowance, £20,000 of tax.
        // Ref: IHTA 1984 s8D(5)(f)-(g), s8E(2)-(5); IHTM46023, IHTM46026, IHTM46044.
        $taperReductionRaw = $estateForTaper > $taperThreshold
            ? ($estateForTaper - $taperThreshold) * $taperRate
            : 0.0;

        // Bounded because s8D(5)(g) ends "but is nil if that amount is greater than
        // the person's default allowance" — statutory, not defensive.
        $taperReduction = min($taperReductionRaw, $rnrbGross);
        $taperedRNRB = $rnrbGross - $taperReduction;

        // s8E(2) caps what the taper has already left, not the gross.
        $residenceCapReduction = max(0.0, $taperedRNRB - $residenceNetValue);
        $fullRNRB = $taperedRNRB - $residenceCapReduction;

        // Computed against the TAPERED figure. Against the gross it reported
        // "capped at the net value of your main residence" in cases where the cap
        // never bit — the reviewer's example is exactly one.
        $cappedByResidence = $residenceCapReduction > 0;

        // Check for taper
        // Both reductions are already computed above, in statutory order, so the
        // three branches now only choose WORDING. They used to re-derive the taper,
        // which is how the published components and the applied figure could differ.
        $excess = max(0.0, $estateForTaper - $taperThreshold);
        $tapered = $taperReduction > 0;

        $components = [
            'rnrb_individual' => $rnrbSingle,
            'rnrb_spouse_modelled' => $rnrbSpouseModelled,
            'rnrb_transferred' => $rnrbTransferred,
            'rnrb_taper_reduction' => $taperReduction,
            'rnrb_residence_cap_reduction' => $residenceCapReduction,
        ];

        if ($fullRNRB <= 0) {
            return [
                'rnrb_available' => 0,
                ...$components,
                'rnrb_status' => $tapered ? 'tapered' : 'residence_capped',
                // R4 — both reductions can be non-zero, and `$tapered` was used as an
                // either/or: with gross £350,000, no residence value and a £50,000
                // taper, it credited the taper with removing all £350,000. The
                // components were right; the sentence was not.
                'rnrb_message' => $residenceCapReduction >= $taperReduction
                    ? 'Residence Nil Rate Band not available — the main residence passing to your direct descendants has no net value once the mortgage secured on it is deducted'.($tapered ? ', and your estate is above the £'.number_format($taperThreshold).' taper threshold' : '').'.'
                    : 'Residence Nil Rate Band fully tapered away. Your estate before reliefs of £'.number_format($estateForTaper).' exceeds the taper threshold of £'.number_format($taperThreshold).' by £'.number_format($excess).', eliminating all £'.number_format($rnrbGross).' of it.',
            ];
        }

        // Both can bite at once, and the message has to account for the WHOLE
        // reduction or the reader is left with a number they cannot reach. It
        // previously narrated the taper alone.
        $sentences = ['Residence Nil Rate Band of £'.number_format($fullRNRB).' available from a maximum of £'.number_format($rnrbGross).'.'];

        if ($tapered) {
            $sentences[] = 'Your estate before reliefs of £'.number_format($estateForTaper).' exceeds the £'.number_format($taperThreshold).' taper threshold by £'.number_format($excess).', reducing the allowance by £'.number_format($taperReduction).' (£1 for every £2 over the threshold).';
        }

        if ($cappedByResidence) {
            $sentences[] = 'It is then capped at the net value of your main residence (£'.number_format($residenceNetValue).'), a further £'.number_format($residenceCapReduction).'.';
        }

        if (! $tapered && ! $cappedByResidence) {
            $sentences = [$poolsSpouse
                ? 'Full Residence Nil Rate Band of £'.number_format($fullRNRB).' available (£'.number_format($rnrbSingle).' each). Your combined estate is below the £'.number_format($taperThreshold).' taper threshold.'
                : (($isWidowed && $rnrbTransferred > 0)
                    ? 'Full Residence Nil Rate Band of £'.number_format($fullRNRB).' available (own £'.number_format($rnrbSingle).' + £'.number_format($rnrbTransferred).' transferred from your late spouse\'s estate). Your estate is below the £'.number_format($taperThreshold).' taper threshold.'
                    : 'Full Residence Nil Rate Band of £'.number_format($fullRNRB).' available. Your estate is below the £'.number_format($taperThreshold).' taper threshold.'),
            ];
        }

        return [
            'rnrb_available' => $fullRNRB,
            ...$components,
            'rnrb_status' => $tapered ? 'tapered' : ($cappedByResidence ? 'residence_capped' : 'full'),
            'rnrb_message' => implode(' ', $sentences),
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

        // W-0431 / Rule 2. Every rate in the sentences below was a hardcoded
        // literal — "40%", "36%", "10%" — beside a calculation reading the real
        // figure from TaxConfigService. That is the W-0132 defect this file
        // already documents, one layer over: a label asserting a rate the figure
        // next to it was not computed at. Change the reduced rate in
        // configuration and the message would keep saying 36% while the estate
        // was charged something else. The sentences now quote what was used.
        $asPercent = static fn (float $rate): string => rtrim(rtrim(number_format($rate * 100, 2), '0'), '.').'%';
        $standardRateLabel = $asPercent($standardRate);
        $reducedRateLabel = $asPercent($reducedRate);
        $thresholdLabel = $asPercent($this->taxConfig->getCharitableThresholdPercent());

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
            // W-0433. This divided by the NET ESTATE while the threshold beside
            // it in every message is 10% of the BASELINE — so the sentence
            // invited the user to compare two percentages of different things.
            // On the peak_earners household it showed 0.6% against a 10%
            // threshold where the statutory figure is 0.81%.
            //
            // The baseline is Schedule 1A's own denominator, and it is already
            // computed above.
            //
            // W-0452 finished the job. W-0433 pointed this card at the baseline
            // and left `EstatePlanService` computing its own percentage from a
            // baseline `EstateAgent` had struck on the individual's net estate —
            // matching denominators in name and not in value, which is how one
            // page came to publish 4.2% while this one published 0.8% for the
            // same household. That second division is gone; the one computed
            // below, after both branches, is now the only division producing
            // this percentage anywhere in the application, and both surfaces
            // read its answer.
        }

        // `$charitableAmount` is the s23(1) exemption the caller deducts from the
        // estate — pooled. `$rateTestAmount` is what the 10% test compares — the
        // survivor's alone. They are equal for a single person and differ for a
        // household where both spouses left a legacy. The messages quote the
        // rate-test figure, because the comparison they describe is the rate test.
        $rateTestAmount ??= $charitableAmount;

        // C2 of the 2026-08-23 verdict. W-0433's fix lived INSIDE the
        // recorded-bequest branch, so `charitable_giving_percent` had two
        // definitions depending on which branch ran: a percentage of the
        // BASELINE when a will recorded a legacy, and the user's typed
        // Inheritance Tax profile figure — a percentage of the NET ESTATE —
        // when it did not. Both were then quoted against a baseline threshold
        // in the same sentence.
        //
        // No seeded profile carries a non-zero value, so no fixture reached the
        // second branch. Any user can enter one.
        //
        // Computed ONCE here, after both branches, from the amount the rate test
        // actually compares and the denominator Schedule 1A actually uses. The
        // profile percentage remains what it always was — an INPUT for deriving
        // the intended amount above — and is no longer mistaken for the output.
        $charitablePercent = $baseline > 0 ? ($rateTestAmount / $baseline) * 100 : 0;

        $qualifies = $charitablePercent > 0 && $rateTestAmount >= $threshold && $baseline > 0;
        $shortfall = max(0, $threshold - $rateTestAmount);

        // W-0451. Whether the survivor's will leaves an ASSET or a residuary
        // share to charity is a property of the estate being rate-tested, so it
        // is settled here beside the amount and the threshold rather than by a
        // second reader asking the same question of a different person.
        //
        // `WillAnalysisService` asked it of whoever was logged in. On a
        // household where the first-to-die left the unvalued gift and the
        // survivor did not, that suppressed a shortfall instruction the survivor
        // could act on; the mirror case invented one the survivor could not.
        $hasUnvaluedCharitableGifts = $this->willAnalysis->hasUnvaluedCharitableGifts($survivor);

        // W-0451 / Rule 20. Every branch answers every question, structurally.
        //
        // The three returns below each re-listed the same five keys, and the
        // third one carries a comment recording the last time a key was added to
        // two of them and forgotten in the third — a rate-test figure that
        // reached no screen. A shared array cannot be forgotten in one branch,
        // so the next key added here is published on all three by construction
        // rather than by the author remembering.
        $position = [
            // W-0451 C1 — WHOSE will the rate test reads, published beside WHAT
            // it reads, from the one resolution that decides it.
            //
            // The batch that moved the numerator to the survivor published the
            // AMOUNTS and the BOOLEANS and not the IDENTITY, on the premise that
            // no second caller would need to know who the survivor was. The
            // premise was false and the code proved it: `EstateAgent` writes
            // sentences with a name in them, had nothing to name, and defaulted
            // to whoever was logged in — so the survivor's charitable position
            // was reported under the first-to-die's name, with an instruction to
            // add a legacy to the wrong will. Following it raises the pooled
            // exemption and leaves `$rateTestAmount` untouched, so the rate never
            // moves and the same instruction is issued again.
            //
            // Published rather than exposing `survivingMember()`: a second caller
            // would have to re-derive the pooling predicate to call it, which is
            // a second copy of the decision that selects the survivor. One
            // resolution, its answer published whole.
            'rate_test_member_id' => $survivor->id,
            'rate_test_member_first_name' => $survivor->first_name,
            // Answered here rather than left to a consumer comparing ids: this
            // method holds both the requesting user and the survivor, and a
            // consumer that has to match them up is a place for them to diverge.
            'rate_test_member_is_requesting_user' => $survivor->id === $user->id,
            // Always the computed figure. The third branch used to publish a
            // literal 0 here; it is reached only when `$charitablePercent` is
            // already 0, so the literal said nothing the variable did not.
            'charitable_percent' => $charitablePercent,
            'charitable_amount' => round($charitableAmount, 2),
            'charitable_rate_test_amount' => round($rateTestAmount, 2),
            'baseline' => round($baseline, 2),
            'threshold' => round($threshold, 2),
            'shortfall' => round($shortfall, 2),
            'qualifies' => $qualifies,
            'has_unvalued_charitable_gifts' => $hasUnvaluedCharitableGifts,
        ];

        // Check if charitable giving meets the 10% of baseline threshold
        if ($qualifies) {
            return [
                'rate' => $reducedRate,
                'type' => 'reduced',
                'message' => 'Reduced Inheritance Tax rate of '.$reducedRateLabel.' applies. Your charitable giving of '.number_format($charitablePercent, 1).'% (£'.number_format($rateTestAmount).') meets the '.$thresholdLabel.' threshold of £'.number_format($threshold).' ('.$thresholdLabel.' of baseline £'.number_format($baseline).').',
                ...$position,
            ];
        }

        // Standard rate applies
        if ($charitablePercent > 0 && $baseline > 0) {
            return [
                'rate' => $standardRate,
                'type' => 'standard',
                'message' => 'Standard Inheritance Tax rate of '.$standardRateLabel.' applies. Your charitable giving of '.number_format($charitablePercent, 1).'% (£'.number_format($rateTestAmount).') is below the '.$thresholdLabel.' threshold of £'.number_format($threshold).'. Increase by £'.number_format($shortfall).' to qualify for the '.$reducedRateLabel.' rate.',
                ...$position,
            ];
        }

        return [
            'rate' => $standardRate,
            'type' => 'standard',
            'message' => 'Standard Inheritance Tax rate of '.$standardRateLabel.' applies. Leave '.$thresholdLabel.'+ of your baseline estate (£'.number_format($baseline).') to charity to qualify for the reduced '.$reducedRateLabel.' rate.',
            ...$position,
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
     * Does this person hold a qualifying residential interest? W-0365.
     *
     * **Joint ownership qualifies.** IHTA 1984 s8H(2) defines a qualifying residential
     * interest as an interest in a dwelling-house which has at some time been the
     * person's residence. A beneficial co-owner recorded as `joint_owner_id` has such
     * an interest; nothing in ss8E–8H requires being the primary named owner of a
     * database row.
     *
     * This filtered to primary-owner-only, and said so deliberately — "to match the
     * pre-PR-5a semantics". That was a statement about this codebase's history, not
     * about the statute, and **the file contradicted itself**:
     * `sumMainResidenceNetShare()` uses the joint-aware reader and counts the very
     * same user's share into the s8E(2) cap. So a joint owner's share raised the cap
     * on a band they were refused. Direction: OVERSTATED tax, by up to the whole
     * residence nil rate band.
     */
    private function hasMainResidence(User $user, ?User $spouse): bool
    {
        $userHasMainRes = $this->propertyStore
            ->forUserByType($user, 'main_residence')
            ->isNotEmpty();

        if ($userHasMainRes) {
            return true;
        }

        if ($spouse) {
            return $this->propertyStore
                ->forUserByType($spouse, 'main_residence')
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
     * share of the value less their share of any mortgage secured on it, valued the
     * way the estate itself is valued so the figure matches the property and
     * mortgage values that feed total_net_estate.
     *
     * **W-0368 — this is the residence band cap, and it is the THIRD valuation site.**
     * It read raw `calculateUserShare()` while the estate had begun taxing the share
     * at its undivided-share value, so the estate was taxed on the discounted figure
     * while the s8E(2) cap was measured against the undiscounted one. Measured on a
     * £360,000 residence held 50% with an unlinked co-owner plus £500,000 cash, that
     * reported an Inheritance Tax liability of £64,800 against £70,000 —
     * **£5,200 understated, and it scales.**
     *
     * One property, two valuations, in one calculation is exactly what W-0368's
     * acceptance 3 forbids, and finding only two of the three sites is how it
     * happened. `UndividedShareDiscount` is the one home; every site that values a
     * share for Inheritance Tax reads it.
     */
    private function sumMainResidenceNetShare(User $user): float
    {
        return (float) $this->propertyStore
            ->forUserByType($user, 'main_residence')
            ->sum(function ($property) use ($user) {
                $valueShare = $this->undividedShareDiscount->shareValue($property, $user);
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
        // `unmodelled_relief_caveat` is here because it is legitimately NULL for
        // most estates — so `?? null` at the consumer cannot tell "this engine did
        // not publish it" from "this estate does not need it", and a stale row
        // would suppress the caveat until the user's assets happened to change.
        // `projected_business_relief_deduction` (W-0465): a stale row missing it
        // coalesces to 0 at the consumer, which is right for most estates and is
        // EXACTLY the defect for a business-owning one — the projected column
        // showing no relief. Shape-guarded so the stale row is rejected instead.
        foreach ([
            'projected_total_allowances',
            'projected_charitable_deduction',
            'projected_rnrb_status',
            'unmodelled_relief_caveat',
            'projected_business_relief_deduction',
            // W-0467 — a stale row without this makes the detector unable to tell a
            // married user with no linked account from a single one, and it would
            // fail SILENTLY, by showing the wrong sentence rather than by erroring.
            'marital_status',
        ] as $key) {
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
        // W-0340 — the CACHE KEY has to pool exactly as the CALCULATION pools, or it
        // varies on data the cached figure does not depend on. These two lines kept the
        // loose predicate after every calculating branch moved to `poolsSpouse()`, so
        // for a linked unmarried couple the key moved with the partner's assets while
        // the answer did not.
        $userAssets = $this->aggregator->gatherUserAssets($user);
        $spouseAssets = $this->poolsSpouse($user, $spouse, $dataSharingEnabled)
            ? $this->aggregator->gatherUserAssets($spouse)
            : collect();

        $assetsString = $userAssets->pluck('current_value')->join(',').'|'.$spouseAssets->pluck('current_value')->join(',')
            .'|'.$this->charitableBequestFingerprint($user);
        $assetsHash = hash('sha256', $assetsString);

        $userLiabilities = $this->aggregator->calculateUserLiabilities($user);
        $spouseLiabilities = $this->poolsSpouse($user, $spouse, $dataSharingEnabled)
            ? $this->aggregator->calculateUserLiabilities($spouse)
            : 0;

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
     * had no callers** — verified repo-wide, twice. So one spouse's gifts reduced the
     * household band when they logged in and did nothing when the other did, and the
     * comment is why nobody looked.
     *
     * **W-0146 deleted that class.** It modelled a transferable band from a LIVING
     * spouse's gift history as though they had died today, which is the model this
     * service rejects on the law: IHTA 1984 s8A creates the claim on the survivor's
     * death and not before. Wiring it up would have produced a second, contradictory
     * answer to a question this method already answers (Rule 20). This is now the
     * only place household gifts are deducted from the band.
     *
     * @param  list<User>  $members  The people whose records this calculation covers
     * @param  float  $nrbSingle  The individual NRB amount
     * @return array NRB deduction breakdown, summed across members
     */
    /**
     * Quick succession relief for the household — IHTA 1984 s141.
     *
     * **W-0527.** Sums the relief over every `inheritance` life event whose donor
     * death falls inside the configured window and whose Inheritance Tax borne on
     * that earlier death the user has actually stated. Everything the formula
     * needs but that figure was already recorded: the amount received and the
     * date it happened.
     *
     * **`iht_paid_on_prior_death` is NULL for almost every inheritance, and that
     * is not zero.** Most estates bear no tax, and a user who has not answered has
     * not said there was none. A NULL contributes nothing and the household is
     * unaffected — which is why criterion 3 holds by construction rather than by
     * a guard somewhere downstream.
     *
     * The years are measured to today, matching the modelled date of death that
     * the rest of the current column is struck at.
     *
     * @param  list<User>  $members  The people whose records this calculation covers
     */
    private function quickSuccessionReliefFor(array $members): float
    {
        if ($members === []) {
            return 0.0;
        }

        $relief = 0.0;

        // Per member, because `forUserOrJoint()` scopes one user at a time and a
        // jointly-recorded inheritance would otherwise be counted once for each
        // of them. `unique('id')` collapses the overlap.
        $events = collect($members)
            ->flatMap(fn (User $member) => LifeEvent::forUserOrJoint($member->id)
                ->where('event_type', 'inheritance')
                ->whereNotNull('iht_paid_on_prior_death')
                ->get()
                ->all())
            ->unique('id');

        foreach ($events as $event) {
            $receivedOn = $event->occurred_at ?? $event->expected_date;

            if ($receivedOn === null) {
                continue;
            }

            $taxPaid = (float) $event->iht_paid_on_prior_death;
            $netReceived = (float) $event->amount;

            $relief += $this->quickSuccessionRelief->reliefFor(
                taxPaidOnFirstDeath: $taxPaid,
                netValueReceived: $netReceived,
                // The gross transfer is the net the beneficiary received plus the
                // tax borne on it. Derived rather than asked for: a user who knows
                // both of the other two knows this by arithmetic, and a third
                // field they could contradict is a worse question than none.
                grossTransfer: $netReceived + $taxPaid,
                yearsBetweenDeaths: Carbon::parse($receivedOn)->floatDiffInYears(today()),
            );
        }

        return $relief;
    }

    private function calculateNRBDeductionForGifts(array $members, float $nrbSingle, ?Carbon $deathDate = null): array
    {
        $totals = [
            'pets_in_7_years' => 0.0,
            'clts_in_7_years' => 0.0,
            'clts_7_to_14_years' => 0.0,
            'nrb_used_by_clts' => 0.0,
            'nrb_used_by_pets' => 0.0,
            'total_nrb_used' => 0.0,
            // Tax on gifts the seven-year window did not save, after taper.
            'failed_gift_tax' => 0.0,
            'failed_gift_taper_saving' => 0.0,
        ];
        $failedGifts = [];

        foreach ($members as $member) {
            $memberDeduction = $this->failedGiftTax->forMember($member, $nrbSingle, $deathDate);

            foreach (array_keys($totals) as $key) {
                $totals[$key] += $memberDeduction[$key];
            }

            foreach ($memberDeduction['failed_gifts'] as $gift) {
                $failedGifts[] = $gift + ['member_id' => $member->id, 'member_first_name' => $member->first_name];
            }
        }

        return array_map(fn (float $value): float => round($value, 2), $totals)
            + [
                'fourteen_year_rule_applied' => $totals['clts_7_to_14_years'] > 0,
                'failed_gifts' => $failedGifts,
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
    private function pooledMembers(User $user, ?User $spouse, bool $dataSharingEnabled): array
    {
        return $this->poolsSpouse($user, $spouse, $dataSharingEnabled) ? [$user, $spouse] : [$user];
    }

    /**
     * Does this household's marital status pool an estate at all?
     *
     * W-0474 — this read `['married']` alone while nine sibling services read
     * `['married', 'civil_partnership']`, including the migration docblock that
     * introduced the status and asserted THIS service branched on both. A civil
     * partnership is treated identically to a marriage for Inheritance Tax:
     * IHTA 1984 s18 spouse exemption, s8A transferable nil rate band and s8G
     * residence nil rate band are all extended to civil partners by Civil
     * Partnership Act 2004 s.246 and SI 2005/3229.
     */
    private function hasSpousalStatus(User $user): bool
    {
        return HouseholdPooling::hasSpousalStatus($user);
    }

    /**
     * THE question every branch asks: do this calculation's figures cover two
     * people's records or one?
     *
     * W-0474 — it used to be asked in two different ways, and the disagreement
     * moved tax in both directions. The headline pooled on
     * `$isMarried && $dataSharingEnabled`; every projection branch pooled on
     * `$dataSharingEnabled && $spouse` alone, and neither `liveSpouse()` nor
     * `hasAcceptedSpousePermission()` consults `marital_status`. So:
     *   - a CIVIL PARTNERSHIP assessed two partners' projected assets, properties,
     *     investments, liabilities and business relief against ONE person's
     *     £325,000 + £175,000, with the taper base struck on the doubled estate —
     *     crossing £2,000,000 roughly twice as fast. OVERSTATED tax.
     *   - an UNMARRIED couple who had linked accounts and accepted sharing got a
     *     headline taxing one estate and a projection pooling two, against a single
     *     nil rate band and no spouse exemption they are not entitled to (W-0340).
     *
     * Both halves now ask this one method. Adding a branch that pools the spouse
     * means calling this, not re-deriving the predicate.
     */
    private function poolsSpouse(User $user, ?User $spouse, bool $dataSharingEnabled): bool
    {
        return HouseholdPooling::poolsSpouse($user, $spouse, $dataSharingEnabled);
    }

    /**
     * Does this asset read as agricultural land? W-0466.
     *
     * A heuristic, deliberately, and narrow on purpose. `assets.asset_type` is
     * `enum('property','pension','investment','business','other')` with no
     * agricultural member, and the table carries no description — `asset_name` is
     * the only place a user can say what the thing is.
     *
     * **Matched only on `other` rows.** A property is already a residence by its own
     * enum, and an investment named "Farmland Fund" is a fund, not land.
     *
     * Terms chosen to be specific rather than generous: each is a word for
     * agricultural land itself, not for farming as an activity. Bitcoin, bicycles and
     * everything else in the `other` bucket do not match, which is the whole point of
     * the CSJ direction this implements.
     *
     * **This must never become an input to a relief CALCULATION.** It is defensible
     * only because it gates a sentence. Agricultural Property Relief turns on
     * agricultural USE AND OCCUPATION — IHTA 1984 s115(2), s116, s117 (two-year
     * owner-occupation, seven-year let) — none of which is inferable from a name
     * somebody typed (tax-compliance-reviewer, round five).
     *
     * **Prefix-matched at a leading word boundary, and that is deliberate** — "farm"
     * has to reach "farmland", "farmhouse" and "farm buildings", so there is no
     * trailing boundary and there cannot be one.
     *
     * **The consequence, stated accurately because an earlier version of this comment
     * did not**: a word merely STARTING with a term matches. "Croftwood Ltd" fires on
     * `croft`; "Orchardson" fires on `orchard`. What the leading boundary does buy is
     * rejecting a term mid-word — "Landcroft Holdings" does NOT fire on `croft`.
     *
     * Those false positives are benign in the only direction that matters here: the
     * sentence is conditional ("If your estate holds farmland or shares listed on
     * that market…"), so a reader whose asset is not farmland simply does not meet
     * the condition and is misled about nothing.
     */
    private function looksAgricultural(object $asset): bool
    {
        if (($asset->asset_type ?? null) !== 'other') {
            return false;
        }

        $name = strtolower(trim((string) ($asset->asset_name ?? '')));

        if ($name === '') {
            return false;
        }

        foreach (self::AGRICULTURAL_ASSET_TERMS as $term) {
            if (preg_match('/\b'.preg_quote($term, '/').'/', $name) === 1) {
                return true;
            }
        }

        return false;
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
     * From the configured effective date, unused defined contribution pension pots
     * will be included in the taxable estate for Inheritance Tax purposes (Autumn
     * Budget 2024). The date lives in
     * `inheritance_tax.pension_iht_inclusion.effective_date` and is 2027-04-06
     * today — do not restate it as a literal anywhere below, which is what W-0372
     * was raised for.
     *
     * Returns both the current rules scenario and the post-amendment scenario,
     * allowing users to understand the potential impact.
     *
     * The `post_2027_rules` key is deliberately NOT renamed: it is a published API
     * key that web, `/m` and native all read, so it is an identifier rather than
     * copy. Only the prose the user reads follows the configured date.
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
        array $baseCalc,
        array $assessment
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

        // W-0513 — WHAT THIS FIGURE ACTUALLY COVERS, and what it does not.
        //
        // The configuration declares `applies_to => ['defined_contribution',
        // 'death_benefits']`, and IHTA 1984 s150A brings lump sum death benefits
        // into the estate alongside unused pots. **This sum covers only the
        // first.** There is no death-benefit column on `dc_pensions` or
        // `db_pensions` — `lump_sum_entitlement` is the retirement commutation
        // lump sum, a different thing — so the application has never captured
        // what a scheme would pay out on death.
        //
        // The figure is therefore an UNDERSTATEMENT for any household whose
        // scheme carries a death-in-service benefit, and it used to be published
        // as though it were the whole answer. Estimating one would be a made-up
        // tax figure on a user's estate, so the coverage is declared instead:
        // `pension_value_covers` and `pension_value_excludes` below say exactly
        // which of the two configured categories were measured.
        $store = $this->pensionStore;
        $configuredCategories = (array) ($pensionInclusion['applies_to'] ?? ['defined_contribution']);
        $coveredCategories = array_values(array_intersect($configuredCategories, ['defined_contribution']));
        $excludedCategories = array_values(array_diff($configuredCategories, $coveredCategories));

        $userPensionValue = (float) $store->forUserByType($user, 'dc')->sum('current_fund_value');
        $spousePensionValue = 0;
        if ($this->poolsSpouse($user, $spouse, $dataSharingEnabled)) {
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

        // W-0364 — this reused `$baseCalc['total_allowances']` and `iht_rate`, the
        // SMALLER estate's answers, while adding the pension pots enlarged the estate.
        // Both tests that turn on estate size were therefore skipped:
        //
        //   * the residence band taper (IHTA 1984 s8D(5)) — an estate at £1.7m with a
        //     £600k pension crosses £2,000,000 and loses the band at £1 per £2. Reusing
        //     the smaller estate's allowances UNDERSTATED the post-2027 bill by up to
        //     the whole £350,000 of band.
        //   * the 10% charitable rate test (Sch 1A) — the baseline grows with the
        //     estate while a fixed legacy does not, so a household on 36% carried that
        //     rate into a scenario where it no longer qualifies.
        //
        // One call to the same assessment with the enlarged estate answers both. This
        // is W-0136's fix applied to the one place W-0136 did not reach.
        $postAmendment = $this->assessTaxPosition(
            $postAmendmentNetEstate,
            $this->getMainResidenceNetValue($user, $assessment['spouse']),
            [
                // The pension enlarges the taper base exactly as it enlarges the
                // estate — s8D(5)(d) strikes it on the estate before reliefs.
                'estate_for_taper' => (float) ($assessment['estate_for_taper'] ?? 0) + $totalPensionValue,
            ] + $assessment
        );

        $totalAllowances = (float) $postAmendment['total_allowances'];
        $ihtRate = (float) $postAmendment['rate']['rate'];
        $charitableDeduction = (float) $postAmendment['charitable_deduction'];
        $postAmendmentTaxableEstate = (float) $postAmendment['taxable_estate'];
        $postAmendmentIHTLiability = (float) $postAmendment['iht_liability'];

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
                // W-0515. This said pensions "pass outside the estate" flat, with
                // no end date, so a user reading it once took it as permanent — while
                // the block immediately below tells them it stops. The change is
                // ENACTED, not proposed, and its commencement date is configured, so
                // the sentence names it rather than implying an indefinite rule.
                'description' => 'Until '.$effectiveDate->format('j F Y').', unused defined contribution pension pots pass outside the estate and are not subject to Inheritance Tax.',
            ],
            'post_2027_rules' => [
                'net_estate' => round($postAmendmentNetEstate, 2),
                // W-0515 — LABELLED, because this is today's pot and the projection
                // publishes a different figure (`projected_unused_pension`, W-0482):
                // the unused fund at the modelled death date, after drawdown. Both
                // are right about different questions, and a household carrying two
                // pension-in-estate numbers with neither named is the defect.
                //
                // Today's pot is the deliberate basis HERE because this block answers
                // "what would the amendment cost me on what I hold now" — a
                // comparison a user can check against their own statement. The
                // projection answers "what will be left at death", which depends on
                // assumptions this scenario is not making.
                'pension_value_included' => round($totalPensionValue, 2),
                'pension_value_basis' => 'current_fund_value',
                // W-0513 — the categories this figure measured, and the ones the
                // configuration names but no column can answer.
                'pension_value_covers' => $coveredCategories,
                'pension_value_excludes' => $excludedCategories,
                'pension_value_basis_label' => 'the value of your pots today, not the amount left after drawdown',
                'projected_unused_pension' => round((float) ($baseCalc['projected_unused_pension'] ?? 0), 2),
                'user_pension_value' => round($userPensionValue, 2),
                'spouse_pension_value' => round($spousePensionValue, 2),
                'iht_liability' => round($postAmendmentIHTLiability, 2),
                'additional_iht' => round($additionalIHT, 2),
                'pensions_included' => true,
                // W-0372 — from the configured date, not a literal. `$effectiveDate`
                // is read at the top of this method and published as its own field
                // four lines above; restating it here meant a Budget that moved the
                // date moved every figure and left the sentence behind.
                'description' => 'From '.$effectiveDate->format('F Y').', unused defined contribution pension pots will be included in the taxable estate for Inheritance Tax purposes.',
            ],
            'impact_summary' => $additionalIHT > 0
                ? 'The '.$effectiveDate->format('Y').' pension amendment could increase your Inheritance Tax liability by £'.number_format($additionalIHT).' if your defined contribution pension pots (£'.number_format($totalPensionValue).', their value today) are included in your estate.'
                : 'The '.$effectiveDate->format('Y').' pension amendment would not increase your Inheritance Tax liability based on current pension values.',
            // W-0513 — stated to the user rather than left as a silent shortfall.
            'coverage_caveat' => $excludedCategories === []
                ? null
                : 'This figure covers your defined contribution pots only. Lump sum death benefits your schemes might pay are also within the amendment, and Fynla does not hold them, so your actual exposure could be higher.',
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
