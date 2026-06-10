<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Mobile\NextActionsService;

it('caps the unified list at four items', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $items = app(NextActionsService::class)->build($user->id);

    expect(count($items))->toBeLessThanOrEqual(4);
});

it('emits an unlock item for a gated module carrying a deeplink action', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $items = app(NextActionsService::class)->build($user->id);

    $unlock = collect($items)->firstWhere('type', 'unlock');
    expect($unlock)->not->toBeNull()
        ->and($unlock['action']['kind'])->toBeIn(['fyn_capture', 'deeplink'])
        ->and($unlock['done'])->toBeFalse();
});

it('sorts by value descending', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $items = app(NextActionsService::class)->build($user->id);
    $values = array_column($items, 'value');
    $sorted = $values;
    rsort($sorted);

    expect($values)->toEqual($sorted);
});
