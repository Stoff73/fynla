<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Notifies a user when a gift is approaching the 7-year Potentially Exempt
 * Transfer exemption threshold.
 *
 * Sent via the database channel to appear in the user's notification centre.
 */
class GiftExemptionNotification extends Notification
{
    public function __construct(
        private readonly string $recipientName,
        private readonly float $giftAmount,
        private readonly string $giftDate,
        private readonly string $exemptionDate,
        private readonly string $milestone,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $formattedAmount = '£'.number_format($this->giftAmount);

        $body = match ($this->milestone) {
            'six_months' => "Your gift of {$formattedAmount} to {$this->recipientName} on {$this->giftDate} will become fully exempt from Inheritance Tax in approximately 6 months ({$this->exemptionDate}). No action is required — this is a reminder that the 7-year exemption period is almost complete.",
            'one_month' => "Your gift of {$formattedAmount} to {$this->recipientName} on {$this->giftDate} will become fully exempt from Inheritance Tax in approximately 1 month ({$this->exemptionDate}).",
            'exempt' => "Your gift of {$formattedAmount} to {$this->recipientName} made on {$this->giftDate} is now fully exempt from Inheritance Tax. The 7-year Potentially Exempt Transfer period is complete.",
            default => "Your gift of {$formattedAmount} to {$this->recipientName} on {$this->giftDate} is approaching the 7-year Inheritance Tax exemption date of {$this->exemptionDate}.",
        };

        return [
            'title' => 'Gift Exemption Reminder',
            'body' => $body,
            'type' => 'gift_exemption',
            'data' => [
                'recipient_name' => $this->recipientName,
                'gift_amount' => $this->giftAmount,
                'gift_date' => $this->giftDate,
                'exemption_date' => $this->exemptionDate,
                'milestone' => $this->milestone,
            ],
        ];
    }
}
