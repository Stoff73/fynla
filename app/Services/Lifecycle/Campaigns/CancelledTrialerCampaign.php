<?php

declare(strict_types=1);

namespace App\Services\Lifecycle\Campaigns;

use App\Mail\Lifecycle\CancelledTrialerMail;
use App\Models\User;
use App\Services\Lifecycle\Contracts\LifecycleCampaign;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class CancelledTrialerCampaign implements LifecycleCampaign
{
    public function name(): string
    {
        return 'cancelled_trialer';
    }

    public function priority(): int
    {
        return 1;
    }

    /**
     * Users who cancelled mid-trial exactly N days ago (N from config,
     * default 3) — cancelled_at < trial_ends_at distinguishes this from
     * Campaign 4 (churned). Filters out anyone with a current active or
     * trialing subscription so re-subscribers don't get the email.
     */
    public function eligibleUsers(): Collection
    {
        $delay = (int) config('lifecycle.cancellation_feedback_delay_days', 3);

        return User::query()
            ->whereHas('subscriptions', fn ($q) => $q
                ->where('status', 'cancelled')
                ->whereNotNull('cancelled_at')
                ->whereNotNull('trial_started_at')
                ->whereColumn('cancelled_at', '<', 'trial_ends_at')
                ->whereDate('cancelled_at', now()->subDays($delay)->toDateString())
            )
            ->whereDoesntHave('subscriptions', fn ($q) => $q->whereIn('status', ['active', 'trialing']))
            ->get();
    }

    public function mailable(User $user): Mailable
    {
        return new CancelledTrialerMail($user, $this->buildFeedbackUrls($user));
    }

    /**
     * @return array<string, string>
     */
    private function buildFeedbackUrls(User $user): array
    {
        $reasons = config('lifecycle.feedback_reasons.cancelled_trialer', []);
        $expiry = now()->addDays((int) config('lifecycle.magic_link_ttl_days', 7));
        $urls = [];

        foreach ($reasons as $reason) {
            $urls[$reason] = URL::temporarySignedRoute(
                'lifecycle.feedback',
                $expiry,
                [
                    'user_id' => $user->id,
                    'campaign' => 'cancelled_trialer',
                    'reason' => $reason,
                ]
            );
        }

        return $urls;
    }
}
