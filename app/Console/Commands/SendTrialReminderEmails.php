<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\TrialExpirationReminder;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTrialReminderEmails extends Command
{
    protected $signature = 'trials:send-reminders';

    protected $description = 'Send trial expiration reminder emails at 3, 2, and 1 days before expiry';

    public function handle(): int
    {
        $sent = 0;

        foreach ([3, 2, 1] as $daysRemaining) {
            $targetDate = Carbon::now()->addDays($daysRemaining)->startOfDay();

            $subscriptions = Subscription::where('status', 'trialing')
                ->whereDate('trial_ends_at', $targetDate->toDateString())
                ->with('user')
                ->get();

            foreach ($subscriptions as $subscription) {
                $user = $subscription->user;
                if (! $user) {
                    continue;
                }

                // Check if already sent
                $alreadySent = DB::table('trial_reminder_log')
                    ->where('user_id', $user->id)
                    ->where('days_remaining', $daysRemaining)
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                try {
                    Mail::to($user->email)->send(new TrialExpirationReminder($user, $daysRemaining));

                    DB::table('trial_reminder_log')->insert([
                        'user_id' => $user->id,
                        'days_remaining' => $daysRemaining,
                        'sent_at' => Carbon::now(),
                    ]);

                    $sent++;
                } catch (\Exception $e) {
                    Log::error('Failed to send trial reminder', [
                        'user_id' => $user->id,
                        'days_remaining' => $daysRemaining,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Sent {$sent} trial reminder email(s).");

        return Command::SUCCESS;
    }
}
