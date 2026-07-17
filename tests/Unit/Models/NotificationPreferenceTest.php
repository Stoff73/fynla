<?php

declare(strict_types=1);

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('NotificationPreference', function () {
    it('belongs to a user', function () {
        $user = User::factory()->create();
        $prefs = NotificationPreference::factory()->create(['user_id' => $user->id]);

        expect($prefs->user->id)->toBe($user->id);
    });

    it('casts boolean preferences correctly', function () {
        $user = User::factory()->create();
        $prefs = NotificationPreference::factory()->create([
            'user_id' => $user->id,
            'policy_renewals' => true,
            'market_updates' => false,
        ]);

        expect($prefs->policy_renewals)->toBeTrue()
            ->and($prefs->market_updates)->toBeFalse();
    });

    it('gets or creates preferences for a user', function () {
        $user = User::factory()->create();

        // First call creates
        $prefs = NotificationPreference::getOrCreateForUser($user->id);
        expect($prefs)->toBeInstanceOf(NotificationPreference::class)
            ->and($prefs->policy_renewals)->toBeTrue()
            ->and($prefs->market_updates)->toBeFalse();

        // Second call returns existing
        $prefs2 = NotificationPreference::getOrCreateForUser($user->id);
        expect($prefs2->id)->toBe($prefs->id);
    });

    it('enforces unique user_id constraint', function () {
        $user = User::factory()->create();
        NotificationPreference::factory()->create(['user_id' => $user->id]);

        expect(fn () => NotificationPreference::factory()->create([
            'user_id' => $user->id,
        ]))->toThrow(QueryException::class);
    });

    it('has the paid lifecycle email preferences defaulting to true', function () {
        $user = User::factory()->create();
        $prefs = NotificationPreference::getOrCreateForUser($user->id);

        expect($prefs->lifecycle_churned_subscriber)->toBeTrue()
            ->and($prefs->lifecycle_lapsed_subscriber)->toBeTrue()
            ->and($prefs->getFillable())->not->toContain(
                'lifecycle_empty_trialer',
                'lifecycle_engaged_trialer',
                'lifecycle_cancelled_trialer',
            );
    });

    it('allows updating individual lifecycle preferences', function () {
        $user = User::factory()->create();
        $prefs = NotificationPreference::getOrCreateForUser($user->id);

        $prefs->update(['lifecycle_lapsed_subscriber' => false]);

        expect($prefs->fresh()->lifecycle_lapsed_subscriber)->toBeFalse();
        expect($prefs->fresh()->lifecycle_churned_subscriber)->toBeTrue();
    });
});
