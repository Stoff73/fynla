<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Goal;
use App\Models\LifeEvent;
use App\Models\LifeInsurancePolicy;
use App\Models\SavingsAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * INV-2.6.1 — handleListRecords / handleListGoals / handleListLifeEvents
 * must NOT truncate, paginate below 100 items, or summarise lists handed
 * back to the model. The model relies on a complete view to give the
 * user a complete answer, so any silent ceiling reintroduces hallucination
 * vectors ("you have 3 savings accounts" when there are 47).
 *
 * The threshold the spec pins is "seed 50+ records per list tool" — the
 * tests below seed 60 to stay safely above any Laravel default chunk size
 * (Builder::chunk uses 1000, but historical handlers have shipped with
 * ad-hoc 25/50/100 caps that this invariant exists to prevent).
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('handleListRecords returns every savings_account row without truncation', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory()->count(60)->create([
        'user_id' => $user->id,
    ]);

    $agent = app(CoordinatingAgent::class);
    $result = invokeProtectedMethod($agent, 'handleListRecords', [
        ['entity_type' => 'savings_account'],
        $user,
    ]);

    expect($result)->toBeArray();
    expect($result['entity_type'] ?? null)->toBe('savings_account');
    expect($result['count'] ?? null)->toBe(60);
    expect($result['records'] ?? [])->toHaveCount(60);
    expect($result['records'][0])->toHaveKeys(['id', 'account_name', 'institution', 'balance']);
});

it('handleListRecords returns every life_insurance row without truncation', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    LifeInsurancePolicy::factory()->count(60)->create([
        'user_id' => $user->id,
    ]);

    $agent = app(CoordinatingAgent::class);
    $result = invokeProtectedMethod($agent, 'handleListRecords', [
        ['entity_type' => 'life_insurance'],
        $user,
    ]);

    expect($result['count'] ?? null)->toBe(60);
    expect($result['records'] ?? [])->toHaveCount(60);
});

it('handleListGoals returns every goal row without truncation', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    Goal::factory()->count(60)->create([
        'user_id' => $user->id,
    ]);

    $agent = app(CoordinatingAgent::class);
    $result = invokeProtectedMethod($agent, 'handleListGoals', [$user]);

    expect($result)->toBeArray();
    expect($result['has_goals'] ?? null)->toBeTrue();
    expect($result['count'] ?? null)->toBe(60);
    expect($result['goals'] ?? [])->toHaveCount(60);
});

it('handleListLifeEvents returns every life_event row without truncation', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    LifeEvent::factory()->count(60)->create([
        'user_id' => $user->id,
    ]);

    $agent = app(CoordinatingAgent::class);
    $result = invokeProtectedMethod($agent, 'handleListLifeEvents', [$user]);

    expect($result)->toBeArray();
    expect($result['has_events'] ?? null)->toBeTrue();
    expect($result['count'] ?? null)->toBe(60);
    expect($result['events'] ?? [])->toHaveCount(60);
});

it('preserves cross-user isolation while listing complete data', function () {
    $owner = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory()->count(60)->create(['user_id' => $owner->id]);
    SavingsAccount::factory()->count(40)->create(['user_id' => $other->id]);

    $agent = app(CoordinatingAgent::class);
    $result = invokeProtectedMethod($agent, 'handleListRecords', [
        ['entity_type' => 'savings_account'],
        $owner,
    ]);

    expect($result['count'])->toBe(60);
});

/**
 * Helper — use reflection to call the private handler methods so the
 * test focuses on the read-completeness contract without the full
 * tool-routing stack getting in the way.
 */
function invokeProtectedMethod(object $instance, string $methodName, array $args = []): mixed
{
    $reflection = new \ReflectionClass($instance);
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);

    return $method->invoke($instance, ...$args);
}
