<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Mobile\PushNotificationService;
use Illuminate\Notifications\Notification;

class DailyInsightNotification extends Notification
{
    public function __construct(
        private readonly string $insightText,
    ) {}

    public function via(object $notifiable): array
    {
        $pushService = app(PushNotificationService::class);

        if ($pushService->shouldSend($notifiable->id, 'fyn_daily_insight')) {
            return ['database'];
        }

        return [];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Your Daily Financial Insight',
            'body' => $this->insightText,
            'type' => 'daily_insight',
        ];
    }
}
