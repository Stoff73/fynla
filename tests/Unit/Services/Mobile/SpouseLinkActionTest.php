<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\SpousePermission;
use App\Models\User;
use App\Services\Mobile\NextActionsService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * CSJ 2026-09-16: while the two accounts are not linked there must be an
 * action saying so. One item, in the one actions model, so web and `/m`
 * both get it; it disappears the moment the link exists.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
});

function actionsFor(User $user): array
{
    return app(NextActionsService::class)->buildAll($user->id);
}

function spouseLinkItem(User $user): ?array
{
    return collect(actionsFor($user))->firstWhere('id', 'household:spouse_link');
}

function coupledUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'is_preview_user' => false,
        'onboarding_completed' => true,
        'onboarding_fyn_step' => null,
        'onboarding_started_at' => now()->subDay(),
        'marital_status' => 'married',
        'first_name' => 'Chris',
    ], $attributes));
}

it('asks the inviter to link the account while a spouse is on file but not linked', function (): void {
    $user = coupledUser();
    FamilyMember::create(['user_id' => $user->id, 'relationship' => 'spouse', 'first_name' => 'Robin', 'last_name' => 'Walk', 'date_of_birth' => '1985-08-22']);

    $item = spouseLinkItem($user);

    expect($item)->not->toBeNull()
        ->and($item['title'])->toBe("Link Robin's Fynla account")
        ->and($item['meta'])->toContain('not their real allowances')
        ->and($item['type'])->toBe('unlock')
        ->and($item['done'])->toBeFalse()
        ->and($item['action']['kind'])->toBe('navigate')
        ->and($item['action']['payload'])->toBe('/spouse-sharing')
        ->and($item['action']['destination']['screen'])->toBe('spouse_sharing');
});

it('asks the invited side to accept while a request is pending on them', function (): void {
    $requester = coupledUser(['first_name' => 'Sam']);
    $invitee = coupledUser(['first_name' => 'Robin']);
    SpousePermission::create(['user_id' => $requester->id, 'spouse_id' => $invitee->id, 'status' => 'pending', 'requested_at' => now()]);

    $item = spouseLinkItem($invitee);

    expect($item)->not->toBeNull()
        ->and($item['title'])->toBe("Accept Sam's request to link your accounts");
});

it('asks a coupled user with no spouse on file to add them', function (): void {
    $user = coupledUser(['marital_status' => 'civil_partnership']);

    $item = spouseLinkItem($user);

    expect($item)->not->toBeNull()
        ->and($item['title'])->toBe('Add your partner');
});

it('drops the action once the accounts are linked', function (): void {
    $user = coupledUser();
    $spouse = coupledUser(['first_name' => 'Robin']);
    $user->forceFill(['spouse_id' => $spouse->id])->save();
    $spouse->forceFill(['spouse_id' => $user->id])->save();
    FamilyMember::create(['user_id' => $user->id, 'relationship' => 'spouse', 'first_name' => 'Robin', 'last_name' => 'Walk', 'date_of_birth' => '1985-08-22', 'linked_user_id' => $spouse->id]);

    expect(spouseLinkItem($user->fresh()))->toBeNull();
});

it('says nothing to a single user with no spouse on file', function (): void {
    expect(spouseLinkItem(coupledUser(['marital_status' => 'single'])))->toBeNull();
});

it('stays quiet mid-walk, where Fyn is about to ask about the spouse', function (): void {
    $user = coupledUser(['onboarding_completed' => false, 'onboarding_fyn_step' => 'campaign_spouse_work']);
    FamilyMember::create(['user_id' => $user->id, 'relationship' => 'spouse', 'first_name' => 'Robin', 'last_name' => 'Walk', 'date_of_birth' => '1985-08-22']);

    expect(spouseLinkItem($user))->toBeNull();
});
