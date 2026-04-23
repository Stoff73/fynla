<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\TrialService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new TrialService();
});

describe('restartTrial', function () {
    it('reactivates an expired subscription with a new 14-day window', function () {
        $user = User::factory()->create(['plan' => 'free']);
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'expired',
            'trial_started_at' => now()->subDays(20),
            'trial_ends_at' => now()->subDays(13),
            'data_retention_starts_at' => now()->subDays(13),
        ]);

        $this->service->restartTrial($user, days: 14);

        $sub->refresh();
        $user->refresh();

        expect($sub->status)->toBe('trialing');
        expect($sub->trial_ends_at->isAfter(now()->addDays(13)))->toBeTrue();
        expect($sub->data_retention_starts_at)->toBeNull();
        expect($user->plan)->toBe('pro');
    });

    it('is idempotent — does nothing if user is already in active trial', function () {
        $user = User::factory()->create(['plan' => 'pro']);
        $futureEnd = now()->addDays(5)->setMicrosecond(0);
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'trialing',
            'trial_started_at' => now()->subDays(2),
            'trial_ends_at' => $futureEnd,
        ]);

        $this->service->restartTrial($user, days: 14);

        $sub->refresh();

        expect($sub->trial_ends_at->toDateTimeString())->toBe($futureEnd->toDateTimeString());
    });

    it('throws if user has an active paid subscription', function () {
        $user = User::factory()->create(['plan' => 'standard']);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'current_period_end' => now()->addDays(30),
        ]);

        expect(fn () => $this->service->restartTrial($user, days: 14))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('updates users.plan from free back to pro on restart', function () {
        $user = User::factory()->create(['plan' => 'free']);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'expired',
            'trial_ends_at' => now()->subDays(2),
        ]);

        $this->service->restartTrial($user, days: 14);

        expect($user->fresh()->plan)->toBe('pro');
    });
});
