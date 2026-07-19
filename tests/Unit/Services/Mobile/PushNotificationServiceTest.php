<?php

declare(strict_types=1);

use App\Models\DeviceToken;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Mobile\ApnsClient;
use App\Services\Mobile\PushNotificationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

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

    it('strips financial detail and sends only a generic allowlisted payload', function () {
        config(['services.fcm.server_key' => 'test-fcm-key']);
        Http::fake(['https://fcm.googleapis.com/*' => Http::response(['success' => 1])]);
        $user = User::factory()->create();
        DeviceToken::factory()->create([
            'user_id' => $user->id,
            'platform' => 'android',
            'device_token' => 'android-token',
        ]);

        $this->service->sendToUser(
            $user->id,
            'Mortgage balance £250,000',
            'Your fixed rate expires in 30 days.',
            ['route' => 'net_worth', 'account_id' => 73, 'amount' => 250000]
        );

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://fcm.googleapis.com/fcm/send'
                && $request['notification'] === [
                    'title' => 'Fynla',
                    'body' => 'Open Fynla to review an update.',
                ]
                && $request['data'] === ['route' => 'net_worth'];
        });
    });

    it('routes iOS device tokens to APNs and ignores unknown route keys', function () {
        $user = User::factory()->create();
        DeviceToken::factory()->create([
            'user_id' => $user->id,
            'platform' => 'ios',
            'device_token' => 'apns-token',
        ]);
        $apns = Mockery::mock(ApnsClient::class);
        $apns->shouldReceive('send')
            ->once()
            ->with('apns-token', [
                'title' => 'Fynla',
                'body' => 'Open Fynla to review an update.',
                'route' => 'dashboard',
            ]);
        $service = new PushNotificationService($apns);

        $service->sendToUser(
            $user->id,
            'Sensitive title',
            'Sensitive body',
            ['route' => 'admin/users', 'transcript' => 'private']
        );
    });
});
