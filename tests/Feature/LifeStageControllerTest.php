<?php

declare(strict_types=1);

use App\Models\User;

it('sets a valid life stage', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/life-stage/set', ['life_stage' => 'university'])
        ->assertOk()
        ->assertJson(['life_stage' => 'university']);

    expect($user->fresh()->life_stage)->toBe('university');
});

it('rejects invalid life stage', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/life-stage/set', ['life_stage' => 'invalid'])
        ->assertUnprocessable();
});

it('returns progress', function () {
    $user = User::factory()->create(['life_stage' => 'university']);

    $this->actingAs($user)
        ->getJson('/api/life-stage/progress')
        ->assertOk()
        ->assertJsonStructure(['success', 'data' => ['life_stage', 'completed_steps']]);
});

it('completes a step', function () {
    $user = User::factory()->create([
        'life_stage' => 'university',
        'life_stage_completed_steps' => [],
    ]);

    $this->actingAs($user)
        ->postJson('/api/life-stage/complete-step', ['step' => 'personal-info'])
        ->assertOk();

    expect($user->fresh()->life_stage_completed_steps)->toContain('personal-info');
});
