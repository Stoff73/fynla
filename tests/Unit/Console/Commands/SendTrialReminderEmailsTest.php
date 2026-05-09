<?php

declare(strict_types=1);

use App\Mail\TrialExpirationReminder;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Sleep::fake();
});

describe('trials:send-reminders SMTP throttle', function () {
    it('paces sends with a 200ms sleep between attempts across all reminder days', function () {
        // 2 users with 3-day reminders + 1 user with 1-day reminder = 3 sends
        $threeDays = Carbon::now()->addDays(3)->startOfDay();
        $oneDay = Carbon::now()->addDay()->startOfDay();

        for ($i = 0; $i < 2; $i++) {
            $user = User::factory()->create();
            Subscription::factory()->trialing()->create([
                'user_id' => $user->id,
                'trial_ends_at' => $threeDays,
            ]);
        }
        $user = User::factory()->create();
        Subscription::factory()->trialing()->create([
            'user_id' => $user->id,
            'trial_ends_at' => $oneDay,
        ]);

        $this->artisan('trials:send-reminders')->assertExitCode(0);

        Mail::assertSent(TrialExpirationReminder::class, 3);
        Sleep::assertSleptTimes(3);
    });

    it('does not sleep when no subscriptions match any reminder day', function () {
        $this->artisan('trials:send-reminders')->assertExitCode(0);

        Mail::assertNothingSent();
        Sleep::assertSleptTimes(0);
    });
});
