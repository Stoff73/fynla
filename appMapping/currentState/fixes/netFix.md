# Net Worth Module Fixes

**Date:** 21 February 2026
**Branch:** `net-worth-fixes`
**Scope:** All actionable issues from NetWorth.md Section 17 (#2–#5, #8–#10). Issues #2 and #3 combined into a single trend removal.

---

## Scope Note

**Not addressed in this round:**

| # | Priority | Issue | Reason |
|---|----------|-------|--------|
| 1 | INFO | No NetWorthAgent | By design -- Net Worth is an aggregation/display layer, not an analytical module |
| 6 | INFO | DB Pensions excluded from net worth | By design -- DB pensions are not accessible capital |
| 7 | INFO | State Pension excluded from net worth | By design -- not capital |

---

## Issues Addressed

### From Section 17 (Known Issues and Limitations)

| # | Priority | Issue | Fix |
|---|----------|-------|-----|
| 2+3 | MEDIUM | Trend data is flat / TrendChart disabled | Remove entire trend pipeline -- backend, frontend, Vuex, API route. Feature is confirmed dead code (see investigation below) |
| 4 | LOW | Joint savings not in `getJointAssets` | Replace TODO empty collection with real `SavingsAccount` query |
| 5 | MEDIUM | Business/chattel changes do not auto-invalidate backend cache | Inject `NetWorthService` into both controllers; call `invalidateCache()` on store/update/destroy |
| 8 | LOW | No soft deletes on business interests or chattels | Add `SoftDeletes` trait and migration |
| 9 | LOW | `ChattelResource` duplicates ownership calculation | Replace inline method with `CalculatesOwnershipShare` trait |
| 10 | LOW | `joint_owner_id` has no FK constraint on business_interests and chattels | Add FK constraint with `ON DELETE SET NULL` after fixing column type |

---

## Changes By File

### New Files

| File | Purpose |
|------|---------|
| `database/migrations/xxxx_add_soft_deletes_to_business_interests_and_chattels.php` | Adds `deleted_at` to both tables |
| `database/migrations/xxxx_add_joint_owner_foreign_keys.php` | Fixes `joint_owner_id` column type and adds FK constraints on `business_interests` and `chattels` |

### Modified Files

| File | Change |
|------|--------|
| `app/Services/NetWorth/NetWorthService.php` | Remove `getNetWorthTrend()` method; implement real joint savings query in `getJointAssets()` |
| `app/Http/Controllers/Api/NetWorthController.php` | Remove `getTrend()` method |
| `routes/api.php` | Remove `GET /api/net-worth/trend` route |
| `resources/js/store/modules/netWorth.js` | Remove `trend` state, `SET_TREND` mutation, `fetchTrend` action, `trendData` getter; remove `fetchTrend` calls from `refreshNetWorth` and `loadAllData` |
| `resources/js/services/netWorthService.js` | Remove `getTrend()` method |
| `app/Services/Estate/NetWorthAnalyzer.php` | Remove `trackNetWorthTrend()` and `saveNetWorthStatement()` methods; remove trend data from `generateSummary()` output |
| `app/Http/Controllers/Api/BusinessInterestController.php` | Inject `NetWorthService`; call `invalidateCache()` on store/update/destroy |
| `app/Http/Controllers/Api/ChattelController.php` | Inject `NetWorthService`; call `invalidateCache()` on store/update/destroy |
| `app/Models/BusinessInterest.php` | Add `SoftDeletes` trait |
| `app/Models/Chattel.php` | Add `SoftDeletes` trait |
| `app/Http/Resources/ChattelResource.php` | Replace inline `calculateUserShare()` with `CalculatesOwnershipShare` trait |

### Deleted Files

| File | Reason |
|------|--------|
| `resources/js/components/NetWorth/NetWorthTrendChart.vue` | Dead component -- never imported or rendered anywhere |
| `app/Models/Estate/NetWorthStatement.php` | Model for table that is never written to; removal is part of trend cleanup |

### Test Files (update or remove)

| File | Change |
|------|--------|
| `resources/js/components/__tests__/NetWorth/NetWorthOverview.spec.js` | Remove trend-related stubs, mocks, and assertions (stale -- test an old version of `NetWorthOverview.vue`) |

---

## Implementation Details

### 2+3. Remove Net Worth Trend Feature (Dead Code Removal)

**Problem:** The trend feature is wired but delivers no value. Investigation confirms:

1. **`getNetWorthTrend()`** (NetWorthService.php:217) is a stub that calls `calculateNetWorth()` N times with the current live data, producing N identical values. The comment in the code says "In production, this would pull from historical snapshots" -- but no snapshots are ever saved.
2. **`saveNetWorthStatement()`** (NetWorthAnalyzer.php:276) is defined but **never called anywhere** in the codebase. The `net_worth_statements` table exists but is always empty.
3. **`NetWorthTrendChart.vue`** is **not imported or registered** in any rendered component. It contains only a hardcoded "Coming Soon" overlay with no conditional rendering -- no chart is ever shown.
4. **Real HTTP requests are wasted:** The Vuex `fetchTrend` action fires on every `loadAllData()` (NetWorthWealthSummary mount) and every `refreshNetWorth()` call. Each request hits `GET /api/net-worth/trend`, runs `calculateNetWorth()` 12 times (once per month), and the response is stored in Vuex state that nothing reads.
5. **`trackNetWorthTrend()`** (NetWorthAnalyzer.php:224) reads the always-empty `net_worth_statements` table and always returns `has_history: false`. Called by `generateSummary()` in the estate flow, producing dead output.
6. **Tests are stale:** `NetWorthOverview.spec.js` tests trend rendering in `NetWorthOverview.vue`, but the current `NetWorthOverview.vue` has no trend code.

**The trend feature is safe to remove because:**
- No user ever sees trend data (chart component is never rendered)
- No other module depends on trend data (no agent, service, or controller reads the Vuex `trendData` getter)
- The `net_worth_statements` table has zero rows (never written to)
- Removing it eliminates 12 redundant `calculateNetWorth()` calls per page load

**Fix -- Backend removals:**

**NetWorthService.php:** Delete `getNetWorthTrend()` method (lines ~217-239).

**NetWorthController.php:** Delete `getTrend()` method (lines ~99-130).

**routes/api.php:** Remove the trend route:

```php
// Remove this line
Route::get('/trend', [NetWorthController::class, 'getTrend']);
```

**NetWorthAnalyzer.php:** Delete `trackNetWorthTrend()` (lines ~224-271) and `saveNetWorthStatement()` (lines ~276-295). In `generateSummary()`, remove the `'net_worth_trend' => $this->trackNetWorthTrend(...)` entry from the returned array.

**NetWorthStatement.php:** Delete the model file. The `net_worth_statements` table can be dropped in a future cleanup migration, but leaving an empty table is harmless and avoids a destructive migration.

**Fix -- Frontend removals:**

**NetWorthTrendChart.vue:** Delete the file entirely. It is not imported anywhere.

**netWorth.js (Vuex store):**
- Remove from state: `trend: []`
- Remove mutation: `SET_TREND`
- Remove action: `fetchTrend`
- Remove getter: `trendData`
- In `refreshNetWorth` action (~line 338): remove `dispatch('fetchTrend')`
- In `loadAllData` action (~line 372): remove `dispatch('fetchTrend')`

**netWorthService.js:** Remove `getTrend()` method (lines ~26-31).

**NetWorthOverview.spec.js:** Remove trend-related stubs and assertions. The test references `NetWorthTrendChart` (stubbed), `fetchTrend` (mocked action), and `trend` state -- all of which will no longer exist.

---

### 4. Joint Savings in `getJointAssets`

**Problem:** `NetWorthService::getJointAssets()` has a TODO stub at lines 529-532 that returns an empty collection for savings:

```php
// Implementation pending: Query SavingsAccount model with ownership_type filter
$cashAccounts = collect([]);
```

All other asset types (properties, investments, businesses, chattels) perform real queries. The `SavingsAccount` model already has `joint_owner_id`, `ownership_type`, `ownership_percentage`, a `jointOwner()` relationship, and the `HasJointOwnership` trait -- everything is in place.

**Fix:** Replace the stub with a real query matching the pattern used by the other asset types:

```php
$cashAccounts = SavingsAccount::where('user_id', $userId)
    ->where('ownership_type', 'joint')
    ->get()
    ->map(function ($account) {
        return [
            'type' => 'savings',
            'id' => $account->id,
            'description' => trim(($account->institution ?? '') . ' - ' . ($account->account_type ?? ''), ' - '),
            'value' => (float) $account->current_balance,
            'ownership_percentage' => (float) ($account->ownership_percentage ?? 50),
            'co_owner' => $account->jointOwner ? $account->jointOwner->name : null,
        ];
    });
```

`SavingsAccount` is already imported in `NetWorthService` (line 15). The query follows the exact pattern used for properties (line 500), investments (line 515), businesses (line 535), and chattels (line 550).

---

### 5. Backend Cache Invalidation for Business Interests and Chattels

**Problem:** When business interest or chattel records are created/updated/deleted, the frontend Vuex stores DO dispatch `refreshNetWorth` (confirmed in `businessInterests.js` lines 79/98/117 and `chattels.js` lines 86/105/124). However, `BusinessInterestController.php` and `ChattelController.php` have **no direct calls** to `NetWorthService::invalidateCache()`.

This means if the API is called outside the Vuex flow (direct API call, admin tool, future mobile client), the backend 30-minute cache (`Cache::remember($cacheKey, 1800, ...)` in `getCachedNetWorth()`) serves stale data.

By contrast, `SavingsController` calls `$this->netWorthService->invalidateCache($user->id)` on every mutating action (lines 231, 337, 342, 390, 395, 436, 440), including busting the joint owner's cache when applicable.

**Fix:** Inject `NetWorthService` into both controllers and call `invalidateCache()` on each mutation.

**BusinessInterestController.php:**

```php
use App\Services\NetWorth\NetWorthService;

class BusinessInterestController extends Controller
{
    public function __construct(
        private NetWorthService $netWorthService
    ) {}
```

At the end of `store()`, `update()`, and `destroy()`:

```php
$this->netWorthService->invalidateCache($user->id);

// Also invalidate joint owner's cache if applicable
if ($business->joint_owner_id) {
    $this->netWorthService->invalidateCache($business->joint_owner_id);
}
```

**ChattelController.php:** Same pattern.

---

### 8. Soft Deletes for Business Interests and Chattels

**Problem:** `BusinessInterest` and `Chattel` models perform hard deletes. Financial records permanently destroyed with no recovery window.

**Fix:**

**Migration:**

```php
Schema::table('business_interests', function (Blueprint $table) {
    $table->softDeletes();
});

Schema::table('chattels', function (Blueprint $table) {
    $table->softDeletes();
});
```

**Model changes:**

```php
// BusinessInterest.php
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessInterest extends Model
{
    use Auditable, HasFactory, HasJointOwnership, SoftDeletes;
    // ...
}

// Chattel.php
use Illuminate\Database\Eloquent\SoftDeletes;

class Chattel extends Model
{
    use Auditable, HasFactory, HasJointOwnership, SoftDeletes;
    // ...
}
```

No controller changes needed -- Eloquent automatically excludes soft-deleted records from queries. The `destroy()` methods in both controllers will now soft-delete instead of hard-delete.

---

### 9. ChattelResource Ownership Calculation

**Problem:** `ChattelResource` (lines 91-120) has a private `calculateUserShare(?int $userId): float` method that duplicates the logic in the `CalculatesOwnershipShare` trait. The two implementations differ subtly:
- Resource defaults `ownership_percentage` to `50` when null
- Trait defaults to `100`, then overrides to `50` for joint types via `$percentage !== 100.0 ? $percentage : 50.0`

Both reach the same result for joint assets with null percentage, but via different code paths. If the canonical trait is ever updated (e.g., adding `tenants_in_common`-specific behaviour), the resource's copy will silently diverge.

**Fix:** Remove the private method and use the shared trait:

```php
use App\Traits\CalculatesOwnershipShare;

class ChattelResource extends JsonResource
{
    use CalculatesOwnershipShare;

    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;

        return [
            // ... existing fields ...
            'user_share' => $userId ? $this->calculateUserShare($this->resource, $userId) : 0.0,
            // ... existing fields ...
        ];
    }

    // Delete the private calculateUserShare() method entirely
}
```

The trait's `calculateUserShare(object $asset, int $userId)` takes the model as the first argument. In a `JsonResource`, `$this->resource` provides the underlying Eloquent model.

---

### 10. Foreign Key Constraints on `joint_owner_id`

**Problem:** `business_interests.joint_owner_id` and `chattels.joint_owner_id` are `bigint` (signed) with no FK constraint. The `users.id` column is `bigint unsigned`. Two issues:

1. **Type mismatch:** `bigint` vs `bigint unsigned` would cause MySQL to reject a standard FK constraint
2. **No referential integrity:** If a joint owner user is deleted, the `joint_owner_id` holds a dangling integer. Queries loading `jointOwner()` return `null` silently, and net worth calculations using `$asset->joint_owner_id === $userId` never match, effectively transferring the joint owner's share to nobody.

**Note:** The investigation found this is a systemic issue across `properties` and `savings_accounts` too. This fix addresses `business_interests` and `chattels` as scoped. The other tables can be addressed in their respective module fixes.

**Fix:**

```php
// Migration: add FK constraints to business_interests and chattels
Schema::table('business_interests', function (Blueprint $table) {
    // Fix column type to match users.id (bigint unsigned)
    $table->unsignedBigInteger('joint_owner_id')->nullable()->change();

    // Add FK with SET NULL on delete (joint owner removed, asset stays)
    $table->foreign('joint_owner_id')
        ->references('id')
        ->on('users')
        ->onDelete('set null');
});

Schema::table('chattels', function (Blueprint $table) {
    $table->unsignedBigInteger('joint_owner_id')->nullable()->change();

    $table->foreign('joint_owner_id')
        ->references('id')
        ->on('users')
        ->onDelete('set null');
});
```

**`ON DELETE SET NULL` rationale:** When a joint owner is deleted, the asset should remain (it belongs to the primary owner via `user_id`). Setting `joint_owner_id` to `null` converts it to a sole-ownership asset rather than orphaning the reference.

**Requires:** `doctrine/dbal` package for column type change via `->change()`. Already in the project's `composer.json`.

---

## Testing Requirements

| Fix | Test |
|-----|------|
| 2+3. Trend removal | Verify `GET /api/net-worth/trend` returns 404; verify `loadAllData()` in Vuex no longer fires trend request; verify `refreshNetWorth` no longer fires trend request; verify estate `generateSummary()` output no longer has `net_worth_trend` key |
| 4. Joint savings | Seed a joint savings account; verify `getJointAssets()` returns it with correct ownership percentage and co-owner name |
| 5. Cache invalidation | Create a business interest via API (not Vuex); verify net worth cache is busted; create a joint chattel; verify both owner and joint owner caches are busted |
| 8. Soft deletes | Delete a business interest; verify it's excluded from queries; verify `withTrashed()` can recover it; same for chattels |
| 9. ChattelResource | Verify `user_share` matches trait calculation for individual, joint, and tenants-in-common chattels |
| 10. FK constraints | Delete a user who is a joint owner; verify `joint_owner_id` is set to null on associated assets (not orphaned) |

---

## Implementation Order

| Order | Fix | Reason |
|-------|-----|--------|
| 1 | #8 Soft deletes | Foundation change, migration needed before other changes |
| 2 | #2+3 Trend removal | Largest change by file count but entirely deletions; eliminates wasted HTTP requests |
| 3 | #5 Cache invalidation | Important for data consistency; small change |
| 4 | #4 Joint savings | Single method change, fills a TODO stub |
| 5 | #9 ChattelResource | Small refactor, no functional impact |
| 6 | #10 FK constraints | Migration with column type change; test carefully for existing data |
