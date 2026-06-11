<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\NetWorth\NetWorthService;

/**
 * NetWorthService::getJointAssets() must surface the co-owner's name for every
 * joint asset type. It previously hard-coded `co_owner => null` for property /
 * investment / business / chattel even though the name was stored (linked user
 * via joint_owner_id, or free text via joint_owner_name).
 */
function jointAssetsFor(User $user): array
{
    return app(NetWorthService::class)->getJointAssets($user);
}

it('shows the co-owner name from a free-text joint_owner_name (property)', function () {
    $user = User::factory()->create();
    Property::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'joint',
        'joint_owner_id' => null,
        'joint_owner_name' => 'Jane Partner',
        'ownership_percentage' => 50,
    ]);

    $joint = collect(jointAssetsFor($user))->firstWhere('type', 'property');

    expect($joint)->not->toBeNull()
        ->and($joint['co_owner'])->toBe('Jane Partner');
});

it('shows the co-owner name from a linked joint owner (property)', function () {
    $user = User::factory()->create();
    $partner = User::factory()->create(['first_name' => 'Linked', 'surname' => 'Spouse']);
    Property::factory()->joint($partner)->create([
        'user_id' => $user->id,
        'ownership_percentage' => 50,
    ]);

    $joint = collect(jointAssetsFor($user))->firstWhere('type', 'property');

    expect($joint)->not->toBeNull()
        ->and($joint['co_owner'])->toBe($partner->name);
});

it('shows the co-owner name from a linked joint owner (savings)', function () {
    $user = User::factory()->create();
    $partner = User::factory()->create(['first_name' => 'Cash', 'surname' => 'Partner']);
    SavingsAccount::factory()->joint($partner)->create([
        'user_id' => $user->id,
        'ownership_percentage' => 50,
    ]);

    $joint = collect(jointAssetsFor($user))->firstWhere('type', 'savings');

    expect($joint)->not->toBeNull()
        ->and($joint['co_owner'])->toBe($partner->name);
});
