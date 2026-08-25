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
 * Pest's helper functions are declared in the GLOBAL namespace, so two test files
 * cannot both define `spouseRow()`. This one and the one in
 * `tests/Feature/Console/ReconcileSpouseFamilyLinksTest.php` collided, and PHP
 * fatals on the redeclaration at collection time — so `./vendor/bin/pest` could
 * not build its suite AT ALL. Not one test failed; the whole run died before any
 * ran, and it had been that way since 2026-08-22 (both files arrived in the same
 * snapshot).
 *
 * Renamed rather than extracted: the two do different jobs — this picks a spouse
 * out of an array of family-member payloads, the other creates a `FamilyMember`
 * row — and sharing a name was the only thing they had in common.
 *
 * @return array<string, mixed>|null
 */
function spouseRowFromPayload(array $members): ?array
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

        $row = spouseRowFromPayload($this->service->getFamilyMembersWithSharing($user->fresh()));

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

        expect(spouseRowFromPayload($this->service->getFamilyMembersWithSharing($user->fresh()))['annual_income'])
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

        $income = spouseRowFromPayload($this->service->getFamilyMembersWithSharing($user->fresh()))['annual_income'];

        expect($income)->toBe(0.0)
            ->and((bool) $income)->toBeFalse();
    });

    it('gives the virtual spouse row the same figure when no family-member row exists', function () {
        $spouse = User::factory()->create(['annual_employment_income' => 120_000]);
        $user = User::factory()->create(['spouse_id' => $spouse->id]);
        $spouse->update(['spouse_id' => $user->id]);

        $row = spouseRowFromPayload($this->service->getFamilyMembersWithSharing($user->fresh()));

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
