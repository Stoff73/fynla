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
