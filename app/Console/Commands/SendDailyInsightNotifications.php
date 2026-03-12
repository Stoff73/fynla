<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\NotificationPreference;
use App\Services\Mobile\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailyInsightNotifications extends Command
{
    protected $signature = 'notifications:daily-insight';

    protected $description = 'Send daily Fyn insight push notifications to opted-in users';

    public function handle(PushNotificationService $pushService): int
    {
        $userIds = NotificationPreference::where('fyn_daily_insight', true)
            ->pluck('user_id');

        $usersWithDevices = DeviceToken::whereIn('user_id', $userIds)
            ->distinct('user_id')
            ->pluck('user_id');

        $count = 0;

        foreach ($usersWithDevices as $userId) {
            try {
                $pushService->sendToUser(
                    $userId,
                    'Your Daily Financial Insight',
                    'Tap to see today\'s personalised tip from Fyn.',
                    ['type' => 'daily_insight', 'route' => '/dashboard']
                );
                $count++;
            } catch (\Exception $e) {
                Log::warning('Failed to send daily insight', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Sent daily insights to {$count} users.");

        return Command::SUCCESS;
    }
}
