<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\UserProfile\UserProfileService;
use Illuminate\Database\Eloquent\Model;

/**
 * W-0176 — `/settings/family` showed a linked spouse earning £120,000 as
 * "Annual Income £0".
 *
 * The `family_members` row keeps whatever income was typed before the accounts
 * were linked and is never written again. Two payload builders in one method
 * disagreed: the stored-row path served the stale column, the virtual-spouse
 * fallback read the linked account. They now share one definition.
 *
 * The column holds the string '0.00', which is truthy in JavaScript — the reason
 * `v-if="member.annual_income"` printed £0 rather than hiding the row.
 */
beforeEach(function () {
    $this->modelEventDispatcher = Model::getEventDispatcher();
    Model::unsetEventDispatcher();

    $this->service = app(UserProfileService::class);
});

afterEach(function () {
    Model::setEventDispatcher($this->modelEventDispatcher);
});

/**
 * @return array<string, mixed>|null
 */
function spouseRow(array $members): ?array
{
    foreach ($members as $member) {
        if (($member['relationship'] ?? null) === 'spouse') {
            return $member;
        }
    }

    return null;
}

describe('a family-member row with a live account behind it', function () {
    it('reads the income off the account, not the stale row', function () {
        $spouse = User::factory()->create(['annual_employment_income' => 120_000]);
        $user = User::factory()->create(['spouse_id' => $spouse->id]);
        $spouse->update(['spouse_id' => $user->id]);

        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'relationship' => 'spouse',
            'name' => $spouse->name,
            'linked_user_id' => $spouse->id,
            'annual_income' => 0,
        ]);

        $row = spouseRow($this->service->getFamilyMembersWithSharing($user->fresh()));

        expect($row['is_linked_account'])->toBeTrue()
            ->and($row['annual_income'])->toBe(120_000.0);
    });

    it('follows the account when its income changes', function () {
        $spouse = User::factory()->create(['annual_employment_income' => 120_000]);
        $user = User::factory()->create(['spouse_id' => $spouse->id]);
        $spouse->update(['spouse_id' => $user->id]);

        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'relationship' => 'spouse',
            'linked_user_id' => $spouse->id,
            'annual_income' => 45_000,
        ]);

        $spouse->update(['annual_employment_income' => 90_000]);

        expect(spouseRow($this->service->getFamilyMembersWithSharing($user->fresh()))['annual_income'])
            ->toBe(90_000.0);
    });

    it('returns a falsy zero rather than a truthy "0.00" when the account really earns nothing', function () {
        $spouse = User::factory()->create(['annual_employment_income' => 0]);
        $user = User::factory()->create(['spouse_id' => $spouse->id]);
        $spouse->update(['spouse_id' => $user->id]);

        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'relationship' => 'spouse',
            'linked_user_id' => $spouse->id,
            'annual_income' => 50_000,
        ]);

        $income = spouseRow($this->service->getFamilyMembersWithSharing($user->fresh()))['annual_income'];

        expect($income)->toBe(0.0)
            ->and((bool) $income)->toBeFalse();
    });

    it('gives the virtual spouse row the same figure when no family-member row exists', function () {
        $spouse = User::factory()->create(['annual_employment_income' => 120_000]);
        $user = User::factory()->create(['spouse_id' => $spouse->id]);
        $spouse->update(['spouse_id' => $user->id]);

        $row = spouseRow($this->service->getFamilyMembersWithSharing($user->fresh()));

        expect($row['is_linked_account'])->toBeTrue()
            ->and($row['annual_income'])->toBe(120_000.0);
    });
});

describe('rows with no account behind them', function () {
    it('leaves a child row on its own recorded income', function () {
        $user = User::factory()->create();

        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'relationship' => 'child',
            'linked_user_id' => null,
            'annual_income' => 5_000,
        ]);

        $members = $this->service->getFamilyMembersWithSharing($user->fresh());

        expect($members[0]['is_linked_account'])->toBeFalse()
            ->and((float) $members[0]['annual_income'])->toBe(5_000.0);
    });
});
