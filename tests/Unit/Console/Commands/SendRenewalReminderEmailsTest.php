<?php

declare(strict_types=1);

use App\Mail\SubscriptionRenewalReminder;
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

describe('subscriptions:send-renewal-reminders SMTP throttle', function () {
    it('paces sends with a 200ms sleep between attempts', function () {
        $renewalDate = Carbon::now()->addDays(7)->startOfDay();

        for ($i = 0; $i < 4; $i++) {
            $user = User::factory()->create();
            Subscription::factory()->create([
                'user_id' => $user->id,
                'status' => 'active',
                'current_period_end' => $renewalDate,
            ]);
        }

        $this->artisan('subscriptions:send-renewal-reminders')->assertExitCode(0);

        Mail::assertSent(SubscriptionRenewalReminder::class, 4);
        Sleep::assertSleptTimes(4);
        Sleep::assertSequence([
            Sleep::usleep(200_000),
            Sleep::usleep(200_000),
            Sleep::usleep(200_000),
            Sleep::usleep(200_000),
        ]);
    });

    it('does not sleep when there are no eligible subscriptions', function () {
        $this->artisan('subscriptions:send-renewal-reminders')->assertExitCode(0);

        Mail::assertNothingSent();
        Sleep::assertSleptTimes(0);
    });
});
