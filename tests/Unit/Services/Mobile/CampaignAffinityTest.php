<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Mobile\NextActionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * WP-6 — SaveTax landing state. A campaign arrival's action list leads with
 * TAX content (real strategies and strategy unlocks) instead of generic
 * cross-module warnings; non-campaign users keep the pure value ranking.
 */
function affinitySort(User $user, array $items): array
{
    $service = app(NextActionsService::class);
    $m = new ReflectionMethod($service, 'applyCampaignAffinity');
    $m->setAccessible(true);

    return $m->invoke($service, $user, $items);
}

function syntheticItems(): array
{
    return [
        ['id' => 'estate_1', 'module' => 'estate', 'value' => 90.0, 'type' => 'recommendation'],
        ['id' => 'protection_1', 'module' => 'protection', 'value' => 80.0, 'type' => 'recommendation'],
        ['id' => 'strategy_unlock:x', 'module' => 'tax', 'value' => 70.0, 'type' => 'unlock'],
        ['id' => 'tax_y', 'module' => 'tax', 'value' => 60.0, 'type' => 'recommendation'],
    ];
}

function syntheticItemsWithRetirement(): array
{
    return [
        ['id' => 'estate_1', 'module' => 'estate', 'value' => 90.0, 'type' => 'recommendation'],
        ['id' => 'protection_1', 'module' => 'protection', 'value' => 80.0, 'type' => 'recommendation'],
        ['id' => 'retirement_a', 'module' => 'retirement', 'value' => 70.0, 'type' => 'recommendation'],
        ['id' => 'retirement_b', 'module' => 'retirement', 'value' => 60.0, 'type' => 'recommendation'],
    ];
}

it('leads with tax items for a savetax campaign arrival', function () {
    $user = User::factory()->create(['funnel_answers' => ['campaign' => 'savetax']]);

    $sorted = affinitySort($user, syntheticItems());

    expect(array_column($sorted, 'id'))
        ->toBe(['strategy_unlock:x', 'tax_y', 'estate_1', 'protection_1']);
});

it('leads with retirement items for a pensioncheck campaign arrival', function () {
    $user = User::factory()->create(['funnel_answers' => ['campaign' => 'pensioncheck']]);

    $sorted = affinitySort($user, syntheticItemsWithRetirement());

    expect(array_column($sorted, 'id'))
        ->toBe(['retirement_a', 'retirement_b', 'estate_1', 'protection_1']);
});

it('keeps the pure value ranking for non-campaign users', function () {
    $user = User::factory()->create(['funnel_answers' => null]);

    $sorted = affinitySort($user, syntheticItems());

    expect(array_column($sorted, 'id'))
        ->toBe(['estate_1', 'protection_1', 'strategy_unlock:x', 'tax_y']);
});
