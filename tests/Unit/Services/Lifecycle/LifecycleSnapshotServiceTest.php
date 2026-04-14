<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Lifecycle\LifecycleSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('isEmpty returns true for a user with no module data', function () {
    $user = User::factory()->create();

    $service = app(LifecycleSnapshotService::class);

    expect($service->isEmpty($user))->toBeTrue();
});

it('isEmpty returns false for a user with a property record', function () {
    $user = User::factory()->create();
    \App\Models\Property::factory()->create(['user_id' => $user->id]);

    $service = app(LifecycleSnapshotService::class);

    expect($service->isEmpty($user))->toBeFalse();
});

it('isEmpty returns false for a user with any module data (test all 6 tables)', function () {
    $tables = [
        \App\Models\Property::class,
        \App\Models\DCPension::class,
        \App\Models\SavingsAccount::class,
        \App\Models\Investment\InvestmentAccount::class,
        \App\Models\LifeInsurancePolicy::class,
        \App\Models\Goal::class,
    ];

    $service = app(LifecycleSnapshotService::class);

    foreach ($tables as $modelClass) {
        $user = User::factory()->create();
        $modelClass::factory()->create(['user_id' => $user->id]);

        expect($service->isEmpty($user))->toBeFalse();
    }
});

it('findUserIdsWithData returns the subset of user IDs that have data', function () {
    $userWithData = User::factory()->create();
    $userWithoutData = User::factory()->create();
    $anotherUserWithData = User::factory()->create();

    \App\Models\Property::factory()->create(['user_id' => $userWithData->id]);
    \App\Models\Goal::factory()->create(['user_id' => $anotherUserWithData->id]);

    $service = app(LifecycleSnapshotService::class);
    $result = $service->findUserIdsWithData([
        $userWithData->id,
        $userWithoutData->id,
        $anotherUserWithData->id,
    ]);

    expect($result->all())->toEqualCanonicalizing([
        $userWithData->id,
        $anotherUserWithData->id,
    ]);
});

it('findUserIdsWithData returns empty collection when no candidates have data', function () {
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    $service = app(LifecycleSnapshotService::class);
    $result = $service->findUserIdsWithData([$u1->id, $u2->id]);

    expect($result->isEmpty())->toBeTrue();
});

it('findUserIdsWithData handles empty input array', function () {
    $service = app(LifecycleSnapshotService::class);
    $result = $service->findUserIdsWithData([]);

    expect($result->isEmpty())->toBeTrue();
});
