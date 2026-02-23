<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('returns dependencies for a goal', function () {
    $goalA = Goal::factory()->emergencyFund()->create(['user_id' => $this->user->id]);
    $goalB = Goal::factory()->create(['user_id' => $this->user->id]);

    $goalB->dependsOn()->attach($goalA->id, ['dependency_type' => 'blocks']);

    $response = $this->actingAs($this->user)
        ->getJson("/api/goals/{$goalB->id}/dependencies");

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'depends_on',
                'depended_on_by',
            ],
        ]);

    expect($response->json('data.depends_on'))->toHaveCount(1);
    expect($response->json('data.depends_on.0.id'))->toBe($goalA->id);
    expect($response->json('data.depends_on.0.dependency_type'))->toBe('blocks');
});

it('returns 404 for another users goal dependencies', function () {
    $otherUser = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/goals/{$goal->id}/dependencies");

    $response->assertNotFound();
});

it('adds a dependency to a goal', function () {
    $goalA = Goal::factory()->emergencyFund()->create(['user_id' => $this->user->id]);
    $goalB = Goal::factory()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/goals/{$goalB->id}/dependencies", [
            'depends_on_goal_id' => $goalA->id,
            'dependency_type' => 'blocks',
        ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($goalB->dependsOn()->count())->toBe(1);
});

it('prevents self-dependency', function () {
    $goal = Goal::factory()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/goals/{$goal->id}/dependencies", [
            'depends_on_goal_id' => $goal->id,
            'dependency_type' => 'blocks',
        ]);

    $response->assertStatus(422);
});

it('prevents circular dependencies', function () {
    $goalA = Goal::factory()->create(['user_id' => $this->user->id]);
    $goalB = Goal::factory()->create(['user_id' => $this->user->id]);

    $goalA->dependsOn()->attach($goalB->id, ['dependency_type' => 'blocks']);

    $response = $this->actingAs($this->user)
        ->postJson("/api/goals/{$goalB->id}/dependencies", [
            'depends_on_goal_id' => $goalA->id,
            'dependency_type' => 'blocks',
        ]);

    $response->assertStatus(422);
});

it('validates required fields when adding dependency', function () {
    $goal = Goal::factory()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/goals/{$goal->id}/dependencies", []);

    $response->assertStatus(422);
});

it('removes a dependency from a goal', function () {
    $goalA = Goal::factory()->create(['user_id' => $this->user->id]);
    $goalB = Goal::factory()->create(['user_id' => $this->user->id]);

    $goalB->dependsOn()->attach($goalA->id, ['dependency_type' => 'blocks']);
    expect($goalB->dependsOn()->count())->toBe(1);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/goals/{$goalB->id}/dependencies/{$goalA->id}");

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($goalB->dependsOn()->count())->toBe(0);
});

it('includes dependency_count and is_blocked in goals index', function () {
    $goalA = Goal::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'active',
    ]);
    $goalB = Goal::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'active',
    ]);

    $goalB->dependsOn()->attach($goalA->id, ['dependency_type' => 'blocks']);

    $response = $this->actingAs($this->user)
        ->getJson('/api/goals');

    $response->assertOk();

    $goals = collect($response->json('data.goals'));
    $goalBData = $goals->firstWhere('id', $goalB->id);

    expect($goalBData['dependency_count'])->toBe(1);
    expect($goalBData['is_blocked'])->toBeTrue();
});

it('shows is_blocked false when blocking dependency is completed', function () {
    $goalA = Goal::factory()->completed()->create([
        'user_id' => $this->user->id,
    ]);
    $goalB = Goal::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'active',
    ]);

    $goalB->dependsOn()->attach($goalA->id, ['dependency_type' => 'blocks']);

    $response = $this->actingAs($this->user)
        ->getJson('/api/goals');

    $goals = collect($response->json('data.goals'));
    $goalBData = $goals->firstWhere('id', $goalB->id);

    expect($goalBData['dependency_count'])->toBe(1);
    expect($goalBData['is_blocked'])->toBeFalse();
});
