<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Mobile\PushNotificationService;
use Illuminate\Notifications\Notification;

class ContributionReminderNotification extends Notification
{
    public function __construct(
        private readonly string $goalName,
    ) {}

    public function via(object $notifiable): array
    {
        $pushService = app(PushNotificationService::class);

        if ($pushService->shouldSend($notifiable->id, 'contribution_reminders')) {
            return ['database'];
        }

        return [];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Contribution Reminder',
            'body' => "Time to check in on your {$this->goalName} goal. A regular contribution keeps you on track.",
            'type' => 'contribution_reminder',
            'data' => [
                'goal_name' => $this->goalName,
            ],
        ];
    }
}
