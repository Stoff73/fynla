<?php

declare(strict_types=1);

namespace App\Services\Lifecycle\Campaigns;

use App\Mail\Lifecycle\EngagedTrialerMail;
use App\Models\User;
use App\Services\Lifecycle\Contracts\LifecycleCampaign;
use App\Services\Lifecycle\LifecycleDiscountCodeGenerator;
use App\Services\Lifecycle\LifecycleEngine;
use App\Services\Lifecycle\LifecycleSnapshotService;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class EngagedTrialerCampaign implements LifecycleCampaign
{
    public function __construct(
        private readonly LifecycleEngine $engine,
        private readonly LifecycleSnapshotService $snapshotService,
        private readonly LifecycleDiscountCodeGenerator $discountGenerator,
    ) {
    }

    public function name(): string
    {
        return 'engaged_trialer';
    }

    public function priority(): int
    {
        return 5;
    }

    /**
     * The inverse of EmptyTrialerCampaign — trial ended, no active sub, AND
     * has data in at least one module table. These are the users worth
     * chasing back with a welcome discount code.
     */
    public function eligibleUsers(): Collection
    {
        return $this->engine->trialAfterEndCandidates()
            ->filter(fn (User $u) => $this->engine->candidateHasData($u->id))
            ->values();
    }

    public function mailable(User $user): Mailable
    {
        $context = $this->snapshotService->buildContext($user);
        $code = $this->discountGenerator->generate($user);

        $magicUrl = URL::temporarySignedRoute(
            'lifecycle.apply-discount',
            now()->addDays((int) config('lifecycle.magic_link_ttl_days', 7)),
            [
                'user_id' => $user->id,
                'campaign' => 'engaged_trialer',
                'code' => $code->code,
            ]
        );

        return new EngagedTrialerMail($user, $context, $magicUrl, $code->code);
    }
}
