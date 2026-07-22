<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\Billing\Apple\VerifiedAppleNotification;
use App\Models\AppleNotificationLog;
use App\Services\Billing\Apple\AppleNotificationProcessor;
use Illuminate\Foundation\Bus\Dispatchable;

final class ProcessAppleNotification
{
    use Dispatchable;

    public function __construct(
        public readonly int $notificationLogId,
        public readonly VerifiedAppleNotification $notification,
    ) {}

    public function handle(AppleNotificationProcessor $processor): bool
    {
        return $processor->process(
            AppleNotificationLog::query()->findOrFail($this->notificationLogId),
            $this->notification,
        );
    }
}
