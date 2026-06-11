<?php

declare(strict_types=1);

use App\Models\PointAward;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns achievements, next actions, and milestones', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user);

    $res = $this->getJson('/api/v1/mobile/achievements');

    $res->assertOk()
        ->assertJsonStructure(['success', 'data' => ['achievements', 'next', 'milestones']]);
    expect(count($res->json('data.next')))->toBeLessThanOrEqual(4);
});

it('earns a data badge using the real award category key', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user);

    PointAward::create([
        'user_id' => $user->id,
        'source_type' => 'data',
        'dedup_key' => 'data:savings_account:first',
        'points' => 20,
        'meta' => [],
    ]);

    $res = $this->getJson('/api/v1/mobile/achievements');

    $res->assertOk();

    $badge = collect($res->json('data.achievements'))->firstWhere('key', 'data_savings_account');

    expect($badge)->not->toBeNull()
        ->and($badge['earned'])->toBeTrue();
});
