<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserGamification;
use Laravel\Sanctum\Sanctum;

it('returns the gamification status', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create(['user_id' => $user->id, 'total_points' => 290, 'level' => 4]);
    Sanctum::actingAs($user);

    $this->getJson('/api/gamification/status')
        ->assertOk()
        ->assertJsonPath('level', 4)
        ->assertJsonPath('level_name', 'Organiser')
        ->assertJsonPath('progress_percent', 50)
        ->assertJsonPath('next_level_name', 'Planner')
        ->assertJsonStructure(['next_actions', 'pending_celebration']);
});

it('surfaces a pending celebration then clears it on ack', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create(['user_id' => $user->id, 'total_points' => 60, 'level' => 2, 'pending_celebration_level' => 2]);
    Sanctum::actingAs($user);

    $this->getJson('/api/gamification/status')
        ->assertOk()
        ->assertJsonPath('pending_celebration.level', 2)
        ->assertJsonPath('pending_celebration.level_name', 'Saver');

    $this->postJson('/api/gamification/celebration/ack')->assertOk()->assertJsonPath('acknowledged', true);

    expect(UserGamification::where('user_id', $user->id)->value('pending_celebration_level'))->toBeNull();
});
