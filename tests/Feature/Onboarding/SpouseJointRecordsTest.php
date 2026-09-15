<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\FamilyMember;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\SpouseJointRecords;
use App\Services\Onboarding\SpouseLinkingService;
use App\Support\SharedOwnership;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// CSJ 2026-09-15: a joint account saved mid-onboarding belongs to the spouse
// when one was declared, but Fyn rarely knows the spouse by name yet and the
// accounts are not linked. Nothing invented goes on the record; it is
// remembered and filled in as the spouse becomes known.

function onboardingCoupleUser(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'campaign_bank_accounts',
        'marital_status' => 'married',
        'funnel_answers' => ['campaign' => 'savetax', 'spouse' => 'yes'],
    ], $overrides));
}

function saveJointHalifax(User $user): array
{
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'joint savings account with Halifax with a balance of 2345, owned 50/50 with my wife',
    ]);

    return app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'institution' => 'Halifax',
        'account_name' => 'Halifax Joint Savings',
        'account_type' => 'savings_account',
        'current_balance' => 2345,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'joint_owner_id' => 0,
        'joint_owner_name' => '0', // the model's placeholder for "not given"
    ], $user, $conversation->id);
}

it('remembers a joint record saved with no named co-owner and stores nothing invented', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = onboardingCoupleUser();

    $result = saveJointHalifax($user);

    expect($result['created'] ?? false)->toBeTrue();
    $account = SavingsAccount::where('user_id', $user->id)->sole();
    expect($account->joint_owner_id)->toBeNull()
        ->and($account->joint_owner_name)->toBeNull()
        ->and($user->fresh()->onboarding_fyn_context[SpouseJointRecords::CONTEXT_KEY] ?? [])
        ->toEqual([['type' => 'savings_account', 'id' => $account->id]]);
});

it('fills the co-owner name when the spouse row arrives with a first name, and keeps waiting for the link', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = onboardingCoupleUser();
    saveJointHalifax($user);

    app(SpouseLinkingService::class)->linkOrCreateSpouse($user->fresh(), [
        'first_name' => 'Jane',
        'email' => 'jane-'.$user->id.'@example.com',
    ]);

    $account = SavingsAccount::where('user_id', $user->id)->sole();
    expect($account->joint_owner_name)->toBe('Jane')
        ->and($account->joint_owner_id)->toBeNull()
        ->and($user->fresh()->onboarding_fyn_context[SpouseJointRecords::CONTEXT_KEY] ?? [])
        ->toHaveCount(1);
});

it('fills the co-owner id when the invitation links the accounts, then forgets the record', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = onboardingCoupleUser();
    saveJointHalifax($user);
    $spouse = User::factory()->create(['is_preview_user' => false, 'first_name' => 'Jane']);

    app(SpouseLinkingService::class)->establishAcceptedLink($user->fresh(), $spouse);

    $account = SavingsAccount::where('user_id', $user->id)->sole();
    expect($account->joint_owner_id)->toBe($spouse->id)
        ->and($account->joint_owner_name)->toBe('Jane')
        ->and($user->fresh()->onboarding_fyn_context[SpouseJointRecords::CONTEXT_KEY] ?? null)->toBeNull();
});

it('fills the name from a spouse family row the journey path wrote directly', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = onboardingCoupleUser();
    saveJointHalifax($user);
    FamilyMember::factory()->create(['user_id' => $user->id, 'relationship' => 'spouse', 'first_name' => 'Priya']);

    app(SpouseJointRecords::class)->apply($user->fresh());

    expect(SavingsAccount::where('user_id', $user->id)->sole()->joint_owner_name)->toBe('Priya');
});

it('does not remember a joint record when the household has no spouse', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = onboardingCoupleUser(['marital_status' => 'single', 'funnel_answers' => ['campaign' => 'savetax', 'spouse' => 'no']]);

    saveJointHalifax($user);

    expect($user->fresh()->onboarding_fyn_context[SpouseJointRecords::CONTEXT_KEY] ?? null)->toBeNull();
});

it('does not remember a joint record saved after onboarding', function (): void {
    $this->seed(TierConfigurationSeeder::class);
    $user = onboardingCoupleUser(['onboarding_completed' => true, 'onboarding_fyn_step' => null]);

    saveJointHalifax($user);

    expect($user->fresh()->onboarding_fyn_context[SpouseJointRecords::CONTEXT_KEY] ?? null)->toBeNull();
});

it('treats the model placeholder "0" as no co-owner name in the one shared rule', function (): void {
    expect(SharedOwnership::counterpartyName('0'))->toBeNull()
        ->and(SharedOwnership::counterpartyName(' '))->toBeNull()
        ->and(SharedOwnership::counterpartyName('Jane'))->toBe('Jane')
        ->and(SharedOwnership::namesCounterparty(['joint_owner_name' => '0']))->toBeFalse();
});

it('treats "my wife" as who the co-owner is, not a name — remembered and replaced by the real name', function (): void {
    // csjones 2026-09-15, user 395: the model wrote joint_owner_name "wife"
    // from "owned 50/50 with my wife", which counted as a named co-owner.
    $this->seed(TierConfigurationSeeder::class);
    $user = onboardingCoupleUser();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'joint savings account with Halifax with a balance of 2345, owned 50/50 with my wife',
    ]);
    app(CoordinatingAgent::class)->executeTool('create_savings_account', [
        'institution' => 'Halifax',
        'account_name' => 'Halifax Joint Savings',
        'account_type' => 'savings_account',
        'current_balance' => 2345,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'joint_owner_name' => 'wife',
    ], $user, $conversation->id);

    expect($user->fresh()->onboarding_fyn_context[SpouseJointRecords::CONTEXT_KEY] ?? [])->toHaveCount(1);

    app(SpouseLinkingService::class)->linkOrCreateSpouse($user->fresh(), [
        'first_name' => 'Jane',
        'email' => 'jane-'.$user->id.'@example.com',
    ]);

    expect(SavingsAccount::where('user_id', $user->id)->sole()->joint_owner_name)->toBe('Jane')
        ->and(SharedOwnership::isRelationshipPlaceholder('My Husband'))->toBeTrue()
        ->and(SharedOwnership::isRelationshipPlaceholder('Jane'))->toBeFalse();
});
