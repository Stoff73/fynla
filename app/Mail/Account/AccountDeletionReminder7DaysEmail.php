<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountDeletionReminder7DaysEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Carbon $executesAt
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Your Fynla account will be deleted in 7 days')
            ->markdown('emails.account.deletion-reminder-7days');
    }
}
