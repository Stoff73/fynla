<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExpireTrials extends Command
{
    protected $signature = 'trials:expire';

    protected $description = 'Expire ended cancelled or one-time subscription access';

    public function handle(): int
    {
        return $this->call('subscriptions:expire');
    }
}
