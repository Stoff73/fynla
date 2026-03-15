<?php

declare(strict_types=1);

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('DeviceToken', function () {
    it('belongs to a user', function () {
        $user = User::factory()->create();
        $token = DeviceToken::factory()->create(['user_id' => $user->id]);

        expect($token->user->id)->toBe($user->id);
    });

    it('scopes to a specific user', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user1->id]);
        DeviceToken::factory()->create(['user_id' => $user2->id]);

        expect(DeviceToken::forUser($user1->id)->count())->toBe(1);
    });

    it('scopes to a specific platform', function () {
        $user = User::factory()->create();
        DeviceToken::factory()->ios()->create(['user_id' => $user->id]);
        DeviceToken::factory()->android()->create(['user_id' => $user->id, 'device_id' => 'android-1']);

        expect(DeviceToken::forPlatform('ios')->count())->toBe(1);
    });

    it('casts last_used_at as datetime', function () {
        $user = User::factory()->create();
        $token = DeviceToken::factory()->create([
            'user_id' => $user->id,
            'last_used_at' => '2026-03-10 12:00:00',
        ]);

        expect($token->last_used_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('enforces unique user_id + device_id constraint', function () {
        $user = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user->id, 'device_id' => 'device-1']);

        expect(fn () => DeviceToken::factory()->create([
            'user_id' => $user->id,
            'device_id' => 'device-1',
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});
