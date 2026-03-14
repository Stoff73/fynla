<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Mobile\PushNotificationService;
use Illuminate\Notifications\Notification;

class SavingsRateExpiryNotification extends Notification
{
    public function __construct(
        private readonly string $accountName,
        private readonly int $daysUntilExpiry,
    ) {}

    public function via(object $notifiable): array
    {
        $pushService = app(PushNotificationService::class);

        if ($pushService->shouldSend($notifiable->id, 'savings_rate_alerts')) {
            return ['database'];
        }

        return [];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Savings Rate Expiry',
            'body' => "The promotional rate on '{$this->accountName}' expires in {$this->daysUntilExpiry} days. Consider switching to a better rate to keep your savings working harder.",
            'type' => 'savings_rate_expiry',
            'data' => [
                'account_name' => $this->accountName,
                'days_until_expiry' => $this->daysUntilExpiry,
            ],
        ];
    }
}
