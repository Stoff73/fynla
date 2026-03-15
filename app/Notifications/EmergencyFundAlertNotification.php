<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Mobile\PushNotificationService;
use Illuminate\Notifications\Notification;

class EmergencyFundAlertNotification extends Notification
{
    public function __construct(
        private readonly float $runwayMonths,
        private readonly float $targetMonths,
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
        $runway = number_format($this->runwayMonths, 1);
        $target = number_format($this->targetMonths, 0);

        return [
            'title' => 'Emergency Fund Alert',
            'body' => "Your emergency fund covers {$runway} months of expenses, below your recommended target of {$target} months. Building this buffer should be a priority.",
            'type' => 'emergency_fund_alert',
            'data' => [
                'runway_months' => $this->runwayMonths,
                'target_months' => $this->targetMonths,
            ],
        ];
    }
}
