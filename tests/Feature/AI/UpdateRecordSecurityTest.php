<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Estate\Trust;
use App\Models\FamilyMember;
use App\Models\Goal;
use App\Models\LifeEvent;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

// ─── Allowlist enforcement: forbidden fields rejected ──────────────────

it('rejects Trust.settlor with fields_not_allowed', function (): void {
    $user = User::factory()->create();
    $trust = Trust::factory()->create(['user_id' => $user->id]);

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'trust',
        'entity_id' => $trust->id,
        'fields' => ['settlor' => 'Some Other Person'],
    ], $user);

    expect($result['error'])->toBe('fields_not_allowed');
    expect($result['disallowed_fields'])->toContain('settlor');
    expect($trust->fresh()->settlor)->toBe($trust->settlor);
});

it('rejects Mortgage.start_date with fields_not_allowed', function (): void {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id]);
    $mortgage = Mortgage::factory()->create([
        'user_id' => $user->id,
        'property_id' => $property->id,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'mortgage',
        'entity_id' => $mortgage->id,
        'fields' => ['start_date' => '2010-01-01'],
    ], $user);

    expect($result['error'])->toBe('fields_not_allowed');
    expect($result['disallowed_fields'])->toContain('start_date');
});

it('rejects Mortgage.mortgage_type with fields_not_allowed', function (): void {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id]);
    $mortgage = Mortgage::factory()->create([
        'user_id' => $user->id,
        'property_id' => $property->id,
        'mortgage_type' => 'repayment',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'mortgage',
        'entity_id' => $mortgage->id,
        'fields' => ['mortgage_type' => 'interest_only'],
    ], $user);

    expect($result['error'])->toBe('fields_not_allowed');
    expect($mortgage->fresh()->mortgage_type)->toBe('repayment');
});

it('rejects FamilyMember.relationship with fields_not_allowed', function (): void {
    $user = User::factory()->create();
    $member = FamilyMember::factory()->create([
        'user_id' => $user->id,
        'relationship' => 'child',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'family_member',
        'entity_id' => $member->id,
        'fields' => ['relationship' => 'spouse'],
    ], $user);

    expect($result['error'])->toBe('fields_not_allowed');
    expect($member->fresh()->relationship)->toBe('child');
});

it('rejects identity FKs across every entity', function (): void {
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id]);

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'goal',
        'entity_id' => $goal->id,
        'fields' => ['user_id' => 9999, 'goal_name' => 'New name'],
    ], $user);

    expect($result['error'])->toBe('fields_not_allowed');
    expect($result['disallowed_fields'])->toContain('user_id');
    expect($goal->fresh()->user_id)->toBe($user->id);
});

it('rejects unsupported entity types', function (): void {
    $user = User::factory()->create();

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'unicorn',
        'entity_id' => 1,
        'fields' => ['name' => 'Sparkle'],
    ], $user);

    expect($result['error'])->toBe('unsupported_entity_type');
});

// ─── Allowlist enforcement: allowed fields succeed ─────────────────────

it('persists allowed Goal fields directly', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $goal = Goal::factory()->create(['user_id' => $user->id, 'goal_name' => 'Old']);

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'goal',
        'entity_id' => $goal->id,
        'fields' => [
            'goal_name' => 'House deposit',
            'target_amount' => 50000,
            'priority' => 'high',
        ],
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('goal');
    expect($result['entity_id'])->toBe($goal->id);
    expect($result['fields_updated'])->toEqualCanonicalizing(['goal_name', 'target_amount', 'priority']);

    $fresh = $goal->fresh();
    expect($fresh->goal_name)->toBe('House deposit');
    expect((float) $fresh->target_amount)->toBe(50000.0);
    expect($fresh->priority)->toBe('high');
});

it('persists allowed SavingsAccount fields directly', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $account = SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 1000,
        'interest_rate' => 1.5,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'savings_account',
        'entity_id' => $account->id,
        'fields' => ['current_balance' => 2500, 'interest_rate' => 4.25],
    ], $user);

    expect($result['success'])->toBeTrue();
    expect((float) $account->fresh()->current_balance)->toBe(2500.0);
    expect((float) $account->fresh()->interest_rate)->toBe(4.25);
});

it('persists allowed Mortgage fields directly', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $property = Property::factory()->create(['user_id' => $user->id]);
    $mortgage = Mortgage::factory()->create([
        'user_id' => $user->id,
        'property_id' => $property->id,
        'outstanding_balance' => 200000,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'mortgage',
        'entity_id' => $mortgage->id,
        'fields' => ['outstanding_balance' => 180000, 'monthly_payment' => 1100],
    ], $user);

    expect($result['success'])->toBeTrue();
    expect((float) $mortgage->fresh()->outstanding_balance)->toBe(180000.0);
});

// ─── Cross-cutting behaviour ───────────────────────────────────────────

it('blocks preview users without consulting the allowlist', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);
    $goal = Goal::factory()->create(['user_id' => $user->id]);

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'goal',
        'entity_id' => $goal->id,
        'fields' => ['goal_name' => 'Anything'],
    ], $user);

    expect($result['blocked'] ?? false)->toBeTrue();
});

it('rejects empty fields payload before allowlist check', function (): void {
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id]);

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'goal',
        'entity_id' => $goal->id,
        'fields' => [],
    ], $user);

    // Either the empty-fields short-circuit or the entity-type check —
    // both are valid early returns. We just need NOT a fill_form return.
    expect($result)->not->toHaveKey('action');
});

it('rejects update for record owned by another user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $other->id]);

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'goal',
        'entity_id' => $goal->id,
        'fields' => ['goal_name' => 'hijack'],
    ], $user);

    expect($result['error'] ?? null)->not->toBeNull();
    expect($goal->fresh()->goal_name)->not->toBe('hijack');
});

it('returns success: true (not action: fill_form) on a valid update', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $event = LifeEvent::factory()->create(['user_id' => $user->id]);

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'life_event',
        'entity_id' => $event->id,
        'fields' => ['event_name' => 'Updated event'],
    ], $user);

    expect($result['success'] ?? false)->toBeTrue();
    expect($result)->not->toHaveKey('action');
});
