<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Account\AccountDeletionReminder1DayEmail;
use App\Mail\Account\AccountDeletionReminder7DaysEmail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendDeletionReminders extends Command
{
    protected $signature = 'accounts:send-deletion-reminders';

    protected $description = 'Send 7-day and 1-day reminders before scheduled account deletion.';

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
        }
    }
}
