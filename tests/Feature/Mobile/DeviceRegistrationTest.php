<?php

declare(strict_types=1);

use App\Models\DeviceToken;
use App\Models\User;

describe('Device Registration API', function () {
    it('registers a new device token', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/mobile/devices', [
            'device_token' => 'fcm-token-abc123',
            'device_id' => 'device-uuid-1',
            'platform' => 'ios',
            'device_name' => 'iPhone 15 Pro',
            'app_version' => '1.0.0',
            'os_version' => 'iOS 17.4',
        ]);

        $response->assertCreated()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'device_id' => 'device-uuid-1',
            'platform' => 'ios',
        ]);
    });

    it('upserts existing device token', function () {
        $user = User::factory()->create();
        DeviceToken::factory()->create([
            'user_id' => $user->id,
            'device_id' => 'device-uuid-1',
            'device_token' => 'old-token',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/mobile/devices', [
            'device_token' => 'new-token',
            'device_id' => 'device-uuid-1',
            'platform' => 'ios',
        ]);

        $response->assertOk();
        expect(DeviceToken::where('user_id', $user->id)->count())->toBe(1);
        expect(DeviceToken::where('user_id', $user->id)->first()->device_token)->toBe('new-token');
    });

    it('lists user devices', function () {
        $user = User::factory()->create();
        DeviceToken::factory()->count(2)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/mobile/devices');

        $response->assertOk()
            ->assertJsonCount(2, 'data.devices');
    });

    it('revokes a device by device_id', function () {
        $user = User::factory()->create();
        DeviceToken::factory()->create([
            'user_id' => $user->id,
            'device_id' => 'device-to-delete',
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/v1/mobile/devices/device-to-delete');

        $response->assertOk();
        $this->assertDatabaseMissing('device_tokens', [
            'user_id' => $user->id,
            'device_id' => 'device-to-delete',
        ]);
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->postJson('/api/v1/mobile/devices', [
            'device_token' => 'token',
            'device_id' => 'id',
            'platform' => 'ios',
        ])->assertUnauthorized();
    });

    it('validates required fields', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/mobile/devices', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['device_token', 'device_id', 'platform']);
    });

    it('validates platform enum', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/mobile/devices', [
            'device_token' => 'token',
            'device_id' => 'id',
            'platform' => 'windows',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    });

    it('prevents access to other users devices', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        DeviceToken::factory()->create([
            'user_id' => $otherUser->id,
            'device_id' => 'other-device',
        ]);

        $this->actingAs($user)->deleteJson('/api/v1/mobile/devices/other-device')
            ->assertNotFound();
    });
});
