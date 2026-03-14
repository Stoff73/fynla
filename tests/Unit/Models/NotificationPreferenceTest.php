<?php

declare(strict_types=1);

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

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
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});
