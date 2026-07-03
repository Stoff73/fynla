<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The verify-edit read-back names the RESULTING records from the database
 * ("add £20 to HSBC" must come back as the stored total), so the user can
 * acknowledge the actual balance rather than the model's paraphrase.
 */
function readBackFor(User $user, string $section, ?array $before): ?string
{
    $director = app(OnboardingChatDirector::class);
    $m = new ReflectionMethod($director, 'verifyEditReadBack');
    $m->setAccessible(true);

    return $m->invoke($director, $user, $section, $before);
}

function snapshotFor(User $user, string $section): ?array
{
    $director = app(OnboardingChatDirector::class);
    $m = new ReflectionMethod($director, 'verifyEditSnapshot');
    $m->setAccessible(true);

    return $m->invoke($director, $user, $section);
}

it('names the resulting balance when a savings account is updated', function () {
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'HSBC savings',
        'institution' => 'HSBC',
        'current_balance' => 500,
    ]);

    $before = snapshotFor($user, 'savings');
    $account->update(['current_balance' => 520]);

    $readBack = readBackFor($user, 'savings', $before);
    expect($readBack)->toContain('Updated HSBC savings at HSBC')
        ->and($readBack)->toContain('balance now £520');
});

it('names a newly added account with its balance', function () {
    $user = User::factory()->create();

    $before = snapshotFor($user, 'savings');
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'HSBC savings',
        'institution' => 'HSBC',
        'current_balance' => 20,
    ]);

    $readBack = readBackFor($user, 'savings', $before);
    expect($readBack)->toContain('Added HSBC savings at HSBC')
        ->and($readBack)->toContain('balance £20');
});

it('returns null when nothing changed', function () {
    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'HSBC savings',
        'current_balance' => 500,
    ]);

    $before = snapshotFor($user, 'savings');

    expect(readBackFor($user, 'savings', $before))->toBeNull();
});

it('returns null for profile-backed sections — the model prose stands there', function () {
    $user = User::factory()->create();

    expect(snapshotFor($user, 'income'))->toBeNull()
        ->and(readBackFor($user, 'income', null))->toBeNull();
});
