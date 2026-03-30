# Admin User Metrics Dashboard & Subscription Tier Update — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "Family" subscription tier with updated pricing (including launch discounts on the pricing page), and build a new "User Metrics" admin tab with real-time analytics on registrations, trials, subscriptions, churn, revenue, and engagement.

**Architecture:** Backend adds a `UserMetricsService` with five query methods exposed via a new `UserMetricsController`. Frontend adds a `UserMetrics.vue` tab to `AdminPanel.vue` with sub-components for snapshot cards, trial breakdown, plan breakdown, charts (ApexCharts), and a data table. Subscription tier changes touch the migration, seeder, middleware, and public pricing page.

**Tech Stack:** Laravel 10, Vue.js 3, Vuex, ApexCharts (vue3-apexcharts), Tailwind CSS, MySQL 8

---

## File Map

### New Files

| File | Purpose |
| ---- | ------- |
| `database/migrations/2026_03_30_100001_add_family_to_plan_enums.php` | Add `family` to plan enum on subscriptions and users tables |
| `database/migrations/2026_03_30_100002_add_launch_prices_to_subscription_plans.php` | Add `launch_monthly_price` and `launch_yearly_price` columns |
| `app/Services/Admin/UserMetricsService.php` | All analytics queries |
| `app/Http/Controllers/Api/UserMetricsController.php` | Five API endpoints |
| `resources/js/components/Admin/UserMetrics.vue` | Tab container with period selector and data fetching |
| `resources/js/components/Admin/metrics/SnapshotCards.vue` | Four top-level metric cards |
| `resources/js/components/Admin/metrics/TrialBreakdown.vue` | Six trial status cards |
| `resources/js/components/Admin/metrics/PlanBreakdown.vue` | Four subscription plan cards |
| `resources/js/components/Admin/metrics/ActivityCharts.vue` | Four ApexCharts (registrations, revenue, churn, engagement) |
| `resources/js/components/Admin/metrics/ActivityTable.vue` | Data table below charts |
| `tests/Unit/Services/Admin/UserMetricsServiceTest.php` | Unit tests for UserMetricsService |
| `tests/Feature/Admin/UserMetricsControllerTest.php` | Feature tests for API endpoints |

### Modified Files

| File | Change |
| ---- | ------ |
| `database/seeders/SubscriptionPlanSeeder.php` | Add Family tier, update all prices (full + launch) |
| `app/Models/SubscriptionPlan.php` | Add `launch_monthly_price`, `launch_yearly_price` to fillable/casts, add `getLaunchPriceForCycle()` |
| `app/Http/Middleware/CheckSubscription.php` | Recognise `family` tier in feature gating |
| `routes/api.php` | Add five user-metrics routes under admin group |
| `resources/js/views/Admin/AdminPanel.vue` | Add `user-metrics` tab, import `UserMetrics.vue` |
| `resources/js/services/adminService.js` | Add five `getUserMetrics*()` methods |
| `resources/js/views/Public/PricingPage.vue` | Add Family tier, launch discount display with strikethrough pricing |

---

## Task 1: Add `family` to plan enums (migration)

**Files:**
- Create: `database/migrations/2026_03_30_100001_add_family_to_plan_enums.php`

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration add_family_to_plan_enums
```

- [ ] **Step 2: Write migration code**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN plan ENUM('student', 'standard', 'family', 'pro') NOT NULL");
        DB::statement("ALTER TABLE users MODIFY COLUMN plan VARCHAR(255) DEFAULT 'free'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN plan ENUM('student', 'standard', 'pro') NOT NULL");
    }
};
```

Note: The `users.plan` column may already be a VARCHAR. If the migration fails on the users table, check the column type with `SHOW COLUMNS FROM users LIKE 'plan'` and adjust accordingly.

- [ ] **Step 3: Run migration**

```bash
php artisan migrate
```

Expected: Migration runs successfully, no errors.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_03_30_100001_add_family_to_plan_enums.php
git commit -m "feat: add family to subscription plan enums"
```

---

## Task 2: Add launch price columns to subscription_plans

**Files:**
- Create: `database/migrations/2026_03_30_100002_add_launch_prices_to_subscription_plans.php`
- Modify: `app/Models/SubscriptionPlan.php`

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration add_launch_prices_to_subscription_plans
```

- [ ] **Step 2: Write migration code**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->integer('launch_monthly_price')->nullable()->after('monthly_price');
            $table->integer('launch_yearly_price')->nullable()->after('yearly_price');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['launch_monthly_price', 'launch_yearly_price']);
        });
    }
};
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate
```

- [ ] **Step 4: Update SubscriptionPlan model**

In `app/Models/SubscriptionPlan.php`, add `launch_monthly_price` and `launch_yearly_price` to the `$fillable` array and `$casts` array:

```php
protected $fillable = [
    'slug', 'name', 'monthly_price', 'launch_monthly_price',
    'yearly_price', 'launch_yearly_price', 'trial_days',
    'is_active', 'features', 'sort_order',
];

protected $casts = [
    'monthly_price' => 'integer',
    'launch_monthly_price' => 'integer',
    'yearly_price' => 'integer',
    'launch_yearly_price' => 'integer',
    'trial_days' => 'integer',
    'is_active' => 'boolean',
    'features' => 'array',
    'sort_order' => 'integer',
];
```

Add a helper method after the existing `getPriceForCycle()`:

```php
public function getLaunchPriceForCycle(string $billingCycle): ?int
{
    return $billingCycle === 'yearly'
        ? $this->launch_yearly_price
        : $this->launch_monthly_price;
}
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_03_30_100002_add_launch_prices_to_subscription_plans.php app/Models/SubscriptionPlan.php
git commit -m "feat: add launch price columns to subscription_plans"
```

---

## Task 3: Update SubscriptionPlanSeeder with Family tier and new pricing

**Files:**
- Modify: `database/seeders/SubscriptionPlanSeeder.php`

- [ ] **Step 1: Read current seeder**

Read `database/seeders/SubscriptionPlanSeeder.php` to see exact structure.

- [ ] **Step 2: Update the plans array**

Replace the existing plans array with the four tiers. Prices are stored in pence. The `monthly_price` and `yearly_price` fields store the FULL price. The `launch_monthly_price` and `launch_yearly_price` store the discounted launch price:

```php
$plans = [
    [
        'slug' => 'student',
        'name' => 'Student',
        'monthly_price' => 499,
        'launch_monthly_price' => 399,
        'yearly_price' => 4500,
        'launch_yearly_price' => 3000,
        'trial_days' => 7,
        'is_active' => true,
        'sort_order' => 1,
        'features' => json_encode([
            'Full financial dashboard',
            'Protection module',
            'Savings module',
            'Goal tracking',
            'Investment module',
            'Retirement module',
        ]),
    ],
    [
        'slug' => 'standard',
        'name' => 'Standard',
        'monthly_price' => 1499,
        'launch_monthly_price' => 1099,
        'yearly_price' => 13500,
        'launch_yearly_price' => 10000,
        'trial_days' => 7,
        'is_active' => true,
        'sort_order' => 2,
        'features' => json_encode([
            'Everything in Student',
            'Personal Valuables',
            'Business',
            'Property',
            'Letter to Spouse / Expression of Wishes',
            'Coordination module',
        ]),
    ],
    [
        'slug' => 'family',
        'name' => 'Family',
        'monthly_price' => 2199,
        'launch_monthly_price' => 1499,
        'yearly_price' => 19900,
        'launch_yearly_price' => 15000,
        'trial_days' => 7,
        'is_active' => true,
        'sort_order' => 3,
        'features' => json_encode([
            'Everything in Standard',
            'Family module',
        ]),
    ],
    [
        'slug' => 'pro',
        'name' => 'Pro',
        'monthly_price' => 2999,
        'launch_monthly_price' => 1999,
        'yearly_price' => 26999,
        'launch_yearly_price' => 20000,
        'trial_days' => 7,
        'is_active' => true,
        'sort_order' => 4,
        'features' => json_encode([
            'Everything in Family',
            'Estate Planning',
            'Holistic Plan',
            'Wills',
            'Powers of Attorney',
            'Trusts',
            'AI document extraction',
            'Advanced projections',
            'Priority support',
        ]),
    ],
];
```

Keep the existing `updateOrCreate` loop on `slug`.

- [ ] **Step 3: Run seeder**

```bash
php artisan db:seed --class=SubscriptionPlanSeeder --force
```

Expected: 4 plans upserted (Student, Standard, Family, Pro).

- [ ] **Step 4: Verify in database**

```bash
php artisan tinker --execute="echo json_encode(\App\Models\SubscriptionPlan::all(['slug','monthly_price','launch_monthly_price','yearly_price','launch_yearly_price'])->toArray(), JSON_PRETTY_PRINT);"
```

Expected: Four rows with correct full and launch prices.

- [ ] **Step 5: Commit**

```bash
git add database/seeders/SubscriptionPlanSeeder.php
git commit -m "feat: add Family tier and update pricing with launch discounts"
```

---

## Task 4: Update CheckSubscription middleware for Family tier

**Files:**
- Modify: `app/Http/Middleware/CheckSubscription.php`

- [ ] **Step 1: Read the current middleware**

Read `app/Http/Middleware/CheckSubscription.php` to understand how plan-based feature gating works (if any plan-specific logic exists beyond active/trial checks).

- [ ] **Step 2: Add Family tier recognition**

If the middleware has plan-specific feature gating logic, add `family` to the appropriate tier group. The Family tier sits between Standard and Pro — it gets everything Standard has plus the Family module, but excludes Estate Planning, Holistic Plan, Wills, Powers, and Trusts.

If the middleware only checks for active subscription status (not plan-specific features), no changes are needed here — the feature gating is handled elsewhere. In that case, skip to Step 3 and just verify.

- [ ] **Step 3: Verify middleware recognises Family plan**

```bash
php artisan tinker --execute="\$u = new \App\Models\User(['plan' => 'family']); echo 'Family plan value: ' . \$u->plan;"
```

Expected: `Family plan value: family`

- [ ] **Step 4: Commit (if changes were made)**

```bash
git add app/Http/Middleware/CheckSubscription.php
git commit -m "feat: add family tier to subscription middleware"
```

---

## Task 5: Update Public Pricing Page

**Files:**
- Modify: `resources/js/views/Public/PricingPage.vue`

- [ ] **Step 1: Read the current pricing page**

Read `resources/js/views/Public/PricingPage.vue` in full to understand the current structure — three plan cards, billing toggle, feature lists.

- [ ] **Step 2: Add launch discount header**

Add a "Launch Discount" banner/badge above the pricing cards. Example placement — after the billing toggle, before the plan cards:

```html
<div class="text-center mb-6">
  <span class="inline-block bg-raspberry-50 text-raspberry-500 text-sm font-bold px-4 py-2 rounded-full">
    Launch Discount — Limited Time
  </span>
</div>
```

- [ ] **Step 3: Add the Family plan card**

Insert a fourth plan card between Standard and Pro. Follow the exact same card structure as the existing plans. The card should show:
- Plan name: "Family"
- Strikethrough full price + launch price displayed prominently
- Feature list based on the spec
- CTA button linking to registration with `plan=family`

- [ ] **Step 4: Update all plan cards with strikethrough pricing**

For each of the four plans, show the full price with a strikethrough and the launch price as the main displayed price:

```html
<!-- Example pricing display pattern -->
<div class="text-center">
  <span class="text-neutral-500 line-through text-lg">£4.99</span>
  <span class="text-3xl font-black text-horizon-500 ml-2">£3.99</span>
  <span class="text-neutral-500 text-sm">/month</span>
</div>
```

Apply this pattern to all four plans for both monthly and yearly views. Use the billing toggle to switch between monthly/yearly as the existing page already does.

Updated prices (display in GBP, not pence):

| Plan | Monthly Full | Monthly Launch | Yearly Full | Yearly Launch |
| ---- | ------------ | -------------- | ----------- | ------------- |
| Student | £4.99 | £3.99 | £45.00 | £30.00 |
| Standard | £14.99 | £10.99 | £135.00 | £100.00 |
| Family | £21.99 | £14.99 | £199.00 | £150.00 |
| Pro | £29.99 | £19.99 | £269.99 | £200.00 |

- [ ] **Step 5: Update feature lists per plan**

Student excludes: Estate Planning, Holistic Plan, Wills, Family, Powers, Personal Valuables, Business, Property, Trusts, Letter to Spouse/Expression of Wishes.

Standard excludes: Estate Planning, Holistic Plan, Wills, Family, Powers, Trusts.

Family excludes: Estate Planning, Holistic Plan, Wills, Powers, Trusts.

Pro: Full access to everything.

Show included features with a check icon, and optionally show key excluded features with an X or simply omit them. Follow the existing pattern in the pricing page for how features are listed.

- [ ] **Step 6: Adjust grid layout for 4 cards**

The current page uses 3 columns. Update to 4 columns:

```html
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
```

Ensure cards are evenly sized. The "Most Popular" badge can stay on Standard or move to Family — check with the user if unclear, default to keeping it on Standard.

- [ ] **Step 7: Test the pricing page visually**

```bash
# Ensure dev server is running
./dev.sh
```

Open `http://localhost:8000/pricing` (or wherever the public pricing page is routed) and verify:
- Four cards display correctly
- Billing toggle switches between monthly/yearly prices
- Strikethrough full prices are visible
- Launch discount prices are prominent
- Launch Discount banner is visible
- Responsive layout works (stacks on mobile)

- [ ] **Step 8: Commit**

```bash
git add resources/js/views/Public/PricingPage.vue
git commit -m "feat: add Family tier and launch discount pricing to public pricing page"
```

---

## Task 6: Build UserMetricsService (backend)

**Files:**
- Create: `app/Services/Admin/UserMetricsService.php`
- Create: `tests/Unit/Services/Admin/UserMetricsServiceTest.php`

- [ ] **Step 1: Write the test file**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Subscription;
use App\Services\Admin\UserMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->service = app(UserMetricsService::class);
});

describe('getSnapshot', function () {
    it('returns correct total registered count excluding preview users', function () {
        User::factory()->count(3)->create(['is_preview_user' => false]);
        User::factory()->count(2)->create(['is_preview_user' => true]);

        $snapshot = $this->service->getSnapshot();

        expect($snapshot['total_registered'])->toBe(3);
    });

    it('returns correct active subscriber count', function () {
        $user = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'plan' => 'standard',
        ]);

        $trialUser = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->create([
            'user_id' => $trialUser->id,
            'status' => 'trialing',
            'plan' => 'student',
            'trial_ends_at' => now()->addDays(5),
        ]);

        $snapshot = $this->service->getSnapshot();

        expect($snapshot['active_subscribers'])->toBe(1);
        expect($snapshot['on_trial'])->toBe(1);
    });

    it('returns correct never paid count', function () {
        User::factory()->count(2)->create(['is_preview_user' => false]);
        $paidUser = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->create([
            'user_id' => $paidUser->id,
            'status' => 'active',
            'plan' => 'pro',
        ]);

        $snapshot = $this->service->getSnapshot();

        expect($snapshot['never_paid'])->toBe(2);
    });
});

describe('getTrialBreakdown', function () {
    it('buckets trial users by days remaining', function () {
        // 4+ days
        $u1 = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->create([
            'user_id' => $u1->id,
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(5),
        ]);

        // 1 day
        $u2 = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->create([
            'user_id' => $u2->id,
            'status' => 'trialing',
            'trial_ends_at' => now()->addHours(20),
        ]);

        // Expired
        $u3 = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->create([
            'user_id' => $u3->id,
            'status' => 'expired',
            'trial_ends_at' => now()->subDays(2),
        ]);

        $breakdown = $this->service->getTrialBreakdown();

        expect($breakdown['four_plus_days'])->toBe(1);
        expect($breakdown['one_day'])->toBe(1);
        expect($breakdown['expired'])->toBe(1);
    });
});

describe('getPlanBreakdown', function () {
    it('returns subscriber counts grouped by plan and billing cycle', function () {
        $u1 = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->create([
            'user_id' => $u1->id,
            'status' => 'active',
            'plan' => 'student',
            'billing_cycle' => 'monthly',
            'amount' => 399,
        ]);

        $u2 = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->create([
            'user_id' => $u2->id,
            'status' => 'active',
            'plan' => 'student',
            'billing_cycle' => 'yearly',
            'amount' => 3000,
        ]);

        $plans = $this->service->getPlanBreakdown();
        $student = collect($plans)->firstWhere('plan', 'student');

        expect($student['total'])->toBe(2);
        expect($student['monthly'])->toBe(1);
        expect($student['yearly'])->toBe(1);
    });
});

describe('getActivity', function () {
    it('returns daily activity for the past 7 days', function () {
        $user = User::factory()->create([
            'is_preview_user' => false,
            'created_at' => now()->subDays(1),
        ]);

        $activity = $this->service->getActivity('day', 7);

        expect($activity)->toBeArray();
        expect(count($activity))->toBe(7);
    });
});

describe('getEngagementStats', function () {
    it('returns onboarding completion percentage', function () {
        User::factory()->create([
            'is_preview_user' => false,
            'onboarding_completed' => true,
        ]);
        User::factory()->create([
            'is_preview_user' => false,
            'onboarding_completed' => false,
        ]);

        // Neither has a subscription (both are non-converters)
        $stats = $this->service->getEngagementStats();

        expect($stats['onboarding_completed_pct'])->toBe(50.0);
    });
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/pest tests/Unit/Services/Admin/UserMetricsServiceTest.php
```

Expected: All tests fail (class not found).

- [ ] **Step 3: Write UserMetricsService**

Create `app/Services/Admin/UserMetricsService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class UserMetricsService
{
    public function getSnapshot(): array
    {
        $totalRegistered = User::where('is_preview_user', false)->count();

        $activeSubscribers = Subscription::whereHas('user', fn ($q) => $q->where('is_preview_user', false))
            ->where('status', 'active')
            ->count();

        $onTrial = Subscription::whereHas('user', fn ($q) => $q->where('is_preview_user', false))
            ->where('status', 'trialing')
            ->where('trial_ends_at', '>', now())
            ->count();

        $usersWithSubscription = Subscription::whereHas('user', fn ($q) => $q->where('is_preview_user', false))
            ->whereIn('status', ['active', 'trialing'])
            ->distinct('user_id')
            ->count('user_id');

        $neverPaid = $totalRegistered - $usersWithSubscription;

        return [
            'total_registered' => $totalRegistered,
            'active_subscribers' => $activeSubscribers,
            'on_trial' => $onTrial,
            'never_paid' => max(0, $neverPaid),
        ];
    }

    public function getTrialBreakdown(): array
    {
        $now = now();

        $baseQuery = fn () => Subscription::whereHas('user', fn ($q) => $q->where('is_preview_user', false));

        $fourPlusDays = (clone $baseQuery)()
            ->where('status', 'trialing')
            ->where('trial_ends_at', '>', $now->copy()->addDays(3))
            ->count();

        $threeDays = (clone $baseQuery)()
            ->where('status', 'trialing')
            ->whereBetween('trial_ends_at', [$now->copy()->addDays(2), $now->copy()->addDays(3)])
            ->count();

        $twoDays = (clone $baseQuery)()
            ->where('status', 'trialing')
            ->whereBetween('trial_ends_at', [$now->copy()->addDays(1), $now->copy()->addDays(2)])
            ->count();

        $oneDay = (clone $baseQuery)()
            ->where('status', 'trialing')
            ->whereBetween('trial_ends_at', [$now->copy(), $now->copy()->addDays(1)])
            ->count();

        $expiringToday = (clone $baseQuery)()
            ->where('status', 'trialing')
            ->whereDate('trial_ends_at', $now->toDateString())
            ->count();

        $expired = (clone $baseQuery)()
            ->where(function ($q) use ($now) {
                $q->where('status', 'expired')
                  ->orWhere(function ($q2) use ($now) {
                      $q2->where('status', 'trialing')
                         ->where('trial_ends_at', '<', $now);
                  });
            })
            ->whereDoesntHave('user', function ($q) {
                $q->whereHas('subscription', fn ($sq) => $sq->where('status', 'active'));
            })
            ->count();

        return [
            'four_plus_days' => $fourPlusDays,
            'three_days' => $threeDays,
            'two_days' => $twoDays,
            'one_day' => $oneDay,
            'expiring_today' => $expiringToday,
            'expired' => $expired,
        ];
    }

    public function getPlanBreakdown(): array
    {
        $results = Subscription::whereHas('user', fn ($q) => $q->where('is_preview_user', false))
            ->where('status', 'active')
            ->select('plan', 'billing_cycle', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as revenue'))
            ->groupBy('plan', 'billing_cycle')
            ->get();

        $plans = [];
        foreach (['student', 'standard', 'family', 'pro'] as $plan) {
            $planRows = $results->where('plan', $plan);
            $monthly = $planRows->firstWhere('billing_cycle', 'monthly');
            $yearly = $planRows->firstWhere('billing_cycle', 'yearly');

            $plans[] = [
                'plan' => $plan,
                'total' => ($monthly->count ?? 0) + ($yearly->count ?? 0),
                'monthly' => $monthly->count ?? 0,
                'yearly' => $yearly->count ?? 0,
                'monthly_revenue' => $monthly->revenue ?? 0,
                'yearly_revenue' => $yearly->revenue ?? 0,
            ];
        }

        return $plans;
    }

    public function getActivity(string $period, int $range): array
    {
        $now = now();
        $format = match ($period) {
            'day' => 'Y-m-d',
            'week' => 'o-W',
            'month' => 'Y-m',
            'quarter' => 'Y-Q',
            'year' => 'Y',
        };

        $startDate = match ($period) {
            'day' => $now->copy()->subDays($range - 1)->startOfDay(),
            'week' => $now->copy()->subWeeks($range - 1)->startOfWeek(),
            'month' => $now->copy()->subMonths($range - 1)->startOfMonth(),
            'quarter' => $now->copy()->subQuarters($range - 1)->firstOfQuarter(),
            'year' => $now->copy()->subYears($range - 1)->startOfYear(),
        };

        $interval = match ($period) {
            'day' => '1 day',
            'week' => '1 week',
            'month' => '1 month',
            'quarter' => '3 months',
            'year' => '1 year',
        };

        $periods = CarbonPeriod::create($startDate, $interval, $now);
        $activity = [];

        foreach ($periods as $date) {
            $periodStart = $date->copy();
            $periodEnd = match ($period) {
                'day' => $periodStart->copy()->endOfDay(),
                'week' => $periodStart->copy()->endOfWeek(),
                'month' => $periodStart->copy()->endOfMonth(),
                'quarter' => $periodStart->copy()->addMonths(3)->subDay()->endOfDay(),
                'year' => $periodStart->copy()->endOfYear(),
            };

            $registrations = User::where('is_preview_user', false)
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->count();

            $conversions = Subscription::whereHas('user', fn ($q) => $q->where('is_preview_user', false))
                ->where('status', 'active')
                ->whereBetween('current_period_start', [$periodStart, $periodEnd])
                ->count();

            $cancellations = Subscription::whereHas('user', fn ($q) => $q->where('is_preview_user', false))
                ->whereNotNull('cancelled_at')
                ->whereBetween('cancelled_at', [$periodStart, $periodEnd])
                ->count();

            $trialExpired = Subscription::whereHas('user', fn ($q) => $q->where('is_preview_user', false))
                ->where('status', 'expired')
                ->whereBetween('trial_ends_at', [$periodStart, $periodEnd])
                ->count();

            $revenue = Subscription::whereHas('user', fn ($q) => $q->where('is_preview_user', false))
                ->where('status', 'active')
                ->whereBetween('current_period_start', [$periodStart, $periodEnd])
                ->sum('amount');

            $activity[] = [
                'period' => $periodStart->format($format),
                'label' => $this->formatPeriodLabel($periodStart, $period),
                'registrations' => $registrations,
                'conversions' => $conversions,
                'cancellations' => $cancellations,
                'trial_expired' => $trialExpired,
                'revenue' => $revenue,
            ];
        }

        return $activity;
    }

    public function getEngagementStats(): array
    {
        $nonConverters = User::where('is_preview_user', false)
            ->whereDoesntHave('subscription', fn ($q) => $q->whereIn('status', ['active']))
            ->get();

        $total = $nonConverters->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'onboarding_completed_pct' => 0.0,
                'used_one_plus_modules_pct' => 0.0,
                'used_three_plus_modules_pct' => 0.0,
            ];
        }

        $onboardingCompleted = $nonConverters->where('onboarding_completed', true)->count();

        $moduleUsage = [];
        foreach ($nonConverters as $user) {
            $modulesUsed = $this->countModulesUsed($user);
            $moduleUsage[] = $modulesUsed;
        }

        $usedOnePlus = collect($moduleUsage)->filter(fn ($count) => $count >= 1)->count();
        $usedThreePlus = collect($moduleUsage)->filter(fn ($count) => $count >= 3)->count();

        return [
            'total' => $total,
            'onboarding_completed_pct' => round(($onboardingCompleted / $total) * 100, 1),
            'used_one_plus_modules_pct' => round(($usedOnePlus / $total) * 100, 1),
            'used_three_plus_modules_pct' => round(($usedThreePlus / $total) * 100, 1),
        ];
    }

    private function countModulesUsed(User $user): int
    {
        $count = 0;
        $userId = $user->id;

        // Check each module table for records belonging to this user
        $moduleTables = [
            'protection_policies',
            'savings_accounts',
            'investments',
            'retirement_plans',
            'properties',
            'goals',
        ];

        foreach ($moduleTables as $table) {
            if (DB::table($table)->where('user_id', $userId)->exists()) {
                $count++;
            }
        }

        // Also check joint ownership
        $jointTables = [
            'savings_accounts',
            'investments',
            'properties',
        ];

        foreach ($jointTables as $table) {
            if (DB::table($table)->where('joint_owner_id', $userId)->exists()) {
                $count++;
            }
        }

        return min($count, count($moduleTables));
    }

    private function formatPeriodLabel(Carbon $date, string $period): string
    {
        return match ($period) {
            'day' => $date->format('D j M'),
            'week' => 'Week ' . $date->format('W'),
            'month' => $date->format('M Y'),
            'quarter' => 'Q' . $date->quarter . ' ' . $date->format('Y'),
            'year' => $date->format('Y'),
        };
    }
}
```

Note: The `countModulesUsed` method checks the actual module tables. Before implementing, verify the exact table names by running `php artisan tinker --execute="echo implode(', ', array_filter(\Illuminate\Support\Facades\Schema::getTableListing(), fn(\$t) => str_contains(\$t, 'protection') || str_contains(\$t, 'saving') || str_contains(\$t, 'invest') || str_contains(\$t, 'retire') || str_contains(\$t, 'propert') || str_contains(\$t, 'goal')));"`. Update the table names in the array if they differ.

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/pest tests/Unit/Services/Admin/UserMetricsServiceTest.php
```

Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Admin/UserMetricsService.php tests/Unit/Services/Admin/UserMetricsServiceTest.php
git commit -m "feat: add UserMetricsService with analytics queries"
```

---

## Task 7: Build UserMetricsController and routes

**Files:**
- Create: `app/Http/Controllers/Api/UserMetricsController.php`
- Create: `tests/Feature/Admin/UserMetricsControllerTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write feature tests**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

    $this->admin = User::factory()->create([
        'is_admin' => true,
        'is_preview_user' => false,
    ]);
    $this->admin->assignRole('admin');
});

describe('UserMetricsController', function () {
    it('returns snapshot data for authenticated admin', function () {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/user-metrics/snapshot');

        $response->assertOk()
            ->assertJsonStructure([
                'total_registered',
                'active_subscribers',
                'on_trial',
                'never_paid',
            ]);
    });

    it('returns trial breakdown for authenticated admin', function () {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/user-metrics/trials');

        $response->assertOk()
            ->assertJsonStructure([
                'four_plus_days',
                'three_days',
                'two_days',
                'one_day',
                'expiring_today',
                'expired',
            ]);
    });

    it('returns plan breakdown for authenticated admin', function () {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/user-metrics/plans');

        $response->assertOk()
            ->assertJsonStructure([
                '*' => ['plan', 'total', 'monthly', 'yearly'],
            ]);
    });

    it('returns activity data with period parameter', function () {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/user-metrics/activity?period=day&range=7');

        $response->assertOk()
            ->assertJsonStructure([
                '*' => ['period', 'label', 'registrations', 'conversions', 'cancellations', 'trial_expired', 'revenue'],
            ]);
    });

    it('returns engagement stats for authenticated admin', function () {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/user-metrics/engagement');

        $response->assertOk()
            ->assertJsonStructure([
                'total',
                'onboarding_completed_pct',
                'used_one_plus_modules_pct',
                'used_three_plus_modules_pct',
            ]);
    });

    it('rejects non-admin users', function () {
        $user = User::factory()->create(['is_admin' => false, 'is_preview_user' => false]);
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/user-metrics/snapshot')->assertForbidden();
    });
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/pest tests/Feature/Admin/UserMetricsControllerTest.php
```

Expected: Fail — controller and routes don't exist yet.

- [ ] **Step 3: Create UserMetricsController**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Admin\UserMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserMetricsController extends Controller
{
    public function __construct(
        private readonly UserMetricsService $metricsService
    ) {}

    public function snapshot(): JsonResponse
    {
        return response()->json($this->metricsService->getSnapshot());
    }

    public function trials(): JsonResponse
    {
        return response()->json($this->metricsService->getTrialBreakdown());
    }

    public function plans(): JsonResponse
    {
        return response()->json($this->metricsService->getPlanBreakdown());
    }

    public function activity(Request $request): JsonResponse
    {
        $period = $request->input('period', 'day');
        $range = (int) $request->input('range', 7);

        $validPeriods = ['day', 'week', 'month', 'quarter', 'year'];
        if (!in_array($period, $validPeriods, true)) {
            return response()->json(['error' => 'Invalid period'], 422);
        }

        $range = max(1, min($range, 365));

        return response()->json($this->metricsService->getActivity($period, $range));
    }

    public function engagement(): JsonResponse
    {
        return response()->json($this->metricsService->getEngagementStats());
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/api.php`, inside the existing admin middleware group (around line 1001), add:

```php
// User Metrics
Route::get('/user-metrics/snapshot', [\App\Http\Controllers\Api\UserMetricsController::class, 'snapshot']);
Route::get('/user-metrics/trials', [\App\Http\Controllers\Api\UserMetricsController::class, 'trials']);
Route::get('/user-metrics/plans', [\App\Http\Controllers\Api\UserMetricsController::class, 'plans']);
Route::get('/user-metrics/activity', [\App\Http\Controllers\Api\UserMetricsController::class, 'activity']);
Route::get('/user-metrics/engagement', [\App\Http\Controllers\Api\UserMetricsController::class, 'engagement']);
```

- [ ] **Step 5: Run tests**

```bash
./vendor/bin/pest tests/Feature/Admin/UserMetricsControllerTest.php
```

Expected: All tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/UserMetricsController.php tests/Feature/Admin/UserMetricsControllerTest.php routes/api.php
git commit -m "feat: add UserMetricsController with five analytics endpoints"
```

---

## Task 8: Add admin service methods (frontend)

**Files:**
- Modify: `resources/js/services/adminService.js`

- [ ] **Step 1: Read current adminService.js**

Read `resources/js/services/adminService.js` to understand the existing pattern (likely uses `apiClient.get()`).

- [ ] **Step 2: Add five new methods**

Add the following methods to `adminService.js`, following the existing pattern:

```javascript
getUserMetricsSnapshot() {
    return apiClient.get('/admin/user-metrics/snapshot');
},

getUserMetricsTrials() {
    return apiClient.get('/admin/user-metrics/trials');
},

getUserMetricsPlans() {
    return apiClient.get('/admin/user-metrics/plans');
},

getUserMetricsActivity(period = 'day', range = 7) {
    return apiClient.get('/admin/user-metrics/activity', {
        params: { period, range }
    });
},

getUserMetricsEngagement() {
    return apiClient.get('/admin/user-metrics/engagement');
},
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/services/adminService.js
git commit -m "feat: add user metrics API methods to adminService"
```

---

## Task 9: Build frontend components — SnapshotCards, TrialBreakdown, PlanBreakdown

**Files:**
- Create: `resources/js/components/Admin/metrics/SnapshotCards.vue`
- Create: `resources/js/components/Admin/metrics/TrialBreakdown.vue`
- Create: `resources/js/components/Admin/metrics/PlanBreakdown.vue`

- [ ] **Step 1: Create SnapshotCards.vue**

```vue
<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div
      v-for="card in cards"
      :key="card.label"
      class="bg-white rounded-card shadow-card p-6 text-center hover:shadow-card-hover transition-shadow duration-150"
    >
      <div class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-2">
        {{ card.label }}
      </div>
      <div class="text-3xl font-black text-horizon-500 leading-tight">
        {{ card.value }}
      </div>
      <div v-if="card.sub" class="text-xs text-neutral-500 mt-1">
        {{ card.sub }}
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SnapshotCards',
  props: {
    data: {
      type: Object,
      required: true,
    },
  },
  computed: {
    cards() {
      const d = this.data;
      const conversionRate = d.total_registered > 0
        ? Math.round((d.active_subscribers / d.total_registered) * 100)
        : 0;
      const neverPaidPct = d.total_registered > 0
        ? Math.round((d.never_paid / d.total_registered) * 100)
        : 0;

      return [
        { label: 'Total Registered', value: d.total_registered, sub: null },
        { label: 'Active Subscribers', value: d.active_subscribers, sub: `${conversionRate}% conversion rate` },
        { label: 'On Trial', value: d.on_trial, sub: '7-day free trial' },
        { label: 'Never Paid', value: d.never_paid, sub: `${neverPaidPct}% of total` },
      ];
    },
  },
};
</script>
```

- [ ] **Step 2: Create TrialBreakdown.vue**

```vue
<template>
  <div class="mb-6">
    <h3 class="text-lg font-bold text-horizon-500 mb-3">Trial Status Breakdown</h3>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <div
        v-for="bucket in buckets"
        :key="bucket.key"
        class="bg-white rounded-card shadow-card p-4 text-center hover:shadow-card-hover transition-shadow duration-150"
      >
        <div class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-1">
          {{ bucket.label }}
        </div>
        <div class="text-2xl font-black text-horizon-500">
          {{ bucket.value }}
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TrialBreakdown',
  props: {
    data: {
      type: Object,
      required: true,
    },
  },
  computed: {
    buckets() {
      const d = this.data;
      return [
        { key: 'four_plus', label: '4+ Days', value: d.four_plus_days },
        { key: 'three', label: '3 Days', value: d.three_days },
        { key: 'two', label: '2 Days', value: d.two_days },
        { key: 'one', label: '1 Day', value: d.one_day },
        { key: 'today', label: 'Expiring Today', value: d.expiring_today },
        { key: 'expired', label: 'Expired', value: d.expired },
      ];
    },
  },
};
</script>
```

- [ ] **Step 3: Create PlanBreakdown.vue**

```vue
<template>
  <div class="mb-8">
    <h3 class="text-lg font-bold text-horizon-500 mb-3">Active Subscribers by Plan</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div
        v-for="plan in data"
        :key="plan.plan"
        class="bg-white rounded-card shadow-card p-6 text-center hover:shadow-card-hover transition-shadow duration-150"
      >
        <div class="text-xs font-bold text-neutral-500 uppercase tracking-widest mb-2">
          {{ plan.plan }}
        </div>
        <div class="text-2xl font-black text-horizon-500">
          {{ plan.total }}
        </div>
        <div class="text-xs text-neutral-500 mt-1">
          {{ plan.monthly }} monthly &middot; {{ plan.yearly }} yearly
        </div>
        <div class="text-sm font-semibold text-spring-500 mt-2 pt-2 border-t border-light-gray">
          {{ formatRevenue(plan) }}/mo
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PlanBreakdown',
  props: {
    data: {
      type: Array,
      required: true,
    },
  },
  methods: {
    formatRevenue(plan) {
      const monthlyRev = plan.monthly_revenue || 0;
      const yearlyMonthlyEquiv = (plan.yearly_revenue || 0) / 12;
      const total = (monthlyRev + yearlyMonthlyEquiv) / 100;
      return `£${total.toFixed(2)}`;
    },
  },
};
</script>
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Admin/metrics/
git commit -m "feat: add SnapshotCards, TrialBreakdown, PlanBreakdown components"
```

---

## Task 10: Build ActivityCharts component

**Files:**
- Create: `resources/js/components/Admin/metrics/ActivityCharts.vue`

- [ ] **Step 1: Create ActivityCharts.vue**

Uses `vue3-apexcharts` (already installed) with colours from `designSystem.js`:

```vue
<template>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Registrations & Conversions -->
    <div class="bg-white rounded-card shadow-card p-6">
      <h4 class="text-sm font-bold text-horizon-500 mb-4">Registrations &amp; Conversions</h4>
      <apexchart
        type="bar"
        height="200"
        :options="registrationsOptions"
        :series="registrationsSeries"
      />
    </div>

    <!-- Revenue -->
    <div class="bg-white rounded-card shadow-card p-6">
      <h4 class="text-sm font-bold text-horizon-500 mb-4">Revenue</h4>
      <apexchart
        type="bar"
        height="200"
        :options="revenueOptions"
        :series="revenueSeries"
      />
    </div>

    <!-- Churn -->
    <div class="bg-white rounded-card shadow-card p-6">
      <h4 class="text-sm font-bold text-horizon-500 mb-4">Churn (Cancellations &amp; Expirations)</h4>
      <apexchart
        type="bar"
        height="200"
        :options="churnOptions"
        :series="churnSeries"
      />
    </div>

    <!-- Engagement -->
    <div class="bg-white rounded-card shadow-card p-6">
      <h4 class="text-sm font-bold text-horizon-500 mb-4">Non-Converters — Engagement</h4>
      <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="text-center p-4 bg-eggshell-500 rounded-lg">
          <div class="text-2xl font-black text-horizon-500">{{ engagement.onboarding_completed_pct }}%</div>
          <div class="text-xs text-neutral-500 mt-1">Completed Onboarding</div>
        </div>
        <div class="text-center p-4 bg-eggshell-500 rounded-lg">
          <div class="text-2xl font-black text-horizon-500">{{ engagement.used_one_plus_modules_pct }}%</div>
          <div class="text-xs text-neutral-500 mt-1">Used 1+ Module</div>
        </div>
        <div class="text-center p-4 bg-eggshell-500 rounded-lg">
          <div class="text-2xl font-black text-horizon-500">{{ engagement.used_three_plus_modules_pct }}%</div>
          <div class="text-xs text-neutral-500 mt-1">Used 3+ Modules</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { CHART_COLORS } from '@/constants/designSystem';

const baseChartOptions = {
  chart: {
    toolbar: { show: false },
    fontFamily: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif",
  },
  grid: {
    borderColor: '#EEEEEE',
    strokeDashArray: 4,
  },
  xaxis: {
    labels: {
      style: { colors: '#717171', fontSize: '11px' },
    },
  },
  yaxis: {
    labels: {
      style: { colors: '#717171', fontSize: '11px' },
    },
  },
  dataLabels: { enabled: false },
  plotOptions: {
    bar: { borderRadius: 3, columnWidth: '60%' },
  },
};

export default {
  name: 'ActivityCharts',
  props: {
    activity: {
      type: Array,
      required: true,
    },
    engagement: {
      type: Object,
      required: true,
    },
  },
  computed: {
    labels() {
      return this.activity.map(a => a.label);
    },
    registrationsOptions() {
      return {
        ...baseChartOptions,
        colors: [CHART_COLORS[5], CHART_COLORS[1]],
        xaxis: { ...baseChartOptions.xaxis, categories: this.labels },
        legend: {
          position: 'bottom',
          fontSize: '12px',
          fontFamily: "'Segoe UI', sans-serif",
          labels: { colors: '#717171' },
        },
      };
    },
    registrationsSeries() {
      return [
        { name: 'Registrations', data: this.activity.map(a => a.registrations) },
        { name: 'Conversions', data: this.activity.map(a => a.conversions) },
      ];
    },
    revenueOptions() {
      return {
        ...baseChartOptions,
        colors: [CHART_COLORS[1]],
        xaxis: { ...baseChartOptions.xaxis, categories: this.labels },
        yaxis: {
          ...baseChartOptions.yaxis,
          labels: {
            ...baseChartOptions.yaxis.labels,
            formatter: (val) => `£${(val / 100).toFixed(0)}`,
          },
        },
        tooltip: {
          y: { formatter: (val) => `£${(val / 100).toFixed(2)}` },
        },
      };
    },
    revenueSeries() {
      return [
        { name: 'Revenue', data: this.activity.map(a => a.revenue) },
      ];
    },
    churnOptions() {
      return {
        ...baseChartOptions,
        colors: ['#F9A8D4', CHART_COLORS[3]],
        xaxis: { ...baseChartOptions.xaxis, categories: this.labels },
        legend: {
          position: 'bottom',
          fontSize: '12px',
          fontFamily: "'Segoe UI', sans-serif",
          labels: { colors: '#717171' },
        },
      };
    },
    churnSeries() {
      return [
        { name: 'Trial Expired', data: this.activity.map(a => a.trial_expired) },
        { name: 'Cancelled', data: this.activity.map(a => a.cancellations) },
      ];
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/Admin/metrics/ActivityCharts.vue
git commit -m "feat: add ActivityCharts component with ApexCharts"
```

---

## Task 11: Build ActivityTable component

**Files:**
- Create: `resources/js/components/Admin/metrics/ActivityTable.vue`

- [ ] **Step 1: Create ActivityTable.vue**

```vue
<template>
  <div class="bg-white rounded-card shadow-card p-6 mb-6">
    <h3 class="text-lg font-bold text-horizon-500 mb-4">Activity Data</h3>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-eggshell-500">
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Period</th>
            <th class="px-4 py-2.5 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">Registrations</th>
            <th class="px-4 py-2.5 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">Conversions</th>
            <th class="px-4 py-2.5 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">Cancellations</th>
            <th class="px-4 py-2.5 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">Trial Expired</th>
            <th class="px-4 py-2.5 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">Revenue</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in data"
            :key="row.period"
            class="border-b border-light-gray hover:bg-savannah-100 transition-colors duration-100"
          >
            <td class="px-4 py-2.5 text-horizon-500">{{ row.label }}</td>
            <td class="px-4 py-2.5 text-right text-horizon-500">{{ row.registrations }}</td>
            <td class="px-4 py-2.5 text-right text-spring-500">{{ row.conversions }}</td>
            <td class="px-4 py-2.5 text-right text-raspberry-500">{{ row.cancellations }}</td>
            <td class="px-4 py-2.5 text-right text-neutral-500">{{ row.trial_expired }}</td>
            <td class="px-4 py-2.5 text-right font-semibold text-horizon-500">{{ formatCurrency(row.revenue) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ActivityTable',
  props: {
    data: {
      type: Array,
      required: true,
    },
  },
  methods: {
    formatCurrency(pence) {
      return `£${(pence / 100).toFixed(2)}`;
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/Admin/metrics/ActivityTable.vue
git commit -m "feat: add ActivityTable component"
```

---

## Task 12: Build UserMetrics tab container and wire into AdminPanel

**Files:**
- Create: `resources/js/components/Admin/UserMetrics.vue`
- Modify: `resources/js/views/Admin/AdminPanel.vue`

- [ ] **Step 1: Create UserMetrics.vue**

```vue
<template>
  <div>
    <h1 class="text-h1 font-black text-horizon-500 mb-1">User Metrics</h1>
    <p class="text-sm text-neutral-500 mb-6">Real-time overview of registrations, trials, subscriptions, and engagement</p>

    <div v-if="loading" class="flex justify-center py-20">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <div v-else-if="error" class="bg-raspberry-50 text-raspberry-600 p-4 rounded-card">
      {{ error }}
    </div>

    <template v-else>
      <SnapshotCards :data="snapshot" />
      <TrialBreakdown :data="trials" />
      <PlanBreakdown :data="plans" />

      <hr class="border-light-gray my-6" />

      <!-- Period selector -->
      <div class="flex items-center justify-between mb-5">
        <h2 class="text-h3 font-bold text-horizon-500">Activity Over Time</h2>
        <div class="inline-flex gap-1 bg-white p-1 rounded-lg shadow-sm">
          <button
            v-for="p in periods"
            :key="p.value"
            class="px-4 py-1.5 rounded-md text-sm font-semibold transition-all duration-150"
            :class="selectedPeriod === p.value
              ? 'bg-raspberry-500 text-white'
              : 'text-neutral-500 hover:bg-savannah-100'"
            @click="changePeriod(p.value)"
          >
            {{ p.label }}
          </button>
        </div>
      </div>

      <ActivityCharts :activity="activity" :engagement="engagement" />
      <ActivityTable :data="activity" />
    </template>
  </div>
</template>

<script>
import adminService from '@/services/adminService';
import SnapshotCards from './metrics/SnapshotCards.vue';
import TrialBreakdown from './metrics/TrialBreakdown.vue';
import PlanBreakdown from './metrics/PlanBreakdown.vue';
import ActivityCharts from './metrics/ActivityCharts.vue';
import ActivityTable from './metrics/ActivityTable.vue';

export default {
  name: 'UserMetrics',
  components: {
    SnapshotCards,
    TrialBreakdown,
    PlanBreakdown,
    ActivityCharts,
    ActivityTable,
  },
  data() {
    return {
      loading: true,
      error: null,
      snapshot: {},
      trials: {},
      plans: [],
      activity: [],
      engagement: {
        total: 0,
        onboarding_completed_pct: 0,
        used_one_plus_modules_pct: 0,
        used_three_plus_modules_pct: 0,
      },
      selectedPeriod: 'day',
      periods: [
        { value: 'day', label: 'Day' },
        { value: 'week', label: 'Week' },
        { value: 'month', label: 'Month' },
        { value: 'quarter', label: 'Quarter' },
        { value: 'year', label: 'Year' },
      ],
    };
  },
  created() {
    this.loadAll();
  },
  methods: {
    async loadAll() {
      this.loading = true;
      this.error = null;

      try {
        const [snapshotRes, trialsRes, plansRes, activityRes, engagementRes] = await Promise.all([
          adminService.getUserMetricsSnapshot(),
          adminService.getUserMetricsTrials(),
          adminService.getUserMetricsPlans(),
          adminService.getUserMetricsActivity(this.selectedPeriod, this.getRangeForPeriod()),
          adminService.getUserMetricsEngagement(),
        ]);

        this.snapshot = snapshotRes.data;
        this.trials = trialsRes.data;
        this.plans = plansRes.data;
        this.activity = activityRes.data;
        this.engagement = engagementRes.data;
      } catch (err) {
        this.error = 'Failed to load user metrics. Please try again.';
        console.error('UserMetrics load error:', err);
      } finally {
        this.loading = false;
      }
    },
    async changePeriod(period) {
      this.selectedPeriod = period;
      try {
        const res = await adminService.getUserMetricsActivity(period, this.getRangeForPeriod());
        this.activity = res.data;
      } catch (err) {
        console.error('Failed to load activity:', err);
      }
    },
    getRangeForPeriod() {
      const ranges = { day: 7, week: 8, month: 6, quarter: 4, year: 3 };
      return ranges[this.selectedPeriod] || 7;
    },
  },
};
</script>
```

- [ ] **Step 2: Add the tab to AdminPanel.vue**

Read `resources/js/views/Admin/AdminPanel.vue` to find the exact tabs array and conditional rendering pattern.

In the `tabs` data array (around line 98), add the user-metrics tab after dashboard:

```javascript
{ id: 'dashboard', label: 'Dashboard' },
{ id: 'user-metrics', label: 'User Metrics' },
{ id: 'users', label: 'User Management' },
// ... rest of tabs
```

In the `getTabIcon` method, add:

```javascript
case 'user-metrics': return 'chart-bar';
```

In the template section where tab content is rendered (around lines 48-65), add:

```html
<UserMetrics v-if="activeTab === 'user-metrics'" />
```

Add the import at the top of the script:

```javascript
import UserMetrics from '@/components/Admin/UserMetrics.vue';
```

And register it in `components`:

```javascript
components: {
  // ... existing components
  UserMetrics,
},
```

- [ ] **Step 3: Verify compilation**

```bash
# Dev server should be running — check terminal for compilation errors
# If not running: ./dev.sh
```

Expected: No compilation errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Admin/UserMetrics.vue resources/js/views/Admin/AdminPanel.vue
git commit -m "feat: add UserMetrics tab to admin panel with all sub-components"
```

---

## Task 13: Seed database and browser test

**Files:** None (testing only)

- [ ] **Step 1: Run database seed**

```bash
php artisan db:seed
```

- [ ] **Step 2: Run all Pest tests**

```bash
./vendor/bin/pest tests/Unit/Services/Admin/UserMetricsServiceTest.php tests/Feature/Admin/UserMetricsControllerTest.php
```

Expected: All tests pass.

- [ ] **Step 3: Browser test — log in as admin**

Navigate to `http://localhost:8000`, log in as `admin@fps.com` with password `Fynl@Adm1n2026!` (or the seeded admin password). Fetch the verification code from the database if needed.

- [ ] **Step 4: Browser test — navigate to User Metrics tab**

Click on the "User Metrics" tab in the admin panel. Verify:
- Page loads without errors
- Snapshot cards display (values may be 0 if no real users)
- Trial breakdown cards display
- Plan breakdown cards display (4 plans: Student, Standard, Family, Pro)
- Period selector renders
- Charts render (may show empty/zero data)
- Data table renders

- [ ] **Step 5: Browser test — period selector**

Click each period button (Day, Week, Month, Quarter, Year). Verify:
- Active button changes to raspberry background
- Charts and table update without errors

- [ ] **Step 6: Browser test — pricing page**

Navigate to the public pricing page. Verify:
- Four plan cards display (Student, Standard, Family, Pro)
- Strikethrough full prices are visible
- Launch discount prices are prominent
- Billing toggle switches between monthly/yearly
- "Launch Discount" badge is visible

- [ ] **Step 7: Final seed**

```bash
php artisan db:seed
```
