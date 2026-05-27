<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Events\Mortgage\MortgageCreated;
use App\Events\Mortgage\MortgageDeleted;
use App\Events\Mortgage\MortgageRestored;
use App\Events\Mortgage\MortgageUpdated;
use App\Models\AuditLog;
use App\Models\Mortgage;
use App\Models\User;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Canonical store for Mortgage entities. Every read and write of
 * App\Models\Mortgage MUST go through this class.
 *
 * Joint-ownership semantics: forUser returns user_id = ? OR joint_owner_id = ?
 * (joint-aware). For primary-only reads, use forUserPrimaryOnly. Pattern locked
 * by MortgageReadConsumerParityTest (PR 5a).
 *
 * Tier-cap key: 'mortgage' (free=10 by default, tier1+=null).
 *
 * Cross-store recalc: every write fires a Mortgage event which triggers
 * PropertyStore::recalculateDerivedForPropertyId. See §4.7 of the Pass 5 plan.
 */
final class MortgageStore
{
    public const ENTITY_KEY = 'mortgage';

    public function __construct(
        private readonly TierGate $tierGate,
    ) {}

    // ─── READ METHODS ──────────────────────────────────────────────────────

    public function find(int $id, User $user): ?Mortgage
    {
        return Mortgage::forUserOrJoint($user->id)->find($id);
    }

    public function forUser(User $user): Collection
    {
        return Mortgage::forUserOrJoint($user->id)->get();
    }

    public function forUserPrimaryOnly(User $user): Collection
    {
        return Mortgage::where('user_id', $user->id)->get();
    }

    public function forUserWithJointOwner(User $user): Collection
    {
        return Mortgage::forUserOrJoint($user->id)->with('jointOwner')->get();
    }

    public function forProperty(int $propertyId, ?User $user = null): Collection
    {
        $query = Mortgage::where('property_id', $propertyId);
        if ($user !== null) {
            $query->forUserOrJoint($user->id);
        }

        return $query->get();
    }

    public function forUserByProperty(User $user): Collection
    {
        return $this->forUserWithJointOwner($user)->groupBy('property_id');
    }

    /**
     * @param  array<int>  $ids
     */
    public function findMany(array $ids, User $user): Collection
    {
        return Mortgage::forUserOrJoint($user->id)->whereIn('id', $ids)->get();
    }

    // ─── WRITE METHODS ─────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $canonical  Output of MortgageNormaliser::from*
     */
    public function create(array $canonical, User $user, IngestSource $source): Mortgage
    {
        $this->validateCanonical($canonical, partial: false);
        $this->enforceTierCap($user);

        return AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($canonical, $user) {
                $mortgage = Mortgage::create($canonical);
                MortgageCreated::dispatch($mortgage, $user->id);

                return $mortgage;
            })
        );
    }

    /**
     * @param  array<string, mixed>  $canonical  Partial — only changed fields
     */
    public function update(Mortgage $mortgage, array $canonical, User $user, IngestSource $source): Mortgage
    {
        if ($mortgage->user_id !== $user->id) {
            throw new RuntimeException('Cannot update a mortgage you do not own (joint owners are read-only)');
        }
        $this->validateCanonical($canonical, partial: true);

        $result = AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($mortgage, $canonical) {
                $mortgage->fill($canonical);
                $dirty = $mortgage->getDirty();
                $changes = [];
                foreach ($dirty as $field => $newValue) {
                    $changes[$field] = [$mortgage->getOriginal($field), $newValue];
                }
                $mortgage->save();

                return ['fresh' => $mortgage->fresh(), 'dirty' => $changes];
            })
        );

        MortgageUpdated::dispatch($result['fresh'], $user->id, $result['dirty']);

        return $result['fresh'];
    }

    /**
     * Find-or-create by (user_id, property_id, lender_name) — idempotent for seeders.
     *
     * @param  array<string, mixed>  $canonical
     */
    public function updateOrCreate(array $canonical, User $user, IngestSource $source): Mortgage
    {
        $this->validateCanonical($canonical, partial: false);

        return AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($canonical, $user) {
                $existing = Mortgage::where('user_id', $user->id)
                    ->where('property_id', $canonical['property_id'])
                    ->where('lender_name', $canonical['lender_name'])
                    ->first();

                if ($existing) {
                    $existing->fill($canonical);
                    $existing->save();
                    MortgageUpdated::dispatch($existing->fresh(), $user->id, []);

                    return $existing->fresh();
                }

                $this->enforceTierCap($user);
                $mortgage = Mortgage::create($canonical);
                MortgageCreated::dispatch($mortgage, $user->id);

                return $mortgage;
            })
        );
    }

    public function delete(Mortgage $mortgage, User $user, IngestSource $source, bool $force = false): void
    {
        if ($mortgage->user_id !== $user->id) {
            throw new RuntimeException('Cannot delete a mortgage you do not own');
        }

        AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($mortgage, $force) {
                $force ? $mortgage->forceDelete() : $mortgage->delete();
            })
        );

        MortgageDeleted::dispatch($mortgage, $user->id, $force);
    }

    public function restore(int $id, User $user, IngestSource $source): Mortgage
    {
        $mortgage = Mortgage::withTrashed()->where('user_id', $user->id)->findOrFail($id);
        if (! $mortgage->trashed()) {
            return $mortgage;
        }

        AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($mortgage) {
                $mortgage->restore();
            })
        );

        MortgageRestored::dispatch($mortgage->fresh(), $user->id);

        return $mortgage->fresh();
    }

    // ─── INTERNAL ──────────────────────────────────────────────────────────

    private function enforceTierCap(User $user): void
    {
        $count = Mortgage::where('user_id', $user->id)->count();

        if (! $this->tierGate->canCreate($user, self::ENTITY_KEY, $count)) {
            throw new TierLimitExceededException(
                self::ENTITY_KEY,
                $count,
                $this->tierGate->hardLimit($user, self::ENTITY_KEY)
            );
        }
    }

    // ─── VALIDATION ────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $canonical
     */
    private function validateCanonical(array $canonical, bool $partial): void
    {
        if (! $partial) {
            foreach (['property_id', 'user_id', 'lender_name', 'mortgage_type', 'outstanding_balance', 'monthly_payment', 'ownership_type', 'ownership_percentage'] as $required) {
                if (! array_key_exists($required, $canonical)) {
                    throw new InvalidArgumentException("Missing required field: {$required}");
                }
            }
        }

        if (isset($canonical['ownership_type']) && ! in_array($canonical['ownership_type'], ['individual', 'joint'], true)) {
            throw new InvalidArgumentException("Invalid ownership_type: {$canonical['ownership_type']} (mortgages do not support tenants_in_common)");
        }

        if (isset($canonical['mortgage_type']) && ! in_array($canonical['mortgage_type'], ['repayment', 'interest_only', 'mixed'], true)) {
            throw new InvalidArgumentException("Invalid mortgage_type: {$canonical['mortgage_type']}");
        }

        if (isset($canonical['outstanding_balance']) && (float) $canonical['outstanding_balance'] < 0) {
            throw new InvalidArgumentException('outstanding_balance must be >= 0');
        }

        if (isset($canonical['ownership_percentage'])) {
            $pct = (float) $canonical['ownership_percentage'];
            if ($pct < 0 || $pct > 100) {
                throw new InvalidArgumentException('ownership_percentage must be between 0 and 100');
            }
        }
    }
}
