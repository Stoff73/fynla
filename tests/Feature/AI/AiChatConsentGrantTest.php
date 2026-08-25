<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserConsent;
use App\Services\Onboarding\SpouseLinkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Every account that can sign in must hold ai_chat consent, or the Fyn gate in
 * AiChatController answers 403 consent_required on web, `/m` and native alike —
 * and no surface offers a way to grant it. Registration has recorded it since
 * 2026-05-05; these cover the two holes that left a signed-in user staring at
 * "Fyn chat consent is required before you can continue" with nothing to tap.
 */
it('creates no consentless account, because it creates no account', function (): void {
    // This case used to assert that a spouse account created by
    // `linkOrCreateSpouse()` was given ai_chat consent — the hole being that an
    // account made on someone's behalf could sign in and meet a 403 with nothing
    // to tap.
    //
    // W-0349 (CSJ, 2026-08-23) closed that hole by removing its cause: the
    // service no longer creates an account for an unregistered address, it
    // invites it. The invitee registers themselves, and registration has
    // recorded ai_chat consent since 2026-05-05 — so the invariant now holds by
    // the ordinary route rather than by a special case here.
    //
    // The assertion is kept rather than deleted, pointed at what must remain
    // true: this path must never again produce an account that nobody consented
    // for. The backfill below still covers the historical ones.
    Mail::fake();

    $currentUser = User::factory()->create(['marital_status' => 'married']);

    $result = app(SpouseLinkingService::class)->linkOrCreateSpouse($currentUser, [
        'first_name' => 'Pat',
        'last_name' => 'Doe',
        'email' => 'pat@example.com',
        'date_of_birth' => '1990-01-01',
    ]);

    expect($result['created_new_user'])->toBeFalse()
        ->and($result['spouse_user'])->toBeNull()
        ->and(User::withTrashed()->where('email', 'pat@example.com')->exists())->toBeFalse();
});

describe('the ai_chat consent backfill', function (): void {
    $runBackfill = function (): void {
        (require database_path('migrations/2026_08_18_140000_backfill_missing_ai_chat_consent.php'))->up();
    };

    it('grants consent to an account that has no row at all', function () use ($runBackfill): void {
        $user = User::factory()->create();
        UserConsent::where('user_id', $user->id)->delete();

        $runBackfill();

        expect(UserConsent::hasConsent($user->id, UserConsent::TYPE_AI_CHAT))->toBeTrue();
    });

    it('leaves a withdrawn consent withdrawn', function () use ($runBackfill): void {
        $user = User::factory()->create();
        UserConsent::where('user_id', $user->id)->delete();
        UserConsent::recordConsent($user->id, UserConsent::TYPE_AI_CHAT, false);

        $runBackfill();

        expect(UserConsent::hasConsent($user->id, UserConsent::TYPE_AI_CHAT))->toBeFalse()
            ->and(UserConsent::where('user_id', $user->id)->where('consent_type', UserConsent::TYPE_AI_CHAT)->count())->toBe(1);
    });
});
