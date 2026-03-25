# Database Conventions

This file supplements the root `CLAUDE.md` with database-specific patterns.

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

**Execution order** (from `DatabaseSeeder.php`, 20 seeders):
1. TaxConfigurationSeeder - 5 UK tax years
2. TaxProductReferenceSeeder - ISA/GIA/Bond tax treatment
3. ActuarialLifeTablesSeeder - Life expectancy data
4. RolesPermissionsSeeder - Auth roles and permissions
5. AdminUserSeeder - Admin test accounts
6. PreviewUserSeeder - 6 preview personas
7. SavingsMarketRatesSeeder - Savings benchmark rates
8. OccupationCodeSeeder - 406 occupation codes
9. PlanConfigurationSeeder - Plan templates
10. RetirementActionDefinitionSeeder - Retirement action triggers
11. InvestmentActionDefinitionSeeder - Investment action triggers
12. SavingsActionDefinitionSeeder - Savings action triggers
13. ProtectionActionDefinitionSeeder - Protection action triggers
14. TaxActionDefinitionSeeder - Tax action triggers
15. EstateActionDefinitionSeeder - Estate action triggers
16. SubscriptionPlanSeeder - Subscription plan pricing
17. AdvisorClientSeeder - Advisor demo data
18. HouseholdSeeder - Household linking
19. TestUsersSeeder - Test users with full data (dev/staging only)

**Idempotency:** Always use `updateOrCreate()` with unique keys to prevent duplicates on reseed.

**Preview persona data:** Loaded from JSON files at `resources/js/data/personas/{personaId}.json`.

## Factories

55 factories in `database/factories/`. Structure:
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
