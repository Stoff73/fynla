<?php

declare(strict_types=1);

use App\Mail\Account\AccountDeletionReminder1DayEmail;
use App\Mail\Account\AccountDeletionReminder7DaysEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Sleep::fake();
});

describe('accounts:send-deletion-reminders SMTP throttle', function () {
    it('paces sends with a 200ms sleep between attempts across both reminder windows', function () {
        // 2 users in the 7-day window + 1 user in the 1-day window = 3 sends
        for ($i = 0; $i < 2; $i++) {
            User::factory()->create([
                'deletion_scheduled_for' => now()->addDays(7),
            ]);
        }
        User::factory()->create([
            'deletion_scheduled_for' => now()->addDay(),
        ]);

        $this->artisan('accounts:send-deletion-reminders')->assertExitCode(0);

        Mail::assertQueued(AccountDeletionReminder7DaysEmail::class, 2);
        Mail::assertQueued(AccountDeletionReminder1DayEmail::class, 1);
        Sleep::assertSleptTimes(3);
    });

    it('does not sleep when no accounts are within either reminder window', function () {
        // User with deletion 30 days out — outside both windows
        User::factory()->create([
            'deletion_scheduled_for' => now()->addDays(30),
        ]);

        $this->artisan('accounts:send-deletion-reminders')->assertExitCode(0);

        Mail::assertNothingQueued();
        Sleep::assertSleptTimes(0);
    });
});
