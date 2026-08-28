<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\CriticalIllnessPolicy;
use App\Models\Estate\Asset;
use App\Models\Estate\Liability;
use App\Models\ExpenditureProfile;
use App\Models\Investment\InvestmentAccount;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Protection\LifeCoverReach;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\PensionStore;
use App\Services\Stores\PropertyStore;
use App\Services\Stores\SavingsStore;
use App\Services\TaxConfigService;
use App\Traits\CalculatesOwnershipShare;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service for aggregating estate assets and liabilities across all modules
 *
 * Single-Record Architecture:
 * - ONE database record stores the FULL asset value
 * - user_id = primary owner, joint_owner_id = secondary owner
 * - Query pattern: where('user_id', $id)->orWhere('joint_owner_id', $id)
 * - User's share calculated from full value * ownership_percentage
 *
 * **What this computes: a SECOND-DEATH estate.** Both members pooled, every record
 * counted once, at each member's own share. Both of `IHTCalculationService::calculate()`'s
 * columns — current and projected — are "both gone" estates. There is no first-death
 * figure anywhere on this path.
 *
 * **Survivorship is therefore not applied, and that is correct rather than missing.**
 * Ownership type decides the SIZE of a member's share (and, for an undivided share
 * held with a non-spouse, its valuation — see `UndividedShareDiscount`). It does not
 * decide whether the share is in the estate at all, because on a second death there
 * is no survivor left for a joint tenancy to pass to.
 *
 * **DO NOT "fix" this to exclude joint-tenancy property.** This docblock used to say
 * "Joint tenancy: Passes to survivor (may exclude from first death estate)", which
 * described a first-death treatment the service has never implemented, and W-0375 was
 * raised because the obvious response to that sentence is to make the code match it.
 * Excluding joint tenancy from a second-death estate would delete roughly HALF the
 * household estate and understate Inheritance Tax by the same.
 *
 * The trap is well stocked: `TaxConfigService::hasSurvivorshipRights()`,
 * `allowsWillOverride()` and `getPropertyOwnership()` all exist, all read live
 * configuration (`joint_tenancy.survivorship = true`), and all have ZERO callers.
 * They look like the missing wiring. They are not — nothing on this path should
 * consult them.
 *
 * If a first-death figure is ever wanted it is a NEW figure with its own name, not a
 * reinterpretation of this one (W-0375 acceptance 2).
 */
class EstateAssetAggregatorService
{
    use CalculatesOwnershipShare;

    public function __construct(
        private readonly PropertyStore $propertyStore,
        private readonly MortgageStore $mortgageStore,
        private readonly LifeCoverReach $lifeCoverReach,
        private readonly TaxConfigService $taxConfig,
        private readonly UndividedShareDiscount $undividedShareDiscount,
    ) {}

    /**
     * Gather all assets for a user from all modules
     *
     * Single-record pattern: Query assets where user is owner OR joint_owner.
     * Returns a collection of standardized asset objects suitable for IHT calculations.
     */
    public function gatherUserAssets(User $user): Collection
    {
        $assets = Asset::where('user_id', $user->id)->get();

        // Investment accounts - Single-record pattern
        $investmentAccounts = InvestmentAccount::forUserOrJoint($user->id)
            ->get();
        $investmentAssets = $investmentAccounts->map(function ($account) use ($user) {
            return (object) [
                'user_id' => $user->id,
                'asset_type' => 'investment',
                'asset_name' => $account->provider.' - '.strtoupper($account->account_type),
                'current_value' => $this->calculateUserShare($account, $user->id),
                'full_value' => (float) $account->current_value,
                'ownership_type' => $account->ownership_type ?? 'individual',
                'ownership_percentage' => $account->ownership_percentage ?? 100,
                'is_primary_owner' => $this->isPrimaryOwner($account, $user->id),
                'is_iht_exempt' => false, // ISAs are NOT IHT-exempt
            ];
        });

        // Properties - Single-record pattern
        $properties = $this->propertyStore->forUserWithJointOwner($user);
        $propertyAssets = $properties->map(function ($property) use ($user) {
            return (object) [
                'user_id' => $user->id,
                'asset_type' => 'property',
                'asset_name' => $property->address_line_1 ?: 'Property',
                // W-0368 — an undivided share co-owned with a NON-spouse is valued
                // for Inheritance Tax at a discount for restricted marketability.
                // **IHTA 1984 s160** is the authority — the price the property would
                // fetch on the open market — and IHTM15071 / SVM113040 are HMRC
                // guidance on applying it, not the source of it.
                //
                // **s161 does not deny the discount between spouses.** It SUBSTITUTES
                // a valuation basis, valuing related property as a proportion of the
                // combined whole, and that basis leaves no restriction for a discount
                // to price. `UndividedShareDiscount` is the one home for the rule.
                //
                // This is the Inheritance Tax path, so the discount belongs here and
                // NOT in `calculateUserShare`, which net worth and every other module
                // read.
                'current_value' => $this->undividedShareDiscount->shareValue($property, $user),
                'undiscounted_share' => $this->calculateUserShare($property, $user->id),
                'undivided_share_discount' => $this->undividedShareDiscount->discountAmount($property, $user),
                'full_value' => (float) $property->current_value,
                'ownership_type' => $property->ownership_type ?? 'individual',
                'ownership_percentage' => $property->ownership_percentage ?? 100,
                'is_primary_owner' => $this->isPrimaryOwner($property, $user->id),
                'property_type' => $property->property_type ?? 'unknown', // For RNRB eligibility
                'is_iht_exempt' => false,
            ];
        });

        // Savings/Cash - Single-record pattern
        $savingsAccounts = app(SavingsStore::class)->forUser($user);
        $savingsAssets = $savingsAccounts->map(function ($account) use ($user) {
            return (object) [
                'user_id' => $user->id,
                'asset_type' => 'cash',
                'asset_name' => $account->institution.' - '.ucfirst($account->account_type),
                'current_value' => $this->calculateUserShare($account, $user->id),
                'full_value' => (float) $account->current_balance,
                'ownership_type' => $account->ownership_type ?? 'individual',
                'ownership_percentage' => $account->ownership_percentage ?? 100,
                'is_primary_owner' => $this->isPrimaryOwner($account, $user->id),
                'is_iht_exempt' => false, // Cash ISAs are NOT IHT-exempt
            ];
        });

        // Business Interests - Single-record pattern with joint ownership support
        $businessInterests = BusinessInterest::forUserOrJoint($user->id)
            ->get();
        $businessAssets = $businessInterests->map(function ($business) use ($user) {
            // Use trait to calculate user's share based on ownership_percentage
            $userValue = $this->calculateUserShare($business, $user->id);

            // W-0091 / W-0463 — QUALIFICATION only. Whether the business qualifies
            // for Business Property Relief is a fact about the business; HOW MUCH
            // relief it gets is a fact about the estate, because the allowance is
            // capped across everything that claims it. Deciding both here is what
            // produced the defect: `is_iht_exempt = true` meant 100% relief,
            // uncapped, forever.
            //
            // The minimum ownership period comes from the configuration rather than
            // a literal 2 (Rule 2).
            $businessRelief = $this->taxConfig->getBusinessRelief();
            $minOwnershipYears = (int) ($businessRelief['min_ownership_years'] ?? 2);

            $reliefQualifies = false;
            if ($business->bpr_eligible && $business->trading_status === 'trading') {
                if ($business->acquisition_date) {
                    $yearsOwned = Carbon::parse($business->acquisition_date)->diffInYears(now());
                    $reliefQualifies = $yearsOwned >= $minOwnershipYears;
                } else {
                    // Marked eligible with no acquisition date — take the flag.
                    $reliefQualifies = true;
                }
            }

            return (object) [
                'user_id' => $user->id,
                'asset_type' => 'business',
                'asset_name' => $business->business_name,
                'current_value' => $userValue,
                'full_value' => (float) $business->current_valuation,
                'ownership_type' => $business->ownership_type ?? 'individual',
                'ownership_percentage' => $business->ownership_percentage ?? 100,
                'is_primary_owner' => $this->isPrimaryOwner($business, $user->id),
                // Set by applyBusinessPropertyRelief() below, once the shared cap
                // has been allocated across every qualifying asset in this estate.
                'is_iht_exempt' => false,
                'iht_relief_amount' => 0.0,
                'iht_relief_type' => $reliefQualifies ? 'business_property_relief' : null,
                'iht_relief_qualifies' => $reliefQualifies,
                'bpr_eligible' => $business->bpr_eligible ?? false,
                'trading_status' => $business->trading_status,
            ];
        });

        // Chattels (personal property) - Single-record pattern with joint ownership support
        $chattels = Chattel::forUserOrJoint($user->id)
            ->get();
        $chattelAssets = $chattels->map(function ($chattel) use ($user) {
            // Use trait to calculate user's share based on ownership_percentage
            $userValue = $this->calculateUserShare($chattel, $user->id);

            return (object) [
                'user_id' => $user->id,
                'asset_type' => 'chattel',
                'asset_name' => $chattel->name,
                'current_value' => $userValue,
                'full_value' => (float) $chattel->current_value,
                'ownership_type' => $chattel->ownership_type ?? 'individual',
                'ownership_percentage' => $chattel->ownership_percentage ?? 100,
                'is_primary_owner' => $this->isPrimaryOwner($chattel, $user->id),
                'is_iht_exempt' => false,
            ];
        });

        // DC Pensions (not IHT liable but needed for income projections in gifting strategy)
        $dcPensions = app(PensionStore::class)->forUserByType($user, 'dc');
        $dcPensionAssets = $dcPensions->map(function ($pension) use ($user) {
            return (object) [
                'user_id' => $user->id,
                'asset_type' => 'dc_pension',
                'asset_name' => $pension->scheme_name,
                'current_value' => $pension->current_fund_value,
                'full_value' => (float) $pension->current_fund_value,
                'ownership_type' => 'individual',
                'ownership_percentage' => 100,
                'is_primary_owner' => true,
                'is_iht_exempt' => true, // DC pensions outside estate if beneficiary nominated
            ];
        });

        // DB Pensions (for income projections only - no transfer value in estate)
        $dbPensions = app(PensionStore::class)->forUserByType($user, 'db');
        $dbPensionAssets = $dbPensions->map(function ($pension) use ($user) {
            return (object) [
                'user_id' => $user->id,
                'asset_type' => 'db_pension',
                'asset_name' => $pension->scheme_name,
                'current_value' => 0, // DB pensions have no IHT value (die with member)
                'full_value' => 0,
                'ownership_type' => 'individual',
                'ownership_percentage' => 100,
                'is_primary_owner' => true,
                'is_iht_exempt' => true,
                // W-0154. Was `$pension->expected_annual_pension ?? 0` — a column that
                // has never existed on `db_pensions`, so this was 0 for every Defined
                // Benefit pension in the application. The derived column is preferred
                // because it is the revalued figure at the scheme's Normal Retirement
                // Age; it is null until a write triggers recalculation, hence the
                // fallback to the accrued figure the form actually captures.
                //
                // Stated plainly: **nothing reads this field today.** No consumer of
                // `gatherUserAssets()` touches `annual_income` on a `db_pension` row —
                // the estate's own retirement income projection
                // (`IHTCalculationService::getRetirementIncome()`) reads no pension at
                // all, which is the larger defect and is tracked separately as W-0154
                // R1. Corrected here so the field is truthful when something does read
                // it, rather than left as a value that would be wrong the day it was
                // wired up.
                'annual_income' => (float) (
                    $pension->projected_annual_pension_at_nra_gbp
                    ?? $pension->accrued_annual_pension
                    ?? 0
                ),
            ];
        });

        return $this->applyBusinessPropertyRelief(
            $assets
                ->concat($investmentAssets)
                ->concat($propertyAssets)
                ->concat($savingsAssets)
                ->concat($businessAssets)
                ->concat($chattelAssets)
                ->concat($dcPensionAssets)
                ->concat($dbPensionAssets)
        );
    }

    /**
     * Allocate Business Property Relief across the estate, under the shared cap.
     *
     * W-0091 / W-0463. Relief used to be a boolean: a qualifying business was
     * worth its full value or nothing, so a £6m trading business left the estate
     * entirely. The £2,500,000 cap has been in force since **2026-04-06** — 100%
     * relief on the first £2.5m of qualifying value and `relief_above_cap` (50%)
     * on the rest — and `TaxConfigService::getBusinessRelief()` held all of it,
     * dated and switched on, with no caller anywhere in the application. On that
     * £6m business the correct answer is £1.75m chargeable, so roughly £700,000 of
     * Inheritance Tax was not being shown.
     *
     * **Why the cap forces an estate-level pass.** It is one allowance covering
     * everything that claims it, so no single asset can decide its own relief.
     *
     * **The allowance is apportioned PRO RATA, and that is mandatory.**
     * IHTA 1984 s124D(7): the 100% allowance is available against all qualifying
     * assets "IN THE SAME PROPORTION AS EACH ASSET REPRESENTS AS PART OF ALL THE
     * QUALIFYING ASSETS in the estate" (IHTM25524). No election, no ordering choice.
     *
     * This took the largest holdings first, on a stated rationale — "the allocation
     * that relieves the most" — that was simply **false**: total relief is
     * `min(cap, total) + max(0, total − cap) × rate`, which is invariant to order.
     * Nothing was being maximised. The order was never visible in the headline tax
     * either, but it IS visible in `is_iht_exempt`, and a wholly-relieved asset is
     * dropped from gross assets — so on two £2m businesses the old order showed
     * £2m of gross assets where pro rata shows £4m, the same net estate reached by
     * a line the user reads differently.
     *
     * **Dated, not hardcoded.** `allowance_cap_effective_date` decides whether the
     * cap applies at all, so a death before 6 April 2026 still gets the old
     * uncapped 100% and the answer follows the configuration rather than the
     * calendar in someone's head.
     *
     * **What this deliberately does not do**, because the data model cannot express
     * it yet — both registered against W-0463:
     *   - **Agricultural Property Relief.** There is no agricultural asset type or
     *     flag anywhere in the schema, so there is nothing to relieve. The cap is
     *     configured as shared between the two reliefs (`cap_shared_with_bpr`), so
     *     when agricultural property does become expressible it must join THIS
     *     allocation rather than get a second cap of its own.
     *   - **AIM shares at 50% outside the cap.** `business_interests` has no column
     *     identifying AIM holdings, so they cannot be distinguished from any other
     *     qualifying business.
     */
    private function applyBusinessPropertyRelief(Collection $assets): Collection
    {
        $config = $this->taxConfig->getBusinessRelief();

        $qualifying = $assets->filter(fn ($asset) => ($asset->iht_relief_qualifies ?? false) === true);

        if ($qualifying->isEmpty()) {
            return $assets;
        }

        $capApplies = (bool) ($config['cap_in_effect'] ?? false)
            && $this->capIsInForce($config['allowance_cap_effective_date'] ?? null);

        $cap = $capApplies ? (float) ($config['allowance_cap'] ?? 0) : INF;
        $rateAboveCap = (float) ($config['relief_above_cap'] ?? 0);

        $totalQualifying = (float) $qualifying->sum('current_value');

        if ($totalQualifying <= 0) {
            return $assets;
        }

        foreach ($qualifying as $asset) {
            $value = (float) $asset->current_value;

            // s124D(7) — each asset's share of the allowance is its share of the
            // qualifying total. Where the cap does not bind at all, every asset is
            // wholly relieved and the proportion is irrelevant.
            $atFullRelief = $cap === INF
                ? $value
                : min($value, $cap * ($value / $totalQualifying));

            $relief = $atFullRelief + (($value - $atFullRelief) * $rateAboveCap);

            $asset->iht_relief_amount = round($relief, 2);
            // Only a wholly relieved asset leaves the estate. A partly relieved one
            // stays, carrying its relief, or the chargeable remainder would vanish
            // with it — the `reject(is_iht_exempt)` consumers take the whole value.
            $asset->is_iht_exempt = round($relief, 2) >= round($value, 2) && $value > 0;
        }

        return $assets;
    }

    /**
     * Whether the capped regime is in force today, per its configured start date.
     *
     * A missing or unparseable date means the cap is NOT applied: the safe default
     * is the treatment that was correct before the cap existed, rather than
     * silently taxing an estate on a date nobody configured.
     */
    private function capIsInForce(?string $effectiveDate): bool
    {
        if ($effectiveDate === null || $effectiveDate === '') {
            return false;
        }

        try {
            return today()->greaterThanOrEqualTo(Carbon::parse($effectiveDate));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Calculate total liabilities for a user
     *
     * Single-record pattern: Query liabilities where user is owner OR joint_owner.
     * Calculate user's share from full value.
     */
    public function calculateUserLiabilities(User $user): float
    {
        // Get liabilities - single-record pattern
        $liabilitiesCollection = Liability::forUserOrJoint($user->id)
            ->get();
        $liabilities = $liabilitiesCollection->sum(function ($liability) use ($user) {
            // Calculate user's share using the trait
            $userShare = $this->calculateUserShare($liability, $user->id);
            \Log::info('Liability: '.($liability->institution ?? 'Unknown').' | Type: '.($liability->type ?? 'Unknown').' | User Share: £'.$userShare);

            return $userShare;
        });

        // Get mortgages - single-record pattern (joint-aware via MortgageStore)
        $mortgages = $this->mortgageStore->forUser($user)
            ->sum(fn ($mortgage) => $this->calculateUserMortgageShare($mortgage, $user->id));

        return $liabilities + $mortgages;
    }

    /**
     * Get mortgages collection for a user
     *
     * Single-record pattern: Returns mortgages where user is owner OR joint_owner.
     */
    public function getUserMortgages(User $user): Collection
    {
        return $this->mortgageStore->forUser($user);
    }

    /**
     * Get liabilities collection for a user
     *
     * Single-record pattern: Returns liabilities where user is owner OR joint_owner.
     */
    public function getUserLiabilities(User $user): Collection
    {
        return Liability::forUserOrJoint($user->id)
            ->get();
    }

    /**
     * The protection cover this user's life carries.
     *
     * **Per-life, not per-household.** This asked `where('user_id', …)`, so a
     * joint-life policy reached only the account that typed it: Sarah Jones was
     * assessed at £0 while David's £500,000 joint-life policy insured her life too,
     * on the one product whose purpose is covering both of them (W-0341). It now
     * routes to `LifeCoverReach`, the one home for "which policies cover this life"
     * (Rule 20) — the same reader `ProtectionAgent`, `ProtectionController` and
     * `LifeInsurancePolicyResource` use, so the four cannot disagree.
     *
     * **Do not add this to a spouse's figure.** The joint-life policy is in both
     * their answers deliberately and pays out once. `LifeCoverReach::householdCoverInTrust()`
     * is the household question, and it stays owner-scoped for that reason.
     *
     * Critical illness stays `user_id`-scoped because it has nothing to reach with:
     * `critical_illness_policies` carries no `joint_life` column, no `joint_owner_id`
     * and no ownership fields — verified against the schema, not inferred. A critical
     * illness policy insures one life by construction here.
     */
    public function getExistingLifeCover(User $user): float
    {
        $lifeInsurance = (float) $this->lifeCoverReach->policiesCovering($user)->sum('sum_assured');

        $criticalIllness = (float) CriticalIllnessPolicy::where('user_id', $user->id)
            ->sum('sum_assured');

        return $lifeInsurance + $criticalIllness;
    }

    /**
     * Get user expenditure data
     */
    public function getUserExpenditure(User $user): array
    {
        // Try ExpenditureProfile first
        $expenditureProfile = ExpenditureProfile::where('user_id', $user->id)->first();
        if ($expenditureProfile) {
            return [
                'monthly_expenditure' => $expenditureProfile->total_monthly_expenditure,
                'annual_expenditure' => $expenditureProfile->total_monthly_expenditure * 12,
            ];
        }

        // Fall back to ProtectionProfile if available
        $protectionProfile = ProtectionProfile::where('user_id', $user->id)->first();
        if ($protectionProfile && $protectionProfile->monthly_expenditure) {
            return [
                'monthly_expenditure' => $protectionProfile->monthly_expenditure,
                'annual_expenditure' => $protectionProfile->monthly_expenditure * 12,
            ];
        }

        // No expenditure data available
        return [
            'monthly_expenditure' => 0,
            'annual_expenditure' => 0,
        ];
    }
}
