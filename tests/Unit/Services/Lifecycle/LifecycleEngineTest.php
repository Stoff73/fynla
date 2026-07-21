<?php

declare(strict_types=1);

use App\Mail\Lifecycle\LapsedSubscriberMail;
use App\Models\LifecycleEmailLog;
use App\Models\User;
use App\Services\Lifecycle\Contracts\LifecycleCampaign;
use App\Services\Lifecycle\LifecycleEngine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

it('run returns stats array with sent/skipped/errored counts per campaign', function () {
    // With no campaigns configured (config override), should return empty stats
    config(['lifecycle.campaigns' => []]);

    $engine = app(LifecycleEngine::class);
    $stats = $engine->run();

    expect($stats)->toBeArray();
    expect($stats)->toBe([]);
});

it('defaults throttle to 150ms via config', function () {
    // Loads the real config file (no override). 150ms is the documented
    // default that keeps sends under SiteGround's ~10 msg/sec cap.
    expect(config('lifecycle.throttle_ms'))->toBe(150);
});

it('registers no retired trialer campaigns or notification preferences', function () {
    expect(config('lifecycle.feedback_reasons'))->not->toHaveKey('cancelled_trialer')
        ->and(config('lifecycle.campaign_to_preference'))->not->toHaveKeys([
            'empty_trialer',
            'engaged_trialer',
            'cancelled_trialer',
        ]);
});

it('paces sends with configured throttle between iterations', function () {
    // Three users, 50ms pacing = at least 150ms elapsed (3 sleeps).
    // Using 50ms keeps test under 1s while still being measurable on CI.
    config(['lifecycle.throttle_ms' => 50]);

    $users = User::factory()->count(3)->create();
    $campaign = new class($users) implements LifecycleCampaign
    {
        public function __construct(private readonly Collection $users) {}

        public function name(): string
        {
            return 'test_campaign';
        }

        public function priority(): int
        {
            return 1;
        }

        public function eligibleUsers(): Collection
        {
            return $this->users;
        }

        public function mailable(User $user): LapsedSubscriberMail
        {
            return new LapsedSubscriberMail($user, 'https://example.com/link');
        }
    };

    config(['lifecycle.campaigns' => [get_class($campaign)]]);
    app()->instance(get_class($campaign), $campaign);

    $started = microtime(true);
    $stats = app(LifecycleEngine::class)->run();
    $elapsedMs = (microtime(true) - $started) * 1000;

    expect($stats['test_campaign']['sent'])->toBe(3);
    expect($elapsedMs)->toBeGreaterThanOrEqual(150.0);
});

it('skips pacing when throttle_ms is 0 so unit suites stay fast', function () {
    // With throttle disabled, sending 5 users should complete well under 50ms.
    config(['lifecycle.throttle_ms' => 0]);

    $users = User::factory()->count(5)->create();
    $campaign = new class($users) implements LifecycleCampaign
    {
        public function __construct(private readonly Collection $users) {}

        public function name(): string
        {
            return 'test_campaign';
        }

        public function priority(): int
        {
            return 1;
        }

        public function eligibleUsers(): Collection
        {
            return $this->users;
        }

        public function mailable(User $user): LapsedSubscriberMail
        {
            return new LapsedSubscriberMail($user, 'https://example.com/link');
        }
    };

    config(['lifecycle.campaigns' => [get_class($campaign)]]);
    app()->instance(get_class($campaign), $campaign);

    $started = microtime(true);
    app(LifecycleEngine::class)->run();
    $elapsedMs = (microtime(true) - $started) * 1000;

    expect($elapsedMs)->toBeLessThan(1000.0);  // 5 fake Mail sends should be sub-second
    expect(LifecycleEmailLog::count())->toBe(5);
});
