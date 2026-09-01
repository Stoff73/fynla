<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Constants\ValidationLimits;
use App\Events\Investment\InvestmentAccountCreated;
use App\Events\Investment\InvestmentAccountDeleted;
use App\Events\Investment\InvestmentAccountRestored;
use App\Events\Investment\InvestmentAccountUpdated;
use App\Models\AuditLog;
use App\Models\Investment\InvestmentAccount;
use App\Models\InvestmentAccountValueSnapshot;
use App\Models\User;
use App\Services\Savings\ISAContributionLedger;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\Snapshots\SnapshotPolicies;
use App\Services\Tiers\TeaserGate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Canonical store for InvestmentAccount entities. Every read and write of
 * App\Models\Investment\InvestmentAccount MUST go through this class.
 *
 * Joint-ownership semantics: forUser returns user_id = ? OR joint_owner_id = ?
 * (joint-aware). For primary-only reads, use forUserPrimaryOnly.
 *
 * Tier-cap key: 'investment' (Free=2 by default, Premium=null).
 */
class InvestmentAccountStore
{
    public const ENTITY_KEY = 'investment';

    /**
     * The account types the `investments_exotic` capability covers — the ONE place
     * that capability is defined (W-0499).
     *
     * It was in the tier matrix, in the upgrade copy at `PaymentController:116`
     * ("Advanced investment types") and in the pricing comparison at
     * `TierComparisonService:22` ("Alternative investments"), and **nothing in the
     * application read it**: no route map entry, no gate, no store check. A feature
     * named in a paid tier's differentiators and usable without paying is a
     * commercial problem, not only an untidy one.
     *
     * The membership is the definition recorded on the persona playbook — Venture
     * Capital Trust and Enterprise Investment Scheme — and nothing beyond it, so
     * this gates what was sold rather than what someone later guessed was meant.
     * `tax_relief_type` is read alongside, because the relief is the thing being
     * claimed and it can be attached to an account of any type;
     * `EstateController:148` already reads the pair the same way.
     */
    public const EXOTIC_ACCOUNT_TYPES = ['vct', 'eis'];

    public const EXOTIC_TAX_RELIEF_TYPES = ['vct', 'eis', 'seis', 'sitr'];

    public function __construct(
        private readonly TierGate $tierGate,
        private readonly SnapshotPolicies $snapshotPolicies,
        private readonly ISAContributionLedger $isaContributionLedger,
        private readonly TeaserGate $teaserGate,
    ) {}

    // ─── READ METHODS ──────────────────────────────────────────────────────

    public function find(int $id, User $user): ?InvestmentAccount
    {
        return InvestmentAccount::forUserOrJoint($user->id)->find($id);
    }

    public function forUser(User $user): Collection
    {
        return InvestmentAccount::forUserOrJoint($user->id)->get();
    }

    public function forUserPrimaryOnly(User $user): Collection
    {
        return InvestmentAccount::where('user_id', $user->id)->get();
    }

    public function forUserWithJointOwner(User $user): Collection
    {
        return InvestmentAccount::forUserOrJoint($user->id)->with('jointOwner')->get();
    }

    public function forUserByType(User $user, string $accountType): Collection
    {
        return InvestmentAccount::forUserOrJoint($user->id)
            ->where('account_type', $accountType)
            ->get();
    }

    /**
     * @param  array<int>  $ids
     */
    public function findMany(array $ids, User $user): Collection
    {
        return InvestmentAccount::forUserOrJoint($user->id)->whereIn('id', $ids)->get();
    }

    // ─── WRITE METHODS ─────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $canonical  Output of InvestmentAccountNormaliser::from*
     */
    public function create(array $canonical, User $user, IngestSource $source): InvestmentAccount
    {
        // The store owns user_id at this write boundary: force it to the acting
        // user so a caller's canonical can never persist a foreign user_id, and so
        // the updateOrCreate path (raw match+data, no user_id) satisfies
        // validateCanonical. Mirrors SavingsStore/PensionStore.
        $canonical['user_id'] = $user->id;

        $this->validateCanonical($canonical, partial: false);
        $this->enforceTierCap($user);
        $this->enforceExoticCapability($canonical, $user);

        $account = AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($canonical, $source) {
                $account = InvestmentAccount::create($canonical);
                $this->writeValueSnapshot($account, null, $source, 'create');
                $this->isaContributionLedger->syncInvestmentAnnualSummary($account, $source);

                return $account;
            })
        );

        event(new InvestmentAccountCreated($account, $user, $source));

        return $account;
    }

    /**
     * @param  array<string, mixed>  $canonical  Partial — only changed fields
     */
    public function update(int $id, array $canonical, User $user, IngestSource $source): InvestmentAccount
    {
        $account = InvestmentAccount::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $this->validateCanonical($canonical, partial: true);

        $result = AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($account, $canonical, $source) {
                $oldValue = $account->current_value === null ? null : (float) $account->current_value;
                $account->fill($canonical);
                $changes = [];
                foreach ($account->getDirty() as $field => $newValue) {
                    $changes[$field] = [$account->getOriginal($field), $newValue];
                }
                $account->save();
                if (array_key_exists('current_value', $changes)) {
                    $this->writeValueSnapshot($account, $oldValue, $source, 'update');
                }
                $this->isaContributionLedger->syncInvestmentAnnualSummary($account, $source);

                return ['fresh' => $account->fresh(), 'changes' => $changes];
            })
        );

        event(new InvestmentAccountUpdated($result['fresh'], $result['changes'], $user, $source));

        return $result['fresh'];
    }

    /**
     * Find-or-create scoped to the user, matched on the caller-supplied $match
     * keys — idempotent for seeders. The explicit match (vs a hardcoded
     * account_name match) is required because account_name is nullable: legacy
     * rows seeded before account_name existed carry NULL, so a hardcoded
     * account_name match would never find them and would insert a duplicate on
     * reseed. Mirrors SavingsStore::updateOrCreate / PensionStore::updateOrCreateDc.
     *
     * @param  array<string, mixed>  $match  Scoping keys (user_id is always added)
     * @param  array<string, mixed>  $data  Values to set on match or create
     */
    public function updateOrCreate(array $match, array $data, User $user, IngestSource $source): InvestmentAccount
    {
        $existing = InvestmentAccount::where('user_id', $user->id)
            ->where($match)
            ->first();

        if ($existing) {
            return $this->update($existing->id, $data, $user, $source);
        }

        return $this->create(array_merge($match, $data), $user, $source);
    }

    public function delete(int $id, User $user, IngestSource $source, bool $force = false): void
    {
        $account = InvestmentAccount::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($account, $force) {
                $force ? $account->forceDelete() : $account->delete();
            })
        );

        event(new InvestmentAccountDeleted($account, $user, $source, $force));
    }

    public function restore(int $id, User $user, IngestSource $source): InvestmentAccount
    {
        $account = InvestmentAccount::withTrashed()->where('user_id', $user->id)->findOrFail($id);
        if (! $account->trashed()) {
            return $account;
        }

        AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($account) {
                $account->restore();
            })
        );

        $fresh = $account->fresh();
        event(new InvestmentAccountRestored($fresh, $user, $source));

        return $fresh;
    }

    // ─── INTERNAL ──────────────────────────────────────────────────────────

    /**
     * Refuse an exotic holding to a tier that was never sold one (W-0499).
     *
     * Enforced **here** rather than on a route, because `investments_exotic` is not
     * a destination — it is a property of the record being written, and web, `/m`
     * and Fyn all write through this Store and through nothing else. A route gate
     * would have to be added three times and would still miss Fyn.
     *
     * Before entry in effect, since the Store refuses before anything persists, and
     * the refusal names the capability so the client can offer the upgrade rather
     * than reporting a generic failure.
     *
     * **Create only, deliberately.** Nothing gated this until now, so a Free user
     * may already hold such an account; refusing an update would strand a record
     * they cannot then correct or delete. Switching the gate on takes nothing away
     * from anyone.
     */
    private function enforceExoticCapability(array $canonical, User $user): void
    {
        $isExotic = in_array($canonical['account_type'] ?? null, self::EXOTIC_ACCOUNT_TYPES, true)
            || in_array($canonical['tax_relief_type'] ?? null, self::EXOTIC_TAX_RELIEF_TYPES, true);

        if (! $isExotic || $this->teaserGate->allows($user, 'investments_exotic')) {
            return;
        }

        throw new TierLimitExceededException(
            'investments_exotic',
            0,
            0,
            'Advanced investment types are a Premium feature.'
        );
    }

    private function enforceTierCap(User $user): void
    {
        $count = InvestmentAccount::where('user_id', $user->id)->count();

        if (! $this->tierGate->canCreate($user, self::ENTITY_KEY, $count)) {
            throw new TierLimitExceededException(
                self::ENTITY_KEY,
                $count,
                $this->tierGate->hardLimit($user, self::ENTITY_KEY)
            );
        }
    }

    private function writeValueSnapshot(
        InvestmentAccount $account,
        ?float $oldValue,
        IngestSource $source,
        string $reason,
    ): void {
        $newValue = $account->current_value === null ? null : (float) $account->current_value;
        if ($newValue === null
            || ! $this->snapshotPolicies->investmentAccountValue()->shouldSnapshot($oldValue, $newValue)) {
            return;
        }

        InvestmentAccountValueSnapshot::query()->create([
            'investment_account_id' => $account->id,
            'column_name' => 'current_value_gbp',
            'value' => $newValue,
            'currency' => 'GBP',
            'value_gbp' => $newValue,
            'taken_at' => now(),
            'trigger_reason' => $reason,
            'ingest_source' => $source->value,
        ]);
    }

    // ─── VALIDATION ────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $canonical
     */
    private function validateCanonical(array $canonical, bool $partial): void
    {
        $req = $partial ? 'sometimes|' : 'required|';

        $rules = [
            'user_id' => $req.'integer|exists:users,id',
            'account_name' => 'sometimes|nullable|string|max:255',
            'account_type' => $req.'in:isa,gia,nsi,onshore_bond,offshore_bond,vct,eis,private_company,crowdfunding,saye,csop,emi,unapproved_options,rsu,other',
            // `trust` added 2026-08-26 (W-0329). The column stores it, both
            // Store/UpdateInvestmentAccountRequest permit it, and the two sibling
            // Stores on the same un-normalised Fyn update path — SavingsStore:315
            // and LiabilityStore:135 — allow the full set. This Store was the only
            // layer refusing it, so a trust-owned account could be recorded through
            // the web request and not through Fyn.
            //
            // `tenants_in_common` stays out: investment_accounts genuinely has no
            // such enum value, and InvestmentAccountNormaliser coerces TIC to joint
            // at the boundary. That exclusion is the documented decision; `trust`
            // was collateral from the same catch-all.
            'ownership_type' => $req.'in:individual,joint,trust',
            'ownership_percentage' => ($partial ? 'sometimes|' : 'required|').ValidationLimits::percentageRules(false),
            'current_value' => 'sometimes|nullable|'.ValidationLimits::currencyRules(false),
            'provider' => 'sometimes|nullable|string|max:255',
            'country' => 'sometimes|nullable|string|max:255',
            'isa_type' => 'sometimes|nullable|in:stocks_and_shares,lifetime,innovative_finance',
            'contributions_ytd' => 'sometimes|nullable|'.ValidationLimits::currencyRules(false),
            'monthly_contribution_amount' => 'sometimes|nullable|'.ValidationLimits::currencyRules(false),
            'joint_owner_id' => 'sometimes|nullable|integer|exists:users,id',
            // W-0042 — a shared record may name an off-platform co-owner, the same
            // way properties, mortgages and chattels already can (W-0025).
            'joint_owner_name' => 'sometimes|nullable|string|max:255',

            // W-0505: enum columns this ruleset never listed. Each list is the
            // column's own enum, so nothing the table would have stored is now
            // refused — the change is that an impossible value is caught here,
            // named, instead of arriving at MySQL as an unattributable error.
            // The first three are NOT NULL with defaults, and their nulls are
            // already dropped by InvestmentAccountNormaliser::NOT_NULL_WITH_DEFAULT.
            'contribution_frequency' => 'sometimes|nullable|in:monthly,quarterly,annually',
            'platform_fee_type' => 'sometimes|nullable|in:percentage,fixed',
            'platform_fee_frequency' => 'sometimes|nullable|in:monthly,quarterly,annually',
            'risk_preference' => 'sometimes|nullable|in:low,lower_medium,medium,upper_medium,high',

            // The sixth axis (W-0329): bounds the form enforces and this Store did
            // not. All nine are columns on `investment_accounts` and all nine are
            // bounded by Store/UpdateInvestmentAccountRequest, but this ruleset had
            // no rule for any of them — and `validateCanonical` runs
            // `Validator::make()` without `validated()`, so an unruled key is not
            // dropped, it is written. Fyn and `/m` write through here and through
            // nothing else, so a 12% platform fee was a 422 on the web form and a
            // successful save through Fyn. Mirrors the request exactly rather than
            // inventing a bound.
            'platform_fee_percent' => 'sometimes|nullable|numeric|min:0|max:10',
            'advisor_fee_percent' => 'sometimes|nullable|numeric|min:0|max:10',
            'interest_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'current_ownership_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'cliff_percentage' => 'sometimes|nullable|integer|min:0|max:100',
            'performance_vesting_min_percent' => 'sometimes|nullable|integer|min:0|max:100',
            'performance_vesting_max_percent' => 'sometimes|nullable|integer|min:0|max:100',
            'saye_monthly_savings' => 'sometimes|nullable|numeric|min:0|max:500',
            'saye_option_discount_percent' => 'sometimes|nullable|numeric|min:0|max:20',
        ];

        $validator = Validator::make($canonical, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }
}
