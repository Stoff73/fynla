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
it('grants ai_chat consent to a spouse account it creates', function (): void {
    Mail::fake();

    $currentUser = User::factory()->create(['marital_status' => 'married']);

    $result = app(SpouseLinkingService::class)->linkOrCreateSpouse($currentUser, [
        'first_name' => 'Pat',
        'last_name' => 'Doe',
        'email' => 'pat@example.com',
        'date_of_birth' => '1990-01-01',
    ]);

    expect($result['created_new_user'])->toBeTrue()
        ->and(UserConsent::hasConsent($result['spouse_user']->id, UserConsent::TYPE_AI_CHAT))->toBeTrue();
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
