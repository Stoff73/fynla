<?php

declare(strict_types=1);

namespace App\Services\Lifecycle\Contracts;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

interface LifecycleCampaign
{
    /** Slug used in lifecycle_email_log.campaign and config keys. */
    public function name(): string;

    /** Collision priority — lower wins on same-day collision. */
    public function priority(): int;

    /** Candidate users for this campaign at this moment. Engine still applies dedup. */
    public function eligibleUsers(): Collection;

    /** Build the Mailable for a specific eligible user. */
    public function mailable(User $user): Mailable;
}
