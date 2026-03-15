<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Mobile\PushNotificationService;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringNotification extends Notification
{
    public function __construct(
        private readonly int $daysRemaining,
    ) {}

    public function via(object $notifiable): array
    {
        $pushService = app(PushNotificationService::class);

        if ($pushService->shouldSend($notifiable->id, 'payment_alerts')) {
            return ['database'];
        }

        return [];
    }

    public function toArray(object $notifiable): array
    {
        $dayWord = $this->daysRemaining === 1 ? 'day' : 'days';

        return [
            'title' => 'Subscription Expiring Soon',
            'body' => "Your Fynla subscription expires in {$this->daysRemaining} {$dayWord}. Renew to keep access to all features.",
            'type' => 'subscription_expiring',
            'data' => [
                'days_remaining' => $this->daysRemaining,
            ],
        ];
    }
}
