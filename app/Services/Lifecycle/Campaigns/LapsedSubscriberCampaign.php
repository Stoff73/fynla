<?php

declare(strict_types=1);

namespace App\Services\Lifecycle\Campaigns;

use App\Mail\Lifecycle\LapsedSubscriberMail;
use App\Models\User;
use App\Services\Lifecycle\Contracts\LifecycleCampaign;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class LapsedSubscriberCampaign implements LifecycleCampaign
{
    public function name(): string
    {
        return 'lapsed_subscriber';
    }

    public function priority(): int
    {
        return 3;
    }

    /**
     * Payment recovery campaign. Fires when a user's subscription is
     * past_due and the current period ended at least N days ago (default 5,
     * so Revolut's retry window has had time to run). Gives the user a
     * 7-day grace window before data retention kicks in.
     */
    public function eligibleUsers(): Collection
    {
        $threshold = (int) config('lifecycle.lapsed_recovery_threshold_days', 5);

        return User::query()
            ->whereHas('subscriptions', fn ($q) => $q
                ->where('status', 'past_due')
                ->where('current_period_end', '<', now()->subDays($threshold))
            )
            ->get();
    }

    public function mailable(User $user): Mailable
    {
        $expiry = now()->addDays((int) config('lifecycle.magic_link_ttl_days', 7));

        $updatePaymentUrl = URL::temporarySignedRoute(
            'lifecycle.update-payment',
            $expiry,
            ['user_id' => $user->id]
        );

        $feedbackUrls = [];
        foreach (config('lifecycle.feedback_reasons.lapsed_subscriber', []) as $reason) {
            $feedbackUrls[$reason] = URL::temporarySignedRoute(
                'lifecycle.feedback',
                $expiry,
                [
                    'user_id' => $user->id,
                    'campaign' => 'lapsed_subscriber',
                    'reason' => $reason,
                ]
            );
        }

        // Grace period ends 7 days after current_period_end — Revolut's retry
        // window. After that the subscription moves to expired and data
        // retention starts.
        $sub = $user->subscriptions()->where('status', 'past_due')->latest('current_period_end')->first();
        $gracePeriodEnd = $sub && $sub->current_period_end
            ? $sub->current_period_end->copy()->addDays(7)->format('j F Y')
            : null;

        return new LapsedSubscriberMail($user, $updatePaymentUrl, $feedbackUrls, $gracePeriodEnd);
    }
}
