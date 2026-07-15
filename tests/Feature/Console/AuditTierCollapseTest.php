<?php

declare(strict_types=1);

use App\Console\Commands\AuditTierCollapse;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;

/**
 * @return array{exit_code: int, payload: array<string, int|bool>}
 */
function runTierCollapseAudit(): array
{
    $payload = app(AuditTierCollapse::class)->audit();

    return [
        'exit_code' => $payload['safe_to_collapse'] ? 0 : 1,
        'payload' => $payload,
    ];
}

it('prints the stable safe JSON object and exits zero when there are no paid rows', function () {
    $result = runTierCollapseAudit();
    $expectedJson = json_encode($result['payload'], JSON_THROW_ON_ERROR);

    $this->artisan('subscriptions:audit-tier-collapse --json')
        ->expectsOutput($expectedJson)
        ->assertExitCode(0);

    expect($result)->toBe([
        'exit_code' => 0,
        'payload' => [
            'active_paid_subscriptions' => 0,
            'active_paid_users' => 0,
            'completed_payments' => 0,
            'retired_tier_user_rows' => 0,
            'retired_tier_subscription_rows' => 0,
            'safe_to_collapse' => true,
        ],
    ]);
});

it('blocks collapse for each currently entitled real paid subscription', function (string $status) {
    $user = User::factory()->create([
        'plan' => 'tier2',
        'tier' => null,
        'is_preview_user' => false,
        'is_lifecycle_test_user' => false,
    ]);

    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'tier2',
        'status' => $status,
        'current_period_end' => now()->addMonth(),
    ]);

    $result = runTierCollapseAudit();

    $this->artisan('subscriptions:audit-tier-collapse --json')
        ->assertExitCode(1);

    expect($result['exit_code'])->toBe(1)
        ->and($result['payload'])->toBe([
            'active_paid_subscriptions' => 1,
            'active_paid_users' => 1,
            'completed_payments' => 0,
            'retired_tier_user_rows' => 1,
            'retired_tier_subscription_rows' => 1,
            'safe_to_collapse' => false,
        ]);
})->with(['active', 'cancelled', 'past_due']);

it('excludes flagged non-real accounts from safety counts but still reports retired rows', function (array $flags) {
    $user = User::factory()->create(array_merge([
        'plan' => 'tier1',
        'tier' => null,
        'is_preview_user' => false,
        'is_lifecycle_test_user' => false,
    ], $flags));

    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'tier1',
        'status' => 'active',
        'current_period_end' => now()->addMonth(),
    ]);

    $result = runTierCollapseAudit();

    expect($result['exit_code'])->toBe(0)
        ->and($result['payload'])->toBe([
            'active_paid_subscriptions' => 0,
            'active_paid_users' => 0,
            'completed_payments' => 0,
            'retired_tier_user_rows' => 1,
            'retired_tier_subscription_rows' => 1,
            'safe_to_collapse' => true,
        ]);
})->with([
    'preview user' => [['is_preview_user' => true]],
    'lifecycle test user' => [['is_lifecycle_test_user' => true]],
]);

it('reports historical completed payments without blocking collapse or changing stored rows', function () {
    $user = User::factory()->create([
        'plan' => 'tier3',
        'tier' => null,
        'is_preview_user' => false,
        'is_lifecycle_test_user' => false,
    ]);
    $subscription = Subscription::factory()->expired()->create([
        'user_id' => $user->id,
        'plan' => 'tier3',
    ]);
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'subscription_id' => $subscription->id,
        'plan_slug' => 'tier3',
        'status' => 'completed',
    ]);

    $before = [
        'user' => $user->fresh()->getAttributes(),
        'subscription' => $subscription->fresh()->getAttributes(),
        'payment' => $payment->fresh()->getAttributes(),
    ];

    $result = runTierCollapseAudit();

    expect($result['exit_code'])->toBe(0)
        ->and($result['payload'])->toBe([
            'active_paid_subscriptions' => 0,
            'active_paid_users' => 0,
            'completed_payments' => 1,
            'retired_tier_user_rows' => 1,
            'retired_tier_subscription_rows' => 1,
            'safe_to_collapse' => true,
        ])
        ->and($user->fresh()->getAttributes())->toBe($before['user'])
        ->and($subscription->fresh()->getAttributes())->toBe($before['subscription'])
        ->and($payment->fresh()->getAttributes())->toBe($before['payment']);
});
