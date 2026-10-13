<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Goal;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

// ─── First call: returns confirmation token ─────────────────────────────

it('first call returns requires_confirmation with a deterministic token', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $goal = Goal::factory()->create(['user_id' => $user->id]);

    $result = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => $goal->id,
    ], $user);

    expect($result)->toHaveKeys(['requires_confirmation', 'confirmation_token', 'entity_type', 'entity_id', 'preview_message']);
    expect($result['requires_confirmation'])->toBeTrue();
    expect($result['confirmation_token'])->toBeString();
    expect(strlen($result['confirmation_token']))->toBe(64); // sha256 hex
    expect($result['entity_type'])->toBe('goal');
    expect($result['entity_id'])->toBe($goal->id);

    // Token must be deterministic — same inputs, same token.
    $again = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => $goal->id,
    ], $user);
    expect($again['confirmation_token'])->toBe($result['confirmation_token']);

    // Record must still exist after a confirmation-pending call.
    expect(Goal::find($goal->id))->not->toBeNull();
});

// ─── Second call: token matches → deletes ──────────────────────────────

it('second call with matching token deletes the record', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $goal = Goal::factory()->create(['user_id' => $user->id]);

    $first = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => $goal->id,
    ], $user);

    $token = $first['confirmation_token'];

    $second = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => $goal->id,
        'confirmation_token' => $token,
    ], $user);

    expect($second['success'] ?? false)->toBeTrue();
    expect($second['deleted'] ?? false)->toBeTrue();
    expect($second['entity_type'])->toBe('goal');
    expect($second['entity_id'])->toBe($goal->id);
    expect(Goal::find($goal->id))->toBeNull();
});

// ─── Wrong token rejected ──────────────────────────────────────────────

it('rejects a wrong token by returning a fresh confirmation requirement', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $goal = Goal::factory()->create(['user_id' => $user->id]);

    $result = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => $goal->id,
        'confirmation_token' => str_repeat('a', 64),
    ], $user);

    expect($result['requires_confirmation'])->toBeTrue();
    expect(Goal::find($goal->id))->not->toBeNull();
});

// ─── Token is bound to (user, entity_type, entity_id, day) ─────────────

it("rejects another user's token (user_id is part of the hash)", function (): void {
    $userA = User::factory()->create(['is_preview_user' => false]);
    $userB = User::factory()->create(['is_preview_user' => false]);
    $goalA = Goal::factory()->create(['user_id' => $userA->id]);
    $goalB = Goal::factory()->create(['user_id' => $userB->id]);

    $userATokenForOwnGoal = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => $goalA->id,
    ], $userA)['confirmation_token'];

    // User B trying to delete their own goal using User A's token.
    $result = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => $goalB->id,
        'confirmation_token' => $userATokenForOwnGoal,
    ], $userB);

    expect($result['requires_confirmation'])->toBeTrue();
    expect(Goal::find($goalB->id))->not->toBeNull();
});

it('rejects a token issued for a different entity_id', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $goalA = Goal::factory()->create(['user_id' => $user->id]);
    $goalB = Goal::factory()->create(['user_id' => $user->id]);

    $tokenForA = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => $goalA->id,
    ], $user)['confirmation_token'];

    $result = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => $goalB->id,
        'confirmation_token' => $tokenForA,
    ], $user);

    expect($result['requires_confirmation'])->toBeTrue();
    expect(Goal::find($goalA->id))->not->toBeNull();
    expect(Goal::find($goalB->id))->not->toBeNull();
});

it('rejects a token issued for a different entity_type', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $goal = Goal::factory()->create(['user_id' => $user->id, 'id' => 999]);
    $savings = SavingsAccount::factory()->create(['user_id' => $user->id, 'id' => 999]);

    $goalToken = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => 999,
    ], $user)['confirmation_token'];

    $result = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'savings_account',
        'entity_id' => 999,
        'confirmation_token' => $goalToken,
    ], $user);

    expect($result['requires_confirmation'])->toBeTrue();
    expect(SavingsAccount::find(999))->not->toBeNull();
});

it("rejects yesterday's token (same-day salt enforces freshness)", function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $goal = Goal::factory()->create(['user_id' => $user->id]);

    // Issue yesterday's token by manually computing the hash.
    $yesterday = Carbon::now()->subDay()->format('Y-m-d');
    $stale = hash('sha256', $user->id.'|goal|'.$goal->id.'|'.$yesterday);

    $result = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => $goal->id,
        'confirmation_token' => $stale,
    ], $user);

    expect($result['requires_confirmation'])->toBeTrue();
    expect(Goal::find($goal->id))->not->toBeNull();
});

// ─── Preview short-circuit & not-found path ────────────────────────────

it('blocks preview users without computing a token', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);
    $goal = Goal::factory()->create(['user_id' => $user->id]);

    $result = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => $goal->id,
    ], $user);

    expect($result['blocked'] ?? false)->toBeTrue();
    expect($result)->not->toHaveKey('confirmation_token');
    expect(Goal::find($goal->id))->not->toBeNull();
});

it('returns not_found if entity does not belong to user (after token check)', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $other->id]);

    $token = hash('sha256', $user->id.'|goal|'.$goal->id.'|'.now()->format('Y-m-d'));

    $result = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => $goal->id,
        'confirmation_token' => $token,
    ], $user);

    expect($result['error'] ?? null)->not->toBeNull();
    expect(Goal::find($goal->id))->not->toBeNull();
});

// ─── T8 (walk fix plan B2.7, Q22): the preview checks the record exists ──
// P #4066 issued a delete preview for "goal #0": the first call handed out a
// confirmation token for a record that does not exist, so Fyn asked the user
// to confirm deleting nothing.

it('refuses a delete preview for a record that does not exist, and says the user has none (Q22)', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => 0,
    ], $user);

    expect($result['error'] ?? null)->toBeTrue()
        ->and($result['error_type'] ?? null)->toBe('not_found')
        ->and($result)->not->toHaveKey('confirmation_token')
        ->and($result['message'])->toBe("You don't have a goal saved, so there's nothing to delete.")
        ->and($result['user_facing'])->toBeTrue()
        ->and($result['saved_records'])->toBe([]);
});

it('hands Fyn the saved records when the id is wrong, and asks which one was meant (T8)', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $deposit = Goal::factory()->create(['user_id' => $user->id, 'goal_name' => 'House deposit']);
    $car = Goal::factory()->create(['user_id' => $user->id, 'goal_name' => 'New car']);

    $result = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => 0,
    ], $user);

    expect($result['error_type'])->toBe('not_found')
        ->and($result['message'])->toBe('Which goal did you mean: House deposit or New car?')
        ->and($result['saved_records'])->toBe([
            ['id' => $deposit->id, 'name' => 'House deposit'],
            ['id' => $car->id, 'name' => 'New car'],
        ])
        ->and($result['instruction'])->toContain('saved_records');
});

it('keeps the steer for the model out of the message a user can see (T8 walk, #22838)', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => 0,
    ], $user);

    expect($result['message'])->not->toContain('read tools')
        ->and($result['message'])->not->toContain('delete_record')
        ->and($result['message'])->not->toContain('id 0');
});

it("refuses a delete preview for another user's record", function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $goal = Goal::factory()->create(['user_id' => User::factory()->create()->id]);

    $result = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => $goal->id,
    ], $user);

    expect($result['error_type'] ?? null)->toBe('not_found')
        ->and($result)->not->toHaveKey('confirmation_token');
});

it('names the record in the delete preview', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $goal = Goal::factory()->create(['user_id' => $user->id, 'goal_name' => 'House deposit']);

    $result = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'goal',
        'entity_id' => $goal->id,
    ], $user);

    expect($result['requires_confirmation'])->toBeTrue()
        ->and($result['preview_message'])->toContain('House deposit');
});

it('previews a joint account for the joint owner, as the delete allows', function (): void {
    $owner = User::factory()->create(['is_preview_user' => false]);
    $partner = User::factory()->create(['is_preview_user' => false]);
    $account = SavingsAccount::factory()->create([
        'user_id' => $owner->id,
        'joint_owner_id' => $partner->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'account_name' => 'Joint saver',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('delete_record', [
        'entity_type' => 'savings_account',
        'entity_id' => $account->id,
    ], $partner);

    expect($result['requires_confirmation'] ?? false)->toBeTrue()
        ->and($result['preview_message'])->toContain('Joint saver');
});
