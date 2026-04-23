<?php

declare(strict_types=1);

namespace App\Services\Lifecycle\Campaigns;

use App\Mail\Lifecycle\EmptyTrialerMail;
use App\Models\User;
use App\Services\Lifecycle\Contracts\LifecycleCampaign;
use App\Services\Lifecycle\LifecycleEngine;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class EmptyTrialerCampaign implements LifecycleCampaign
{
    public function __construct(
        private readonly LifecycleEngine $engine,
    ) {
    }

    public function name(): string
    {
        return 'empty_trialer';
    }

    public function priority(): int
    {
        return 4;
    }

    /**
     * Users whose trial ended (expired or trialing-past-end), no active/past_due
     * sub, AND no data in any module table. Uses the engine's shared candidate
     * cache so Campaign 4 and Campaign 5 don't each re-run the heavy query.
     */
    public function eligibleUsers(): Collection
    {
        return $this->engine->trialAfterEndCandidates()
            ->reject(fn (User $u) => $this->engine->candidateHasData($u->id))
            ->values();
    }

    public function mailable(User $user): Mailable
    {
        $magicUrl = URL::temporarySignedRoute(
            'lifecycle.restart-trial',
            now()->addDays((int) config('lifecycle.magic_link_ttl_days', 7)),
            ['user_id' => $user->id]
        );

        return new EmptyTrialerMail($user, $magicUrl);
    }
}
