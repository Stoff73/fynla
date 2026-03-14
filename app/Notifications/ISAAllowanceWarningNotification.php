<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Mobile\PushNotificationService;
use Illuminate\Notifications\Notification;

class ISAAllowanceWarningNotification extends Notification
{
    public function __construct(
        private readonly float $remainingAllowance,
        private readonly int $daysUntilYearEnd,
    ) {}

    public function via(object $notifiable): array
    {
        $pushService = app(PushNotificationService::class);

        if ($pushService->shouldSend($notifiable->id, 'isa_allowance_warnings')) {
            return ['database'];
        }

        return [];
    }

    public function toArray(object $notifiable): array
    {
        $formatted = number_format($this->remainingAllowance, 0);

        return [
            'title' => 'ISA Allowance Reminder',
            'body' => "You have £{$formatted} of your ISA allowance remaining with {$this->daysUntilYearEnd} days until the end of the tax year. Use it or lose it — the allowance does not carry forward.",
            'type' => 'isa_allowance_warning',
            'data' => [
                'remaining_allowance' => $this->remainingAllowance,
                'days_until_year_end' => $this->daysUntilYearEnd,
            ],
        ];
    }
}
