<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns a unified next_actions list of at most four items', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user);

    $res = $this->getJson('/api/v1/mobile/dashboard');

    $res->assertOk();
    $actions = $res->json('data.next_actions');
    expect($actions)->toBeArray()
        ->and(count($actions))->toBeLessThanOrEqual(4);
    $res->assertJsonPath('data.level.actions_total', count($actions));
});

it('returns focus_areas with a Top card whose actions equal next_actions', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user);

    $res = $this->getJson('/api/v1/mobile/dashboard');

    $res->assertOk();
    $areas = $res->json('data.focus_areas');
    expect($areas)->toBeArray()
        ->and($areas[0]['key'])->toBe('top');

    // The Top card's actions ARE the unified next_actions list (no duplication).
    expect($res->json('data.next_actions'))->toEqual($areas[0]['actions']);
});
