<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Mobile\PushNotificationService;
use Illuminate\Notifications\Notification;

class SecurityAlertNotification extends Notification
{
    public function __construct(
        private readonly string $eventDescription,
    ) {}

    public function via(object $notifiable): array
    {
        $pushService = app(PushNotificationService::class);

        if ($pushService->shouldSend($notifiable->id, 'security_alerts')) {
            return ['database'];
        }

        return [];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Security Alert',
            'body' => $this->eventDescription,
            'type' => 'security_alert',
        ];
    }
}
