<?php

declare(strict_types=1);

use App\Models\Referral;
use App\Models\User;
use App\Services\Payment\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

describe('generateCode', function () {
    it('generates a code in FYN-XXXXX format', function () {
        $user = User::factory()->create();
        $service = app(ReferralService::class);

        $code = $service->generateCode($user);

        expect($code)->toMatch('/^FYN-[A-Z0-9]{5}$/');
        expect($user->fresh()->referral_code)->toBe($code);
    });

    it('returns existing code if user already has one', function () {
        $user = User::factory()->create(['referral_code' => 'FYN-EXIST']);
        $service = app(ReferralService::class);

        $code = $service->generateCode($user);

        expect($code)->toBe('FYN-EXIST');
    });
});

describe('sendInvitation', function () {
    it('creates a referral record and sends email', function () {
        Mail::fake();
        $user = User::factory()->create(['referral_code' => 'FYN-ABC12']);
        $user->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'yearly', 'status' => 'active',
            'amount' => 10000, 'current_period_start' => now(), 'current_period_end' => now()->addYear(),
        ]);

        $service = app(ReferralService::class);
        $referral = $service->sendInvitation($user, 'friend@example.com');

        expect($referral->status)->toBe('pending');
        expect($referral->referee_email)->toBe('friend@example.com');
        expect($referral->referral_code)->toBe('FYN-ABC12');
        Mail::assertSent(\App\Mail\ReferralInvitationEmail::class);
    });

    it('rejects if user has no active subscription', function () {
        $user = User::factory()->create();
        $user->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'yearly', 'status' => 'trialing',
            'amount' => 0, 'current_period_start' => now(), 'current_period_end' => now()->addDays(7),
        ]);

        $service = app(ReferralService::class);

        expect(fn () => $service->sendInvitation($user, 'friend@example.com'))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('rejects self-referral', function () {
        $user = User::factory()->create(['email' => 'me@example.com', 'referral_code' => 'FYN-SELF1']);
        $user->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'yearly', 'status' => 'active',
            'amount' => 10000, 'current_period_start' => now(), 'current_period_end' => now()->addYear(),
        ]);

        $service = app(ReferralService::class);

        expect(fn () => $service->sendInvitation($user, 'me@example.com'))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('rejects duplicate invitation to same email', function () {
        $user = User::factory()->create(['referral_code' => 'FYN-DUP12']);
        $user->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'yearly', 'status' => 'active',
            'amount' => 10000, 'current_period_start' => now(), 'current_period_end' => now()->addYear(),
        ]);

        Referral::create([
            'referrer_id' => $user->id, 'referral_code' => 'FYN-DUP12',
            'referee_email' => 'already@example.com', 'status' => 'pending', 'referred_at' => now(),
        ]);

        $service = app(ReferralService::class);

        expect(fn () => $service->sendInvitation($user, 'already@example.com'))
            ->toThrow(\InvalidArgumentException::class);
    });
});

describe('applyReferralBonus', function () {
    it('extends both subscriptions by 1 week for monthly purchase', function () {
        $referrer = User::factory()->create(['referral_code' => 'FYN-REF01']);
        $referrerSub = $referrer->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'monthly', 'status' => 'active',
            'amount' => 1099, 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        $referrerOriginalEnd = $referrerSub->current_period_end->copy();

        $referee = User::factory()->create(['referred_by_code' => 'FYN-REF01']);
        $refereeSub = $referee->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'monthly', 'status' => 'active',
            'amount' => 1099, 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        $refereeOriginalEnd = $refereeSub->current_period_end->copy();

        Referral::create([
            'referrer_id' => $referrer->id, 'referee_id' => $referee->id,
            'referral_code' => 'FYN-REF01', 'referee_email' => $referee->email,
            'status' => 'registered', 'referred_at' => now(), 'registered_at' => now(),
        ]);

        $service = app(ReferralService::class);
        $service->applyReferralBonus($referee, 'monthly');

        $referrerSub->refresh();
        $refereeSub->refresh();

        expect($referrerSub->current_period_end->diffInDays($referrerOriginalEnd))->toBe(7);
        expect($refereeSub->current_period_end->diffInDays($refereeOriginalEnd))->toBe(7);

        $referral = Referral::where('referee_id', $referee->id)->first();
        expect($referral->bonus_applied)->toBeTrue();
        expect($referral->status)->toBe('converted');
    });

    it('extends both subscriptions by 1 month for annual purchase', function () {
        $referrer = User::factory()->create(['referral_code' => 'FYN-REF02']);
        $referrerSub = $referrer->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'yearly', 'status' => 'active',
            'amount' => 10000, 'current_period_start' => now(), 'current_period_end' => now()->addYear(),
        ]);
        $referrerOriginalEnd = $referrerSub->current_period_end->copy();

        $referee = User::factory()->create(['referred_by_code' => 'FYN-REF02']);
        $refereeSub = $referee->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'yearly', 'status' => 'active',
            'amount' => 10000, 'current_period_start' => now(), 'current_period_end' => now()->addYear(),
        ]);
        $refereeOriginalEnd = $refereeSub->current_period_end->copy();

        Referral::create([
            'referrer_id' => $referrer->id, 'referee_id' => $referee->id,
            'referral_code' => 'FYN-REF02', 'referee_email' => $referee->email,
            'status' => 'registered', 'referred_at' => now(), 'registered_at' => now(),
        ]);

        $service = app(ReferralService::class);
        $service->applyReferralBonus($referee, 'yearly');

        $referrerSub->refresh();
        $refereeSub->refresh();

        expect($referrerSub->current_period_end->diffInMonths($referrerOriginalEnd))->toBe(1);
        expect($refereeSub->current_period_end->diffInMonths($refereeOriginalEnd))->toBe(1);
    });

    it('does not apply bonus twice', function () {
        $referrer = User::factory()->create(['referral_code' => 'FYN-ONCE1']);
        $referrer->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'yearly', 'status' => 'active',
            'amount' => 10000, 'current_period_start' => now(), 'current_period_end' => now()->addYear(),
        ]);

        $referee = User::factory()->create(['referred_by_code' => 'FYN-ONCE1']);
        $referee->subscription()->create([
            'plan' => 'standard', 'billing_cycle' => 'yearly', 'status' => 'active',
            'amount' => 10000, 'current_period_start' => now(), 'current_period_end' => now()->addYear(),
        ]);

        Referral::create([
            'referrer_id' => $referrer->id, 'referee_id' => $referee->id,
            'referral_code' => 'FYN-ONCE1', 'referee_email' => $referee->email,
            'status' => 'converted', 'bonus_applied' => true,
            'referred_at' => now(), 'registered_at' => now(), 'converted_at' => now(),
        ]);

        $service = app(ReferralService::class);
        $service->applyReferralBonus($referee, 'yearly');

        expect(Referral::where('referee_id', $referee->id)->first()->bonus_applied)->toBeTrue();
    });
});
