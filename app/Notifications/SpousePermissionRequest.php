<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SpousePermissionRequest extends Notification implements ShouldQueue
{
    use Queueable;

    private $requesterName;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $requesterName)
    {
        $this->requesterName = $requesterName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Spouse Data Sharing Request')
            ->line($this->requesterName.' has requested permission to view your financial data.')
            ->line('This allows for holistic family financial planning.')
            ->action('View Request', url('/settings/spouse-permission'))
            ->line('If you did not expect this request, no further action is required.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->requesterName.' requested data access.',
        ];
    }
}
