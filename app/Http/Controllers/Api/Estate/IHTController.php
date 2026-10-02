<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Estate;

use App\Http\Controllers\Controller;
use App\Http\Traits\GatesEstateAccess;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Estate\IHTProfile;
use App\Models\Estate\Will;
use App\Models\User;
use App\Services\Estate\ComprehensiveEstatePlanService;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Estate\IHTCalculationService;
use App\Services\Estate\IHTFormattingService;
use App\Services\TaxConfigService;
use App\Services\Tiers\TeaserGate;
use App\Support\HouseholdPooling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IHTController extends Controller
{
    use GatesEstateAccess;
    use SanitizedErrorResponse;

    public function __construct(
        private readonly IHTCalculationService $ihtCalculationService,
        private readonly EstateAssetAggregatorService $assetAggregator,
        private readonly TaxConfigService $taxConfig,
        private readonly IHTFormattingService $formattingService,
        private readonly TeaserGate $teaserGate,
    ) {}

    /**
     * UNIFIED IHT Calculation - Handles all scenarios:
     * - Single users
     * - Married users without linked spouse
     * - Married users with linked spouse (second death scenario)
     */
    public function calculateIHT(Request $request): JsonResponse
    {
        $user = $request->user();

        // Full-only sub-route: spec §10.2 / SP2 PR7.
        $this->requireFullEstate($user);

        try {
            // Determine user scenario
            $spouse = $user->liveSpouse();
            $dataSharingEnabled = $user->sharesFinancialDataWithSpouse();

            // Calculate IHT using the simplified service
            $calculation = $this->ihtCalculationService->calculate($user, $spouse, $dataSharingEnabled);

            // Format assets and liabilities breakdown
            $userAssets = $this->assetAggregator->gatherUserAssets($user);
            $spouseAssets = ($spouse && $dataSharingEnabled)
                ? $this->assetAggregator->gatherUserAssets($spouse)
                : collect();

            $assetsBreakdown = $this->formattingService->formatAssetsBreakdown(
                $userAssets,
                $spouseAssets,
                $dataSharingEnabled,
                $user,
                $spouse,
                $calculation
            );

            $liabilitiesBreakdown = $this->formattingService->formatLiabilitiesBreakdown(
                $user,
                $spouse,
                $dataSharingEnabled
            );

            // W-0470 / W-0465 F5, F6 — these three lines used to overwrite the
            // engine's own figures with the DISPLAY breakdown's, and the
            // breakdown does not project:
            //
            //   * mortgages: `($ageAtDeath >= 70) ? 0 : $userShare` — a binary
            //     cliff on a hardcoded age, off a hardcoded horizon of 85,
            //     reading no maturity date (`IHTFormattingService:376`);
            //   * every other liability: `'projected_balance' => $userShare` —
            //     never amortised at all (`:406`, `:415`);
            //   * and the CURRENT total read `Liability::where('user_id')`, one
            //     leg, where the engine reads `forUserOrJoint()` — so a debt the
            //     user is joint owner of was inside the net estate and missing
            //     from the Liabilities row printed beside it.
            //
            // `IHTCalculationService::projectMemberLiabilities()` reads the real
            // maturity date or estimates a payoff, amortises, returns zero for a
            // debt that ends before death, and runs on the household horizon the
            // assets are grown to. That is the deductible liability at death —
            // IHTA 1984 s5(3), s162, s175A.
            //
            // **The direction decided this.** The breakdown's figure is
            // systematically larger, and a larger liability means a smaller taper
            // base, less taper, more residence band surviving, LESS tax. Adopting
            // it to make the column reconcile would have moved tax the wrong way.
            // (tax-compliance-reviewer, round four, 2026-08-24.)
            //
            // Do not reintroduce any of them. The display breakdown still renders
            // its own per-liability rows; rebuilding those on the projection is
            // the remaining half of W-0470.

            // The table's columns five years either side of the expected death:
            // the engine at a shifted horizon, and each asset projected from it
            // the same way the at-death column is (CSJ 2026-10-01: the table grew
            // every row at a 4.7% typed into the browser).
            $offsets = [];
            foreach (['minus_5' => -5, 'plus_5' => 5] as $key => $years) {
                $offsets[$key] = $this->ihtCalculationService->calculateAtHorizonOffset($user, $spouse, $dataSharingEnabled, $years);
                $assetsBreakdown = $this->withOffsetColumn(
                    $assetsBreakdown,
                    $this->formattingService->formatAssetsBreakdown($userAssets, $spouseAssets, $dataSharingEnabled, $user, $spouse, $offsets[$key]),
                    $key,
                );
            }

            // Format response for frontend compatibility
            $response = [
                'success' => true,
                'calculation' => $calculation,
                'assets_breakdown' => $assetsBreakdown,
                'liabilities_breakdown' => $liabilitiesBreakdown,
                'data_sharing_enabled' => $dataSharingEnabled, // Add to top level for frontend
            ];

            // Add formatted data for easy frontend consumption
            $response['iht_summary'] = [
                'current' => [
                    'net_estate' => $calculation['total_net_estate'],
                    'gross_assets' => $calculation['total_gross_assets'],
                    'liabilities' => $calculation['total_liabilities'],
                    // W-0154 F2: the summary carried three of the five figures, so the
                    // breakdown the user is shown could not be reconciled. The modelled
                    // second-death spouse band and the gift deduction both existed in
                    // the raw calculation and had no way to reach a screen.
                    'nrb_available' => $calculation['nrb_available'],
                    // The estate above the nil rate band, for the table's row (it
                    // was worked out in the browser, with a spouse's band assumed
                    // whenever the server sent none).
                    'estate_after_nrb' => round(max(0.0, (float) $calculation['total_net_estate'] - (float) $calculation['nrb_available']), 2),
                    'nrb_individual' => $calculation['nrb_individual'],
                    'nrb_spouse_modelled' => $calculation['nrb_spouse_modelled'],
                    'nrb_transferred' => $calculation['nrb_transferred'],
                    'nrb_gift_deduction' => $calculation['nrb_gift_deduction'],
                    'nrb_message' => $calculation['nrb_message'],
                    'rnrb_available' => $calculation['rnrb_available'],
                    'rnrb_individual' => $calculation['rnrb_individual'],
                    // W-0154 F2 — the residence band's components, so the column
                    // adds up on screen the way the nil rate band's does above.
                    // Publishing the total and one component leaves the reader with
                    // a subtotal they cannot reach.
                    'rnrb_spouse_modelled' => $calculation['rnrb_spouse_modelled'],
                    'rnrb_residence_cap_reduction' => $calculation['rnrb_residence_cap_reduction'],
                    'rnrb_taper_reduction' => $calculation['rnrb_taper_reduction'],
                    'rnrb_transferred' => $calculation['rnrb_transferred'],
                    'rnrb_status' => $calculation['rnrb_status'],
                    'rnrb_message' => $calculation['rnrb_message'],
                    'total_allowances' => $calculation['total_allowances'],
                    // W-0134: the charitable exemption reduces the estate's value; it
                    // is NOT an allowance and does not belong in the total above. It
                    // reached no screen at all until now, which is why the column
                    // between Net Estate and Taxable Estate was £20,000 short.
                    // W-0091 — partial Business Property Relief. Zero for an estate
                    // holding no qualifying business, which is most of them.
                    'business_relief_deduction' => $calculation['business_relief_deduction'] ?? 0,
                    // C5 — tax on gifts the seven-year rule did not save, after
                    // taper. Computed since 6302cd661 and published to nobody, which
                    // made the whole taper-relief feature invisible. Deliberately
                    // separate from `iht_liability`: this is the recipient's charge,
                    // falling on the estate only if unpaid after twelve months.
                    'failed_gift_tax' => $calculation['failed_gift_tax'] ?? 0,
                    'failed_gift_taper_saving' => $calculation['failed_gift_taper_saving'] ?? 0,
                    'failed_gifts' => $calculation['failed_gifts'] ?? [],
                    'charitable_deduction' => $calculation['charitable_deduction'],
                    // W-0399. The engine separates the pooled s23(1) exemption from
                    // the survivor-only Sch 1A rate-test amount; this summary
                    // published only the first, so `IHTPlanning.vue` rendered the
                    // HOUSEHOLD's £20,000 under the words "Your will leaves …"
                    // while the message beside it quoted the survivor's £10,000.
                    // Both figures were correct and neither was labelled.
                    'charitable_rate_test_amount' => $calculation['charitable_rate_test_amount'],
                    'taxable_estate' => $calculation['taxable_estate'],
                    'iht_liability' => $calculation['iht_liability'],
                    'iht_rate' => $calculation['iht_rate'],
                    'iht_rate_percent' => $calculation['iht_rate_percent'],
                    // W-0132: the rate the liability beside it was calculated at, and
                    // the server's own explanation of why. The summary published the
                    // percentage but not the type or the message, so the only screen
                    // that could state the rate correctly was `/plans/estate`, which
                    // reads the raw calculation. `IHTPlanning.vue` was left deciding
                    // the rate itself from a user toggle and got it wrong.
                    'iht_rate_type' => $calculation['iht_rate_type'],
                    'iht_rate_message' => $calculation['iht_rate_message'],
                    'effective_rate' => $calculation['effective_rate'],
                    // W-0466 — null for an estate the exclusions cannot affect, so a
                    // screen renders it with `v-if` and no household sees a caveat
                    // that does not apply to it. The WORDS come from the engine
                    // (Rule 20): web and `/m` ship separate bundles that share no
                    // constants, and `/m` computes nothing.
                    'unmodelled_relief_caveat' => $calculation['unmodelled_relief_caveat'] ?? null,
                    'projected_pension_inclusion_caveat' => $calculation['projected_pension_inclusion_caveat'] ?? null,
                    // W-0363 — published to every surface, not just the one that
                    // happened to be open when it was written (Rule 19/20).
                ],
                'projected' => [
                    'net_estate' => $calculation['projected_net_estate'],
                    'gross_assets' => $calculation['projected_gross_assets'],
                    'liabilities' => $calculation['projected_liabilities'],
                    // W-0465 — the projected column's own relief. It applied none at
                    // all, so a capped business showed the whole relief in one column
                    // and nothing in the other, on a screen built to compare them.
                    'business_relief_deduction' => $calculation['projected_business_relief_deduction'] ?? 0,
                    // W-0136 — the projection has its OWN allowances. The residence
                    // band tapers away above £2,000,000 and the charitable exemption
                    // is re-assessed against the projected estate, so the projected
                    // column cannot be reconciled against the current figures.
                    'nrb_available' => $calculation['projected_nrb_available'],
                    'estate_after_nrb' => round(max(0.0, (float) $calculation['projected_net_estate'] - (float) $calculation['projected_nrb_available']), 2),
                    'nrb_individual' => $calculation['nrb_individual'],
                    'nrb_spouse_modelled' => $calculation['nrb_spouse_modelled'],
                    'nrb_transferred' => $calculation['nrb_transferred'],
                    'nrb_gift_deduction' => $calculation['nrb_gift_deduction'],
                    'rnrb_available' => $calculation['projected_rnrb_available'],
                    'rnrb_individual' => $calculation['projected_rnrb_individual'],
                    'rnrb_spouse_modelled' => $calculation['projected_rnrb_spouse_modelled'],
                    'rnrb_residence_cap_reduction' => $calculation['projected_rnrb_residence_cap_reduction'],
                    'rnrb_taper_reduction' => $calculation['projected_rnrb_taper_reduction'],
                    'rnrb_transferred' => $calculation['projected_rnrb_transferred'],
                    'rnrb_status' => $calculation['projected_rnrb_status'],
                    'rnrb_message' => $calculation['projected_rnrb_message'],
                    'total_allowances' => $calculation['projected_total_allowances'],
                    'charitable_deduction' => $calculation['projected_charitable_deduction'],
                    'taxable_estate' => $calculation['projected_taxable_estate'],
                    'iht_liability' => $calculation['projected_iht_liability'],
                    'iht_rate' => $calculation['projected_iht_rate'],
                    'iht_rate_percent' => $calculation['projected_iht_rate_percent'],
                    'years_to_death' => $calculation['years_to_death'],
                    'estimated_age_at_death' => $calculation['estimated_age_at_death'],
                    'retirement_age' => $calculation['retirement_age'] ?? null,
                    // Asset-specific projections (new methodology)
                    'cash' => $calculation['projected_cash'] ?? null,
                    'investments' => $calculation['projected_investments'] ?? null,
                    'properties' => $calculation['projected_properties'] ?? null,
                ],
                'is_married' => $calculation['is_married'],
                'is_widowed' => $calculation['is_widowed'] ?? false,
                'data_sharing_enabled' => $calculation['data_sharing_enabled'],
                // The table's columns five years either side of the expected
                // death, from the same engine at a shifted horizon (the web table
                // grew assets at a 4.7% typed into the browser).
                'projected_minus_5' => $this->column($offsets['minus_5']),
                'projected_plus_5' => $this->column($offsets['plus_5']),
            ];

            // Add will information for estate planning status display
            $will = Will::where('user_id', $user->id)->first();
            $response['will_info'] = [
                'has_will' => $will?->has_will ?? false,
                'will_answered' => $will !== null,
                'last_updated' => $will?->will_last_updated?->toIso8601String(),
                'executor_name' => $will?->executor_name,
            ];

            // Upcoming life events and their Inheritance Tax effect, from the one
            // rule the estate plan uses (the IHT screen worked these out itself).
            $response['life_events_impact'] = rescue(
                fn () => app(ComprehensiveEstatePlanService::class)->buildLifeEventsImpact(
                    $user,
                    (float) $calculation['iht_liability'],
                    $calculation,
                ),
                null,
                report: true,
            );

            // Add cash projection breakdown for transparency
            $response['cash_projection_breakdown'] = $this->formattingService->generateCashProjectionBreakdown(
                $user,
                $spouse,
                $dataSharingEnabled,
                $calculation
            );

            return response()->json($response);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'IHT calculation');
        }
    }

    /*
     * W-0343. A private `getExistingLifeCover(User, ?User)` sat here, summing each
     * side's in-trust policies with a raw `where('user_id')` query. **Nothing called
     * it** — the name appeared once in this file, at its own declaration.
     *
     * Deleted rather than wired up, because the question already has an owner and a
     * live answer. `LifeCoverReach::householdCoverInTrust()` owns it (W-0186);
     * `EstateAgent:140` calls it and publishes `life_cover.user_cover_in_trust`,
     * `spouse_cover_in_trust` and `total_cover_in_trust`, which
     * `EstatePlanService:636,871` read. Checked before deleting: this was a leftover,
     * not an omission — no figure the estate response was supposed to carry went
     * missing with it.
     *
     * **If you need a household in-trust cover figure in this controller, call
     * `LifeCoverReach`. Do not re-derive it here.** The deleted copy was already
     * wrong in two ways the owner handles: a raw `user_id` query misses a joint-life
     * policy the spouse is also assured under (W-0186), and it bypassed the
     * live/reciprocal spouse gate (W-0278), so it would have disclosed a deleted
     * partner's in-trust cover.
     */

    /** @return array<string, mixed> one projected column of the table */
    private function column(array $c): array
    {
        return [
            'years_to_death' => $c['years_to_death'],
            'estimated_age_at_death' => $c['estimated_age_at_death'],
            'gross_assets' => $c['projected_gross_assets'],
            'liabilities' => $c['projected_liabilities'],
            'net_estate' => $c['projected_net_estate'],
            'total_allowances' => $c['projected_total_allowances'],
            'estate_after_nrb' => round(max(0.0, (float) $c['projected_net_estate'] - (float) $c['projected_nrb_available']), 2),
            'taxable_estate' => $c['projected_taxable_estate'],
            'iht_liability' => $c['projected_iht_liability'],
        ];
    }

    /**
     * Copy one shifted run's projected figures onto the main breakdown as
     * `projected_value_{key}` (rows), `projected_total_{key}` (owner) and
     * `group_totals.*.{key}`. The rows come from the same gather in the same
     * order, so they line up by position.
     */
    private function withOffsetColumn(array $breakdown, array $offset, string $key): array
    {
        foreach (['user', 'spouse'] as $owner) {
            if (empty($breakdown[$owner]) || empty($offset[$owner])) {
                continue;
            }
            foreach ($breakdown[$owner]['assets'] as $type => $rows) {
                foreach ($rows as $i => $row) {
                    $breakdown[$owner]['assets'][$type][$i]['projected_value_'.$key] = round((float) ($offset[$owner]['assets'][$type][$i]['projected_value'] ?? 0), 2);
                }
                $breakdown[$owner]['group_totals'][$type][$key] = round((float) ($offset[$owner]['group_totals'][$type]['projected'] ?? 0), 2);
            }
            $breakdown[$owner]['projected_total_'.$key] = round((float) ($offset[$owner]['projected_total'] ?? 0), 2);
        }

        return $breakdown;
    }

    /**
     * Store or update IHT profile for the authenticated user
     */
    public function storeOrUpdateIHTProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        // Full-only sub-route: defence-in-depth server gate (spec §10.2 / SP2 PR7).
        $this->requireFullEstate($user);

        $validated = $request->validate([
            // W-0509 — read from the one list rather than restated here. The literal
            // this replaces predated civil partnerships and 422'd them.
            'marital_status' => ['nullable', 'string', Rule::in(HouseholdPooling::ALL_MARITAL_STATUSES)],
            'has_spouse' => ['nullable', 'boolean'],
            'own_home' => ['nullable', 'boolean'],
            'home_value' => ['nullable', 'numeric', 'min:0'],
            'nrb_transferred_from_spouse' => ['nullable', 'numeric', 'min:0', 'max:'.(int) $this->taxConfig->getInheritanceTax()['nil_rate_band']],
            // Was absent from this list entirely, so nothing bounded it at any layer.
            'rnrb_transferred_from_spouse' => ['nullable', 'numeric', 'min:0', 'max:'.(int) $this->taxConfig->getInheritanceTax()['residence_nil_rate_band']],
            'charitable_giving_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $profile = IHTProfile::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        // Invalidate cache as profile has changed
        $this->ihtCalculationService->invalidateCache($user);

        return response()->json([
            'success' => true,
            'message' => 'IHT profile updated successfully',
            'data' => $profile,
        ]);
    }

    /**
     * Invalidate IHT calculation cache
     */
    public function invalidateCache(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->ihtCalculationService->invalidateCache($user);

        return response()->json([
            'success' => true,
            'message' => 'IHT calculation cache cleared',
        ]);
    }
}
