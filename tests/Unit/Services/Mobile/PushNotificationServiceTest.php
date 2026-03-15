<?php

declare(strict_types=1);

use App\Models\DeviceToken;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Mobile\PushNotificationService;

beforeEach(function () {
    $this->service = app(PushNotificationService::class);
});

describe('PushNotificationService', function () {
    it('checks notification preferences before sending', function () {
        $user = User::factory()->create();
        NotificationPreference::factory()->create([
            'user_id' => $user->id,
            'policy_renewals' => false,
        ]);
        DeviceToken::factory()->create(['user_id' => $user->id]);

        $result = $this->service->shouldSend($user->id, 'policy_renewals');
        expect($result)->toBeFalse();
    });

    it('returns true when preference is enabled', function () {
        $user = User::factory()->create();
        NotificationPreference::factory()->create([
            'user_id' => $user->id,
            'policy_renewals' => true,
        ]);
        DeviceToken::factory()->create(['user_id' => $user->id]);

        $result = $this->service->shouldSend($user->id, 'policy_renewals');
        expect($result)->toBeTrue();
    });

    it('returns false when user has no device tokens', function () {
        $user = User::factory()->create();
        NotificationPreference::factory()->create(['user_id' => $user->id]);

        $result = $this->service->shouldSend($user->id, 'policy_renewals');
        expect($result)->toBeFalse();
    });

    it('returns user device tokens', function () {
        $user = User::factory()->create();
        DeviceToken::factory()->count(2)->create(['user_id' => $user->id]);

        $tokens = $this->service->getDeviceTokens($user->id);
        expect($tokens)->toHaveCount(2);
    });

    it('removes stale device tokens', function () {
        $user = User::factory()->create();
        $device = DeviceToken::factory()->create(['user_id' => $user->id]);

        $this->service->removeStaleToken($device->device_token);

        $this->assertDatabaseMissing('device_tokens', [
            'device_token' => $device->device_token,
        ]);
    });
});
