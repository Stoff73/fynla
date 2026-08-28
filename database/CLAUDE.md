# Database Conventions

Supplements the root `CLAUDE.md`. **Before adding a column that records a user's choice, load the `data-integrity-traps` skill** — a `NOT NULL DEFAULT` makes "never asked" indistinguishable from "chose this", and that distinction is what half the defect board turns on.

**NEVER `migrate:fresh` or `migrate:refresh`.** They drop all tables. Reseed with `php artisan db:seed`.

## Migrations

Naming `YYYY_MM_DD_HHMMSS_{action}_{table}_table.php`; anonymous class, `declare(strict_types=1)`, `up()` and `down()`.

Guard against reruns: `if (Schema::hasTable('x')) { return; }`, `if (Schema::hasColumn('t','c')) { return; }`.

**Run a new migration once before it lands.** A migration that has never executed is invisible until it takes out every batch's suite simultaneously: `RefreshDatabase` applies all pending migrations, so a broken one fails every DB-touching test with **0 assertions**, identically in every per-batch database. That mimics the deadlock contention mode but does not clear on retry.

**On a shared dev database, migrate ONE file, not everything:**

```bash
php artisan migrate --path=database/migrations/2026_08_21_120000_my_migration.php
```

Bare `php artisan migrate` applies *every* pending migration including other people's — and a data migration is theirs to run. This has already happened once.

**MySQL will not accept a bound parameter in DDL.** `ALTER TABLE ... COMMENT ?` is a syntax error. Inline it, escaping quotes, and **only ever inline a value you control** — never request input:

```php
$comment = str_replace("'", "''", self::COLUMN_COMMENT);
DB::statement("ALTER TABLE `t` MODIFY `c` DECIMAL(5,2) NULL COMMENT '{$comment}'");
```

**A data migration must also fix what was DERIVED from the bad data.** Correcting a source column while leaving a cached or derived column computed from its old value means the fix looks complete and the wrong number still renders. `db_pensions.spouse_pension_projected_gbp` is the worked case — recalculate it for every row corrected, and log each change so a populated environment keeps a deploy-log record.

## Columns

- Standard: `id()`, `foreignId('user_id')->constrained()->cascadeOnDelete()`, `timestamps()`, `softDeletes()` where applicable.
- Joint ownership: `foreignId('joint_owner_id')->nullable()->constrained('users')->onDelete('set null')` (preserve the record, null the link), `enum('ownership_type', [...])`, `decimal('ownership_percentage', 5, 2)` = the primary owner's share.
- Precision: currency `decimal(15,2)`; rates `decimal(5,4)` (0.0500 = 5%); percentages `decimal(5,2)`.

**Always index `joint_owner_id`** — the `WHERE user_id = ? OR joint_owner_id = ?` pattern is everywhere. Composites for common pairs (`['user_id','status']`, `['user_id','created_at']`).

## Canonical enums — never deviate

- **Ownership:** `individual` (never `sole`), `joint`, `tenants_in_common`, `trust`
- **Property:** `main_residence`, `secondary_residence`, `buy_to_let`
- **Mortgage:** `repayment`, `interest_only`, `mixed`
- **Status:** `active`, `paused`, `completed`, `abandoned`
- **Priority:** `critical`, `high`, `medium`, `low`
- **Frequency:** `weekly`, `monthly`, `quarterly`, `annually`

**A validation rule and its column must agree — but the two directions are not symmetric.** See the `data-integrity-traps` skill before writing a guard.

## JSON columns

`config_data` (TaxConfiguration's full year config), `old_values`/`new_values`/`metadata` (AuditLog), `milestones`/`projection_data` (goals).

## Seeders

`DatabaseSeeder` runs two phases. **Phase 1 (always)** — 18 required seeders; order matters, notably `RolesPermissionsSeeder` **before** `AdminUserSeeder`. **Phase 2 (only when `APP_ENV` is local/development/staging)** — Household, TestUsers, Chris, AdvisorClient.

**Idempotency: always `updateOrCreate()` with unique keys**, or reseeding duplicates rows.

Preview persona data loads from `resources/js/data/personas/{personaId}.json`.

## Factories

State methods for variants, chainable: `Asset::factory()->ihtExempt()->joint()->create()`. Use `fake()`, not `$this->faker`.
