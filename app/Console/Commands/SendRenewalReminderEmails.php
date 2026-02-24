<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\SubscriptionRenewalReminder;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRenewalReminderEmails extends Command
{
    protected $signature = 'subscriptions:send-renewal-reminders';

    protected $description = 'Send renewal reminder emails 7 days before subscription auto-renews';

    public function handle(): int
    {
        $targetDate = Carbon::now()->addDays(7)->startOfDay();

        $subscriptions = Subscription::where('status', 'active')
            ->whereDate('current_period_end', $targetDate->toDateString())
            ->with('user')
            ->get();

        $sent = 0;

        foreach ($subscriptions as $subscription) {
            $user = $subscription->user;
            if (! $user) {
                continue;
            }

            // Dedup: check if already sent for this billing period
            $alreadySent = DB::table('renewal_reminder_log')
                ->where('subscription_id', $subscription->id)
                ->where('period_end_date', $targetDate->toDateString())
                ->exists();

            if ($alreadySent) {
                continue;
            }

            try {
                Mail::to($user->email)->send(new SubscriptionRenewalReminder($user, $subscription));

                DB::table('renewal_reminder_log')->insert([
                    'subscription_id' => $subscription->id,
                    'period_end_date' => $targetDate->toDateString(),
                    'sent_at' => Carbon::now(),
                ]);

                $sent++;
            } catch (\Exception $e) {
                Log::error('Failed to send renewal reminder', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Sent {$sent} renewal reminder email(s).");

        return Command::SUCCESS;
    }
}
