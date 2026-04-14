<?php

declare(strict_types=1);

namespace App\Services\Lifecycle;

use App\Models\LifecycleEmailLog;
use App\Models\User;
use App\Services\Lifecycle\Contracts\LifecycleCampaign;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class LifecycleEngine
{
    private bool $testMode = false;

    /** @var array<int, User> */
    private array $cachedTrialAfterEndCandidates = [];

    /** @var array<int, int> */
    private array $cachedHasDataIds = [];

    public function __construct(
        private readonly LifecycleSnapshotService $snapshotService,
        private readonly LifecycleDiscountCodeGenerator $discountGenerator,
    ) {
    }

    public function setTestMode(bool $testMode): self
    {
        $this->testMode = $testMode;

        return $this;
    }

    /**
     * Run every registered campaign in priority order, applying the dedup,
     * preference, test-user, and same-day collision filters in filterEligible.
     *
     * @return array<string, array{sent: int, skipped: int, errored: int}>
     */
    public function run(): array
    {
        $stats = [];
        $emailedToday = collect();

        $campaigns = collect(config('lifecycle.campaigns', []))
            ->map(fn ($class) => app($class))
            ->sortBy(fn (LifecycleCampaign $c) => $c->priority())
            ->values();

        foreach ($campaigns as $campaign) {
            $name = $campaign->name();
            $stats[$name] = ['sent' => 0, 'skipped' => 0, 'errored' => 0];

            try {
                $eligible = $this->filterEligible($campaign, $emailedToday);

                foreach ($eligible as $user) {
                    try {
                        $this->dispatchEmail($campaign, $user);
                        $emailedToday->push($user->id);
                        $stats[$name]['sent']++;
                    } catch (Throwable $e) {
                        Log::error('Lifecycle email send failed', [
                            'campaign' => $name,
                            'user_id' => $user->id,
                            'exception' => $e::class,
                            'message' => $e->getMessage(),
                        ]);
                        $stats[$name]['errored']++;
                    }
                }
            } catch (Throwable $e) {
                Log::error('Lifecycle campaign failed', [
                    'campaign' => $name,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
                $stats[$name]['errored']++;
            }
        }

        return $stats;
    }

    /**
     * Apply the engine's filters to a campaign's candidate set:
     *  - reject preview personas
     *  - reject lifecycle test users unless engine is in test mode
     *  - reject users who already got an email earlier in this same run
     *  - reject users who already have a log row for this campaign
     */
    private function filterEligible(LifecycleCampaign $campaign, Collection $emailedToday): Collection
    {
        return $campaign->eligibleUsers()
            ->reject(fn (User $u) => $u->is_preview_user)
            ->reject(fn (User $u) => $u->is_lifecycle_test_user && ! $this->testMode)
            ->reject(fn (User $u) => $emailedToday->contains($u->id))
            ->reject(fn (User $u) => LifecycleEmailLog::where('user_id', $u->id)
                ->where('campaign', $campaign->name())
                ->exists());
    }

    private function dispatchEmail(LifecycleCampaign $campaign, User $user): void
    {
        $mailable = $campaign->mailable($user);

        $recipient = $user->is_lifecycle_test_user && config('lifecycle.test_recipient_override')
            ? config('lifecycle.test_recipient_override')
            : $user->email;

        Mail::to($recipient)->send($mailable);

        LifecycleEmailLog::create([
            'user_id' => $user->id,
            'campaign' => $campaign->name(),
            'sent_at' => now(),
            'context' => null,
        ]);
    }

    /**
     * Candidate set for the "after trial end" eligibility check used by
     * CancelledTrialerCampaign and EmptyTrialerCampaign. Cached per engine
     * instance because both campaigns hit it in the same run.
     */
    public function trialAfterEndCandidates(): Collection
    {
        if (empty($this->cachedTrialAfterEndCandidates)) {
            $anchorDays = (int) config('lifecycle.eligibility_anchor_days', 9);

            $this->cachedTrialAfterEndCandidates = User::query()
                ->where('created_at', '<=', now()->subDays($anchorDays))
                ->whereHas('subscriptions', fn ($q) => $q
                    ->where(fn ($q2) => $q2
                        ->where('status', 'expired')
                        ->orWhere(fn ($q3) => $q3
                            ->where('status', 'trialing')
                            ->where('trial_ends_at', '<', now())
                        )
                    )
                )
                ->whereDoesntHave('subscriptions', fn ($q) => $q->whereIn('status', ['active', 'past_due']))
                ->get()
                ->all();

            $this->cachedHasDataIds = $this->snapshotService
                ->findUserIdsWithData(collect($this->cachedTrialAfterEndCandidates)->pluck('id')->all())
                ->flip()
                ->all();
        }

        return collect($this->cachedTrialAfterEndCandidates);
    }

    public function candidateHasData(int $userId): bool
    {
        return isset($this->cachedHasDataIds[$userId]);
    }
}
