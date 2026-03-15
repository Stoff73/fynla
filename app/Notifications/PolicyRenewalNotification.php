<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Mobile\PushNotificationService;
use Illuminate\Notifications\Notification;

class PolicyRenewalNotification extends Notification
{
    public function __construct(
        private readonly string $policyName,
        private readonly string $renewalDate,
    ) {}

    public function via(object $notifiable): array
    {
        $pushService = app(PushNotificationService::class);

        if ($pushService->shouldSend($notifiable->id, 'policy_renewals')) {
            return ['database'];
        }

        return [];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Policy Renewal Reminder',
            'body' => "Your {$this->policyName} renews on {$this->renewalDate}. Review your coverage to ensure it still meets your needs.",
            'type' => 'policy_renewal',
            'data' => [
                'policy_name' => $this->policyName,
                'renewal_date' => $this->renewalDate,
            ],
        ];
    }
}
