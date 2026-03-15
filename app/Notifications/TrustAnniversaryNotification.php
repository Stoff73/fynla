<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Notifies a user when a relevant property trust is approaching its
 * 10-year anniversary, at which point a periodic charge may apply.
 *
 * Sent via the database channel to appear in the user's notification centre.
 */
class TrustAnniversaryNotification extends Notification
{
    public function __construct(
        private readonly string $trustName,
        private readonly string $anniversaryDate,
        private readonly int $daysUntil,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Trust Anniversary Reminder',
            'body' => "Your {$this->trustName} trust approaches its 10-year anniversary on {$this->anniversaryDate} (in {$this->daysUntil} days). A periodic charge of up to 6% of the trust value above the Nil Rate Band may apply. We recommend reviewing the trust valuation and consulting your solicitor or financial adviser.",
            'type' => 'trust_anniversary',
            'data' => [
                'trust_name' => $this->trustName,
                'anniversary_date' => $this->anniversaryDate,
                'days_until' => $this->daysUntil,
            ],
        ];
    }
}
