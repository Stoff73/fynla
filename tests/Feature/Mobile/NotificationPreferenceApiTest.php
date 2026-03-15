<?php

declare(strict_types=1);

use App\Models\NotificationPreference;
use App\Models\User;

describe('Notification Preferences API', function () {
    it('returns default preferences for new user', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/mobile/notifications/preferences');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'policy_renewals' => true,
                    'goal_milestones' => true,
                    'contribution_reminders' => true,
                    'market_updates' => false,
                    'fyn_daily_insight' => true,
                    'security_alerts' => true,
                    'payment_alerts' => true,
                ],
            ]);
    });

    it('updates specific preferences', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/mobile/notifications/preferences', [
            'market_updates' => true,
            'fyn_daily_insight' => false,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $prefs = NotificationPreference::where('user_id', $user->id)->first();
        expect($prefs->market_updates)->toBeTrue()
            ->and($prefs->fyn_daily_insight)->toBeFalse()
            ->and($prefs->policy_renewals)->toBeTrue(); // unchanged
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->getJson('/api/v1/mobile/notifications/preferences')
            ->assertUnauthorized();
    });

    it('validates boolean types', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/mobile/notifications/preferences', [
            'policy_renewals' => 'not-a-boolean',
        ])->assertUnprocessable();
    });
});
