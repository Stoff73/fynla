<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountDeletionReminder1DayEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Carbon $executesAt
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Final reminder: your Fynla account will be deleted tomorrow')
            ->markdown('emails.account.deletion-reminder-1day');
    }
}
