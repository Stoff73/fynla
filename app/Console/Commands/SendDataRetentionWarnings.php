<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\DataRetentionWarning;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDataRetentionWarnings extends Command
{
    protected $signature = 'data-retention:send-warnings';

    protected $description = 'Send data retention warning emails to users in the 30-day grace period';

    /**
     * Email schedule: Day 1 (30 days left), Day 15 (15 days left),
     * Days 20-29 (daily urgency: 10 down to 1 day left).
     */
    private const EMAIL_DAYS = [1, 15, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29];

    public function handle(): int
    {
        $subscriptions = Subscription::where('status', 'expired')
            ->whereNotNull('data_retention_starts_at')
            ->with('user')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No subscriptions in grace period.');

            return Command::SUCCESS;
        }

        $sent = 0;

        foreach ($subscriptions as $subscription) {
            $dayNumber = (int) Carbon::now()->startOfDay()->diffInDays(
                Carbon::parse($subscription->data_retention_starts_at)->startOfDay(),
                false
            ) * -1 + 1;

            // Only send on scheduled days
            if (! in_array($dayNumber, self::EMAIL_DAYS)) {
                continue;
            }

            // Dedup: check if already sent for this day
            $alreadySent = DB::table('data_retention_email_log')
                ->where('subscription_id', $subscription->id)
                ->where('day_number', $dayNumber)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $daysRemaining = 30 - $dayNumber + 1;
            if ($daysRemaining < 1) {
                continue;
            }

            $user = $subscription->user;
            if (! $user || $user->is_preview_user) {
                continue;
            }

            try {
                Mail::to($user->email)->send(new DataRetentionWarning($user, $daysRemaining));

                DB::table('data_retention_email_log')->insert([
                    'subscription_id' => $subscription->id,
                    'day_number' => $dayNumber,
                    'sent_at' => now(),
                ]);

                $sent++;
            } catch (\Exception $e) {
                Log::error('Failed to send data retention warning email', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'day_number' => $dayNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Sent {$sent} data retention warning email(s).");

        return Command::SUCCESS;
    }
}
