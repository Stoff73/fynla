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
        ->assertJsonStructure(['next_actions', 'celebrate_from', 'celebrate_to']);
});
