<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountDeletionConfirmationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function build(): self
    {
        return $this
            ->subject('Your Fynla account has been deleted')
            ->markdown('emails.account.deletion-confirmation');
    }
}
