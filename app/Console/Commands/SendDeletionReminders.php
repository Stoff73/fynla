<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Account\AccountDeletionReminder1DayEmail;
use App\Mail\Account\AccountDeletionReminder7DaysEmail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;

class SendDeletionReminders extends Command
{
    protected $signature = 'accounts:send-deletion-reminders';

    protected $description = 'Send 7-day and 1-day reminders before scheduled account deletion.';

    /**
     * SiteGround SMTP relay caps at 10 messages/second. Pace at 5/s. Even
     * though this cron runs at 00:20 (off-peak) and uses Mail::queue, the
     * sync queue driver in production resolves to a synchronous send, so
     * the same rate-limit applies.
     */
    private const SMTP_THROTTLE_MICROSECONDS = 200_000;

    public function handle(): int
    {
        $this->sendForWindow(7, AccountDeletionReminder7DaysEmail::class);
        $this->sendForWindow(1, AccountDeletionReminder1DayEmail::class);

        return Command::SUCCESS;
    }

    private function sendForWindow(int $daysRemaining, string $mailable): void
    {
        $start = now()->addDays($daysRemaining)->subHours(12);
        $end = now()->addDays($daysRemaining)->addHours(12);

        $users = User::whereNull('deleted_at')
            ->whereNotNull('deletion_scheduled_for')
            ->whereBetween('deletion_scheduled_for', [$start, $end])
            ->whereDoesntHave('deletionReminderLog', function ($q) use ($daysRemaining) {
                $q->where('days_remaining', $daysRemaining);
            })
            ->get();

        foreach ($users as $user) {
            Mail::to($user->email)->queue(new $mailable($user, $user->deletion_scheduled_for));
            DB::table('account_deletion_reminder_log')->insert([
                'user_id' => $user->id,
                'days_remaining' => $daysRemaining,
                'sent_at' => now(),
            ]);

            Sleep::usleep(self::SMTP_THROTTLE_MICROSECONDS);
        }
    }
}
