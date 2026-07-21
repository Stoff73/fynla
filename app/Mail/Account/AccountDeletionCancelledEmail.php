<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountDeletionCancelledEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function build(): self
    {
        return $this
            ->subject('Your scheduled account deletion has been cancelled')
            ->markdown('emails.account.deletion-cancelled');
    }
}
