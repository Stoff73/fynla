<?php

declare(strict_types=1);

use App\Models\DeviceToken;
use App\Models\NotificationPreference;
use App\Models\User;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('SendDailyInsightNotifications', function () {
    it('runs successfully', function () {
        $this->artisan('notifications:daily-insight')
            ->assertExitCode(0);
    });

    it('sends to users with devices and opt-in', function () {
        $user = User::factory()->create();
        NotificationPreference::factory()->create([
            'user_id' => $user->id,
            'fyn_daily_insight' => true,
        ]);
        DeviceToken::factory()->create(['user_id' => $user->id]);

        $this->artisan('notifications:daily-insight')
            ->expectsOutputToContain('Sent daily insights to 1 users')
            ->assertExitCode(0);
    });

    it('skips users without devices', function () {
        $user = User::factory()->create();
        NotificationPreference::factory()->create([
            'user_id' => $user->id,
            'fyn_daily_insight' => true,
        ]);
        // No device tokens

        $this->artisan('notifications:daily-insight')
            ->expectsOutputToContain('Sent daily insights to 0 users')
            ->assertExitCode(0);
    });
});
