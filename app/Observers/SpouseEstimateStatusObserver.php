<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\Onboarding\SpouseHoldingTransfer;

/**
 * When a linked partner says they are retired or not working, the income their
 * partner gave for them, held as an estimate of pay because nobody knew their
 * status at the link, is restated as what it really is
 * (SpouseHoldingTransfer::restateEstimateForStatus).
 *
 * An observer rather than a line in one form: the status is set by the setup
 * question, the funnel mapper, the desktop and mobile profile forms and Fyn,
 * and every one of them saves the user. The same reason as
 * SurvivingSpouseExpenditureObserver: a rule applied only at the call sites
 * somebody remembered is skipped by the next one.
 */
final class SpouseEstimateStatusObserver
{
    public function __construct(private readonly SpouseHoldingTransfer $transfer) {}

    public function updated(User $user): void
    {
        if ($user->wasChanged('employment_status')) {
            $this->transfer->restateEstimateForStatus($user);
        }
    }
}
