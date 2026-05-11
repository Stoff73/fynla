<?php

declare(strict_types=1);

use App\Mail\DataRetentionWarning;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Sleep::fake();
});

describe('data-retention:send-warnings SMTP throttle', function () {
    it('paces sends with a 200ms sleep between each attempt to stay under SiteGround 10/s SMTP cap', function () {
        $startsAt = now()->subDays(19)->startOfDay();

        for ($i = 0; $i < 5; $i++) {
            $user = User::factory()->create(['is_preview_user' => false]);
            Subscription::factory()
                ->expired()
                ->create([
                    'user_id' => $user->id,
                    'data_retention_starts_at' => $startsAt,
                ]);
        }

        $this->artisan('data-retention:send-warnings')->assertExitCode(0);

        Mail::assertSent(DataRetentionWarning::class, 5);

        Sleep::assertSleptTimes(5);
        Sleep::assertSequence([
            Sleep::usleep(200_000),
            Sleep::usleep(200_000),
            Sleep::usleep(200_000),
            Sleep::usleep(200_000),
            Sleep::usleep(200_000),
        ]);
    });

    it('still sleeps when a send fails so a partial-failure burst does not exhaust the SMTP rate-limit', function () {
        $startsAt = now()->subDays(19)->startOfDay();

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP boom'));

        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create(['is_preview_user' => false]);
            Subscription::factory()
                ->expired()
                ->create([
                    'user_id' => $user->id,
                    'data_retention_starts_at' => $startsAt,
                ]);
        }

        $this->artisan('data-retention:send-warnings')->assertExitCode(0);

        Sleep::assertSleptTimes(3);
        expect(DB::table('data_retention_email_log')->count())->toBe(0);
    });

    it('skips users not on a scheduled email day and does not sleep for them', function () {
        $user = User::factory()->create(['is_preview_user' => false]);
        Subscription::factory()
            ->expired()
            ->create([
                'user_id' => $user->id,
                'data_retention_starts_at' => now()->subDays(1)->startOfDay(),
            ]);

        $this->artisan('data-retention:send-warnings')->assertExitCode(0);

        Mail::assertNothingSent();
        Sleep::assertSleptTimes(0);
    });
});
