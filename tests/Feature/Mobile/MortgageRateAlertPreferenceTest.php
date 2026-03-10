<?php

declare(strict_types=1);

use App\Models\NotificationPreference;
use App\Models\User;

describe('Mortgage Rate Alert Preference', function () {
    it('returns mortgage_rate_alerts in preferences response', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/mobile/notifications/preferences');

        $response->assertOk()
            ->assertJsonPath('data.mortgage_rate_alerts', true);
    });

    it('can update mortgage_rate_alerts preference', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/mobile/notifications/preferences', [
            'mortgage_rate_alerts' => false,
        ]);

        $response->assertOk();

        $prefs = NotificationPreference::where('user_id', $user->id)->first();
        expect($prefs->mortgage_rate_alerts)->toBeFalse();
    });
});
