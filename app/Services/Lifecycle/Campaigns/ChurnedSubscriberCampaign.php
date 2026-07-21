<?php

declare(strict_types=1);

namespace App\Services\Lifecycle\Campaigns;

use App\Mail\Lifecycle\ChurnedSubscriberMail;
use App\Models\User;
use App\Services\Lifecycle\Contracts\LifecycleCampaign;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class ChurnedSubscriberCampaign implements LifecycleCampaign
{
    public function name(): string
    {
        return 'churned_subscriber';
    }

    public function priority(): int
    {
        return 2;
    }

    /** Users who cancelled exactly N days ago after a completed payment. */
    public function eligibleUsers(): Collection
    {
        $delay = (int) config('lifecycle.cancellation_feedback_delay_days', 3);

        return User::query()
            ->whereHas('subscriptions', fn ($q) => $q
                ->where('status', 'cancelled')
                ->whereNotNull('cancelled_at')
                ->whereDate('cancelled_at', now()->subDays($delay)->toDateString())
                ->whereHas('payments', fn ($paymentQuery) => $paymentQuery->where('status', 'completed'))
            )
            ->whereDoesntHave('subscriptions', fn ($q) => $q->where('status', 'active'))
            ->get();
    }

    public function mailable(User $user): Mailable
    {
        $reasons = config('lifecycle.feedback_reasons.churned_subscriber', []);
        $expiry = now()->addDays((int) config('lifecycle.magic_link_ttl_days', 7));
        $urls = [];

        foreach ($reasons as $reason) {
            $urls[$reason] = URL::temporarySignedRoute(
                'lifecycle.feedback',
                $expiry,
                [
                    'user_id' => $user->id,
                    'campaign' => 'churned_subscriber',
                    'reason' => $reason,
                ]
            );
        }

        // Compute a human-readable subscription duration for the template copy.
        $sub = $user->subscriptions()->where('status', 'cancelled')->latest('cancelled_at')->first();
        $duration = null;
        if ($sub && $sub->current_period_start && $sub->cancelled_at) {
            $duration = $sub->current_period_start->diffForHumans($sub->cancelled_at, ['parts' => 1]);
        }

        return new ChurnedSubscriberMail($user, $urls, $duration);
    }
}
