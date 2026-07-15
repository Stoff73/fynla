<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\UserMetricsService;

beforeEach(function () {
    $this->service = app(UserMetricsService::class);
});

// =========================================================================
// getSnapshot
// =========================================================================

describe('getSnapshot', function () {
    it('returns correct counts excluding preview users', function () {
        // 3 real users
        User::factory()->count(3)->create(['is_preview_user' => false]);

        // 2 preview users (should be excluded)
        User::factory()->count(2)->create(['is_preview_user' => true]);

        $snapshot = $this->service->getSnapshot();

        expect($snapshot['total_registered'])->toBe(3);
    });

    it('correctly counts active subscribers', function () {
        $activeUser = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->create([
            'user_id' => $activeUser->id,
            'status' => 'active',
        ]);

        $trialUser = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->trialing()->create([
            'user_id' => $trialUser->id,
        ]);

        // Preview user with active sub (should not count)
        $previewUser = User::factory()->create(['is_preview_user' => true]);
        Subscription::factory()->create([
            'user_id' => $previewUser->id,
            'status' => 'active',
        ]);

        $snapshot = $this->service->getSnapshot();

        expect($snapshot['active_subscribers'])->toBe(1);
    });

    it('correctly counts never-paid users', function () {
        // 5 real users total
        $users = User::factory()->count(5)->create(['is_preview_user' => false]);

        // 1 active subscriber; the other 4 have no active subscription =
        // never_paid (pure freemium: a user is "paid" only with an active sub).
        Subscription::factory()->create([
            'user_id' => $users[0]->id,
            'status' => 'active',
        ]);

        $snapshot = $this->service->getSnapshot();

        expect($snapshot['total_registered'])->toBe(5)
            ->and($snapshot['never_paid'])->toBe(4);
    });
});

// =========================================================================
// getPlanBreakdown
// =========================================================================

describe('getPlanBreakdown', function () {
    it('groups active subscriptions by plan and billing cycle', function () {
        // 2 monthly Premium subscriptions
        foreach (range(1, 2) as $_) {
            $user = User::factory()->create(['is_preview_user' => false]);
            Subscription::factory()->create([
                'user_id' => $user->id,
                'plan' => 'premium',
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'amount' => 699,
            ]);
        }

        // 1 yearly Premium subscription
        $user = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan' => 'premium',
            'billing_cycle' => 'yearly',
            'status' => 'active',
            'amount' => 5999,
        ]);

        // 1 trialing sub (should NOT count)
        $trialUser = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->trialing()->create([
            'user_id' => $trialUser->id,
            'plan' => 'premium',
        ]);

        $breakdown = $this->service->getPlanBreakdown();

        $premium = collect($breakdown)->firstWhere('plan', 'premium');
        expect($premium['total'])->toBe(3)
            ->and($premium['monthly'])->toBe(2)
            ->and($premium['yearly'])->toBe(1)
            ->and($premium['monthly_revenue'])->toBe(1398)
            ->and($premium['yearly_revenue'])->toBe(5999);
    });

    it('returns only the canonical paid plan type', function () {
        $breakdown = $this->service->getPlanBreakdown();

        $planNames = array_column($breakdown, 'plan');
        expect($planNames)->toBe(['premium']);
    });

    it('excludes preview user subscriptions', function () {
        $previewUser = User::factory()->create(['is_preview_user' => true]);
        Subscription::factory()->create([
            'user_id' => $previewUser->id,
            'plan' => 'premium',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'amount' => 1999,
        ]);

        $breakdown = $this->service->getPlanBreakdown();
        $premium = collect($breakdown)->firstWhere('plan', 'premium');

        expect($premium['total'])->toBe(0)
            ->and($premium['monthly_revenue'])->toBe(0);
    });
});

// =========================================================================
// getActivity
// =========================================================================

describe('getActivity', function () {
    it('returns correct number of period buckets', function () {
        $activity = $this->service->getActivity('month', 6);

        expect(count($activity))->toBeGreaterThanOrEqual(1)
            ->and(count($activity))->toBeLessThanOrEqual(6);
    });

    it('counts registrations in the correct period', function () {
        // Create a user registered 2 days ago
        User::factory()->create([
            'is_preview_user' => false,
            'created_at' => now()->subDays(2),
        ]);

        // Create a user registered today
        User::factory()->create([
            'is_preview_user' => false,
            'created_at' => now(),
        ]);

        $activity = $this->service->getActivity('week', 2);

        // Total registrations across all buckets should be 2
        $totalRegistrations = array_sum(array_column($activity, 'registrations'));
        expect($totalRegistrations)->toBe(2);
    });

    it('excludes preview users from activity data', function () {
        User::factory()->create([
            'is_preview_user' => true,
            'created_at' => now(),
        ]);

        User::factory()->create([
            'is_preview_user' => false,
            'created_at' => now(),
        ]);

        $activity = $this->service->getActivity('month', 1);
        $totalRegistrations = array_sum(array_column($activity, 'registrations'));

        expect($totalRegistrations)->toBe(1);
    });

    it('tracks conversions and cancellations', function () {
        // Active subscription started today (within the current month bucket)
        $user1 = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->create([
            'user_id' => $user1->id,
            'status' => 'active',
            'current_period_start' => now()->subHour(),
            'amount' => 1099,
        ]);

        // Cancelled subscription today
        $user2 = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->cancelled()->create([
            'user_id' => $user2->id,
            'cancelled_at' => now()->subHour(),
        ]);

        $activity = $this->service->getActivity('month', 1);

        $totalConversions = array_sum(array_column($activity, 'conversions'));
        $totalCancellations = array_sum(array_column($activity, 'cancellations'));

        expect($totalConversions)->toBe(1)
            ->and($totalCancellations)->toBe(1);
    });

    it('accepts different period types', function () {
        $dayActivity = $this->service->getActivity('day', 7);
        $weekActivity = $this->service->getActivity('week', 4);
        $quarterActivity = $this->service->getActivity('quarter', 4);
        $yearActivity = $this->service->getActivity('year', 2);

        // All should return arrays without errors
        expect($dayActivity)->toBeArray()
            ->and($weekActivity)->toBeArray()
            ->and($quarterActivity)->toBeArray()
            ->and($yearActivity)->toBeArray();
    });
});

// =========================================================================
// getEngagementStats
// =========================================================================

describe('getEngagementStats', function () {
    it('returns onboarding completion percentage for non-converters', function () {
        // 4 users with no active subscription
        User::factory()->count(2)->create([
            'is_preview_user' => false,
            'onboarding_completed' => true,
        ]);
        User::factory()->count(2)->create([
            'is_preview_user' => false,
            'onboarding_completed' => false,
        ]);

        $stats = $this->service->getEngagementStats();

        expect($stats['total'])->toBe(4)
            ->and($stats['onboarding_completed_pct'])->toBe(50.0);
    });

    it('excludes active subscribers from engagement stats', function () {
        // User with active sub (should be excluded from non-converters)
        $activeUser = User::factory()->create([
            'is_preview_user' => false,
            'onboarding_completed' => true,
        ]);
        Subscription::factory()->create([
            'user_id' => $activeUser->id,
            'status' => 'active',
        ]);

        // User without active sub (non-converter)
        User::factory()->create([
            'is_preview_user' => false,
            'onboarding_completed' => false,
        ]);

        $stats = $this->service->getEngagementStats();

        expect($stats['total'])->toBe(1)
            ->and($stats['onboarding_completed_pct'])->toBe(0.0);
    });

    it('calculates module usage percentages', function () {
        // User who has used 2 modules (savings + properties)
        $user1 = User::factory()->create([
            'is_preview_user' => false,
            'onboarding_completed' => true,
        ]);
        SavingsAccount::factory()->create(['user_id' => $user1->id]);
        Property::factory()->create(['user_id' => $user1->id]);

        // User who has used 0 modules
        User::factory()->create([
            'is_preview_user' => false,
            'onboarding_completed' => false,
        ]);

        $stats = $this->service->getEngagementStats();

        expect($stats['total'])->toBe(2)
            ->and($stats['used_one_plus_modules_pct'])->toBe(50.0)
            ->and($stats['used_three_plus_modules_pct'])->toBe(0.0);
    });

    it('returns zeros when there are no non-converters', function () {
        // Only an active subscriber
        $user = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $stats = $this->service->getEngagementStats();

        expect($stats['total'])->toBe(0)
            ->and($stats['onboarding_completed_pct'])->toBe(0.0)
            ->and($stats['used_one_plus_modules_pct'])->toBe(0.0)
            ->and($stats['used_three_plus_modules_pct'])->toBe(0.0);
    });

    it('excludes preview users from engagement stats', function () {
        User::factory()->create([
            'is_preview_user' => true,
            'onboarding_completed' => true,
        ]);

        User::factory()->create([
            'is_preview_user' => false,
            'onboarding_completed' => true,
        ]);

        $stats = $this->service->getEngagementStats();

        expect($stats['total'])->toBe(1);
    });
});
