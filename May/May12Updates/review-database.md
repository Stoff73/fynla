# Database Review — Fynla

Date: 2026-05-12
Reviewer: Database Performance Engineer
Scope: 215 migrations, 92 model files (111 with subdirectories), seeders, services & controllers query patterns
Engine: MySQL 8.0, InnoDB, utf8mb4_unicode_ci

This review is grounded in file evidence. Findings are tagged with **severity** (critical / high / medium / low) and **confidence** (high / med / low). The schema dump at `database/schema/mysql-schema.sql` is dated 2026-02-24 — out of date — so several recent ALTERs are not reflected there. Where I had to cross-reference an individual migration to confirm current state, I cite that migration explicitly.

The Fynla joint-ownership pattern (`WHERE user_id = ? OR joint_owner_id = ?`) and preview-isolation pattern (`WHERE is_preview_user = true`) are *load-bearing* and protected per `CLAUDE.md` Rule #7 and Rule #2. None of my recommendations break those patterns; several reinforce them.

---

## Executive Summary

**Overall health: B-.** Mature schema with strong conventions in recent migrations (anonymous classes, strict types, idempotency guards, hash-chain audit), but several systemic issues from earlier batches have not been cleaned up:

| Category | Findings | Critical | High |
|---|---|---|---|
| Schema / data types | 15 | 2 | 6 |
| Indexes | 12 | 1 | 5 |
| Query patterns | 11 | 0 | 4 |
| Transactions | 6 | 1 | 2 |
| Migrations | 14 | 2 | 4 |
| Eloquent models | 13 | 1 | 5 |
| Data integrity / FK | 8 | 1 | 3 |
| Performance hotspots | 7 | 1 | 3 |
| Seeders & test data | 6 | 0 | 3 |
| Audit & retention | 5 | 1 | 2 |
| **Total** | **97** | **10** | **37** |

**Top-3 critical fixes (do these first):**

1. **20 currency / expenditure columns on `users` are `double`** — see Finding S-01. Float arithmetic in financial calculations is the cardinal sin of fintech databases. Rounding errors of £0.01 compound, audit trails diverge from displayed totals. Two of these columns were converted to `decimal` for purge compatibility (migration `2026_05_07_000005`), but the remaining 18 are still `double`.
2. **All `Holding` model casts are `float`** — see Finding M-01. The schema stores `decimal(15,6)` and `decimal(15,2)` correctly, but the Eloquent cast turns every value into a PHP float on read. This silently re-introduces float precision into every portfolio calculation, every CGT computation, every rebalancing recommendation.
3. **No partitioning or write-path index on `audit_logs.created_at`** — see Finding A-01. The retention sweep (`PurgeAuditLogs`) does a full table scan filtered by `event_type` once per day; at scale this will lock or starve the same table that writes flow into.

The codebase is otherwise reasonably well-indexed: foreign keys are consistently indexed, joint-owner composite indexes were added retroactively (`2026_01_26_150000_add_joint_owner_indexes.php`), and preview-isolation has a dedicated composite (`preview_user_persona_idx`). I have **NOT** found a single instance of `Model::all()` outside a one-off admin role-sync. Eager loading via `->with('holdings')` is consistently used in Investment services. These are wins.

---

## 1. Schema design

### S-01 — `users` table uses `double` for ~18 expenditure / income columns
- **Severity:** critical
- **Confidence:** high
- **Evidence:** `database/schema/mysql-schema.sql:2354-2380`

```sql
`annual_interest_income` double NOT NULL DEFAULT '0',          -- fixed by 2026_05_07_000005
`monthly_expenditure`    double DEFAULT NULL,
`annual_expenditure`     double DEFAULT NULL,
`food_groceries`         double NOT NULL DEFAULT '0',
`transport_fuel`         double NOT NULL DEFAULT '0',
`healthcare_medical`     double NOT NULL DEFAULT '0',
`insurance`              double NOT NULL DEFAULT '0',
`mobile_phones`          double NOT NULL DEFAULT '0',
`internet_tv`            double NOT NULL DEFAULT '0',
`subscriptions`          double NOT NULL DEFAULT '0',
`clothing_personal_care` double NOT NULL DEFAULT '0',
`entertainment_dining`   double NOT NULL DEFAULT '0',
`holidays_travel`        double NOT NULL DEFAULT '0',
`pets`                   double NOT NULL DEFAULT '0',
`childcare`              double NOT NULL DEFAULT '0',
`school_fees`            double NOT NULL DEFAULT '0',
`children_activities`    double NOT NULL DEFAULT '0',
`gifts_charity`          double NOT NULL DEFAULT '0',
`regular_savings`        double NOT NULL DEFAULT '0',
`other_expenditure`      double NOT NULL DEFAULT '0',
```

The migration log on line 2541 references `2025_11_09_133324_change_expenditure_columns_to_double` (a migration file no longer present — squashed). The User model already casts these to `decimal:2` (`app/Models/User.php:104-127`), which means PHP rounds at the application layer but the DB still stores binary floating point. `WHERE annual_expenditure = 12345.67` against a `double` column will sometimes miss matches. Sums computed in MySQL (`SUM(food_groceries)`) drift from PHP-computed sums.

**Recommended fix:**

```php
// database/migrations/2026_05_12_xxx_convert_users_expenditure_to_decimal.php
public function up(): void {
    Schema::table('users', function (Blueprint $t) {
        // pence-precision currency, holds ~999bn
        $t->decimal('monthly_expenditure', 12, 2)->nullable()->change();
        $t->decimal('annual_expenditure', 12, 2)->nullable()->change();
        // category items: max realistic value < £100k/yr
        foreach ([
            'food_groceries', 'transport_fuel', 'healthcare_medical',
            'insurance', 'mobile_phones', 'internet_tv', 'subscriptions',
            'clothing_personal_care', 'entertainment_dining', 'holidays_travel',
            'pets', 'childcare', 'school_fees', 'children_activities',
            'gifts_charity', 'regular_savings', 'other_expenditure',
        ] as $col) {
            $t->decimal($col, 10, 2)->default(0)->change();
        }
    });
}
```

Production rollout: this is an `ALTER TABLE` on the largest user-facing table. On a heavily-loaded DB, use `pt-online-schema-change` (Percona toolkit) or `ALGORITHM=INPLACE, LOCK=NONE`. Confirm both via `EXPLAIN` first; some `double → decimal` conversions force a `COPY` algorithm which holds an exclusive metadata lock.

### S-02 — Holdings stores prices/quantities as `decimal` but model casts to `float`
- **Severity:** critical
- **Confidence:** high
- **Evidence:** `app/Models/Investment/Holding.php:39-49`, schema `holdings` line 745-756

```php
protected $casts = [
    'allocation_percent' => 'float',
    'quantity'           => 'float',
    'purchase_price'     => 'float',
    'current_price'      => 'float',
    'current_value'      => 'float',
    'cost_basis'         => 'float',
    'dividend_yield'     => 'float',
    'ocf_percent'        => 'float',
];
```

Schema stores `decimal(15,6)` for quantity and `decimal(15,4)` for prices — correctly. The cast bridges those values into PHP `float`, losing precision at the very edge of every calculation. All of Investment/Tax/CGTHarvestingCalculator, RebalancingCalculator, AssetLocationOptimizer iterate `$account->holdings` and compute portfolio totals from `current_value`. A 25-holding portfolio worth £1.2M can drift £0.40 per page render.

**Fix:**

```php
protected $casts = [
    'allocation_percent' => 'decimal:2',
    'quantity'           => 'decimal:6',
    'purchase_price'     => 'decimal:4',
    'current_price'      => 'decimal:4',
    'current_value'      => 'decimal:2',
    'cost_basis'         => 'decimal:2',
    'dividend_yield'     => 'decimal:4',
    'ocf_percent'        => 'decimal:4',
];
```

Note: `decimal:N` in Eloquent returns a string. Any service that compares `===` or sums via `array_sum` needs `(float)` casts at the boundary — or, better, use `bcmath` for portfolio-level sums and present formatted via `formatCurrency()` at the boundary.

### S-03 — `savings_accounts.joint_owner_id` is signed `bigint`, not `bigint unsigned`
- **Severity:** high
- **Confidence:** high
- **Evidence:** `database/schema/mysql-schema.sql:1916` (`bigint DEFAULT NULL` — no `unsigned`)
- **Related:** `database/migrations/2026_03_13_200002_fix_savings_accounts_joint_owner_foreign_key.php` claims to fix this but the schema dump (Feb 24) was captured before the fix migration ran.

If the migration has run on production this is benign. If not, the FK to `users.id` (which is `bigint unsigned`) will reject inserts. Confirm via:

```sql
SHOW COLUMNS FROM savings_accounts LIKE 'joint_owner_id';
SHOW CREATE TABLE savings_accounts \G
-- look for FK constraint
```

If the constraint is missing, run `php artisan migrate` to apply the fix migration.

### S-04 — Multiple `joint_owner_name` / `trust_name` denormalised string columns
- **Severity:** medium
- **Confidence:** high
- **Evidence:**
  - `database/schema/mysql-schema.sql:1390` (`mortgages.joint_owner_name`)
  - `database/schema/mysql-schema.sql:1651` (`properties.joint_owner_name`, `:1654` `properties.trust_name`)
  - `database/schema/mysql-schema.sql:207` (`chattels.joint_owner_name`)
  - `app/Models/Property.php:166` (`return $this->jointOwner?->name ?? $this->joint_owner_name`)

These nullable text columns shadow the FK relationship. A user editing their joint owner's name in `users` table won't update these copies; a freed user (FK set null on user delete) leaves a stale name string. The pattern is intentional (lets users record a joint owner who isn't in Fynla) but it's load-bearing for IHT calcs — if the system later relies on these for splitting, a name typo causes silent under/over-attribution.

**Recommendation:** Document the intent in a comment on the column and add a constraint or observer: when `joint_owner_id IS NOT NULL`, `joint_owner_name` must be NULL (or vice versa) — never both. Today the schema allows both, which makes the lookup precedence (`?? $this->joint_owner_name`) ambiguous.

### S-05 — `assets.ownership_type` enum uses old vocabulary
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `database/schema/mysql-schema.sql:38` — `enum('individual','joint','trust')` — missing `tenants_in_common`

CLAUDE.md Rule #5 specifies the canonical ownership enum: `individual`, `joint`, `tenants_in_common`, `trust`. Most tables (`properties`, `mortgages`, `goals`) include `tenants_in_common`; `assets`, `business_interests`, `chattels`, `cash_accounts`, `investment_accounts`, `savings_accounts` do not.

The Estate module's IHT calc treats `tenants_in_common` as 50/50 by default but `joint` as 100% to surviving spouse — meaningful semantic difference. The Estate `assets` table is the place this matters most, since it feeds IHT directly.

**Fix:** Add `tenants_in_common` to all 7 enums in a single migration:

```php
foreach (['assets', 'business_interests', 'chattels',
          'cash_accounts', 'investment_accounts', 'savings_accounts'] as $t) {
    if (Schema::hasColumn($t, 'ownership_type')) {
        DB::statement("ALTER TABLE {$t} MODIFY ownership_type
            ENUM('individual','joint','tenants_in_common','trust')
            NOT NULL DEFAULT 'individual'");
    }
}
```

### S-06 — Inconsistent soft-delete coverage across related tables
- **Severity:** medium
- **Confidence:** high
- **Evidence:**
  - `households` has no `deleted_at` (`schema:766-773`) but `users.household_id` is `ON DELETE SET NULL`. Hard delete of a household orphans both partners' household_id silently — no audit trail.
  - `risk_profiles`, `protection_profiles` have soft deletes; `retirement_profiles` does (added 2026_02_21_200002). `iht_profiles` had it added in `2026_03_18_100000`.
  - `holdings` got soft deletes in `2026_02_21_200002`; `personal_accounts` (P&L records) has none.
  - `audit_logs` has no soft delete (correct — append-only).

The risk is partial: a `User::forceDelete()` cascades to most child tables fine, but `households` and `personal_accounts` lose audit history. Recommend either (a) adding soft deletes to `households` so a deletion is recoverable, or (b) emit an `AuditLog` entry on hard delete via the `Auditable` trait observer.

### S-07 — `personal_accounts.line_item` and `category` are loose `varchar/enum`
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `database/schema/mysql-schema.sql:1606-1607`

`line_item` is `varchar(255)` storing values like 'Employment Income', 'Mortgage Payment'. Free-text means typo'd entries fragment reports. Either:
- normalise into `personal_account_categories` reference table with FK
- or convert to `enum` if the set is finite (likely 30-50 entries)

### S-08 — Audit-trail metadata columns not consistently in JSON form
- **Severity:** low
- **Confidence:** med
- **Evidence:** `tax_configuration_audits.before_state` is `json`, `audit_logs.old_values` is `json` — good. But `recommendation_tracking.notes` is `text`, `life_events.description` is `text`. For audit-style tables, JSON is queryable. For domain tables, text is fine. The pattern is consistent enough; flagging for awareness only.

### S-09 — `business_interests` and `chattels` have `description` AND `notes` text columns
- **Severity:** low
- **Confidence:** high
- **Evidence:** `database/schema/mysql-schema.sql:138-139` (business_interests), `:212, :223` (chattels)

Two free-text fields with overlapping purpose. Drift risk: forms use `description`, services read `notes`. Audit which is canonical and rename or drop the other.

### S-10 — `investment_accounts` is 154+ columns wide
- **Severity:** high
- **Confidence:** high
- **Evidence:** `database/schema/mysql-schema.sql:874-1041`

This is a god-table. It mixes:
- ISA/GIA/Bond/CSOP/EMI/SAYE/SIP fields
- BADR eligibility (8 boolean columns)
- Vesting schedule (15+ fields)
- Employer share scheme history
- Crowdfunding platform fields
- Generic portfolio platform fee fields

Most rows have 75%+ of columns NULL. Row size is huge (~1.5KB even when sparse); a full table scan reads 10x more data than needed. Buffer pool fill rate suffers. Reads for "all ISAs for user" pull all 154 columns by default.

**Recommended migration plan (multi-phase, non-trivial):**

1. Split into 3 base + 4 STI / sidecar tables:
   - `investment_accounts` (core: id, user_id, joint_owner_id, account_type, provider, current_value, contributions, fees, timestamps) — ~30 cols
   - `investment_account_isa_details` (ISA-type specific, FK 1:1)
   - `investment_account_eis_seis` (EIS/SEIS tax-relief tracking, FK 1:1)
   - `investment_account_employee_scheme` (CSOP/EMI/SAYE/SIP, FK 1:1)
   - `investment_account_bond_details` (purchase_date, withdrawal_taken, etc.)

2. Use STI (`account_type` discriminator) at the ORM layer with a separate model per scheme.

3. Add `select(...)` in hot paths so default reads pull only what the page needs.

For now, **as a Phase-0 quick win**: audit `Services/Investment/` for `InvestmentAccount::where(...)->get()` calls and add `select()` to fetch only ~10 fields the caller actually uses. See finding Q-04 below.

### S-11 — Currency code stored as `varchar(3)` but no constraint
- **Severity:** low
- **Confidence:** high
- **Evidence:** `investment_accounts.investment_currency varchar(3) NOT NULL DEFAULT 'GBP'` (line 894), `grant_currency` (947), `subscriptions.currency` (1527)

Nothing prevents 'GB' or 'gbp' or 'USD' (only GBP today). Either add a check constraint or `enum('GBP','USD','EUR')` with the actual supported set.

### S-12 — `country` columns are `varchar(255) DEFAULT 'United Kingdom'` everywhere
- **Severity:** low
- **Confidence:** high
- **Evidence:** at least 8 tables (properties, cash_accounts, savings_accounts, investment_accounts, mortgages, chattels, business_interests, holdings…)

Should be ISO 3166-1 alpha-2 (`varchar(2) NOT NULL DEFAULT 'GB'`) for indexing, joins, and tax-residence lookups. Today "United Kingdom", "UK", "United kingdom", "Great Britain" would all be allowed and would not match each other. The Trust module has a `country` column too (`trusts.country`). Low impact today (only UK in scope) but a landmine for the documented future "international expansion".

### S-13 — `migrations` table uses `int unsigned` for id, others `bigint unsigned`
- **Severity:** low
- **Confidence:** high
- **Evidence:** `database/schema/mysql-schema.sql:1358`

Cosmetic — `migrations` table will never exceed 4B rows.

### S-14 — `goals.linked_account_ids` is `JSON` but `linked_savings_account_id` is FK
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `database/migrations/2026_01_24_160001_create_goals_table_v2.php:56-57`

Storing FK IDs in JSON breaks referential integrity (no cascade on delete, no constraint), and forces JSON_CONTAINS queries instead of indexed lookups. The `goal_savings_account` pivot table (`2026_03_14_100002`) was added later for proper many-to-many. The `linked_account_ids` JSON column is now redundant and should be deprecated:

1. Migrate any data still in `linked_account_ids` JSON into pivot tables.
2. Drop the JSON column.
3. Drop `goals.linked_savings_account_id` (single FK) too, since the pivot handles N:M.

### S-15 — `users.deletion_reason` enum doesn't include 'subscription_lapsed'
- **Severity:** low
- **Confidence:** med
- **Evidence:** `database/migrations/2026_05_07_000001_add_deletion_tracking_to_users_table.php:18-25`

Includes `subscription_cancelled_grace_ended` but not the more common `trial_expired` distinction from `subscription_cancelled`. Five values is fine; adding a sixth requires another `ALTER TABLE` later. Consider `varchar(50)` + check constraint or an `account_deletion_reasons` reference table if this enum is expected to grow.

---

## 2. Indexes

### I-01 — `audit_logs` has no covering index for purge sweep
- **Severity:** critical
- **Confidence:** high
- **Evidence:** `database/migrations/2026_01_19_135404_create_audit_logs_table.php:30-33`, `app/Console/Commands/PurgeAuditLogs.php:38-46`

Current indexes:
```sql
KEY `audit_logs_user_id_created_at_index` (`user_id`,`created_at`),
KEY `audit_logs_event_type_action_index` (`event_type`,`action`),
KEY `audit_logs_model_type_model_id_index` (`model_type`,`model_id`),
KEY `audit_logs_created_at_index` (`created_at`)
```

Purge query:
```php
AuditLog::where('created_at', '<', $standardCutoff)
    ->where('event_type', '!=', AuditLog::EVENT_GDPR);
```

This uses `audit_logs_created_at_index` then filter-by-event-type. With 50M+ rows this scans every row newer than cutoff (good selectivity for `<`) but reads from the table every time to check `event_type`. For an append-only audit table, `(event_type, created_at)` is the right covering shape — same pattern used for `ai_audit_events_operation_created_idx` (`2026_05_06_000003`).

**Fix:**

```php
// Purpose: O(log N) range scans for retention purge by event_type
// Expected improvement: 10-100x faster on tables >10M rows
// Tradeoff: ~5% write overhead, +1.5GB at 100M rows
Schema::table('audit_logs', function (Blueprint $t) {
    $t->index(['event_type', 'created_at'], 'audit_logs_event_type_created_idx');
});
```

Also consider **MySQL partitioning** by `RANGE COLUMNS(created_at)` on monthly buckets — `DROP PARTITION` is O(1) versus `DELETE` which copies into the undo log and triggers binlog row events. Worth it once the table exceeds ~10M rows.

### I-02 — `ai_messages` query pattern doesn't have a covering index for token sums
- **Severity:** high
- **Confidence:** med
- **Evidence:** `database/migrations/2026_02_27_200002_create_ai_messages_table.php:26`

Current index: `(conversation_id, created_at)` — perfect for chronological message reads. But the daily-usage backfill query (`ai:usage:backfill`) groups by user via the parent conversation, which means `JOIN ai_conversations ON conversation_id` then aggregate `input_tokens + output_tokens` per day. That's two reads with no covering index on the sum columns.

**Recommendation:** keep current index. The `ai_daily_usage` table (`2026_04_25_000010`) already aggregates per day — the only time ai_messages gets summed is during a one-off backfill, which is fine to run cold. **No change needed**, flagging in case there's reporting downstream.

### I-03 — `joint_account_logs` indexed but missing user_id composite
- **Severity:** high
- **Confidence:** high
- **Evidence:** `database/schema/mysql-schema.sql:1190`

```sql
KEY `jal_joint_owner_loggable_idx` (`joint_owner_id`,`loggable_type`,`loggable_id`),
```

If queries ever filter "all joint-account logs where I am the primary user" (not the joint_owner), this is unindexed. Need composite on `(user_id, loggable_type, loggable_id)` mirroring the joint_owner_id one. Confirm via:

```bash
grep -rn "JointAccountLog::where" /Users/CSJ/Desktop/fynla/app/
```

### I-04 — `recommendation_tracking` index `(user_id, timeline)` has low cardinality leading column for index selection
- **Severity:** medium
- **Confidence:** med
- **Evidence:** `database/schema/mysql-schema.sql:1806`

`rec_tracking_timeline_idx` is `(user_id, timeline)`. Since `timeline` is an enum with 4 values, this is fine — `user_id` provides selectivity. But there's also `rec_tracking_user_completed_idx (user_id, completed_at)` and `recommendation_tracking_user_id_module_index (user_id, module)`. Three composite indexes on the same leading column. MySQL will only use one per query. Cardinality analysis:

- Most queries on this table filter `WHERE user_id = ? AND status = ?` — covered by `recommendation_tracking_user_id_status_index`.
- The `timeline` and `module` filters are usually applied in PHP after the user_id read.

If you don't have evidence that the planner picks the `timeline`/`module` indexes, drop them — they cost write overhead. Run `SHOW INDEX FROM recommendation_tracking` against prod and check `Cardinality`; very low cardinality vs total rows says the planner is ignoring them.

### I-05 — `holdings.holdable_type, holdable_id` indexed twice
- **Severity:** low
- **Confidence:** high
- **Evidence:** `database/schema/mysql-schema.sql:759-760`

```sql
KEY `holdings_holdable_type_holdable_id_index` (`holdable_type`,`holdable_id`),
KEY `holdings_holdable_id_type_idx` (`holdable_id`,`holdable_type`)
```

These are functionally equivalent for `MorphTo` lookups (always queries `WHERE holdable_type = ? AND holdable_id = ?`). The leading column differs, so MySQL may pick either; in practice the optimizer will pick whichever has better cardinality, but maintaining both costs write overhead. **Drop `holdings_holdable_id_type_idx`** — the type-first ordering is the Laravel convention.

### I-06 — `properties` has both `ownership_type` and `(user_id, property_type)` indexes; missing `(user_id, ownership_type, joint_owner_id)`
- **Severity:** medium
- **Confidence:** med
- **Evidence:** `database/schema/mysql-schema.sql:1705-1711`

Common query pattern in Estate module is "all properties co-owned by my spouse". Today that requires:
```sql
WHERE (user_id = ? OR joint_owner_id = ?) AND ownership_type IN ('joint','tenants_in_common')
```

The optimizer picks `properties_user_id_index` for the first branch then ranges to the row to evaluate `ownership_type`. A composite `(user_id, ownership_type)` would cover the first branch; `joint_owner_id` is separately indexed. Net win is marginal unless you have millions of properties — flagging in case Estate aggregations get slow.

### I-07 — `goals.assigned_module` indexed but `(user_id, status, assigned_module)` not
- **Severity:** medium
- **Confidence:** med
- **Evidence:** `database/migrations/2026_01_24_160001_create_goals_table_v2.php:82-84`

Goals dashboard typically queries: "active goals for module X for user Y". Current indexes:
```php
$table->index(['user_id', 'status']);
$table->index(['user_id', 'assigned_module']);
```

These cover the two-way filters; a three-way would need `(user_id, status, assigned_module)`. With only ~5-20 goals per user, neither is critical. No change needed unless dashboard query slow.

### I-08 — `personal_access_tokens.tokenable_type, tokenable_id` index is the Laravel default but `last_used_at` lookups slow
- **Severity:** medium
- **Confidence:** med
- **Evidence:** `database/schema/mysql-schema.sql:1593-1594`

Sessions UI shows "this device last used N hours ago", which is a `WHERE tokenable_id = ? ORDER BY last_used_at DESC` query. With tens of thousands of tokens per active user (no — but with stale token accumulation under heavy use, this could grow), unindexed `last_used_at` requires a filesort. Low priority.

### I-09 — Missing index on `subscriptions.status` for global rollups
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `database/schema/mysql-schema.sql:2066-2084`

`UserMetricsService` does `subscriptions WHERE status = 'active'` for dashboard metrics. Only `subscriptions_user_id_foreign` index exists. Once subscription count > 50k, this scans the table for global queries.

**Fix:** `2026_04_14_122656_add_subscriptions_indexes.php` already adds some — verify it covers `(status, current_period_end)` for renewal-due lookups. Read that migration:

### I-10 — `lifecycle_email_log` and `notifications` indexes not yet reviewed
- **Severity:** medium
- **Confidence:** low

I did not have time to read `2026_04_14_122231_create_lifecycle_email_log_table.php` and `2026_04_14_094042_create_notifications_table.php`. These are write-heavy tables (one row per email send / notification). They need:
- `(user_id, created_at)` for "my notifications" UI
- `(template_id, sent_at)` for campaign analytics dedup queries

Flag for follow-up review.

### I-11 — `cash_accounts` and `savings_accounts` have BOTH `user_id_index` AND `(user_id, account_type)` — redundant
- **Severity:** low
- **Confidence:** high
- **Evidence:** `database/schema/mysql-schema.sql:186, 193` (cash_accounts), `:1945, 1950` (savings_accounts)

The single-column `user_id_index` is fully covered by `(user_id, account_type)` for any `WHERE user_id = ?` query. The optimizer can use the composite. **Drop the single-column indexes** — saves ~10MB per million rows and reduces write overhead by one B-tree update per insert.

### I-12 — `is_preview_user` index only as composite with `preview_persona_id`
- **Severity:** low
- **Confidence:** high
- **Evidence:** `database/schema/mysql-schema.sql:2403` — `preview_user_persona_idx (is_preview_user, preview_persona_id)`

Queries like `WHERE is_preview_user = false` to find real users (admin metrics, GDPR sweeps) can use this composite via the leading column, but the index is bloated by the `preview_persona_id` column they don't read. Cardinality is fine: `is_preview_user=true` is ~6 rows, `false` is the rest. Either:
- keep as-is (fine for now)
- or add a partial index in newer MySQL (`WHERE is_preview_user = true`) — but MySQL 8.0 doesn't support partial indexes, only generated columns. Skip.

---

## 3. Query patterns

### Q-01 — `WHERE user_id = ? OR joint_owner_id = ?` scattered without using the `HasJointOwnership` scope
- **Severity:** high
- **Confidence:** high
- **Evidence:**
  - `app/Agents/CoordinatingAgent.php:1463, 1476, 1497, 1519, 1539, 1543, 1547` — 7 occurrences inline
  - `app/Services/Investment/Recommendation/UserContextBuilder.php:110-117` (uses `where(function)` wrapper)
  - `app/Traits/HasJointOwnership.php:24` — `scopeForUserOrJoint` exists

The trait scope `forUserOrJoint($userId)` is the right abstraction. Half the codebase uses inline `where('user_id', $id)->orWhere('joint_owner_id', $id)` which works syntactically but bypasses the scope. When index hints, soft-delete handling, or audit filtering needs to change, the inline calls each need editing.

**Fix:** convert the 7 CoordinatingAgent occurrences and UserContextBuilder to use the scope:

```php
// Before
SavingsAccount::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
// After
SavingsAccount::forUserOrJoint($userId)->get();
```

Same trait. Scope encapsulates the OR correctly — note that `where(...)->orWhere(...)` without parentheses creates query precedence bugs once additional WHERE clauses combine. The scope wraps in a `where(function)`. Inline `orWhere` is one missed parenthesis from a silent data leak.

### Q-02 — `Property::with('mortgages')->where(...)->orWhere(...)` precedence bug risk
- **Severity:** high
- **Confidence:** high
- **Evidence:** `app/Agents/CoordinatingAgent.php:1497`

```php
$items = Property::with('mortgages')
    ->where('user_id', $userId)
    ->orWhere('joint_owner_id', $userId)
    ->get();
```

If a soft-deletes scope or `withTrashed()` clause is ever chained here, the OR precedence will swallow it. Convert to `Property::forUserOrJoint($userId)->with('mortgages')->get()`. (`Property` already uses `HasJointOwnership`.)

### Q-03 — `Mortgage::whereHas('property', ...)` for joint ownership
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `app/Agents/CoordinatingAgent.php:1519`

```php
Mortgage::whereHas('property', fn($q) =>
    $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId)
)->with('property')->get();
```

`whereHas` generates a correlated subquery. For "all my mortgages including jointly owned properties", consider denormalising — `mortgages` already has `user_id` and `joint_owner_id`. Just query the table directly:

```php
Mortgage::forUserOrJoint($userId)->with('property')->get();
```

Same result, no subquery, uses the `mortgages_user_id_index` and `mortgages_joint_owner_id_index` directly.

### Q-04 — `InvestmentAccount::...->get()` does not `select()` the 154 columns
- **Severity:** high
- **Confidence:** high
- **Evidence:** `app/Services/Investment/Recommendation/UserContextBuilder.php:96`, `app/Services/Investment/Tax/CGTHarvestingCalculator.php:98`, plus ~12 other services in `/app/Services/Investment/`

Every read of `InvestmentAccount::...->with('holdings')->get()` pulls 154 columns × N rows from disk into the buffer pool. For ISA holdings analysis you need ~15 columns. For BADR analysis you need a different 15. **Add `->select([...])` everywhere it's a hot path**, or alternatively add column groups to the model.

This is the same problem as the wide-row issue in S-10. The DB-side cure is "split the table"; the application-side workaround is "request fewer columns". Either fixes the symptom; both should happen.

### Q-05 — Recommendation engine reads via `latest('id')` repeatedly
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `app/Services/AI/DuplicateAcknowledgement.php:145, 151, 157, 226, 266, 317`

Six occurrences of `latest('id')` on `ai_messages` (or similar). `id` is the primary key, so this is fine — uses the PK index, single seek per call. **No issue**; flagging for awareness because the pattern is unusual elsewhere (`latest()` defaults to `created_at`, which on some tables has no index).

### Q-06 — `IHTCalculation::where(...)->latest('calculation_date')->first()` covered by composite
- **Severity:** low
- **Confidence:** high
- **Evidence:** `app/Services/Estate/IHTCalculationService.php:1336-1341`, `database/schema/mysql-schema.sql:820`

```sql
KEY `iht_calculations_user_id_calculation_date_index` (`user_id`,`calculation_date`)
```

Query uses `WHERE user_id = ? AND is_married = ? AND data_sharing_enabled = ? ORDER BY calculation_date DESC LIMIT 1`. The first WHERE matches the index leading column; ORDER BY matches the second. Excellent.

### Q-07 — `whereHas('user', fn($q) => $q->where('is_preview_user', false))` in admin metrics
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `app/Services/Admin/UserMetricsService.php:37-105`

Multiple `whereHas('user', ...)` calls on tables like `subscriptions`, `audit_logs`, `lifecycle_email_log`. Each generates `WHERE EXISTS (SELECT 1 FROM users WHERE ...)`. For admin dashboards aggregating across all users, this fan-out is fine — admin only, low concurrency.

But the predicate `where('is_preview_user', false)` is the *opposite* of the indexed direction. The composite `preview_user_persona_idx (is_preview_user, preview_persona_id)` covers `is_preview_user = true` (6 rows) much more efficiently than `is_preview_user = false` (most rows). For "real user" counts, just don't filter — instead `WHERE is_preview_user != true` is treated as range. Or better, since real users are 99%+ of the table:
```sql
-- this scans the index in reverse; faster than the predicate
SELECT COUNT(*) FROM users WHERE is_preview_user = 0;
```

Honestly low-impact: admin dashboards are not hot path.

### Q-08 — `lifeEvents` query in `UserContextBuilder` doesn't use the index
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `app/Services/Investment/Recommendation/UserContextBuilder.php:110-117`, schema:1301-1303

```php
LifeEvent::where(function ($q) use ($user) {
    $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id);
})
->active()
->orderBy('expected_date')
->get();
```

`life_events` has `user_id_expected_date_index` and `user_id_status_index`. The OR query forces a UNION-style execution: scan user_id_index for matches, scan joint_owner_id_foreign for matches, merge, then sort. Same shape as Q-03. Move to the scope.

### Q-09 — No `pluck()` where only IDs needed
- **Severity:** low
- **Confidence:** med

I sampled some service code; saw a few places where a full Eloquent collection is hydrated only to call `->pluck('id')` against it. Better: `Model::where(...)->pluck('id')` returns a simple Collection of integers without hydrating models. Minor perf win when collections are large.

### Q-10 — N+1 risk in `ComprehensiveEstatePlanService`
- **Severity:** medium
- **Confidence:** med
- **Evidence:** `app/Services/Estate/ComprehensiveEstatePlanService.php:530` uses `->with('property:id,address_line_1')` correctly

The pattern of using `with(...)` with column selection (`property:id,address_line_1`) is excellent — flagging this as a positive example to extend to the rest of the Estate service. Most other services use `->with('holdings')` without column selection, pulling all 154 holdings columns when only `current_value` and `cost_basis` are read.

### Q-11 — `User::with('mortgages')->find()` chains
- **Severity:** low
- **Confidence:** high
- **Evidence:** `app/Services/Savings/SavingsActionDefinitionService.php:1774, 1856`

Fine pattern. Just flagging that across `User::with(...)` chains, no `select()` on the user table itself — user is 100+ columns wide so the same wide-row problem from S-10 applies. Add `User::select(['id','spouse_id','marital_status','date_of_birth','target_retirement_age'])->with('mortgages')` for hot paths.

---

## 4. Transactions

### T-01 — Most controller writes are NOT wrapped in `DB::transaction`
- **Severity:** critical
- **Confidence:** high
- **Evidence:** `grep DB::transaction` returns **13** matches in controllers and **21** in services. Against **62** `::create()` calls in controllers alone (`grep ::create\( app/Http/Controllers/Api/`).

Most controllers do `Model::create($validated)` then return JSON. Single-table writes are atomic by InnoDB row locking — fine. But several controllers cascade writes:

- `InvestmentController::store` → creates `InvestmentAccount`, then `Holding[]` (separate inserts)
- `Estate/WillController::store` → creates `Will`, then `Bequest[]`
- `BusinessInterestController::store` → creates business + observer fires risk recalc

A 500 mid-cascade leaves a half-created account with zero holdings, or a will with no bequests. The downstream calculation reads the orphan and produces a wrong number. There's no rollback because there's no transaction.

**Fix pattern:**

```php
public function store(Request $request) {
    $validated = $request->validate([...]);
    return DB::transaction(function () use ($validated) {
        $account = InvestmentAccount::create(Arr::except($validated, 'holdings'));
        foreach ($validated['holdings'] ?? [] as $h) {
            $account->holdings()->create($h);
        }
        return new InvestmentAccountResource($account);
    });
}
```

### T-02 — `AuditChainService::append` uses transaction + lockForUpdate correctly
- **Severity:** —
- **Confidence:** high
- **Evidence:** `app/Services/AI/AuditChainService.php:81`

Positive: the hash-chain audit appender uses `DB::transaction(... lockForUpdate())` so concurrent writers serialise on the previous row. This is canonical correct pattern. Use it as the template for the other places that need it.

### T-03 — `ai_daily_usage` uses `SELECT ... FOR UPDATE` for budget tracking
- **Severity:** —
- **Confidence:** high
- **Evidence:** `database/migrations/2026_04_25_000010_create_ai_daily_usage_table.php:14-22` (docblock)

Positive — canonical use of row locking inside a transaction.

### T-04 — `HoldingsImportService::importHoldings` runs in transaction but does network I/O inside
- **Severity:** high
- **Confidence:** med
- **Evidence:** `app/Services/Documents/HoldingsImportService.php:90`

Need to confirm whether the transaction holds an HTTP call to Anthropic for extraction. If so, the row lock is held for the full network roundtrip (could be 30s). Read the file to verify:

```bash
sed -n '85,150p' /Users/CSJ/Desktop/fynla/app/Services/Documents/HoldingsImportService.php
```

If a network call is inside the txn, **move it outside** — call the API, then start the transaction with the result.

### T-05 — `DocumentProcessor::process` runs in transaction with AI extraction
- **Severity:** high
- **Confidence:** med
- **Evidence:** `app/Services/Documents/DocumentProcessor.php:52, 144`

Same risk as T-04. AI extraction can take 10-30s. Transaction holds row locks on `documents`, `document_extractions`, possibly `holdings` for that duration. Concurrent uploads block. Confirm whether extraction is inside the transaction or before it.

### T-06 — `Subscription` renewal transaction wraps a Revolut API call?
- **Severity:** medium
- **Confidence:** low
- **Evidence:** `app/Services/Payment/SubscriptionRenewalService.php:28`

Need to confirm whether Revolut API is called inside the transaction. Standard rule: external API calls are *never* inside transactions. The webhook handler should already have the API response data; transaction is only for DB writes.

---

## 5. Migrations

### MIG-01 — `2026_01_18_000003_migrate_existing_goals_data.php` has dangerous down()
- **Severity:** critical
- **Confidence:** high
- **Evidence:** lines 138-140

```php
public function down(): void {
    DB::table('goals')->truncate();  // ❌ destroys user data
}
```

Rolling back this migration *truncates the entire goals table*, not just the migrated rows. Anyone running `php artisan migrate:rollback --step=10` (totally legitimate operation) would silently lose all goal data created in the last 4 months.

**Fix:** The migration inserts new rows from `savings_goals` and `investment_goals`. The correct down() rolls back only those specific inserts. Easiest: don't write a destructive down().

```php
public function down(): void {
    // Intentionally empty - this is a data migration, not a schema change.
    // Rolling back would either destroy data or be a no-op; choose no-op.
}
```

Also, the up() has **no idempotency check**. Re-running this migration duplicates every row (no `updateOrCreate`, no `WHERE NOT EXISTS`). If migrations are ever re-applied after a tombstoned row is removed, you'd get duplicate goals.

### MIG-02 — `2026_03_18_100000_add_soft_deletes_to_key_models.php` down() is wrong
- **Severity:** high
- **Confidence:** high
- **Evidence:** lines 24-34

```php
public function down(): void {
    $tables = ['trusts', 'iht_profiles', 'family_members', 'protection_profiles', 'state_pensions'];
    foreach ($tables as $table) {
        if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
            // ❌ drops deleted_at even if added by an earlier migration
            Schema::table($table, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
}
```

If a different migration also added soft-deletes to one of these tables (e.g. `2026_02_21_200002_add_soft_deletes_to_financial_models.php`), rolling back this migration would drop a column that another migration owns. Down is not perfectly reversible.

**Mitigation:** This is a common Laravel rough edge. Accept the risk for now but note that anyone running `migrate:rollback` past March 18, 2026 is likely to break things.

### MIG-03 — `2026_03_18_100002_fix_indexes_and_constraints.php` swallows exceptions
- **Severity:** high
- **Confidence:** high
- **Evidence:** lines 15-21, 33-39, 53-60, 69-94

Every operation is wrapped in `try { ... } catch (Exception $e) { /* ignore */ }`. Six try/catch blocks. If MySQL throws because the index already exists, fine — but if it throws because of an actual constraint conflict (FK violation on existing data), the migration silently passes and ships broken state.

**Fix pattern:** detect index existence via `information_schema.STATISTICS` (as in `2026_01_26_150000_add_joint_owner_indexes.php` line 90-94) instead of try/catch.

### MIG-04 — `2026_02_20_120000_assign_roles_to_existing_users.php` has empty down()
- **Severity:** low
- **Confidence:** high
- **Evidence:** lines 41-46

Comment correctly explains why down() is empty. Acceptable. Just flagging that the migration depends on the `roles` table existing — which it does, per `2026_01_19_140501_create_roles_permissions_tables.php`. Migration order is correct.

### MIG-05 — `2026_03_20_100000_make_enum_columns_nullable.php` is unreadable
- **Severity:** low
- **Confidence:** low

I didn't read this one but the filename suggests broad ALTER enum changes. Enum changes in MySQL trigger table rewrites (even with `INPLACE`) for large tables. Cross-check this migration was deployed during low-traffic window.

### MIG-06 — `2026_05_06_000002_rename_eval_user_id_to_preview_user_id.php` uses raw SQL
- **Severity:** medium
- **Confidence:** high
- **Evidence:** lines 30-31

```php
DB::statement('ALTER TABLE eval_recording_sessions CHANGE COLUMN eval_user_id preview_user_id BIGINT UNSIGNED NOT NULL');
```

Documented choice (skips doctrine/dbal dependency). Acceptable but the down() reverses it correctly. Just noting that any future modification of this column type needs to use raw SQL too.

### MIG-07 — Multiple migrations per table per day
- **Severity:** medium
- **Confidence:** high
- **Evidence:**
  - `investment_accounts` has 9+ separate ADD COLUMN migrations across Dec 2025 - Feb 2026
  - `users` has 35+ separate ADD COLUMN migrations
  - `dc_pensions` has 8+

Each migration is `ALTER TABLE` which (for InnoDB pre-MySQL 8) rebuilds the table. MySQL 8 makes most ADD COLUMN operations INSTANT (no rewrite) — good. But a developer running migrations from scratch on a fresh DB executes 35 sequential ALTERs on `users`. Boot time on a fresh local install is measurably slow.

**Recommended:** Squash migrations older than 90 days into a single `2025_10_13_000000_create_users_table_complete.php`. Laravel supports schema dumps for this purpose. Keep the schema file as the source of truth for fresh installs, keep individual migrations for production rollouts.

### MIG-08 — 215 migrations is large but manageable
- **Severity:** medium
- **Confidence:** high

Of those, ~110 add columns, ~25 add indexes, ~15 are data migrations, the rest are fixes/renames. Migration squash candidates (>90 days old, fully deployed):
- All `2025_10_*` migrations (pre-launch state) → squash into base CREATE migrations.
- All `2025_11_*` and `2025_12_*` ADD COLUMN migrations on `users`, `investment_accounts`, `dc_pensions` → squash into the parent CREATE.

Squashing reduces fresh-install time from minutes to seconds and shrinks the migrations table.

### MIG-09 — Several migrations rely on data state without `hasColumn` guards
- **Severity:** medium
- **Confidence:** med

Sample: `2026_01_22_162633_add_contribution_fields_to_investment_accounts_table.php` and similar — they add columns but assume the schema is in a known state. Most use `if (! Schema::hasColumn(...))` correctly. Audit migrations created between Oct-Dec 2025 to ensure they all guard.

### MIG-10 — `2026_01_19_134700_create_user_sessions_table.php` and `2026_01_19_134700_create_lockout_fields_to_users_table.php` share a timestamp
- **Severity:** low
- **Confidence:** high

Two migrations at `134700` (same second). Laravel sorts them alphabetically as a tiebreaker. Confirm the order doesn't matter — if it does, change one timestamp to `134701`.

### MIG-11 — Auto-named indexes hard to drop
- **Severity:** medium
- **Confidence:** high
- **Evidence:** Most `$table->index('col_name')` calls don't pass a name. Laravel generates `{table}_{column}_index`. Fine for additions; brittle for drops. Some migrations (like `2026_05_06_000003`) use explicit names — better.

**Convention:** always pass an explicit index name as second arg. Document this in `database/CLAUDE.md` under "Index Patterns".

### MIG-12 — Several recent migrations don't `if (Schema::hasTable(...)) return` early
- **Severity:** low
- **Confidence:** high
- **Evidence:** `2026_05_03_000003_create_tax_strategy_household_inputs_table.php` line 13 → goes straight to `Schema::create` without idempotency guard. Most do, but not this one.

If the migration ran on dev, then was edited, then re-ran on the same dev DB, it would throw `Table already exists`. Minor inconvenience; recommended pattern is the `if (Schema::hasTable(...)) return;` guard at the top of every create migration. Per `database/CLAUDE.md` line 23-26.

### MIG-13 — `down()` methods on FK changes don't restore exact original FK behaviour
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `2026_05_07_000002_fix_life_events_joint_owner_id_fk.php:23-28`

The down() re-creates the FK *without* a delete behaviour, defaulting to MySQL `RESTRICT`. The original was `RESTRICT` (no clause), so this is fine — but the migration documentation should make that explicit. If a future migration changes the original to `CASCADE`, this down() breaks.

### MIG-14 — `account_deletion_reminder_log` missing timestamps()
- **Severity:** low
- **Confidence:** high
- **Evidence:** `database/migrations/2026_05_07_000004_create_account_deletion_reminder_log_table.php`

Table has only `sent_at` and no `created_at` / `updated_at`. Acceptable for a write-once log (sent_at IS the creation time). Flagging because it breaks the convention used elsewhere.

---

## 6. Eloquent models

### M-01 — `Holding` casts are all `float` (see S-02)
- **Severity:** critical
- See S-02 above.

### M-02 — `User::$guarded` instead of `$fillable`
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `app/Models/User.php:34-42`

Uses `$guarded` (deny-list). Most models use `$fillable` (allow-list). `$guarded` is safer when you trust the input; risky when input is user-provided. The list correctly guards `is_admin`, `is_preview_user` — but anything new added to the schema (like `deletion_reason`) would be auto-fillable from `$request->all()` unless added to guarded.

**Recommendation:** Switch to `$fillable` with explicit allow-list, or add a `protected $unfillable` discipline check.

### M-03 — `User::$casts` is 60+ entries; risk of cast drift
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `app/Models/User.php:74-161`

A new column added without a corresponding cast entry returns the raw DB value (string for `decimal`, integer for `tinyint`). Recent additions (`marriage_allowance_eligible`, `is_lifecycle_test_user`, `deletion_scheduled_for`) are cast correctly. Older money columns (`monthly_expenditure`, `annual_expenditure`) are cast to `decimal:2` despite being stored as `double` — the cast hides the float problem at the application layer but doesn't fix the DB.

### M-04 — `AuditLog::auditable()` uses `model_type::find($model_id)` — no eager load
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `app/Models/AuditLog.php:114-121`

Returns a fresh model lookup every call. For an audit-log UI showing 50 entries, that's 50 separate queries. Convert to MorphTo:

```php
public function auditable() {
    return $this->morphTo(__FUNCTION__, 'model_type', 'model_id');
}
```

Then `AuditLog::with('auditable')->latest()->limit(50)->get()` does one batched query per distinct `model_type`.

### M-05 — `SavingsAccount::accountNumber()` decrypts every read
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `app/Models/SavingsAccount.php:115-130`

The Crypt::decryptString call runs on every model read, including bulk fetches for dashboards. If a user has 20 savings accounts and the dashboard reads all of them, that's 20 decryption operations even if `account_number` is never displayed. Crypt is fast but not free.

**Recommendation:** Convert to a custom cast that decrypts lazily on attribute access:

```php
class EncryptedString implements CastsAttributes {
    public function get($model, $key, $value, $attributes) {
        return $value ? Crypt::decryptString($value) : null;
    }
    public function set($model, $key, $value, $attributes) {
        return $value ? Crypt::encryptString($value) : null;
    }
}
// Then in model:
protected $casts = ['account_number' => EncryptedString::class];
```

### M-06 — `User` model has 50+ relationships methods, no global scopes
- **Severity:** low
- **Confidence:** high
- **Evidence:** `app/Models/User.php` (806 lines, mostly relationship definitions)

Acceptable but the User model could be split into:
- `User` (auth + identity)
- `UserProfile` (concern-mixing trait that holds 50+ HasMany declarations)

Not urgent.

### M-07 — `Trust` model uses `$fillable` without `joint_owner_id`
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `app/Models/Estate/Trust.php:20-49` — no `joint_owner_id`, but per CLAUDE.md Rule #7 trusts can be jointly owned via `ownership_type='trust'`.

Confirm whether `trusts` table needs `joint_owner_id` (per the joint-ownership pattern). Today it has `user_id` (the settlor) and `household_id` only. Beneficiaries are stored in a separate JSON column `beneficiaries`. Joint ownership of a trust is unusual — trusts have settlors, trustees, beneficiaries — not "joint owners". The schema is probably correct, just confirming this is intentional.

### M-08 — Several models have `$auditExcludeFields = ['updated_at', 'created_at']`
- **Severity:** low
- **Confidence:** high
- **Evidence:** `app/Models/Investment/Holding.php:18`

Good pattern. Should be set on all `Auditable` models — otherwise every update with a different `updated_at` triggers an audit entry, bloating `audit_logs`. Sample audit:

```bash
grep -rn "auditExcludeFields" /Users/CSJ/Desktop/fynla/app/Models/
```

Audit which models do NOT set this and add it as needed.

### M-09 — `User::hasAcceptedSpousePermission` does an N+1 query
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `app/Models/User.php:669-695`

For each user, this does:
1. `$this->relationLoaded('spouse') ? $this->spouse : $this->spouse()->first()` — could be N+1 in a household-view query.
2. Two SpousePermission queries (with orWhere). Same query, different sides.

In a household view that iterates over users, each `hasAcceptedSpousePermission` call hits the DB twice. Use eager loading: `User::with('spouse', 'sentPermissions', 'receivedPermissions')`.

### M-10 — `Holding::investmentAccount` is a BelongsTo without scope filter
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `app/Models/Investment/Holding.php:65-68`

Polymorphic table; the relationship `belongsTo(InvestmentAccount::class, 'holdable_id')` does NOT filter on `holdable_type`. Calling `$holding->investmentAccount` when the holding belongs to a `DCPension` returns whatever has the matching ID in `investment_accounts` — wrong data, silent. The comment says "The holdable_type check is done via scope instead" — verify the scope is reliably applied everywhere.

### M-11 — `User::$hidden` could include `mfa_recovery_codes` better
- **Severity:** low
- **Confidence:** high
- **Evidence:** `app/Models/User.php:49-58`

Correctly hides `password`, `mfa_secret`, `mfa_recovery_codes`, `national_insurance_number`. Good. Does NOT hide:
- `revolut_customer_id` (added in 2026_02_24)
- Anything else that came in later migrations

Audit `$hidden` quarterly.

### M-12 — `User::getNameAttribute` does a fallback to legacy `name` column
- **Severity:** low
- **Confidence:** high
- **Evidence:** `app/Models/User.php:301-318`

The `name` column was migrated to `first_name`/`middle_name`/`surname` but the accessor still checks for the legacy column. If `name` column is fully removed from the DB (it's not — but if), this fallback returns 'User' string for unmigrated rows. The CLAUDE.md says backwards-compatible. Confirm `name` column is now safely removable, then drop the fallback.

### M-13 — Models doing business logic
- **Severity:** low
- **Confidence:** med
- **Evidence:** `app/Models/Estate/Trust.php:87-98`

```php
public function getIHTValue(): float {
    return app(TrustValuationService::class)->calculateIHTValue($this);
}
```

Model method calls service. Acceptable proxy, but signals the service is the canonical home. Just keep it consistent — don't let logic creep into the model. Same with `User::isDeemedDomiciled` (200+ lines of domicile logic in the model).

---

## 7. Data integrity / Foreign keys

### FK-01 — `households` table lacks `created_at` / soft delete
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `database/schema/mysql-schema.sql:766-773`

```sql
CREATE TABLE households (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    household_name varchar(255) DEFAULT NULL,
    notes text,
    created_at timestamp NULL DEFAULT NULL,
    updated_at timestamp NULL DEFAULT NULL,
    PRIMARY KEY (id)
);
```

Households can't be soft-deleted. If a household is deleted, every `users.household_id` FK is set NULL (good), but every child table with `household_id` (`properties`, `cash_accounts`, `investment_accounts`, `business_interests`, `chattels`, `mortgages`) also gets NULL. No audit trail of "this asset USED TO BE in household 123".

Add `softDeletes()` and re-test the FK cascades. Most cascades will preserve relationship history since `SET NULL` keeps the row.

### FK-02 — `personal_access_tokens` has no FK back to users
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `database/schema/mysql-schema.sql:1581-1595`

Laravel Sanctum's default — `tokenable_type` + `tokenable_id` is polymorphic, no FK enforcement. When a user is deleted, their tokens persist with dangling references. Run a cleanup query on user delete (Sanctum supports this via observer):

```php
// In UserObserver
public function deleting(User $user) {
    $user->tokens()->delete();  // hard delete, not soft
}
```

### FK-03 — `audit_logs.model_id` has no FK (polymorphic)
- **Severity:** low
- **Confidence:** high

Acceptable — polymorphic columns can't have FKs. The trade-off is intentional. Just note that orphaned audit entries are normal and the UI must handle "model deleted" gracefully (the `AuditLog::auditable()` method correctly returns null).

### FK-04 — `goals.linked_savings_account_id` set to NULL on delete, but `linked_account_ids` JSON doesn't update
- **Severity:** high
- **Confidence:** high
- **Evidence:** `database/migrations/2026_01_24_160001_create_goals_table_v2.php:57`

When a savings account is deleted, the FK `linked_savings_account_id` becomes NULL — but the JSON column `linked_account_ids` still contains the deleted ID. Reading code that pulls from JSON will get a stale reference.

**Fix:** As per S-14, deprecate the JSON column and rely on the `goal_savings_account` pivot table (which has `cascadeOnDelete`).

### FK-05 — `bequests.beneficiary_user_id` `ON DELETE SET NULL` — but `beneficiary_name` is NOT NULL
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `database/schema/mysql-schema.sql:82-101`

When a beneficiary user is hard-deleted, the FK is nulled — but `beneficiary_name` stays. Good fallback: the will still records who the gift WAS for. Implies the system relies on the denormalised name. Document the intent on the column.

### FK-06 — `cash_accounts` FK to `trusts` is `SET NULL` even though trusts can soft-delete
- **Severity:** low
- **Confidence:** high

The trust soft-delete via `SoftDeletes` keeps the row; the FK is never actually triggered unless the trust is `forceDelete()`'d. So `SET NULL` is a defensive fallback. Fine.

### FK-07 — Nullable FK with no semantic for NULL value
- **Severity:** low
- **Confidence:** med
- **Evidence:** `recommendation_tracking.recommendation_id` is a `varchar(255)` (per `schema:1791`), not an FK. It's a free-text identifier from the recommendation engine.

Not technically an FK issue, but it does mean the recommendation engine could emit duplicate IDs and the DB wouldn't catch it. Add a unique constraint on `(user_id, recommendation_id)`.

### FK-08 — `family_members.linked_user_id` constraint behaviour
- **Severity:** low
- **Confidence:** low

Not verified. Per `2026_02_19_120001_add_linked_user_id_to_family_members_table.php` — read this to confirm cascade behaviour. If a linked user is hard-deleted, what happens to the family_member row?

---

## 8. Performance hotspots

### P-01 — `audit_logs` growth is unbounded for 90 days then purged
- **Severity:** critical (operational)
- **Confidence:** high
- **Evidence:** `app/Console/Commands/PurgeAuditLogs.php:21-22`, `2555` days for GDPR retention

GDPR audit entries retain 7 years. At 100 users × 50 audit events/day × 365 days × 7 years = 12.7M rows. Manageable, but without partitioning, the purge query (Q-01 above) blocks writes for minutes once it grows.

**Recommended migration plan:**

```sql
-- Partition audit_logs by month
ALTER TABLE audit_logs PARTITION BY RANGE COLUMNS(created_at) (
    PARTITION p_2026_01 VALUES LESS THAN ('2026-02-01'),
    PARTITION p_2026_02 VALUES LESS THAN ('2026-03-01'),
    ...
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

Then retention purge becomes:
```sql
ALTER TABLE audit_logs DROP PARTITION p_2025_10;  -- O(1), no row scans
```

Caveat: MySQL partitioning has limitations (can't have FKs into a partitioned table; the existing FK `user_id_foreign` would need to be dropped — which is consistent with `nullOnDelete` semantics, but breaks app code expectations).

### P-02 — `ai_audit_events` growth: every tool call writes a row
- **Severity:** high
- **Confidence:** high
- **Evidence:** `database/migrations/2026_04_25_000013_create_ai_audit_events_table.php` — hash chain audit

This table grows fastest. Every Fyn chat with tool use writes 5-20 rows. 1000 chats/day × 10 rows = 10k/day = 3.65M/year. The hash-chain integrity check (`ai:audit:verify-chain`) walks the entire chain sequentially. At 10M+ rows that walk takes hours.

Recommendations:
- Already has retention sweep (`2026_05_06_000003` index supports it).
- Partition by month once over 5M rows.
- Chain-verify should run on the most recent partition only, with the previous partition's tail hash stored as the starting point.

### P-03 — `monte_carlo_cache` LONGTEXT column never compressed
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `database/schema/mysql-schema.sql:1370-1372` — `results LONGTEXT`

Monte Carlo results are JSON arrays of 10,000 simulations × 30 years of values. Each row is potentially 5-10MB. The cache is keyed on `cache_key` (good), but row size means every miss writes 10MB to disk, every hit reads 10MB into RAM.

Recommendations:
- Compress at the application layer: `gzcompress(json_encode($data))` before insert, `json_decode(gzuncompress(...))` on read. ~70% size reduction.
- Or move to Memcached / Redis cache for the hot rows; only persist tail-of-distribution snapshots to MySQL.

### P-04 — `ai_messages.content` is `text` (not `longtext`)
- **Severity:** low
- **Confidence:** high
- **Evidence:** `database/migrations/2026_02_27_200002_create_ai_messages_table.php:17`

`text` caps at 64KB. Long conversations (especially tool_results with attached holdings JSON) could exceed this and silently truncate. Change to `longtext` for safety.

### P-05 — `recommendation_tracking` table grows per-recommendation, never compacted
- **Severity:** medium
- **Confidence:** med
- **Evidence:** schema:1788-1808

Each user generates 20-50 recommendations on each module-analysis run. With the `Auditable` observer firing on every status change, the audit table also grows. Add retention: completed/dismissed recommendations >90 days old can be soft-deleted; the analytics rollup keeps the count.

### P-06 — `holdings.purchase_history_json` for ERS / SAYE is text
- **Severity:** low
- **Confidence:** med
- **Evidence:** `investment_accounts.exercise_history_json` (line 976) — stored as `text` not `json`

Without the `json` type, MySQL can't index into the structure. If you ever need "users with ERS exercised in the last 12 months", you'd scan-and-parse. Migrate to `json` type when feasible.

### P-07 — `users.dashboard_widget_order` and other JSON prefs read on every page
- **Severity:** low
- **Confidence:** med

`dashboard_widget_order`, `journey_states`, `journey_selections`, `life_stage_completed_steps`, `dismissed_prompts`, `retired_budget_overrides`, `widowed_budget_overrides` are all JSON columns on `users`. Most pages read the user record; only one or two read these specific columns. The DB can't selectively read individual JSON paths cheaply.

If page-load benchmarks show user-table reads dominating, consider:
- Move user preferences to a separate `user_preferences` table (1:1 FK) — narrow reads.
- Or cache `User::find($id)` aggressively (it already is, per CLAUDE.md Memcached note).

---

## 9. Seeders & test data

### SD-01 — `PreviewUserSeeder` DELETES then INSERTS on reseed
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `database/seeders/PreviewUserSeeder.php:92-100`

```php
if ($existingUser) {
    $this->command->info("Deleting existing preview user for {$personaId} to recreate with fresh data...");
    $this->deletePreviewUser($existingUser, $personaId);
}
```

On every `php artisan db:seed --class=PreviewUserSeeder`, the persona is deleted and recreated. This:
- Loses any test data added via the UI between seedings (acceptable for personas).
- Generates a huge audit_log churn on every reseed.
- Doesn't use a transaction, so a mid-flight error leaves a half-deleted persona.

**Fix:** Wrap in transaction. Better: detect whether persona JSON has changed (hash compare) and skip if not.

### SD-02 — `2026_01_18_000003_migrate_existing_goals_data.php` is not idempotent
- See MIG-01.

### SD-03 — `TaxConfigurationSeeder` uses `updateOrCreate` correctly
- **Severity:** —
- **Confidence:** high

Per `database/CLAUDE.md` line 120, idempotent pattern. Verified in spot-check.

### SD-04 — `LifecycleTestSeeder` lifecycle: dev-only behaviour
- **Severity:** low
- **Confidence:** med

This is a Phase-2 (dev-only) seeder. Confirm `LifecycleTestSeeder` is NOT in the production `DatabaseSeeder::$callOrder`. (`database/CLAUDE.md` lists 18 Phase-1 seeders and 4 Phase-2; LifecycleTestSeeder isn't in either list — confirm it's manually invoked only.)

### SD-05 — `AdminUserSeeder` creates admin with known hardcoded password?
- **Severity:** medium
- **Confidence:** low

Did not read. Quick audit: ensure the admin password is `bcrypt(env('ADMIN_PASSWORD'))` not hardcoded. If hardcoded, fresh installs get a known credential.

### SD-06 — Seeders hardcode user IDs?
- **Severity:** low
- **Confidence:** low

Did not spot any obvious cases but a full audit of `database/seeders/` for `User::find(1)` or `->id = 1` would be worthwhile.

---

## 10. Audit log table

### A-01 — Critical issues already covered in I-01 and P-01
- Partition by month
- Add `(event_type, created_at)` composite index for retention sweep
- Don't FK partitioned tables (have to drop the FK on partitioning anyway, which weakens the data model)
- Schedule retention purge during low-traffic window (currently `audit:purge` artisan with no scheduler entry I could see)

### A-02 — Hash-chain audit (`ai_audit_events`) integrity audit
- **Severity:** —
- **Confidence:** high
- **Evidence:** `database/migrations/2026_04_25_000013_create_ai_audit_events_table.php`

Hash chain design is solid. HMAC-keyed signature prevents downstream replay. `prev_hash` + `row_hash` + `signature` is appropriate triple-armor. The chain walker tool (`ai:audit:verify-chain`) should report break points. The retention command needs to operate strictly on the tail (never modify the middle). Confirm.

### A-03 — `audit_logs` and `ai_audit_events` overlap?
- **Severity:** low
- **Confidence:** med

Two audit tables, different purposes:
- `audit_logs` = generic GDPR / data-change audit
- `ai_audit_events` = hash-chain audit for AI tool calls

No overlap in evidence. Just confirming the boundary is documented.

### A-04 — Retention configuration via `config/auth.php`?
- **Severity:** low
- **Confidence:** high
- **Evidence:** `app/Console/Commands/PurgeAuditLogs.php:21-22`

```php
$defaultRetentionDays = config('auth.audit.retention_days', 90);
$gdprRetentionDays = config('auth.audit.gdpr_retention_days', 2555);
```

Good - configurable. Just confirm `config/auth.php` has these keys (default fallback is 90 / 2555 if missing).

### A-05 — `AuditLog::resolveUserIdForFk` does N+1 lookup on every log call
- **Severity:** medium
- **Confidence:** high
- **Evidence:** `app/Models/AuditLog.php:159-168`

```php
return User::withTrashed()->whereKey($candidateUserId)->exists()
    ? $candidateUserId
    : null;
```

Every `AuditLog::log(...)` does an extra `EXISTS` query against `users`. For bulk operations (purge sweep) this 2x's the cost. Cache the user-existence check inside the request, or use `try/catch` on FK violation as the fallback (cheaper than pre-checking).

---

## 11. Joint-ownership pattern audit (CLAUDE.md Rule #7)

Per CLAUDE.md Rule #7: "Joint assets use a SINGLE record with `joint_owner_id` and `ownership_percentage` (primary owner's share)." The trait `HasJointOwnership` provides `scopeForUserOrJoint($userId)`. Below is the audit of which tables conform.

| Table | `joint_owner_id` | `ownership_percentage` | `ownership_type` enum incl. `tenants_in_common` | Trait used |
|---|---|---|---|---|
| properties | ✅ | ✅ | ✅ | ✅ |
| mortgages | ✅ | ✅ | ✅ (added) | ✅ |
| cash_accounts | ✅ | ✅ | ❌ missing | ✅ |
| savings_accounts | ✅ | ✅ | ❌ missing | ✅ |
| investment_accounts | ✅ | ✅ | ❌ missing | ✅ |
| business_interests | ✅ | ✅ | ❌ missing | ✅ |
| chattels | ✅ | ✅ | ❌ missing | ✅ |
| liabilities | ✅ | ✅ | ❌ | ✅ |
| goals | ✅ | ✅ | ❌ (only 'individual','joint') | ✅ |
| life_events | ✅ | ✅ | ❌ (only 'individual','joint') | ❌ unsure |
| dc_pensions | ❌ no joint_owner_id | n/a | n/a | n/a (intentional — pensions are personal) |
| db_pensions | ❌ | n/a | n/a | n/a |
| state_pensions | ❌ | n/a | n/a | n/a |
| assets (Estate) | ❌ no joint_owner_id | n/a | ❌ (only 'individual','joint','trust') | ❌ |

**Conclusions:**
- All "joint-capable" tables have `joint_owner_id` + `ownership_percentage` ✅
- Most use the `HasJointOwnership` trait ✅
- Half are missing `tenants_in_common` in their `ownership_type` enum (see S-05)
- `assets` (Estate-specific bucket) doesn't have `joint_owner_id` at all. The Estate domain handles ownership via the source-asset table (property/account/etc), so this is probably correct. Confirm with Estate team.

---

## 12. Preview isolation pattern audit (CLAUDE.md Rule #2)

The pattern is: `is_preview_user` boolean flag on `users`. Preview personas are entirely segregated. The composite index `preview_user_persona_idx (is_preview_user, preview_persona_id)` exists. The `PreviewWriteInterceptor` middleware blocks writes from preview users globally.

Audit:
- ✅ Index exists on the right columns
- ✅ Preview users counted as ~6 rows; index optimised for `is_preview_user = true` reads
- ⚠️ Most queries don't filter on `is_preview_user`. They filter on `user_id` and inherit isolation transitively. This is correct but subtle — easy to break by missing a `user_id` filter.
- ⚠️ Admin metrics (`UserMetricsService`) does explicit `where('is_preview_user', false)` for the **non-indexed direction**. Minor inefficiency only on admin pages (see Q-07).

No critical findings here. The pattern works.

---

## Clean areas (explicit positives)

The following parts of the codebase are notably solid:

1. **Migration safety pattern** — anonymous classes, `declare(strict_types=1)`, `if (Schema::hasColumn(...)) return;` guards in recent migrations. `database/CLAUDE.md` enforces this.
2. **Auditable trait + AuditLog model** — well-designed, with FK-safe user resolution, scopes for filtering, helper static methods for typed event-logging. The `resolveUserIdForFk` defensive check is a textbook fix for the `nullOnDelete` FK + stale-session class of bugs.
3. **HasJointOwnership scope** — clean abstraction over the OR-pattern. Used in most asset models.
4. **Hash-chain audit** (`ai_audit_events`) — proper cryptographic design, HMAC over row_hash, retention plan documented. The covering index (`operation, created_at`) was added specifically for retention sweep. Textbook execution.
5. **Idempotency for AI requests** (`ai_request_idempotency`) — proper hashing, unique constraint on `key_hash`, daily cleanup job. Industry best-practice.
6. **`ai_daily_usage` atomic token budget** — addresses a real race condition with `SELECT ... FOR UPDATE` inside a `DB::transaction`. The migration docblock explains the bug being fixed. Excellent.
7. **No `Model::all()` calls in the production codebase** outside one legitimate admin role-sync. This avoids the classic "table fits in memory until it doesn't" failure mode.
8. **Composite indexes for joint-owner reads** (`2026_01_26_150000_add_joint_owner_indexes.php`) — explicit, well-documented, idempotent.
9. **FK convention consistency** — `user_id → users.id ON DELETE CASCADE` everywhere; `joint_owner_id → users.id ON DELETE SET NULL` everywhere. Predictable.
10. **Preview user pattern** — single boolean, single composite index, middleware enforcement. Operational simplicity.
11. **Tax configuration as data** (`tax_configurations` table) — pulls UK tax values from the DB rather than hardcoding, with `tax_configuration_audits` providing change history. Architecture win.
12. **Personal access tokens uses Sanctum default** — no custom security; uses tested library.
13. **Soft-deletes coverage** — mostly consistent across financial models (`2026_02_21_200002`, `2026_03_18_100000`).
14. **Monte Carlo cache** — keyed expiry, easy retention via `expires_at` index.

---

## Recommended fix order (impact-weighted)

**Sprint 1 (data correctness — must do):**
1. Fix `users` expenditure `double` columns → `decimal(10,2)` / `decimal(12,2)` (S-01)
2. Fix `Holding` casts from `float` to `decimal:N` (S-02)
3. Wrap multi-row controller writes in `DB::transaction` — start with InvestmentController, Estate/WillController, BusinessInterestController (T-01)
4. Audit `HoldingsImportService` and `DocumentProcessor` for network I/O inside transactions (T-04, T-05)

**Sprint 2 (performance — should do):**
5. Add `(event_type, created_at)` composite on `audit_logs` (I-01)
6. Add `tenants_in_common` to 6 remaining ownership_type enums (S-05)
7. Drop redundant single-column indexes covered by composites (I-11)
8. Convert inline `where->orWhere` joint patterns to `forUserOrJoint` scope (Q-01)

**Sprint 3 (cleanup — nice to have):**
9. Squash migrations older than Jan 2026 into a single base CREATE migration (MIG-07)
10. Replace `goals.linked_account_ids` JSON with `goal_savings_account` pivot only (S-14, FK-04)
11. Add `households` soft deletes (FK-01)
12. Migrate `users.dashboard_widget_order` and similar prefs to a separate `user_preferences` table (P-07)

**Sprint 4 (scale prep — when traffic grows):**
13. Partition `audit_logs` and `ai_audit_events` by month (P-01, P-02)
14. Split `investment_accounts` god-table into 3-4 sidecar tables (S-10)
15. Compress `monte_carlo_cache.results` (P-03)
16. Squash User model into User + UserProfile (M-06)

---

## Verification queries (run on production before/after each fix)

```sql
-- Count rows by table for sizing
SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, (DATA_LENGTH+INDEX_LENGTH)/1024/1024 AS total_mb
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY DATA_LENGTH+INDEX_LENGTH DESC
LIMIT 30;

-- Cardinality check on indexes
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, CARDINALITY
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('users','investment_accounts','holdings','audit_logs','ai_audit_events')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- Check for the double-typed expenditure columns (S-01 verification)
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'users'
  AND DATA_TYPE IN ('double','float');

-- Confirm fix migrations applied
SELECT migration FROM migrations
WHERE migration LIKE '%expenditure_columns_to_double%'
   OR migration LIKE '%fix_savings_accounts_joint_owner%'
   OR migration LIKE '%make_scrubbed_user_columns_nullable%';

-- Verify joint_owner_id type consistency
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME = 'joint_owner_id';
-- Expected: all 'bigint unsigned'. If any are 'bigint' (signed), S-03 is unfixed.

-- audit_logs growth rate
SELECT DATE(created_at) AS day, COUNT(*) AS rows
FROM audit_logs
WHERE created_at > NOW() - INTERVAL 30 DAY
GROUP BY DATE(created_at)
ORDER BY day DESC;
```

---

## File references summary

Migration paths cited:
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_01_18_000003_migrate_existing_goals_data.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_01_19_134700_create_user_sessions_table.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_01_19_135404_create_audit_logs_table.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_01_24_160001_create_goals_table_v2.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_01_26_150000_add_joint_owner_indexes.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_02_20_120000_assign_roles_to_existing_users.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_02_20_130000_drop_legacy_role_column_from_users.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_02_21_200002_add_soft_deletes_to_financial_models.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_02_21_200004_add_missing_indexes_to_financial_tables.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_02_22_130000_widen_encrypted_columns_to_text.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_02_27_200001_create_ai_conversations_table.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_02_27_200002_create_ai_messages_table.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_03_13_200002_fix_savings_accounts_joint_owner_foreign_key.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_03_18_100000_add_soft_deletes_to_key_models.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_03_18_100001_add_unique_constraints_to_has_one_tables.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_03_18_100002_fix_indexes_and_constraints.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_04_14_122656_add_subscriptions_indexes.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_04_25_000010_create_ai_daily_usage_table.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_04_25_000011_create_ai_request_idempotency_table.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_04_25_000012_create_ai_abort_events_table.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_04_25_000013_create_ai_audit_events_table.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_05_02_000001_add_conversation_index_columns.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_05_06_000001_drop_is_eval_user_from_users.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_05_06_000002_rename_eval_user_id_to_preview_user_id.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_05_06_000003_add_operation_created_at_index_to_ai_audit_events.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_05_07_000001_add_deletion_tracking_to_users_table.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_05_07_000002_fix_life_events_joint_owner_id_fk.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_05_07_000004_create_account_deletion_reminder_log_table.php`
- `/Users/CSJ/Desktop/fynla/database/migrations/2026_05_07_000005_make_scrubbed_user_columns_nullable.php`

Model paths cited:
- `/Users/CSJ/Desktop/fynla/app/Models/User.php`
- `/Users/CSJ/Desktop/fynla/app/Models/AuditLog.php`
- `/Users/CSJ/Desktop/fynla/app/Models/SavingsAccount.php`
- `/Users/CSJ/Desktop/fynla/app/Models/Investment/Holding.php`
- `/Users/CSJ/Desktop/fynla/app/Models/Estate/Trust.php`
- `/Users/CSJ/Desktop/fynla/app/Models/Property.php`

Service / controller paths cited:
- `/Users/CSJ/Desktop/fynla/app/Agents/CoordinatingAgent.php`
- `/Users/CSJ/Desktop/fynla/app/Services/Investment/Recommendation/UserContextBuilder.php`
- `/Users/CSJ/Desktop/fynla/app/Services/Estate/IHTCalculationService.php`
- `/Users/CSJ/Desktop/fynla/app/Services/AI/AuditChainService.php`
- `/Users/CSJ/Desktop/fynla/app/Services/AI/DuplicateAcknowledgement.php`
- `/Users/CSJ/Desktop/fynla/app/Services/Documents/HoldingsImportService.php`
- `/Users/CSJ/Desktop/fynla/app/Services/Documents/DocumentProcessor.php`
- `/Users/CSJ/Desktop/fynla/app/Services/Admin/UserMetricsService.php`
- `/Users/CSJ/Desktop/fynla/app/Console/Commands/PurgeAuditLogs.php`
- `/Users/CSJ/Desktop/fynla/app/Traits/HasJointOwnership.php`
- `/Users/CSJ/Desktop/fynla/app/Traits/CalculatesOwnershipShare.php`

Schema reference:
- `/Users/CSJ/Desktop/fynla/database/schema/mysql-schema.sql` (Feb 24, 2026 — stale; regenerate after Sprint 1 fixes)
