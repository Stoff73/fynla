<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Mobile\PushNotificationService;
use Illuminate\Notifications\Notification;

class SavingsMaturityAlertNotification extends Notification
{
    public function __construct(
        private readonly string $accountName,
        private readonly int $daysUntilMaturity,
    ) {}

    public function via(object $notifiable): array
    {
        $pushService = app(PushNotificationService::class);

        if ($pushService->shouldSend($notifiable->id, 'savings_maturity_alerts')) {
            return ['database'];
        }

        return [];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Savings Maturity Alert',
            'body' => "Your fixed-rate account '{$this->accountName}' matures in {$this->daysUntilMaturity} days. Review your reinvestment options to avoid defaulting to a lower rate.",
            'type' => 'savings_maturity_alert',
            'data' => [
                'account_name' => $this->accountName,
                'days_until_maturity' => $this->daysUntilMaturity,
            ],
        ];
    }
}
