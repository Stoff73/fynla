<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\DataDeletionConfirmation;
use App\Models\Subscription;
use App\Services\Payment\DataPurgeService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PurgeExpiredUserData extends Command
{
    protected $signature = 'data-retention:purge-expired';

    protected $description = 'Permanently delete data for users whose 30-day grace period has expired';

    public function handle(DataPurgeService $purgeService): int
    {
        $cutoff = Carbon::now()->startOfDay()->subDays(30);

        $subscriptions = Subscription::where('status', 'expired')
            ->whereNotNull('data_retention_starts_at')
            ->where('data_retention_starts_at', '<=', $cutoff)
            ->with('user')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No expired users past 30-day grace period.');

            return Command::SUCCESS;
        }

        $purged = 0;

        foreach ($subscriptions as $subscription) {
            $user = $subscription->user;
            if (! $user || $user->trashed()) {
                continue;
            }

            // Skip preview users — never purge test data
            if ($user->is_preview_user) {
                continue;
            }

            $firstName = $user->first_name;
            $email = $user->email;

            try {
                $result = $purgeService->purgeUserData($user);

                $this->info("Purged user #{$user->id}: {$result['records_deleted']} records across {$result['tables_purged']} tables.");

                // Send deletion confirmation email
                try {
                    Mail::to($email)->send(new DataDeletionConfirmation($firstName ?? 'User', $email));
                } catch (\Exception $e) {
                    Log::error('Failed to send data deletion confirmation email', [
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }

                $purged++;
            } catch (\Exception $e) {
                Log::error('Failed to purge user data', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("Failed to purge user #{$user->id}: {$e->getMessage()}");
            }
        }

        $this->info("Purged {$purged} user(s).");

        return Command::SUCCESS;
    }
}
