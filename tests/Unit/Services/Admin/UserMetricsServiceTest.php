<?php

declare(strict_types=1);

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

    it('correctly counts trialing users', function () {
        $trialUser = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->trialing()->create([
            'user_id' => $trialUser->id,
            'trial_ends_at' => now()->addDays(7),
        ]);

        // Expired trial (trial_ends_at in the past, but status still trialing)
        $expiredTrialUser = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->trialing()->create([
            'user_id' => $expiredTrialUser->id,
            'trial_ends_at' => now()->subDay(),
        ]);

        $snapshot = $this->service->getSnapshot();

        expect($snapshot['on_trial'])->toBe(1);
    });

    it('correctly counts never-paid users', function () {
        // 5 real users total
        $users = User::factory()->count(5)->create(['is_preview_user' => false]);

        // 1 active subscriber
        Subscription::factory()->create([
            'user_id' => $users[0]->id,
            'status' => 'active',
        ]);

        // 1 trialing user
        Subscription::factory()->trialing()->create([
            'user_id' => $users[1]->id,
        ]);

        // 3 users with no subscription at all = never_paid
        $snapshot = $this->service->getSnapshot();

        expect($snapshot['total_registered'])->toBe(5)
            ->and($snapshot['never_paid'])->toBe(3);
    });
});

// =========================================================================
// getTrialBreakdown
// =========================================================================

describe('getTrialBreakdown', function () {
    it('buckets trial users by days remaining', function () {
        // 4+ days
        $user1 = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->trialing()->create([
            'user_id' => $user1->id,
            'trial_ends_at' => now()->addDays(5),
        ]);

        // 3 days bucket (between now+2d and now+3d)
        $user2 = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->trialing()->create([
            'user_id' => $user2->id,
            'trial_ends_at' => now()->addDays(2)->addHours(12),
        ]);

        // 2 days bucket (between now+1d and now+2d)
        $user3 = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->trialing()->create([
            'user_id' => $user3->id,
            'trial_ends_at' => now()->addDays(1)->addHours(12),
        ]);

        // 1 day bucket (between now and now+1d)
        $user4 = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->trialing()->create([
            'user_id' => $user4->id,
            'trial_ends_at' => now()->addHours(12),
        ]);

        $breakdown = $this->service->getTrialBreakdown();

        expect($breakdown['four_plus_days'])->toBe(1)
            ->and($breakdown['three_days'])->toBe(1)
            ->and($breakdown['two_days'])->toBe(1)
            ->and($breakdown['one_day'])->toBe(1);
    });

    it('counts expired trials correctly', function () {
        // Expired trial (trialing with trial_ends_at in the past, no active sub)
        $user1 = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->trialing()->create([
            'user_id' => $user1->id,
            'trial_ends_at' => now()->subDays(2),
        ]);

        // Explicitly expired status
        $user2 = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->expired()->create([
            'user_id' => $user2->id,
            'trial_ends_at' => now()->subDays(5),
        ]);

        // User with expired trial but also an active sub (should NOT count as expired)
        // Note: hasOne relationship means one subscription per user, so this user's
        // subscription is active — they shouldn't appear in expired
        $user3 = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->create([
            'user_id' => $user3->id,
            'status' => 'active',
        ]);

        $breakdown = $this->service->getTrialBreakdown();

        expect($breakdown['expired'])->toBe(2);
    });

    it('excludes preview users from trial breakdown', function () {
        $previewUser = User::factory()->create(['is_preview_user' => true]);
        Subscription::factory()->trialing()->create([
            'user_id' => $previewUser->id,
            'trial_ends_at' => now()->addDays(5),
        ]);

        $breakdown = $this->service->getTrialBreakdown();

        expect($breakdown['four_plus_days'])->toBe(0);
    });
});

// =========================================================================
// getPlanBreakdown
// =========================================================================

describe('getPlanBreakdown', function () {
    it('groups active subscriptions by plan and billing cycle', function () {
        // 2 monthly student subs
        foreach (range(1, 2) as $_) {
            $user = User::factory()->create(['is_preview_user' => false]);
            Subscription::factory()->create([
                'user_id' => $user->id,
                'plan' => 'student',
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'amount' => 399,
            ]);
        }

        // 1 yearly standard sub
        $user = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan' => 'standard',
            'billing_cycle' => 'yearly',
            'status' => 'active',
            'amount' => 10000,
        ]);

        // 1 trialing sub (should NOT count)
        $trialUser = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()->trialing()->create([
            'user_id' => $trialUser->id,
            'plan' => 'pro',
        ]);

        $breakdown = $this->service->getPlanBreakdown();

        // Find student plan
        $student = collect($breakdown)->firstWhere('plan', 'student');
        expect($student['total'])->toBe(2)
            ->and($student['monthly'])->toBe(2)
            ->and($student['yearly'])->toBe(0)
            ->and($student['monthly_revenue'])->toBe(798);

        // Find standard plan
        $standard = collect($breakdown)->firstWhere('plan', 'standard');
        expect($standard['total'])->toBe(1)
            ->and($standard['monthly'])->toBe(0)
            ->and($standard['yearly'])->toBe(1)
            ->and($standard['yearly_revenue'])->toBe(10000);

        // Pro should be 0 (trialing doesn't count)
        $pro = collect($breakdown)->firstWhere('plan', 'pro');
        expect($pro['total'])->toBe(0);
    });

    it('returns all four plan types', function () {
        $breakdown = $this->service->getPlanBreakdown();

        $planNames = array_column($breakdown, 'plan');
        expect($planNames)->toBe(['student', 'standard', 'family', 'pro']);
    });

    it('excludes preview user subscriptions', function () {
        $previewUser = User::factory()->create(['is_preview_user' => true]);
        Subscription::factory()->create([
            'user_id' => $previewUser->id,
            'plan' => 'pro',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'amount' => 1999,
        ]);

        $breakdown = $this->service->getPlanBreakdown();
        $pro = collect($breakdown)->firstWhere('plan', 'pro');

        expect($pro['total'])->toBe(0)
            ->and($pro['monthly_revenue'])->toBe(0);
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
        \App\Models\Property::factory()->create(['user_id' => $user1->id]);

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
