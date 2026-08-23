<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Bequest;
use App\Models\Estate\Will;
use App\Models\User;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

/**
 * Will Analysis Service
 *
 * Analyzes wills for:
 * - Charitable bequest status (10% threshold for reduced IHT rate)
 * - Trust-triggering wishes detection
 * - Bequest totals and allocations
 */
class WillAnalysisService
{
    /**
     * Shown instead of a "give another £X" instruction when the will leaves an
     * asset or a residuary share to charity — see hasUnvaluedCharitableGifts().
     *
     * W-0451 / C4. This was a `const` hardcoding "the 10% needed" — the same
     * quantity, in the same `message` key, in the same return array as the three
     * sentences fixed alongside it. Under a 12% configuration one branch said
     * 10% and three said 12%.
     *
     * **It survived because a `const` cannot interpolate.** That is a third
     * structural blind spot beside rates-as-decimals-in-arithmetic and
     * rates-in-comparisons: a sweep reading `const` declarations sees a fixed
     * string and moves on, because a fixed string is what a constant is for.
     * **The moment a sentence needs a configured value it stops being a
     * constant**, whatever the language lets you declare.
     *
     * Kept as a method rather than deleted so the one call site and the one test
     * that name it keep a single home to reference.
     */
    public function unvaluedCharitableGiftsMessage(): string
    {
        $threshold = rtrim(rtrim(number_format($this->taxConfig->getCharitableThresholdPercent() * 100, 2), '0'), '.');

        return 'Your will also leaves an asset, or a share of what remains, to charity. We cannot put a figure on that here, so we cannot tell you whether you have reached the '.$threshold.'% needed for the reduced Inheritance Tax rate. A solicitor can.';
    }

    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * The household's charitable rate position, in the words the plan and the
     * decision trace print.
     *
     * **This method no longer computes the position — it expresses one that has
     * already been settled** (W-0451 / W-0452). It is handed the result of
     * `IHTCalculationService::calculate()` and reads the figures that service
     * published: the Schedule 1A baseline, the threshold, the survivor's donated
     * amount, the shortfall, and the two Inheritance Tax bills whose difference
     * is the saving.
     *
     * **Why it stopped computing.** It used to strike its own baseline from
     * whatever net estate and nil rate band the caller happened to pass, and
     * total the LOGGED-IN user's bequests as the numerator. `EstateAgent` passed
     * the individual's net estate with the household's available band — one
     * person's assets against two people's allowance — so:
     *
     *   * `/plans/estate` published a charitable percentage of **4.2%** on a page
     *     whose own Net Estate row read £1,728,780, while `/estate` published
     *     **0.8%** for the same household in the same session; and
     *   * the saving it published was the rate differential on that baseline,
     *     printed in a decision trace beside two figures struck on the HOUSEHOLD
     *     chargeable estate — "at 40% = £343,512, at 36% = £309,161 — saving
     *     £19,580", where the two figures subtract to £34,351.
     *
     * Per the 2026-08-21 statutory ruling the rate test runs on the survivor's
     * will against the estate passing on the second death, so the numerator and
     * the denominator both belong to the household calculation and neither can
     * be re-derived here without inventing a second answer (Rule 20).
     *
     * The estate's own wills are still read — by `IHTCalculationService`, through
     * `getCharitableBequestTotal()` and `hasUnvaluedCharitableGifts()` on this
     * class, of the survivor. This method needs no user because the question of
     * *whose* will is settled before it is called.
     *
     * @param  array<string, mixed>  $ihtCalculation  the result of `IHTCalculationService::calculate()`
     * @return array Analysis with status, amounts, and the saving
     */
    public function analyzeCharitableBequests(array $ihtCalculation): array
    {
        $baseline = (float) ($ihtCalculation['charitable_baseline'] ?? 0);
        $threshold = (float) ($ihtCalculation['charitable_threshold'] ?? 0);
        $rateTestAmount = (float) ($ihtCalculation['charitable_rate_test_amount'] ?? 0);
        $charitablePercent = (float) ($ihtCalculation['charitable_giving_percent'] ?? 0);
        $shortfall = (float) ($ihtCalculation['charitable_shortfall'] ?? 0);
        $qualifies = (bool) ($ihtCalculation['charitable_rate_qualifies'] ?? false);
        $hasUnvalued = (bool) ($ihtCalculation['charitable_has_unvalued_gifts'] ?? false);
        $effectiveRate = (float) ($ihtCalculation['iht_rate'] ?? 0);

        // W-0451 C1 — whose will these figures describe. Carried through so a
        // sentence-writer never has to fall back to whoever is logged in.
        $rateTestMemberId = $ihtCalculation['charitable_rate_test_member_id'] ?? null;
        $rateTestMemberFirstName = $ihtCalculation['charitable_rate_test_member_first_name'] ?? null;
        $rateTestIsRequestingUser = (bool) ($ihtCalculation['charitable_rate_test_is_requesting_user'] ?? true);

        $taxableEstate = (float) ($ihtCalculation['taxable_estate'] ?? 0);
        $taxableEstateIfQualifying = (float) ($ihtCalculation['charitable_taxable_estate_if_qualifying'] ?? 0);
        $taxAtStandardRate = (float) ($ihtCalculation['charitable_tax_at_standard_rate'] ?? 0);
        $taxAtReducedRate = (float) ($ihtCalculation['charitable_tax_at_reduced_rate'] ?? 0);
        $saving = (float) ($ihtCalculation['charitable_rate_saving'] ?? 0);

        $excess = $qualifies ? max(0, $rateTestAmount - $threshold) : 0.0;

        // Presentation only. WHETHER the estate qualifies is decided once, in
        // `determineIHTRate()`, and arrives as `charitable_rate_qualifies`; this
        // splits a qualifying estate into "meets" and "exceeds" so the sentence
        // can say which, and it decides nothing.
        $status = match (true) {
            ! $qualifies => 'below',
            $rateTestAmount > $threshold * 1.01 => 'above',
            default => 'at',
        };

        return [
            'status' => $status,                    // 'below', 'at', 'above'
            'rate_test_member_id' => $rateTestMemberId,
            'rate_test_member_first_name' => $rateTestMemberFirstName,
            'rate_test_is_requesting_user' => $rateTestIsRequestingUser,
            'charitable_total' => round($rateTestAmount, 2),
            'charitable_percent' => round($charitablePercent, 4),
            'baseline' => round($baseline, 2),
            'threshold' => round($threshold, 2),
            'shortfall' => round($shortfall, 2),
            'excess' => round($excess, 2),
            'effective_rate' => $effectiveRate,
            'effective_rate_percent' => round($effectiveRate * 100, 0),
            // The two bills the saving is the difference of — and the two bases
            // they were struck on — published so every sentence that quotes the
            // saving can print its own working and have the subtraction come
            // out. A reader who cannot see the second base cannot check the
            // second bill, which is how a 43% error stood on a decision trace.
            'taxable_estate' => round($taxableEstate, 2),
            'taxable_estate_if_qualifying' => round($taxableEstateIfQualifying, 2),
            'tax_at_standard_rate' => round($taxAtStandardRate, 2),
            'tax_at_reduced_rate' => round($taxAtReducedRate, 2),
            'potential_saving' => $qualifies ? 0 : round($saving, 2),
            'current_saving' => $qualifies ? round($saving, 2) : 0,
            'has_unvalued_charitable_gifts' => $hasUnvalued,
            'message' => $hasUnvalued
                ? $this->unvaluedCharitableGiftsMessage()
                : $this->getCharitableStatusMessage($status, $shortfall, $excess, $threshold, $saving),
        ];
    }

    /**
     * Does this user leave an asset, or a share of what remains, to charity?
     *
     * Both qualify for the exemption in law (IHTA 1984 s.23) and both count
     * toward the 10% component, but neither row carries a figure this service
     * can total — an asset gift holds a description, a residuary gift a share
     * of an amount that is not known here.
     *
     * Where one exists we go quiet rather than tell the user to increase their
     * giving by a figure that assumes it is worth nothing. Telling someone to
     * give away another £40,000 to buy a relief they may already hold is a
     * worse failure than saying we cannot tell.
     */
    /**
     * Whether this person is leaving anything to charity, and what.
     *
     * W-0132. `/settings/family` asked "Do you wish to leave anything to charity?"
     * and answered "Not set" on an account with a £10,000 charitable legacy the
     * estate calculation was already using. It was reading `users.charitable_bequest`
     * — a column written by a toggle on `/estate` and never loaded back — so it was
     * a FOURTH answer to a question the will already answers, and it disagreed with
     * the other three.
     *
     * The will is the instrument HMRC reads and it is the one home for this
     * (`determineIHTRate()` was moved onto it by W-0020). This method exists so no
     * screen has to re-derive the answer from raw bequest rows: `isCharitable()` is
     * a heuristic on the model, and a second copy of it in a Vue component is
     * exactly the drift Rule 20 forbids.
     *
     * **No totalling of estate-dependent gifts.** A percentage or residuary gift is
     * worth nothing until an estate is valued, and a settings page has no business
     * running an estate calculation to render a card. Fixed sums are totalled;
     * anything whose value depends on the estate is flagged rather than counted as
     * zero, so the card never prints a total that quietly omits a gift.
     *
     * @return array{has_bequests: bool, count: int, fixed_total: float, has_estate_share: bool}
     */
    public function charitableBequestSummary(User $user): array
    {
        $will = Will::where('user_id', $user->id)->with('bequests')->first();

        $charitable = $will
            ? $will->bequests->filter(fn (Bequest $bequest) => $bequest->isCharitable())
            : collect();

        return [
            'has_bequests' => $charitable->isNotEmpty(),
            'count' => $charitable->count(),
            'fixed_total' => round((float) $charitable
                ->where('bequest_type', 'specific_amount')
                ->sum('specific_amount'), 2),
            'has_estate_share' => $charitable->contains(
                fn (Bequest $bequest) => in_array($bequest->bequest_type, ['percentage', 'specific_asset', 'residuary'], true)
            ),
        ];
    }

    public function hasUnvaluedCharitableGifts(User $user): bool
    {
        $will = Will::where('user_id', $user->id)->with('bequests')->first();

        if (! $will) {
            return false;
        }

        return $will->bequests
            ->filter(fn (Bequest $bequest) => $bequest->isCharitable())
            ->contains(fn (Bequest $bequest) => in_array($bequest->bequest_type, ['specific_asset', 'residuary'], true));
    }

    /**
     * Get total value of charitable bequests for a user
     *
     * Includes both percentage-based and specific amount bequests to charities
     *
     * @param  User  $user  The user to check
     * @param  float  $netEstate  Net estate for percentage calculations
     * @return float Total charitable bequest value
     */
    public function getCharitableBequestTotal(User $user, float $netEstate = 0): float
    {
        $will = Will::where('user_id', $user->id)->with('bequests')->first();

        if (! $will) {
            return 0;
        }

        $total = 0;

        foreach ($will->bequests as $bequest) {
            // Check if bequest is to a charity
            if (! $bequest->isCharitable()) {
                continue;
            }

            // 'specific_asset' and 'residuary' are absent below deliberately,
            // not by oversight. Both DO count toward the 10% component in law
            // (IHTA 1984 s.23 / Sch 1A) — they are excluded because neither row
            // carries a figure this service can total, not because they fail to
            // qualify. An asset gift holds only a description; a residuary gift
            // is a share of an amount not known here. Where either exists,
            // hasUnvaluedCharitableGifts() suppresses the "give another £X"
            // instruction, because a total that assumes they are worth nothing
            // must not become advice.
            if ($bequest->bequest_type === 'percentage' && $bequest->percentage_of_estate) {
                $total += $netEstate * ($bequest->percentage_of_estate / 100);
            } elseif ($bequest->bequest_type === 'specific_amount' && $bequest->specific_amount) {
                // W-0020: this read 'specific' until 2026-08-21 — a value the
                // bequest_type enum has never been able to hold, so the branch
                // was dead and a charitable CASH legacy contributed nothing.
                // Only a percentage gift could ever reach the reduced rate,
                // which is the opposite of how charitable legacies are usually
                // written.
                $total += (float) $bequest->specific_amount;
            }
        }

        return $total;
    }

    /**
     * Detect trust-triggering wishes in will bequests and executor notes
     *
     * Scans bequest notes and executor instructions for patterns that suggest
     * trust structures should be recommended
     *
     * @param  Will  $will  The will to analyze
     * @return array Array of detected wish triggers with recommendations
     */
    public function detectTrustTriggeringWishes(Will $will): array
    {
        $triggers = [];
        $wishPatterns = $this->getWishPatterns();

        // Scan bequest notes and executor instructions
        $textToScan = collect($will->bequests)->pluck('notes')->filter()->join(' ')
            .' '.($will->executor_notes ?? '');

        // Normalize text for matching
        $textToScan = strtolower($textToScan);

        foreach ($wishPatterns as $key => $config) {
            foreach ($config['patterns'] as $pattern) {
                if (str_contains($textToScan, strtolower($pattern))) {
                    $triggers[] = [
                        'wish_type' => $key,
                        'matched_pattern' => $pattern,
                        'trust_type' => $config['trust_type'],
                        'description' => $config['description'],
                        'iht_treatment' => $config['iht_treatment'],
                        'recommendation' => "Consider creating a {$config['description']} to fulfil this wish",
                    ];
                    break; // One match per category is enough
                }
            }
        }

        return $triggers;
    }

    /**
     * Get all charitable bequests for a user
     *
     * @param  User  $user  The user to check
     * @return Collection Collection of charitable bequests
     */
    public function getCharitableBequests(User $user): Collection
    {
        $will = Will::where('user_id', $user->id)->with('bequests')->first();

        if (! $will) {
            return collect();
        }

        return $will->bequests->filter(fn (Bequest $bequest) => $bequest->isCharitable());
    }

    /**
     * Get wish patterns for trust detection
     *
     * @return array Pattern configuration
     */
    private function getWishPatterns(): array
    {
        return [
            'education_trust' => [
                'patterns' => ['education', 'school fees', 'university', 'college', 'school'],
                'trust_type' => 'bare_trust',
                'description' => 'Education Trust for Children',
                'iht_treatment' => 'Bare trust = PET, not CLT',
            ],
            'income_family' => [
                'patterns' => ['income for family', 'income for spouse', 'living expenses', 'income to my wife', 'income to my husband'],
                'trust_type' => 'interest_in_possession',
                'description' => 'Interest in Possession Trust',
                'iht_treatment' => 'Pre-2006 IIP = not relevant property',
            ],
            'income_children' => [
                'patterns' => ['income for child', 'income for kids', 'maintenance', 'income for my son', 'income for my daughter'],
                'trust_type' => 'discretionary',
                'description' => 'Discretionary Trust for Minors',
                'iht_treatment' => 'Relevant property - 10-year charges apply',
            ],
            'age_restriction' => [
                'patterns' => ['at age 25', 'when they reach', 'upon turning', 'at age 21', 'when older', 'at age 30'],
                'trust_type' => 'age_18_to_25',
                'description' => 'Age 18-25 Trust',
                'iht_treatment' => 'Special treatment - reduced exit charges',
            ],
            'asset_protection' => [
                'patterns' => ['protect from divorce', 'creditor protection', 'bankruptcy', 'protect assets', 'safeguard from'],
                'trust_type' => 'discretionary',
                'description' => 'Asset Protection Trust',
                'iht_treatment' => 'Relevant property - full charges apply',
            ],
            'special_needs' => [
                'patterns' => ['special needs', 'disability', 'disabled', 'vulnerable', 'care needs'],
                'trust_type' => 'disabled_person',
                'description' => "Disabled Person's Trust",
                'iht_treatment' => 'Exempt from periodic/exit charges',
            ],
            'business_succession' => [
                'patterns' => ['business to continue', 'company shares', 'business succession', 'keep the business running'],
                'trust_type' => 'business_property',
                'description' => 'Business Property Trust',
                'iht_treatment' => 'May qualify for BPR - IHT efficient',
            ],
            'property_management' => [
                'patterns' => ['property to be managed', 'rental income', 'let property', 'investment property managed'],
                'trust_type' => 'property_trust',
                'description' => 'Property Trust',
                'iht_treatment' => 'Relevant property - professional management',
            ],
        ];
    }

    /**
     * Generate message for charitable bequest status
     *
     * @param  string  $status  'below', 'at', or 'above'
     * @param  float  $shortfall  Amount below threshold
     * @param  float  $excess  Amount above threshold
     * @param  float  $threshold  10% threshold value
     * @param  float  $potentialSaving  Potential IHT saving
     * @return string Status message
     */
    private function getCharitableStatusMessage(
        string $status,
        float $shortfall,
        float $excess,
        float $threshold,
        float $potentialSaving
    ): string {
        // W-0451. This read the array directly with its own `?? 0.36` fallback.
        // `TaxConfigService::getCharitableReducedRate()` is the one home, and
        // its docblock records this exact duplication as consolidated across six
        // sites — while this site still stood. A completion note is how the next
        // reader stops looking, so the note is corrected in the same change.
        $reducedRatePercent = round($this->taxConfig->getCharitableReducedRate() * 100);
        $thresholdPercent = round($this->taxConfig->getCharitableThresholdPercent() * 100);

        return match ($status) {
            // W-0451: the threshold was hardcoded "10%" here while `:55` computed
            // it from configuration — the same quantity, read two ways, three
            // hundred lines apart in one class. Rule 9: "IHT" spelled out, five
            // instances across these three sentences.
            'above' => 'Your charitable bequests exceed the '.$thresholdPercent.'% threshold by £'.number_format($excess).'. You qualify for the reduced '.$reducedRatePercent.'% Inheritance Tax rate, saving £'.number_format($potentialSaving).' in Inheritance Tax.',
            'at' => 'Your charitable bequests meet the '.$thresholdPercent.'% threshold of £'.number_format($threshold).'. You qualify for the reduced '.$reducedRatePercent.'% Inheritance Tax rate, saving £'.number_format($potentialSaving).' in Inheritance Tax.',
            'below' => 'Your charitable bequests are £'.number_format($shortfall).' below the '.$thresholdPercent.'% threshold of £'.number_format($threshold).'. Increase charitable giving by £'.number_format($shortfall).' to qualify for the reduced '.$reducedRatePercent.'% rate and save £'.number_format($potentialSaving).' in Inheritance Tax.',
            default => 'Unable to determine charitable bequest status.',
        };
    }
}
