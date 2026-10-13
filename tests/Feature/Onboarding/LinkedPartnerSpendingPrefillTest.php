<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\SpousePermission;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\SpouseLinkingService;
use App\Services\Onboarding\WalkFormPrefill;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Walk R22 (2026-10-09, fynla.org): Morgan gave the household's £3,500 a month
 * before inviting Sam; Sam's walk then asked "What your household spends each
 * month" blank. What a partner gave is carried over for the spouse to confirm
 * (ruling 50, CSJ 2026-09-29), as the work, pension and spouse forms already are.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    Mail::fake();
    Notification::fake();
});

/** @return array{0: User, 1: User, 2: AiConversation} */
function spendingPrefillCouple(?float $morganSpending, ?float $samSpending = null): array
{
    $morgan = User::factory()->create(['first_name' => 'Morgan', 'marital_status' => 'married', 'monthly_expenditure' => $morganSpending]);
    $sam = User::factory()->create(['first_name' => 'Sam', 'monthly_expenditure' => $samSpending]);
    app(SpouseLinkingService::class)->establishAcceptedLink($morgan, $sam);
    $conversation = AiConversation::create(['user_id' => $sam->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding', 'metadata' => ['source' => 'fyn_onboarding']]);

    return [$morgan->fresh(), $sam->fresh(), $conversation->fresh()];
}

it('fills in the household spending the linked partner gave before the link', function (): void {
    [, $sam, $conversation] = spendingPrefillCouple(3500);

    $prefill = app(WalkFormPrefill::class)->for($sam, $conversation, CaptureForms::EXPENDITURE_TAX);

    expect($prefill['values'][CaptureForms::LEAD]['monthly_total'] ?? null)->toBe(3500.0);
});

it('puts two stored halves back together as the household figure', function (): void {
    [, $sam, $conversation] = spendingPrefillCouple(1750, 1750);

    $prefill = app(WalkFormPrefill::class)->for($sam, $conversation, CaptureForms::EXPENDITURE);

    expect($prefill['values'][CaptureForms::LEAD]['monthly_total'] ?? null)->toBe(3500.0);
});

it('leaves the form blank when the partner has given no spending', function (): void {
    [, $sam, $conversation] = spendingPrefillCouple(null);

    expect(app(WalkFormPrefill::class)->for($sam, $conversation, CaptureForms::EXPENDITURE_TAX))->toBeNull();
});

it("does not read the partner's spending when they do not share their data", function (): void {
    [$morgan, $sam, $conversation] = spendingPrefillCouple(3500);
    SpousePermission::query()->whereIn('user_id', [$morgan->id, $sam->id])->delete();
    SpousePermission::create(['user_id' => $sam->id, 'spouse_id' => $morgan->id, 'status' => 'rejected']);

    expect(app(WalkFormPrefill::class)->for($sam->fresh(), $conversation, CaptureForms::EXPENDITURE_TAX))->toBeNull();
});

it('leaves the form blank when the household keeps its spending separate', function (): void {
    [$morgan, $sam, $conversation] = spendingPrefillCouple(3500);
    $sam->forceFill(['expenditure_sharing_mode' => 'separate'])->save();

    expect(app(WalkFormPrefill::class)->for($sam->fresh(), $conversation, CaptureForms::EXPENDITURE_TAX))->toBeNull();
});
