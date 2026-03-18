# Advisor Dashboard Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a financial advisor dashboard where advisors can view and manage their assigned clients, track communications and reviews, generate suitability reports, and impersonate client profiles with full audit logging.

**Architecture:** New `advisor_clients` and `client_activities` tables linked to existing User model. `is_advisor` boolean flag on users (independent of RBAC role — a user can be both admin and advisor). Cache-based impersonation (no token replacement). `AdvisorMiddleware` checks `is_advisor` flag. New `UserModuleTrackingService` aggregates module data per client (created in this plan, also reusable by future admin enhancement). Household/couples appear as single rows via `spouse_id` linking. Full audit trail via existing `AuditLog`.

**Tech Stack:** Laravel 10, Vue.js 3, Vuex, Pest testing, MySQL 8, Laravel Cache

**Spec:** `docs/superpowers/specs/2026-03-17-admin-advisor-design.md` (Feature 2)

**Worktree:** `feature/advisor-dashboard`

**Self-contained:** No external dependencies. `UserModuleTrackingService` is created in Task 4 of this plan.

**Design guide:** `fynlaDesignGuide.md` v1.2.0 — MUST be read before any UI work.

---

## File Map

### New Files

| File | Responsibility |
|------|---------------|
| `database/migrations/2026_03_17_200001_add_is_advisor_to_users_table.php` | Add `is_advisor` boolean column |
| `database/migrations/2026_03_17_200002_create_advisor_clients_table.php` | Advisor-client relationship table |
| `database/migrations/2026_03_17_200003_create_client_activities_table.php` | Communication/report tracking table |
| `app/Models/AdvisorClient.php` | Advisor-client relationship model |
| `app/Models/ClientActivity.php` | Activity tracking model |
| `database/seeders/AdvisorClientSeeder.php` | Seed preview personas as clients of chris@fynla.org |
| `database/factories/AdvisorClientFactory.php` | Factory for tests |
| `database/factories/ClientActivityFactory.php` | Factory for tests |
| `app/Services/Admin/UserModuleTrackingService.php` | Aggregates P S I R E module status per user (household-aware) |
| `app/Services/Advisor/AdvisorDashboardService.php` | Dashboard stats, client list, activity feed |
| `app/Services/Advisor/ClientActivityService.php` | CRUD for client activities |
| `app/Services/Advisor/AdvisorImpersonationService.php` | Enter/exit client profiles via cache |
| `app/Http/Controllers/Api/AdvisorController.php` | All advisor API endpoints |
| `app/Http/Requests/StoreClientActivityRequest.php` | Validation for activity logging |
| `app/Http/Middleware/AdvisorMiddleware.php` | Checks `is_advisor` flag |
| `app/Http/Middleware/AdvisorImpersonationMiddleware.php` | Resolves impersonated user from cache |
| `resources/js/layouts/AdvisorLayout.vue` | Sidebar + content layout with "ADVISOR VIEW" badge |
| `resources/js/views/Advisor/AdvisorDashboard.vue` | Main dashboard page |
| `resources/js/views/Advisor/AdvisorClientList.vue` | Full client table view |
| `resources/js/views/Advisor/AdvisorClientDetail.vue` | Read-only client overview |
| `resources/js/views/Advisor/AdvisorActivityLog.vue` | Activity feed + log form |
| `resources/js/views/Advisor/AdvisorReviewsDue.vue` | Review management |
| `resources/js/views/Advisor/AdvisorReports.vue` | Suitability report tracking |
| `resources/js/components/Advisor/ClientModuleDots.vue` | P S I R E status dots |
| `resources/js/components/Advisor/ClientActivityForm.vue` | Modal form to log activity |
| `resources/js/components/Advisor/AdvisorBanner.vue` | Top banner during impersonation |
| `resources/js/store/modules/advisor.js` | Vuex store for advisor state |
| `resources/js/services/advisorService.js` | API wrapper for advisor endpoints |
| `tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php` | Module tracking tests (household-aware) |
| `tests/Unit/Services/Advisor/AdvisorDashboardServiceTest.php` | Dashboard service tests |
| `tests/Unit/Services/Advisor/AdvisorImpersonationServiceTest.php` | Impersonation logic tests |
| `tests/Feature/Api/AdvisorControllerTest.php` | API endpoint tests |
| `tests/Feature/Middleware/AdvisorMiddlewareTest.php` | Middleware tests |

### Modified Files

| File | Change |
|------|--------|
| `app/Models/User.php` | Add `is_advisor` to `$guarded`/`$casts`, add relationships, add `isAdvisor()` accessor |
| `app/Models/Role.php` (lines 13-25) | Add `ROLE_ADVISOR` and `LEVEL_ADVISOR` constants, `getAdvisorRole()` static |
| `app/Models/Permission.php` (lines 30-40) | Add `ADVISOR_ACCESS` constant |
| `app/Services/Auth/PermissionService.php` (lines 112-180) | Add advisor role + permissions to `syncDefaultRolesAndPermissions()` |
| `app/Models/AuditLog.php` | No changes needed (uses existing `EVENT_ADMIN`) |
| `app/Http/Middleware/PreviewWriteInterceptor.php` (lines 47-68) | Add advisor routes to `EXCLUDED_ROUTES` |
| `routes/api.php` (after admin routes) | Add advisor route group |
| `resources/js/router/index.js` (lines 743-755, 1054-1069) | Add advisor routes and `requiresAdvisor` guard |
| `resources/js/store/modules/auth.js` (lines 13-24) | Add `isAdvisor` getter |
| `database/seeders/DatabaseSeeder.php` | Register `AdvisorClientSeeder` |

---

## Task 1: Database Migrations

**Files:**
- Create: `database/migrations/2026_03_17_200001_add_is_advisor_to_users_table.php`
- Create: `database/migrations/2026_03_17_200002_create_advisor_clients_table.php`
- Create: `database/migrations/2026_03_17_200003_create_client_activities_table.php`

- [ ] **Step 1: Create is_advisor migration**

```bash
php artisan make:migration add_is_advisor_to_users_table
```

Add `is_advisor` boolean column defaulting to `false`, placed after `is_admin`.

- [ ] **Step 2: Create advisor_clients migration**

```bash
php artisan make:migration create_advisor_clients_table
```

Schema per spec section 2.1: `advisor_id`, `client_id` (both FK to users), `status` enum (active/inactive/pending), `assigned_date`, `last_review_date`, `next_review_due`, `review_frequency_months`, `notes`, timestamps. Unique constraint on `(advisor_id, client_id)`. Indexes on `(advisor_id, status)` and `next_review_due`.

- [ ] **Step 3: Create client_activities migration**

```bash
php artisan make:migration create_client_activities_table
```

Schema per spec section 2.1: `advisor_client_id` (FK to advisor_clients ON DELETE CASCADE), `advisor_id`, `client_id` (both FK to users), `activity_type` enum, `summary` varchar(500), `details` text nullable, `activity_date` datetime, `follow_up_date` date nullable, `follow_up_completed` boolean, `report_type` varchar(100) nullable, `report_sent_date` date nullable, `report_acknowledged_date` date nullable, timestamps. Indexes on `advisor_client_id`, `(advisor_id, client_id)`, `activity_type`, `activity_date`, `(follow_up_date, follow_up_completed)`.

- [ ] **Step 4: Run migrations**

```bash
php artisan migrate
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/*is_advisor* database/migrations/*advisor_clients* database/migrations/*client_activities*
git commit -m "feat: add advisor database schema (users.is_advisor, advisor_clients, client_activities)"
```

---

## Task 2: Models, RBAC & User Model Changes

**Files:**
- Create: `app/Models/AdvisorClient.php`
- Create: `app/Models/ClientActivity.php`
- Create: `database/factories/AdvisorClientFactory.php`
- Create: `database/factories/ClientActivityFactory.php`
- Modify: `app/Models/User.php` (lines 28-37, 68-140, 145-155)
- Modify: `app/Models/Role.php` (lines 13-25, add static helper)
- Modify: `app/Models/Permission.php` (lines 30-40)
- Modify: `app/Services/Auth/PermissionService.php` (lines 112-180)

- [ ] **Step 1: Create AdvisorClient model**

Create `app/Models/AdvisorClient.php`:

```php
<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdvisorClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'advisor_id', 'client_id', 'status', 'assigned_date',
        'last_review_date', 'next_review_due', 'review_frequency_months', 'notes',
    ];

    protected $casts = [
        'assigned_date' => 'date',
        'last_review_date' => 'date',
        'next_review_due' => 'date',
        'review_frequency_months' => 'integer',
    ];

    public function advisor(): BelongsTo { return $this->belongsTo(User::class, 'advisor_id'); }
    public function client(): BelongsTo { return $this->belongsTo(User::class, 'client_id'); }
    public function activities(): HasMany { return $this->hasMany(ClientActivity::class); }

    public function scopeActive($query) { return $query->where('status', 'active'); }
    public function scopeForAdvisor($query, int $advisorId) { return $query->where('advisor_id', $advisorId); }
}
```

- [ ] **Step 2: Create ClientActivity model**

Create `app/Models/ClientActivity.php`:

```php
<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'advisor_client_id', 'advisor_id', 'client_id', 'activity_type',
        'summary', 'details', 'activity_date', 'follow_up_date',
        'follow_up_completed', 'report_type', 'report_sent_date', 'report_acknowledged_date',
    ];

    protected $casts = [
        'activity_date' => 'datetime',
        'follow_up_date' => 'date',
        'follow_up_completed' => 'boolean',
        'report_sent_date' => 'date',
        'report_acknowledged_date' => 'date',
    ];

    public function advisorClient(): BelongsTo { return $this->belongsTo(AdvisorClient::class); }
    public function advisor(): BelongsTo { return $this->belongsTo(User::class, 'advisor_id'); }
    public function client(): BelongsTo { return $this->belongsTo(User::class, 'client_id'); }

    public function scopeOfType($query, string $type) { return $query->where('activity_type', $type); }
    public function scopeReports($query) { return $query->where('activity_type', 'suitability_report'); }
}
```

- [ ] **Step 3: Create factories**

Create `database/factories/AdvisorClientFactory.php`:

```php
<?php
declare(strict_types=1);
namespace Database\Factories;

use App\Models\AdvisorClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdvisorClientFactory extends Factory
{
    protected $model = AdvisorClient::class;

    public function definition(): array
    {
        return [
            'advisor_id' => User::factory(),
            'client_id' => User::factory(),
            'status' => 'active',
            'assigned_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'last_review_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'next_review_due' => fake()->dateTimeBetween('now', '+6 months'),
            'review_frequency_months' => 12,
            'notes' => null,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'next_review_due' => fake()->dateTimeBetween('-3 months', '-1 day'),
        ]);
    }
}
```

Create `database/factories/ClientActivityFactory.php`:

```php
<?php
declare(strict_types=1);
namespace Database\Factories;

use App\Models\ClientActivity;
use App\Models\AdvisorClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientActivityFactory extends Factory
{
    protected $model = ClientActivity::class;

    public function definition(): array
    {
        return [
            'advisor_client_id' => AdvisorClient::factory(),
            'advisor_id' => User::factory(),
            'client_id' => User::factory(),
            'activity_type' => fake()->randomElement(['email', 'phone', 'meeting', 'note']),
            'summary' => fake()->sentence(),
            'details' => null,
            'activity_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'follow_up_date' => null,
            'follow_up_completed' => false,
        ];
    }

    public function suitabilityReport(): static
    {
        return $this->state(fn () => [
            'activity_type' => 'suitability_report',
            'report_type' => fake()->randomElement(['protection_review', 'annual_review', 'pension_transfer']),
            'report_sent_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
```

- [ ] **Step 4: Modify User model**

In `app/Models/User.php`:

**a)** Add `'is_advisor'` to `$guarded` array (line 28, after `'is_admin'`):
```php
'is_admin',
'is_advisor',
'is_preview_user',
```

**b)** Add to `$casts` (line 72, after `'is_admin' => 'boolean'`):
```php
'is_advisor' => 'boolean',
```

**c)** `is_advisor` is **independent of role** — do NOT sync it in `booted()`. Unlike `is_admin` which syncs from `role_id`, `is_advisor` is a standalone flag. A user can be admin (role=admin) AND advisor (is_advisor=true) simultaneously. This is set directly via `DB::table('users')->update(['is_advisor' => true])` in the seeder.

**d)** Add relationships (after existing relationships):
```php
public function advisorClients(): HasMany
{
    return $this->hasMany(AdvisorClient::class, 'advisor_id');
}

public function advisors(): HasMany
{
    return $this->hasMany(AdvisorClient::class, 'client_id');
}
```

- [ ] **Step 5: Add ROLE_ADVISOR to Role model**

In `app/Models/Role.php` (after `LEVEL_ADMIN` constant at line 25):

```php
public const ROLE_ADVISOR = 'advisor';
public const LEVEL_ADVISOR = 25;
```

Add static helper (after `getAdminRole()` at line 136):

```php
public static function getAdvisorRole(): ?self
{
    return self::findByName(self::ROLE_ADVISOR);
}
```

- [ ] **Step 6: Add ADVISOR_ACCESS permission constant**

In `app/Models/Permission.php` (after `ADMIN_BACKUP` at line 40):

```php
public const ADVISOR_ACCESS = 'advisor.access';
```

- [ ] **Step 7: Update PermissionService to create advisor role + permissions**

In `app/Services/Auth/PermissionService.php`, inside `syncDefaultRolesAndPermissions()`:

**a)** After the `$adminRole` creation block (~line 140), add:

```php
$advisorRole = Role::firstOrCreate(
    ['name' => Role::ROLE_ADVISOR],
    [
        'display_name' => 'Advisor',
        'description' => 'Financial advisor with client management access',
        'level' => Role::LEVEL_ADVISOR,
    ]
);
```

**b)** Add advisor permission to the `$permissions` array (~line 154):

```php
[Permission::ADVISOR_ACCESS, 'Access Advisor Dashboard', Permission::CATEGORY_ADMIN],
```

**c)** After the admin role permission sync (~line 179), add:

```php
// Advisor role permissions
$advisorPermissions = [
    Permission::ADVISOR_ACCESS,
    Permission::USERS_VIEW,
];

$advisorRole->syncPermissions(
    Permission::whereIn('name', $advisorPermissions)->pluck('id')->toArray()
);
```

**d)** Add `isAdvisor()` helper method (after `isSupport()` at line 79):

```php
public function isAdvisor(User $user): bool
{
    return (bool) $user->is_advisor;
}
```

- [ ] **Step 8: Run RolesPermissionsSeeder to create the role**

```bash
php artisan db:seed --class=RolesPermissionsSeeder --force
```

- [ ] **Step 9: Commit**

```bash
git add app/Models/AdvisorClient.php app/Models/ClientActivity.php app/Models/User.php app/Models/Role.php app/Models/Permission.php app/Services/Auth/PermissionService.php database/factories/AdvisorClientFactory.php database/factories/ClientActivityFactory.php
git commit -m "feat: AdvisorClient and ClientActivity models, RBAC advisor role and permissions"
```

---

## Task 3: Seeder

**Files:**
- Create: `database/seeders/AdvisorClientSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Create AdvisorClientSeeder**

The seeder:
1. Finds chris@fynla.org and sets `is_advisor = true` via `DB::table('users')->where('email', 'chris@fynla.org')->update(['is_advisor' => true])`
2. Finds all primary preview persona users (`is_preview_user = true`, `is_primary_account = true`)
3. Creates `advisor_clients` records linking them to chris@fynla.org with varied `assigned_date`, `last_review_date`, `next_review_due` (some overdue for demo)
4. Seeds 3-5 `client_activities` per client with varied types (email, phone, meeting, suitability_report) and realistic dates

**Avatar colour mapping per persona** (matching mockup exactly):

| Persona | Avatar Colour | Overdue? |
|---------|--------------|----------|
| James & Emily Carter (young_family) | `violet-500` | No |
| David & Sarah Mitchell (peak_earners) | `raspberry-500` | Yes — 92 days overdue |
| Margaret Thompson (widow) | `spring-500` | No |
| Alex Chen (entrepreneur) | `savannah-500` | Yes — 117 days overdue |
| John Morgan (young_saver) | `light-blue-500` | No |
| Robert & Patricia Williams (retired_couple) | `horizon-500` | No |

Store the avatar colour in the seeder's client config array so the frontend can render it. The `advisor_clients.notes` field can store `{"avatar_colour": "violet-500"}` or add a new `avatar_colour` field to the response from `getClientList()`.

**Review date seeding:** Mitchell's `next_review_due` = 2025-12-15 (overdue), Chen's `next_review_due` = 2025-11-20 (overdue). All others have recent or upcoming review dates.

Use `updateOrCreate` keyed on `(advisor_id, client_id)` so re-running is safe.

- [ ] **Step 2: Register in DatabaseSeeder.php**

Add `$this->call(AdvisorClientSeeder::class);`

- [ ] **Step 3: Run seeder**

```bash
php artisan db:seed --class=AdvisorClientSeeder --force
```

- [ ] **Step 4: Verify**

```bash
php artisan tinker --execute="echo \App\Models\AdvisorClient::count() . ' clients, ' . \App\Models\ClientActivity::count() . ' activities';"
```

Expected: `6 clients, ~24 activities`

- [ ] **Step 5: Commit**

```bash
git add database/seeders/AdvisorClientSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat: seed preview personas as advisor clients with sample activities"
```

---

## Task 4: UserModuleTrackingService

**Files:**
- Create: `app/Services/Admin/UserModuleTrackingService.php`
- Test: `tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php`

This service aggregates P S I R E module status per user. It's household-aware: for coupled users (linked via `spouse_id`), it merges both spouses' data so joint assets count once. This service is also reusable by a future admin panel enhancement.

- [ ] **Step 1: Write tests**

Create `tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php`:

```php
it('returns complete status for user with all modules populated');
it('returns partial status for user with some data in a module');
it('returns empty status for user with no data');
it('counts joint assets once not twice for coupled users');
it('merges spouse data when spouse_id is linked');
it('returns skipped status for explicitly skipped modules');
it('eager loads all relationships to prevent N+1');
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/pest tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php
```

- [ ] **Step 3: Implement UserModuleTrackingService**

Create `app/Services/Admin/UserModuleTrackingService.php`:

```php
<?php
declare(strict_types=1);
namespace App\Services\Admin;

use App\Models\User;

class UserModuleTrackingService
{
    /**
     * Get module status for a user (household-aware).
     *
     * Returns: ['protection' => ['status' => 'complete|partial|empty|skipped', 'details' => [...]], ...]
     */
    public function getModuleStatus(User $user): array
    {
        // Eager load all module relationships
        $user->loadMissing([
            'lifeInsurancePolicies', 'criticalIllnessPolicies',
            'incomeProtectionPolicies', 'disabilityPolicies', 'sicknessIllnessPolicies',
            'cashAccounts', 'savingsAccounts', 'isaAccounts',
            'investmentAccounts.holdings', 'riskProfile',
            'dcPensions', 'dbPensions', 'statePension', 'retirementProfile',
            'will', 'lastingPowersOfAttorney', 'trusts', 'gifts', 'assets',
        ]);

        // For coupled users, also load spouse data
        $spouse = null;
        if ($user->spouse_id) {
            $spouse = User::with([
                'lifeInsurancePolicies', 'criticalIllnessPolicies',
                'cashAccounts', 'savingsAccounts',
                'investmentAccounts', 'dcPensions', 'dbPensions',
            ])->find($user->spouse_id);
        }

        return [
            'protection' => $this->protectionStatus($user, $spouse),
            'savings' => $this->savingsStatus($user, $spouse),
            'investment' => $this->investmentStatus($user, $spouse),
            'retirement' => $this->retirementStatus($user, $spouse),
            'estate' => $this->estateStatus($user, $spouse),
        ];
    }

    // Each *Status method returns ['status' => string, 'details' => array]
    // 'complete' = all key sub-areas have data
    // 'partial' = some data entered but key areas missing
    // 'empty' = no records at all
    // 'skipped' = user explicitly skipped this module (check onboarding_skipped_steps)
    // Details include counts and summaries per sub-area

    private function protectionStatus(User $user, ?User $spouse): array
    {
        $skipped = in_array('protection', $user->onboarding_skipped_steps ?? []);
        if ($skipped) { return ['status' => 'skipped', 'details' => []]; }

        $life = $user->lifeInsurancePolicies->count() + ($spouse?->lifeInsurancePolicies->count() ?? 0);
        $ci = $user->criticalIllnessPolicies->count() + ($spouse?->criticalIllnessPolicies->count() ?? 0);
        $ip = $user->incomeProtectionPolicies->count();
        $disability = $user->disabilityPolicies->count();
        $sickness = $user->sicknessIllnessPolicies->count();

        $total = $life + $ci + $ip + $disability + $sickness;
        $status = $total === 0 ? 'empty' : ($life > 0 && $ci > 0 ? 'complete' : 'partial');

        return ['status' => $status, 'details' => compact('life', 'ci', 'ip', 'disability', 'sickness')];
    }

    private function savingsStatus(User $user, ?User $spouse): array
    {
        $skipped = in_array('savings', $user->onboarding_skipped_steps ?? []);
        if ($skipped) { return ['status' => 'skipped', 'details' => []]; }

        $cash = $user->cashAccounts->count() + ($spouse?->cashAccounts->count() ?? 0);
        $savings = $user->savingsAccounts->count() + ($spouse?->savingsAccounts->count() ?? 0);
        $isa = $user->isaAccounts->count();
        $cashTotal = $user->cashAccounts->sum('current_balance') + ($spouse?->cashAccounts->sum('current_balance') ?? 0);
        $savingsTotal = $user->savingsAccounts->sum('current_balance') + ($spouse?->savingsAccounts->sum('current_balance') ?? 0);

        $total = $cash + $savings + $isa;
        $hasEmergencyFund = $cash > 0;
        $status = $total === 0 ? 'empty' : ($cash > 0 && $savings > 0 ? 'complete' : 'partial');

        return ['status' => $status, 'details' => compact('cash', 'savings', 'isa', 'cashTotal', 'savingsTotal', 'hasEmergencyFund')];
    }

    private function investmentStatus(User $user, ?User $spouse): array
    {
        $skipped = in_array('investment', $user->onboarding_skipped_steps ?? []);
        if ($skipped) { return ['status' => 'skipped', 'details' => []]; }

        $accounts = $user->investmentAccounts->count() + ($spouse?->investmentAccounts->count() ?? 0);
        $holdings = $user->investmentAccounts->sum(fn ($a) => $a->holdings->count());
        $totalValue = $user->investmentAccounts->sum('current_value') + ($spouse?->investmentAccounts->sum('current_value') ?? 0);
        $hasRiskProfile = $user->riskProfile !== null;

        $status = $accounts === 0 ? 'empty' : ($accounts > 0 && $hasRiskProfile ? 'complete' : 'partial');

        return ['status' => $status, 'details' => compact('accounts', 'holdings', 'totalValue', 'hasRiskProfile')];
    }

    private function retirementStatus(User $user, ?User $spouse): array
    {
        $skipped = in_array('retirement', $user->onboarding_skipped_steps ?? []);
        if ($skipped) { return ['status' => 'skipped', 'details' => []]; }

        $dc = $user->dcPensions->count() + ($spouse?->dcPensions->count() ?? 0);
        $db = $user->dbPensions->count() + ($spouse?->dbPensions->count() ?? 0);
        $hasStatePension = $user->statePension !== null;
        $hasRetirementProfile = $user->retirementProfile !== null;
        $dcTotal = $user->dcPensions->sum('fund_value') + ($spouse?->dcPensions->sum('fund_value') ?? 0);

        $total = $dc + $db + ($hasStatePension ? 1 : 0);
        $status = $total === 0 ? 'empty' : ($dc > 0 && $hasStatePension && $hasRetirementProfile ? 'complete' : 'partial');

        return ['status' => $status, 'details' => compact('dc', 'db', 'hasStatePension', 'hasRetirementProfile', 'dcTotal')];
    }

    private function estateStatus(User $user, ?User $spouse): array
    {
        $skipped = in_array('estate', $user->onboarding_skipped_steps ?? []);
        if ($skipped) { return ['status' => 'skipped', 'details' => []]; }

        $hasWill = $user->will !== null;
        $lpa = $user->lastingPowersOfAttorney->count();
        $trustCount = $user->trusts->count();
        $trustValue = $user->trusts->sum('current_value');
        $giftCount = $user->gifts->count();
        $assetCount = $user->assets->count();

        $total = ($hasWill ? 1 : 0) + $lpa + $trustCount + $giftCount + $assetCount;
        $status = $total === 0 ? 'empty' : ($hasWill && $lpa > 0 ? 'complete' : 'partial');

        return ['status' => $status, 'details' => compact('hasWill', 'lpa', 'trustCount', 'trustValue', 'giftCount', 'assetCount')];
    }
}
```

**Key household handling:** When `$user->spouse_id` is set, load the spouse and merge counts. Joint assets (`joint_owner_id` set) are only counted on the primary owner's record — never double-counted.

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/pest tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/Admin/UserModuleTrackingService.php tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php
git commit -m "feat: UserModuleTrackingService with household-aware module status"
```

---

## Task 5: Backend Services

**Files:**
- Create: `app/Services/Advisor/AdvisorDashboardService.php`
- Create: `app/Services/Advisor/ClientActivityService.php`
- Create: `app/Services/Advisor/AdvisorImpersonationService.php`
- Test: `tests/Unit/Services/Advisor/AdvisorDashboardServiceTest.php`
- Test: `tests/Unit/Services/Advisor/AdvisorImpersonationServiceTest.php`

- [ ] **Step 1: Write AdvisorDashboardService tests**

```php
it('returns correct dashboard stats for advisor');
it('returns client list with module status from UserModuleTrackingService');
it('shows coupled clients as single row with combined name');
it('filters out preview users in production mode');
it('returns overdue reviews sorted by due date');
it('returns recent activity feed limited by count');
it('caches client list for 5 minutes');
```

- [ ] **Step 2: Write AdvisorImpersonationService tests**

```php
it('stores impersonation state in cache on enter');
it('clears cache on exit');
it('detects active impersonation');
it('rejects entering unassigned client');
it('rejects entering admin user');
it('rejects entering another advisor');
it('rejects nested impersonation');
it('auto-expires after 8 hours');
it('logs enter and exit to AuditLog');
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
./vendor/bin/pest tests/Unit/Services/Advisor/
```

- [ ] **Step 4: Implement AdvisorDashboardService**

Create `app/Services/Advisor/AdvisorDashboardService.php`:

```php
<?php
declare(strict_types=1);
namespace App\Services\Advisor;

use App\Models\AdvisorClient;
use App\Models\ClientActivity;
use App\Models\User;
use App\Services\Admin\UserModuleTrackingService;
use Illuminate\Support\Facades\Cache;

class AdvisorDashboardService
{
    public function __construct(
        private readonly UserModuleTrackingService $moduleTracking
    ) {}

    public function getDashboardStats(User $advisor): array
    {
        $clients = $advisor->advisorClients()->active()->count();
        $reviewsDue = $advisor->advisorClients()->active()
            ->where('next_review_due', '<=', now())->count();
        $commsThisWeek = ClientActivity::where('advisor_id', $advisor->id)
            ->where('activity_date', '>=', now()->startOfWeek())->count();
        $reportsThisMonth = ClientActivity::where('advisor_id', $advisor->id)
            ->reports()->where('activity_date', '>=', now()->startOfMonth())->count();

        return compact('clients', 'reviewsDue', 'commsThisWeek', 'reportsThisMonth');
    }

    public function getClientList(User $advisor, array $filters = []): array
    {
        return Cache::remember("advisor:{$advisor->id}:clients", 300, function () use ($advisor) {
            $query = $advisor->advisorClients()
                ->active()
                ->with(['client', 'activities' => fn ($q) => $q->latest('activity_date')->limit(5)]);

            $advisorClients = $query->get();

            // In production, filter out preview personas (spec 2.3 / 2.4)
            // In local/staging, keep them for demo purposes
            $isProduction = app()->environment('production');

            return $advisorClients
                ->when($isProduction, fn ($collection) => $collection->filter(
                    fn (AdvisorClient $ac) => ! $ac->client->is_preview_user
                ))
                ->map(function (AdvisorClient $ac) {
                    $client = $ac->client;
                    $spouse = $client->spouse_id ? User::find($client->spouse_id) : null;

                    // Household-aware display name (spec 2.8)
                    $displayName = $spouse
                        ? "{$client->first_name} & {$spouse->first_name} {$client->last_name}"
                        : "{$client->first_name} {$client->last_name}";

                    return [
                        'id' => $ac->id,
                        'client_id' => $client->id,
                        'display_name' => $displayName,
                        'persona_type' => $client->preview_persona_id,
                        'is_preview_user' => $client->is_preview_user,
                        'module_status' => $this->moduleTracking->getModuleStatus($client),
                        'last_review_date' => $ac->last_review_date,
                        'next_review_due' => $ac->next_review_due,
                        'review_frequency_months' => $ac->review_frequency_months,
                        'status' => $ac->status,
                        'last_communication' => $ac->activities->first(fn ($a) => $a->activity_type !== 'suitability_report'),
                        'last_report' => $ac->activities->first(fn ($a) => $a->activity_type === 'suitability_report'),
                    ];
                })->values()->toArray();
        });
    }

    public function getReviewsDue(User $advisor): array
    {
        $advisorClients = $advisor->advisorClients()
            ->active()
            ->where(function ($q) {
                $q->where('next_review_due', '<=', now())
                  ->orWhere('next_review_due', '<=', now()->addDays(30));
            })
            ->with(['client', 'activities' => fn ($q) => $q->latest('activity_date')->limit(1)])
            ->orderBy('next_review_due', 'asc')
            ->get();

        return $advisorClients->map(function (AdvisorClient $ac) {
            $client = $ac->client;
            $spouse = $client->spouse_id ? User::find($client->spouse_id) : null;
            $displayName = $spouse
                ? "{$client->first_name} & {$spouse->first_name} {$client->last_name}"
                : "{$client->first_name} {$client->last_name}";

            $daysOverdue = $ac->next_review_due?->isPast()
                ? (int) $ac->next_review_due->diffInDays(now())
                : null;
            $daysUntilDue = $ac->next_review_due?->isFuture()
                ? (int) now()->diffInDays($ac->next_review_due)
                : null;

            return [
                'id' => $ac->id,
                'client_id' => $client->id,
                'display_name' => $displayName,
                'next_review_due' => $ac->next_review_due,
                'last_review_date' => $ac->last_review_date,
                'days_overdue' => $daysOverdue,
                'days_until_due' => $daysUntilDue,
                'is_overdue' => $ac->next_review_due?->isPast() ?? false,
                'review_frequency_months' => $ac->review_frequency_months,
                'last_activity' => $ac->activities->first(),
                'module_status' => $this->moduleTracking->getModuleStatus($client),
            ];
        })->toArray();
    }

    public function getRecentActivity(User $advisor, int $limit = 10): array
    {
        $activities = ClientActivity::where('advisor_id', $advisor->id)
            ->with(['client', 'advisorClient'])
            ->latest('activity_date')
            ->limit($limit)
            ->get();

        return $activities->map(function (ClientActivity $activity) {
            $client = $activity->client;
            $spouse = $client->spouse_id ? User::find($client->spouse_id) : null;
            $displayName = $spouse
                ? "{$client->first_name} & {$spouse->first_name} {$client->last_name}"
                : "{$client->first_name} {$client->last_name}";

            return [
                'id' => $activity->id,
                'client_id' => $activity->client_id,
                'client_name' => $displayName,
                'activity_type' => $activity->activity_type,
                'summary' => $activity->summary,
                'activity_date' => $activity->activity_date,
                'report_type' => $activity->report_type,
                'follow_up_date' => $activity->follow_up_date,
                'follow_up_completed' => $activity->follow_up_completed,
            ];
        })->toArray();
    }
}
```

**Household handling (spec 2.8):** Coupled clients appear as a single row ("James & Emily Carter"). The `advisor_clients` record links to the primary account holder. Module status dots reflect household data via `UserModuleTrackingService` which merges spouse data. No separate `advisor_clients` record needed for the spouse.

- [ ] **Step 5: Implement ClientActivityService**

Create `app/Services/Advisor/ClientActivityService.php`:

```php
<?php
declare(strict_types=1);
namespace App\Services\Advisor;

use App\Models\AdvisorClient;
use App\Models\AuditLog;
use App\Models\ClientActivity;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ClientActivityService
{
    public function create(User $advisor, array $data): ClientActivity
    {
        $advisorClient = AdvisorClient::where('advisor_id', $advisor->id)
            ->where('client_id', $data['client_id'])
            ->active()
            ->firstOrFail();

        $activity = ClientActivity::create([
            'advisor_client_id' => $advisorClient->id,
            'advisor_id' => $advisor->id,
            'client_id' => $data['client_id'],
            'activity_type' => $data['activity_type'],
            'summary' => $data['summary'],
            'details' => $data['details'] ?? null,
            'activity_date' => $data['activity_date'],
            'follow_up_date' => $data['follow_up_date'] ?? null,
            'follow_up_completed' => $data['follow_up_completed'] ?? false,
            'report_type' => $data['report_type'] ?? null,
            'report_sent_date' => $data['report_sent_date'] ?? null,
            'report_acknowledged_date' => $data['report_acknowledged_date'] ?? null,
        ]);

        Cache::forget("advisor:{$advisor->id}:clients");

        AuditLog::logAdmin('log_activity', null, [
            'advisor_id' => $advisor->id,
            'client_id' => $data['client_id'],
            'activity_type' => $data['activity_type'],
        ]);

        return $activity;
    }

    public function update(User $advisor, int $activityId, array $data): ClientActivity
    {
        $activity = ClientActivity::where('advisor_id', $advisor->id)
            ->findOrFail($activityId);

        $activity->update($data);

        Cache::forget("advisor:{$advisor->id}:clients");

        return $activity->fresh();
    }

    public function listForAdvisor(User $advisor, array $filters = []): array
    {
        $query = ClientActivity::where('advisor_id', $advisor->id)
            ->with(['client', 'advisorClient'])
            ->latest('activity_date');

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['activity_type'])) {
            // Support comma-separated types for Communications filter
            $types = is_array($filters['activity_type'])
                ? $filters['activity_type']
                : explode(',', $filters['activity_type']);
            $query->whereIn('activity_type', $types);
        }

        if (! empty($filters['date_from'])) {
            $query->where('activity_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('activity_date', '<=', $filters['date_to']);
        }

        return $query->paginate($filters['per_page'] ?? 20)->toArray();
    }

    public function listForClient(int $advisorClientId): array
    {
        return ClientActivity::where('advisor_client_id', $advisorClientId)
            ->latest('activity_date')
            ->get()
            ->toArray();
    }
}
```

- [ ] **Step 6: Implement AdvisorImpersonationService**

Create `app/Services/Advisor/AdvisorImpersonationService.php`:

```php
<?php
declare(strict_types=1);
namespace App\Services\Advisor;

use App\Models\AdvisorClient;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AdvisorImpersonationService
{
    private const TTL_HOURS = 8;

    public function enterClientProfile(User $advisor, User $client): array
    {
        // Security guards (spec 2.4)
        abort_unless(
            AdvisorClient::where('advisor_id', $advisor->id)
                ->where('client_id', $client->id)
                ->where('status', 'active')
                ->exists(),
            403, 'Client is not assigned to you'
        );
        abort_if($client->is_admin, 403, 'Cannot enter an admin account');
        abort_if($client->is_advisor, 403, 'Cannot enter another advisor account');
        abort_if($this->isImpersonating($advisor), 403, 'Already impersonating a client');

        $tokenId = $advisor->currentAccessToken()->id;
        Cache::put(
            "advisor_impersonation:{$tokenId}",
            ['client_id' => $client->id, 'started_at' => now()],
            now()->addHours(self::TTL_HOURS)
        );

        AuditLog::logAdmin('enter_client', null, [
            'advisor_id' => $advisor->id,
            'client_id' => $client->id,
        ]);

        return ['impersonating' => true, 'client' => $client->only(['id', 'first_name', 'last_name', 'email'])];
    }

    public function exitClientProfile(User $advisor): void
    {
        $tokenId = $advisor->currentAccessToken()->id;
        $cached = Cache::get("advisor_impersonation:{$tokenId}");

        if ($cached) {
            AuditLog::logAdmin('exit_client', null, [
                'advisor_id' => $advisor->id,
                'client_id' => $cached['client_id'],
            ]);
            Cache::forget("advisor_impersonation:{$tokenId}");
        }
    }

    public function isImpersonating(User $advisor): bool
    {
        $tokenId = $advisor->currentAccessToken()?->id;
        return $tokenId && Cache::has("advisor_impersonation:{$tokenId}");
    }

    public function getImpersonatedClientId(User $advisor): ?int
    {
        $tokenId = $advisor->currentAccessToken()?->id;
        return Cache::get("advisor_impersonation:{$tokenId}")['client_id'] ?? null;
    }
}
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
./vendor/bin/pest tests/Unit/Services/Advisor/
```

- [ ] **Step 8: Commit**

```bash
git add app/Services/Advisor/ tests/Unit/Services/Advisor/
git commit -m "feat: advisor services (dashboard, activities, impersonation) with household handling"
```

---

## Task 6: Controller, Middleware & Routes

**Files:**
- Create: `app/Http/Controllers/Api/AdvisorController.php`
- Create: `app/Http/Requests/StoreClientActivityRequest.php`
- Create: `app/Http/Middleware/AdvisorMiddleware.php`
- Create: `app/Http/Middleware/AdvisorImpersonationMiddleware.php`
- Modify: `routes/api.php`
- Modify: `app/Http/Middleware/PreviewWriteInterceptor.php` (lines 47-68)
- Modify: `app/Http/Kernel.php` (register middleware)
- Test: `tests/Feature/Api/AdvisorControllerTest.php`
- Test: `tests/Feature/Middleware/AdvisorMiddlewareTest.php`

- [ ] **Step 1: Write controller tests**

```php
it('returns 403 for non-advisor user on all advisor endpoints');
it('returns dashboard stats for advisor');
it('returns client list with module status for advisor');
it('returns client detail for assigned client');
it('rejects client detail for unassigned client');
it('starts impersonation for assigned client');
it('rejects impersonation for unassigned client');
it('ends impersonation');
it('lists activities filtered by client');
it('creates activity with valid data');
it('validates activity data');
it('returns overdue reviews');
it('returns suitability reports');
```

- [ ] **Step 2: Write middleware tests**

```php
it('allows advisor to access advisor routes');
it('blocks non-advisor from advisor routes');
it('impersonation middleware substitutes user when cache entry exists');
it('impersonation middleware passes through when no cache entry');
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
./vendor/bin/pest tests/Feature/Api/AdvisorControllerTest.php tests/Feature/Middleware/AdvisorMiddlewareTest.php
```

- [ ] **Step 4: Create StoreClientActivityRequest**

Per spec section 2.5. Validation rules for all activity fields including conditional `required_if:activity_type,suitability_report` for `report_type`.

- [ ] **Step 5: Create AdvisorMiddleware**

Create `app/Http/Middleware/AdvisorMiddleware.php`:

```php
<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdvisorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_advisor) {
            return response()->json([
                'success' => false,
                'message' => 'Advisor access required.',
            ], 403);
        }

        return $next($request);
    }
}
```

- [ ] **Step 6: Create AdvisorImpersonationMiddleware**

Create `app/Http/Middleware/AdvisorImpersonationMiddleware.php`:

```php
<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AdvisorImpersonationMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $tokenId = $user->currentAccessToken()?->id;
        if (! $tokenId) {
            return $next($request);
        }

        $cached = Cache::get("advisor_impersonation:{$tokenId}");
        if ($cached) {
            $client = User::find($cached['client_id']);
            if ($client) {
                // Store real advisor for audit logging
                $request->attributes->set('advisor', $user);
                // Swap auth to client
                auth()->setUser($client);
            }
        }

        return $next($request);
    }
}
```

- [ ] **Step 7: Register middleware in Kernel.php**

In `app/Http/Kernel.php`, add to `$middlewareAliases` (after `'agent.token'` at line 79):

```php
'advisor' => \App\Http\Middleware\AdvisorMiddleware::class,
'advisor.impersonate' => \App\Http\Middleware\AdvisorImpersonationMiddleware::class,
```

- [ ] **Step 8: Create AdvisorController**

All 11 methods per spec section 2.5. Injects `AdvisorDashboardService`, `ClientActivityService`, `AdvisorImpersonationService`.

- [ ] **Step 9: Add routes**

Add to `routes/api.php` after admin routes:

```php
Route::middleware(['auth:sanctum', 'advisor'])
    ->prefix('advisor')
    ->controller(AdvisorController::class)
    ->group(function () {
        Route::get('dashboard', 'dashboard');
        Route::get('clients', 'clients');
        Route::get('clients/{id}', 'clientDetail');
        Route::get('clients/{id}/modules', 'clientModuleStatus');
        Route::post('clients/{id}/enter', 'enterClient');
        Route::post('exit', 'exitClient');
        Route::get('activities', 'activities');
        Route::post('activities', 'storeActivity');
        Route::put('activities/{id}', 'updateActivity');
        Route::get('reviews-due', 'reviewsDue');
        Route::get('reports', 'reports');
    });
```

- [ ] **Step 10: Add PreviewWriteInterceptor exclusions**

In `app/Http/Middleware/PreviewWriteInterceptor.php`, add to `EXCLUDED_ROUTES` array:

```php
'api/advisor/clients/*/enter',
'api/advisor/exit',
```

- [ ] **Step 11: Run tests to verify they pass**

```bash
./vendor/bin/pest tests/Feature/Api/AdvisorControllerTest.php tests/Feature/Middleware/AdvisorMiddlewareTest.php
```

- [ ] **Step 12: Commit**

```bash
git add app/Http/Controllers/Api/AdvisorController.php app/Http/Requests/StoreClientActivityRequest.php app/Http/Middleware/AdvisorMiddleware.php app/Http/Middleware/AdvisorImpersonationMiddleware.php app/Http/Kernel.php routes/api.php app/Http/Middleware/PreviewWriteInterceptor.php tests/Feature/
git commit -m "feat: advisor controller, middleware, routes with full test coverage"
```

---

## Task 7: Frontend — Vuex Store, Service & Router

**Files:**
- Create: `resources/js/store/modules/advisor.js`
- Create: `resources/js/services/advisorService.js`
- Modify: `resources/js/store/index.js` (register advisor module)
- Modify: `resources/js/store/modules/auth.js` (lines 13-24 — add isAdvisor getter)
- Modify: `resources/js/router/index.js` (lines 743-755, 1054-1069)

- [ ] **Step 1: Create advisorService.js**

API wrapper with methods: `getDashboard()`, `getClients(filters)`, `getClientDetail(id)`, `getClientModules(id)`, `enterClient(id)`, `exitClient()`, `getActivities(filters)`, `createActivity(data)`, `updateActivity(id, data)`, `getReviewsDue()`, `getReports()`.

- [ ] **Step 2: Create advisor Vuex store**

`resources/js/store/modules/advisor.js` — namespaced module with state (clients, activities, dashboardStats, reviewsDue, impersonating, impersonatedClient), mutations, actions wrapping the API service, getters (overdueReviews, clientById, activeClients).

- [ ] **Step 3: Register advisor store module**

Add `advisor` to the modules in `resources/js/store/index.js`.

- [ ] **Step 4: Add isAdvisor getter to auth store**

In `resources/js/store/modules/auth.js` after the `isSupport` getter (line 18):

```javascript
isAdvisor: (state) => state.user?.is_advisor === true,
```

**Why `state.user?.is_advisor` and not `state.role === 'advisor'`:** Unlike `isAdmin` which checks `state.role` (set from `$user->role->name` on the backend), `is_advisor` is a standalone boolean flag. A user can be admin (role='admin') AND advisor (is_advisor=true) simultaneously — chris@fynla.org is both. The `role` string would be 'admin', so checking `state.role === 'advisor'` would miss admin-advisors.

- [ ] **Step 5: Add advisor routes to router**

In `resources/js/router/index.js`, add the advisor route group with lazy-loaded components:

```javascript
{
  path: '/advisor',
  component: () => import('../layouts/AdvisorLayout.vue'),
  meta: { requiresAuth: true, requiresAdvisor: true },
  children: [
    { path: '', name: 'AdvisorDashboard', component: () => import('../views/Advisor/AdvisorDashboard.vue') },
    { path: 'clients', name: 'AdvisorClients', component: () => import('../views/Advisor/AdvisorClientList.vue') },
    { path: 'clients/:id', name: 'AdvisorClientDetail', component: () => import('../views/Advisor/AdvisorClientDetail.vue') },
    { path: 'activities', name: 'AdvisorActivities', component: () => import('../views/Advisor/AdvisorActivityLog.vue') },
    { path: 'reviews', name: 'AdvisorReviews', component: () => import('../views/Advisor/AdvisorReviewsDue.vue') },
    { path: 'reports', name: 'AdvisorReports', component: () => import('../views/Advisor/AdvisorReports.vue') },
  ]
}
```

- [ ] **Step 6: Add requiresAdvisor guard**

In the router guard (after the `requiresAdmin` check around line 1069):

```javascript
} else if (to.matched.some(r => r.meta.requiresAdvisor) && !store.getters['auth/isAdvisor']) {
  next({ name: 'Dashboard' });
}
```

- [ ] **Step 7: Commit**

```bash
git add resources/js/store/modules/advisor.js resources/js/services/advisorService.js resources/js/store/index.js resources/js/store/modules/auth.js resources/js/router/index.js
git commit -m "feat: advisor Vuex store, API service, and router integration"
```

---

## Task 8: Frontend — Layout & Dashboard Components

**Files:**
- Create: `resources/js/layouts/AdvisorLayout.vue`
- Create: `resources/js/views/Advisor/AdvisorDashboard.vue`
- Create: `resources/js/components/Advisor/ClientModuleDots.vue`
- Reference: `March/March17Updates/Advisor/advisor-dashboard-mockup.html` (visual reference)
- Reference: `fynlaDesignGuide.md` (MUST read before starting)

**CRITICAL: Read `fynlaDesignGuide.md` before writing any component CSS/markup.**

- [ ] **Step 1: Create AdvisorLayout.vue**

Layout with:
- Top bar: `bg-horizon-500` with fynla logo, "ADVISOR VIEW" badge (`bg-violet-500 rounded-full`), user avatar
- Sidebar: `bg-white w-64 border-r border-light-gray`. Navigation items per mockup, grouped into sections:
  - **Overview:** Dashboard (`/advisor`), All Clients (`/advisor/clients`) with count badge
  - **Actions:** Reviews Due (`/advisor/reviews`) with count badge, Communications (`/advisor/activities?type=email,phone,letter,meeting` — filtered activity log showing all advisor-client communications: emails, phone calls, letters, meetings), Suitability Reports (`/advisor/reports`)
  - **Quick Access:** Activity Log (`/advisor/activities` — unfiltered, all activity types), Settings (`/settings` — existing app settings page, not an advisor-specific route)
- Active sidebar item: `bg-raspberry-50 text-raspberry-500`
- Hover: `bg-savannah-100 text-horizon-500`
- Content area: `bg-eggshell-500` with `router-view`

**Communications vs Activity Log:** Both link to the activity log view but "Communications" pre-filters to communication types only (email, phone, letter, meeting), while "Activity Log" shows all types including notes, reviews, and suitability reports. Pass `?type=email,phone,letter,meeting` query param for Communications. The `AdvisorActivityLog.vue` reads this from `$route.query.type` to set initial filters.

**Settings:** Links to the existing `/settings` route (same settings page all users see). Not an advisor-specific view.

- [ ] **Step 2: Create ClientModuleDots.vue**

Reusable component receiving `moduleStatus` object. Renders P S I R E dots with same design as admin `UserModuleStatus.vue` but without the expandable detail (just the dots for the table row).

- [ ] **Step 3: Create AdvisorDashboard.vue**

Per mockup (`March/March17Updates/Advisor/advisor-dashboard-mockup.html`). Sections:
- Stats row: 4 cards (Active Clients, Reviews Due, Communications, Reports)
- Client Overview table: Name, Modules (ClientModuleDots), Last Review, Last Communication, Last Report, Status, Actions (View / Enter Profile)
- Module legend bar
- Bottom grid: Reviews Due cards (left), Recent Activity feed (right)

**Household display (spec 2.8):** Coupled clients render as "James & Emily Carter" — the `display_name` field from the API already handles this. Avatars show initials of the primary account holder.

All data loaded from `advisor/fetchDashboard` and `advisor/fetchClients` Vuex actions on mount.

"Enter Profile" button dispatches `advisor/enterClient` and navigates to client dashboard.
"View" button navigates to `/advisor/clients/:id`.

- [ ] **Step 4: Test in browser**

```bash
./dev.sh
```

Log in as chris@fynla.org → navigate to `/advisor`. Verify dashboard renders with all 6 personas as clients. Check colours, typography, spacing against design guide.

- [ ] **Step 5: Commit**

```bash
git add resources/js/layouts/AdvisorLayout.vue resources/js/views/Advisor/AdvisorDashboard.vue resources/js/components/Advisor/ClientModuleDots.vue
git commit -m "feat: advisor layout and dashboard with client overview"
```

---

## Task 9: Frontend — Client Detail, Activity & Reports Views

**Files:**
- Create: `resources/js/views/Advisor/AdvisorClientList.vue`
- Create: `resources/js/views/Advisor/AdvisorClientDetail.vue`
- Create: `resources/js/views/Advisor/AdvisorActivityLog.vue`
- Create: `resources/js/views/Advisor/AdvisorReviewsDue.vue`
- Create: `resources/js/views/Advisor/AdvisorReports.vue`
- Create: `resources/js/components/Advisor/ClientActivityForm.vue`

- [ ] **Step 1: Create AdvisorClientList.vue**

Full-page client table with search, filters (status, review due), same columns as dashboard table but with pagination.

- [ ] **Step 2: Create AdvisorClientDetail.vue**

Read-only client overview showing: client profile card, module status with granular breakdown (reuses `UserModuleStatus` pattern from admin), latest activity timeline, upcoming review info, suitability report history. "Enter Profile" button at top.

- [ ] **Step 3: Create ClientActivityForm.vue**

Modal form to log a new activity. Fields per spec section 2.5: client selector, activity type, summary, details, activity date, follow-up date, report-specific fields (conditional on type = suitability_report). Emits `save` (not `submit`).

- [ ] **Step 4: Create AdvisorActivityLog.vue**

Activity feed with filters (client, type, date range). Chronological list with activity icons (email=violet, phone=spring, meeting=savannah, report=raspberry). "Log Activity" button opens `ClientActivityForm`.

- [ ] **Step 5: Create AdvisorReviewsDue.vue**

List of clients with overdue/upcoming reviews. Cards showing client name, days overdue/until due, last review summary, recommended actions.

- [ ] **Step 6: Create AdvisorReports.vue**

Filtered view of activities where `activity_type = 'suitability_report'`. Shows report type, sent date, acknowledged date, client name.

- [ ] **Step 7: Test all views in browser**

Navigate through each view, test CRUD for activities, verify filters work.

- [ ] **Step 8: Commit**

```bash
git add resources/js/views/Advisor/ resources/js/components/Advisor/ClientActivityForm.vue
git commit -m "feat: advisor client detail, activity log, reviews, and reports views"
```

---

## Task 10: Impersonation Banner & Flow

**Files:**
- Create: `resources/js/components/Advisor/AdvisorBanner.vue`
- Modify: `resources/js/layouts/AppLayout.vue` (add banner slot)

- [ ] **Step 1: Create AdvisorBanner.vue**

Fixed banner at top of page when impersonating. Design: `bg-violet-500 text-white` bar with: "You are viewing [Client Name]'s profile as their advisor" + "Exit" button (`bg-white text-violet-500 rounded-button`).

The banner reads `impersonating` and `impersonatedClient` from the `advisor` Vuex store. "Exit" dispatches `advisor/exitClient` and navigates to `/advisor`.

- [ ] **Step 2: Integrate banner into AppLayout.vue**

Add `<AdvisorBanner v-if="isImpersonating" />` at the very top of the authenticated layout, above the navigation. The banner should be position-fixed so it stays visible while scrolling.

The `isImpersonating` computed reads from `$store.state.advisor.impersonating`.

- [ ] **Step 3: Test impersonation flow**

1. Log in as chris@fynla.org
2. Navigate to `/advisor`
3. Click "Enter Profile" on James Carter
4. Verify banner appears
5. Navigate client's modules — verify data shows
6. Click "Exit" — verify return to advisor dashboard

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Advisor/AdvisorBanner.vue resources/js/layouts/AppLayout.vue
git commit -m "feat: advisor impersonation banner and enter/exit flow"
```

---

## Task 11: Final Integration & Seed

- [ ] **Step 1: Run full test suite**

```bash
./vendor/bin/pest
```

All tests must pass.

- [ ] **Step 2: Run code formatter**

```bash
./vendor/bin/pint
```

- [ ] **Step 3: Seed database**

```bash
php artisan db:seed
```

- [ ] **Step 4: Full browser test**

Test complete flow:
- Login as chris@fynla.org → `/advisor` dashboard loads
- All 6 personas visible as clients (coupled personas show as "Name & Spouse Name")
- Module dots correct (household-aware — joint assets counted once)
- Review due dates and overdue indicators
- Activity log with seeded data
- Log new activity via form
- Enter client profile → banner shows → navigate modules → exit
- Suitability reports view
- Non-advisor user blocked from `/advisor` routes
- Test with a different user (not chris) → verify `/advisor` redirects to dashboard

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "chore: final integration, formatting, and seed verification"
```
