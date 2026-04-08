<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Mail\ReferralInvitationEmail;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ReferralService
{
    /**
     * Generate or return existing referral code for a user.
     */
    public function generateCode(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }

        do {
            $code = 'FYN-' . strtoupper(Str::random(5));
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

        $subscription = $referrer->subscription;
        if (! $subscription || $subscription->status !== 'active') {
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
                'referee_email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Referral invitation sent', [
            'referrer_id' => $referrer->id,
            'referee_email' => $email,
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
    public function applyReferralBonus(User $referee, string $billingCycle): void
    {
        if (! $referee->referred_by_code) {
            return;
        }

        $referral = Referral::where('referee_id', $referee->id)
            ->where('bonus_applied', false)
            ->first();

        if (! $referral) {
            return;
        }

        $referrer = $referral->referrer;
        if (! $referrer) {
            return;
        }

        $isMonthly = $billingCycle === 'monthly';

        // Refresh referee's subscription to get latest data (confirmPayment may have just updated it)
        $referee->load('subscription');
        $refereeSub = $referee->subscription;
        if ($refereeSub && $refereeSub->current_period_end) {
            $refereeSub->update([
                'current_period_end' => $isMonthly
                    ? $refereeSub->current_period_end->addWeek()
                    : $refereeSub->current_period_end->addMonth(),
            ]);
        }

        // Refresh referrer's subscription to get latest data (confirmPayment may have just updated it)
        $referrer->load('subscription');
        $referrerSub = $referrer->subscription;
        if ($referrerSub && $referrerSub->current_period_end) {
            $referrerSub->update([
                'current_period_end' => $isMonthly
                    ? $referrerSub->current_period_end->addWeek()
                    : $referrerSub->current_period_end->addMonth(),
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
    }
}
