# Database Conventions

This file supplements the root `CLAUDE.md` with database-specific patterns.

> **GOLDEN RULE #20 (CSJ, NEVER IGNORE):** every Fyn change — prompt, vocabulary, behaviour, rendering — is made ONCE, in ONE place, for ALL surfaces and paths. If more than one mechanism implements the behaviour, consolidating to one source is PART of the fix. Full text: root `CLAUDE.md` Rule 20.

**CRITICAL: NEVER use `migrate:fresh` or `migrate:refresh`. These DROP ALL TABLES. Use `php artisan db:seed` to reseed.**

## Migrations

**File naming:** `YYYY_MM_DD_HHMMSS_{action}_{table_name}_table.php`

**Structure:** Anonymous class pattern with strict types:
```php
<?php
declare(strict_types=1);

return new class extends Migration {
    public function up(): void { /* Schema::create or Schema::table */ }
    public function down(): void { /* Schema::dropIfExists or reverse */ }
};
```

**Safety checks** (prevent errors on rerun):
```php
if (Schema::hasTable('table_name')) { return; }
if (Schema::hasColumn('table', 'column')) { return; }
```

**Run a new migration once before it lands.** A migration that has never executed
is invisible until it takes out every batch's test suite at the same moment:
`RefreshDatabase` applies all pending migrations, so a broken one fails every
DB-touching test with **0 assertions**, in every per-batch database identically.
That looks like the deadlock contention mode but does not clear on retry.

**On a shared dev database, migrate ONE file, not everything:**
```bash
php artisan migrate --path=database/migrations/2026_08_21_120000_my_migration.php
```
Bare `php artisan migrate` applies *every* pending migration, including other
people's — and a data migration is theirs to run, not yours. This has already
happened: one agent's bare `migrate` swept up two other batches' data migrations
in the same batch number.

**MySQL will not accept a bound parameter in DDL.** This fails with a syntax error
near `?`:
```php
DB::statement('ALTER TABLE `t` MODIFY `c` DECIMAL(5,2) NULL COMMENT ?', [$comment]);  // WRONG
```
Inline it instead, escaping any quotes. Only ever inline a value you control —
never request input:
```php
$comment = str_replace("'", "''", self::COLUMN_COMMENT);
DB::statement("ALTER TABLE `t` MODIFY `c` DECIMAL(5,2) NULL COMMENT '{$comment}'");
```

**A data migration must also fix what was DERIVED from the bad data.** Correcting a
source column while leaving a cached/derived column computed from its old value
means the fix looks complete and the wrong number still renders. `db_pensions`
carries `spouse_pension_projected_gbp`; migration `2026_08_21_120000` recalculates
it for every row it corrects, and logs each change so a populated environment
leaves a deploy-log record of exactly what moved.

## Common Column Patterns

**Standard columns** (most tables):
- `id()` - Auto-incrementing primary key
- `foreignId('user_id')->constrained()->cascadeOnDelete()`
- `timestamps()` - created_at, updated_at
- `softDeletes()` - deleted_at (where applicable)

**Joint ownership columns** (properties, savings, investments, goals, etc.):
- `foreignId('joint_owner_id')->nullable()->constrained('users')->onDelete('set null')`
- `enum('ownership_type', ['individual', 'joint', 'tenants_in_common', 'trust'])`
- `decimal('ownership_percentage', 5, 2)` - Primary owner's share

**Decimal precision:**
- Currency: `decimal('field', 15, 2)` - up to 999,999,999,999.99
- Rates: `decimal('field', 5, 4)` - e.g., 0.0500 = 5%
- Percentages: `decimal('field', 5, 2)` - 0.00 to 100.00

## Foreign Keys

```php
// Owned data - cascade delete when user deleted
$table->foreignId('user_id')->constrained()->cascadeOnDelete();

// Joint ownership - preserve record, null the link
$table->foreignId('joint_owner_id')->nullable()->constrained('users')->onDelete('set null');
```

## Enum Values (Canonical)

Never deviate from these:
- **Ownership:** `individual`, `joint`, `tenants_in_common`, `trust` (never `sole`)
- **Property:** `main_residence`, `secondary_residence`, `buy_to_let`
- **Mortgage:** `repayment`, `interest_only`, `mixed`
- **Status:** `active`, `paused`, `completed`, `abandoned`
- **Priority:** `critical`, `high`, `medium`, `low`
- **Frequency:** `weekly`, `monthly`, `quarterly`, `annually`

## JSON Columns

Used for flexible/nested data:
- `config_data` - TaxConfiguration stores full tax year config
- `old_values`, `new_values` - AuditLog tracks data changes
- `metadata` - AuditLog context
- `milestones`, `projection_data` - Goal progress tracking

## Index Patterns

```php
// Single column
$table->index('user_id');

// Composite (for common query patterns)
$table->index(['user_id', 'status']);
$table->index(['user_id', 'created_at']);

// Unique
$table->unique(['rate_key', 'tax_year']);
```

Always index `joint_owner_id` for the `WHERE user_id = ? OR joint_owner_id = ?` query pattern.

## Seeders

**Seeder classes** live in `database/seeders/`, orchestrated by `DatabaseSeeder`.

**Phase 1 — Required Data** (always runs, 18 seeders, executed in this order):
1. TaxConfigurationSeeder - 5 UK tax years
2. TaxProductReferenceSeeder - ISA/GIA/Bond tax treatment
3. ActuarialLifeTablesSeeder - Life expectancy data
4. RolesPermissionsSeeder - Auth roles and permissions (before AdminUserSeeder)
5. AdminUserSeeder - Admin test accounts
6. PreviewUserSeeder - 6 preview personas
7. SavingsMarketRatesSeeder - Savings benchmark rates
8. OccupationCodeSeeder - ONS SOC 2020 occupation codes
9. PlanConfigurationSeeder - Plan templates and admin-configurable values
10. RetirementActionDefinitionSeeder - Retirement action triggers
11. InvestmentActionDefinitionSeeder - Investment action triggers
12. SavingsActionDefinitionSeeder - Savings action triggers
13. ProtectionActionDefinitionSeeder - Protection action triggers
14. TaxActionDefinitionSeeder - Tax action triggers
15. EstateActionDefinitionSeeder - Estate action triggers
16. SubscriptionPlanSeeder - Subscription plan pricing
17. DiscountCodeSeeder - Promotional discount codes
18. ExistingInsightsMetadataSeeder - Insights article metadata (bodies stay in Vue)

**Phase 2 — Optional Dev Data** (only runs when `APP_ENV` is `local`, `development`, or `staging`):
19. HouseholdSeeder - Household linking
20. TestUsersSeeder - Test users with full data
21. ChrisUserSeeder - chris@fynla.org account (mirrors production)
22. AdvisorClientSeeder - Advisor demo relationships

**Idempotency:** Always use `updateOrCreate()` with unique keys to prevent duplicates on reseed.

**Preview persona data:** Loaded from JSON files at `resources/js/data/personas/{personaId}.json` (6 files: `entrepreneur`, `peak_earners`, `retired_couple`, `student`, `young_family`, `young_saver`).

## Factories

Factories live in `database/factories/`. Structure:
```php
class MyModelFactory extends Factory {
    protected $model = MyModel::class;

    public function definition(): array {
        return ['field' => fake()->value()];
    }

    // State methods for variants
    public function mainResidence(): static {
        return $this->state(fn () => ['property_type' => 'main_residence']);
    }
}
```

Use `fake()` (not `$this->faker`). Chain states: `Model::factory()->state1()->state2()->create()`.


## A `NOT NULL DEFAULT` makes "never asked" indistinguishable from "chose this"

**Added 2026-08-22**, from the expenditure-sharing work.

`users.expenditure_sharing_mode` is `enum('joint','separate') NOT NULL DEFAULT 'joint'`.
**So a married user who has never opened the form, never seen the toggle and never formed
a view reads identically to one who deliberately chose Joint.** Live shape on dev when
this was found: **19 users, all `joint`, zero `separate`, 12 with a spouse — nobody has
ever chosen. Every value is the default.**

**The column had already turned an unanswered question into an answer before any feature
read it.** Any code treating that value as a declaration inherits the fabrication — and
a rule like *"if no preference is recorded, ask"* has nothing to detect, because the
unanswered state is not expressible.

**This is the same defect as a tri-state column behind a two-state control** (`NULL` /
`true` / `false` rendered by a falsy check, so *"we have not asked you"* and *"you told
us no"* look identical). Both destroy the distinction between an absent fact and a
chosen one — which is the distinction half this board's defects turn on.

**When adding a column that records a user's choice, ask what "not yet asked" looks
like.** If the answer is "the same as one of the choices", either make it nullable or add
a `..._declared_at` companion. **A default is a convenience for the schema, not a
statement by the user** — and code downstream cannot tell the difference.

**Where a default must stand, the consequence is disclosure, not arithmetic:** a surface
acting on a defaulted value should say what it assumed, the way a form does by showing
the setting beside the input. A surface with no such affordance — a chat turn, an API
call — cannot rely on it silently.
