<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Mobile\PushNotificationService;
use Illuminate\Notifications\Notification;

class GoalMilestoneNotification extends Notification
{
    public function __construct(
        private readonly string $goalName,
        private readonly int $percentage,
    ) {}

    public function via(object $notifiable): array
    {
        $pushService = app(PushNotificationService::class);

        if ($pushService->shouldSend($notifiable->id, 'goal_milestones')) {
            return ['database'];
        }

        return [];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Goal Milestone Reached',
            'body' => "You've reached {$this->percentage}% of your {$this->goalName} goal. Keep up the great work!",
            'type' => 'goal_milestone',
            'data' => [
                'goal_name' => $this->goalName,
                'percentage' => $this->percentage,
            ],
        ];
    }
}
