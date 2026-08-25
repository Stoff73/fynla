<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Mail\ReferralInvitationEmail;
use App\Models\Referral;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ReferralService
{
    public function __construct(
        private readonly SubscriptionEntitlementService $entitlements,
    ) {}

    /**
     * Generate or return existing referral code for a user.
     */
    public function generateCode(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }

        do {
            $code = 'FYN-'.strtoupper(Str::random(5));
        } while (User::where('referral_code', $code)->exists());

        $user->update(['referral_code' => $code]);

        return $code;
    }

    /**
     * Send a referral invitation email.
     */
    public function sendInvitation(User $referrer, string $email): Referral
    {
        $email = strtolower(trim($email));

        if ($this->entitlements->activePaidFor($referrer) === null) {
            throw new \InvalidArgumentException('You must have an active paid subscription to refer a friend.');
        }

        if (strtolower($referrer->email) === $email) {
            throw new \InvalidArgumentException('You cannot refer yourself.');
        }

        $existing = Referral::where('referrer_id', $referrer->id)
            ->where('referee_email', $email)
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException('You have already invited this person.');
        }

        $code = $this->generateCode($referrer);

        $referral = Referral::create([
            'referrer_id' => $referrer->id,
            'referral_code' => $code,
            'referee_email' => $email,
            'status' => 'pending',
            'referred_at' => now(),
        ]);

        try {
            Mail::to($email)->send(new ReferralInvitationEmail($referrer, $code));
        } catch (\Exception $e) {
            Log::error('Failed to send referral invitation email', [
                'referrer_id' => $referrer->id,
                'referral_code' => $code,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Referral invitation sent', [
            'referrer_id' => $referrer->id,
            'referral_code' => $code,
        ]);

        return $referral;
    }

    /**
     * Link a newly registered user to their referral.
     */
    public function applyReferralOnRegistration(User $newUser, string $referralCode): void
    {
        $referral = Referral::where('referral_code', $referralCode)
            ->where('referee_email', strtolower($newUser->email))
            ->where('status', 'pending')
            ->first();

        if (! $referral) {
            $referral = Referral::where('referral_code', $referralCode)
                ->whereNull('referee_id')
                ->where('status', 'pending')
                ->orderBy('referred_at', 'asc')
                ->first();
        }

        if (! $referral) {
            $newUser->update(['referred_by_code' => $referralCode]);

            return;
        }

        $referral->update([
            'referee_id' => $newUser->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        $newUser->update(['referred_by_code' => $referralCode]);

        Log::info('Referral registration linked', [
            'referral_id' => $referral->id,
            'referee_id' => $newUser->id,
            'referral_code' => $referralCode,
        ]);
    }

    /**
     * Apply referral bonus after a successful payment.
     */
    public function applyReferralBonus(
        User $referee,
        string $billingCycle,
        ?int $refereeSubscriptionId = null,
    ): void {
        if (! $referee->referred_by_code) {
            return;
        }

        DB::transaction(function () use ($referee, $billingCycle, $refereeSubscriptionId): void {
            $referral = Referral::where('referee_id', $referee->id)
                ->lockForUpdate()
                ->first();

            if (! $referral || $referral->bonus_applied) {
                return;
            }

            $referrer = $referral->referrer;
            if (! $referrer) {
                return;
            }

            $subscriptions = Subscription::whereIn('user_id', [$referee->id, $referrer->id])
                ->orderBy('user_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->groupBy('user_id');
            $refereeSubscription = $this->entitlements->activePaidFrom(
                $subscriptions->get($referee->id, collect()),
                $refereeSubscriptionId,
            );
            $referrerSubscription = $this->entitlements->activePaidFrom(
                $subscriptions->get($referrer->id, collect()),
            );

            if ($refereeSubscription?->current_period_end === null
                || $referrerSubscription?->current_period_end === null) {
                return;
            }

            $isMonthly = $billingCycle === 'monthly';

            foreach ([$refereeSubscription, $referrerSubscription] as $subscription) {
                $subscription->update([
                    'current_period_end' => $isMonthly
                        ? $subscription->current_period_end->copy()->addWeek()
                        : $subscription->current_period_end->copy()->addMonth(),
                ]);
            }

            $referral->update([
                'bonus_applied' => true,
                'status' => 'converted',
                'converted_at' => now(),
            ]);

            $bonusText = $isMonthly ? '1 week' : '1 month';
            Log::info('Referral bonus applied', [
                'referral_id' => $referral->id,
                'referrer_id' => $referrer->id,
                'referee_id' => $referee->id,
                'bonus' => $bonusText,
                'billing_cycle' => $billingCycle,
            ]);
        });
    }
}
