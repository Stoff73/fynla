<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Mobile\PushNotificationService;
use Illuminate\Notifications\Notification;

class MortgageRateAlertNotification extends Notification
{
    public function __construct(
        private readonly string $address,
        private readonly int $daysUntilExpiry,
    ) {}

    public function via(object $notifiable): array
    {
        $pushService = app(PushNotificationService::class);

        if ($pushService->shouldSend($notifiable->id, 'mortgage_rate_alerts')) {
            return ['database'];
        }

        return [];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Mortgage Rate Alert',
            'body' => "Your fixed rate on {$this->address} expires in {$this->daysUntilExpiry} days. Now might be a good time to review your options.",
            'type' => 'mortgage_rate_alert',
            'data' => [
                'address' => $this->address,
                'days_until_expiry' => $this->daysUntilExpiry,
            ],
        ];
    }
}
