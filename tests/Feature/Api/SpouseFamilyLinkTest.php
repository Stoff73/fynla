<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\SpousePermission;
use App\Models\User;
use App\Services\Onboarding\SpouseLinkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * W-0051 — a spouse family member is only a "linked account" when an account is
 * actually linked, and everything that reads or writes that fact reads
 * `linked_user_id`, never `relationship === 'spouse'`.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();

    $this->household = Household::factory()->create();

    $this->user = User::factory()->create([
        'household_id' => $this->household->id,
        'first_name' => 'Priya',
        'surname' => 'Raman',
    ]);

    $this->actingAs($this->user, 'sanctum');
});

/** The exact shape onboarding used to produce: a spouse row linking to nobody. */
function orphanSpouseRow(User $owner): FamilyMember
{
    return FamilyMember::factory()->create([
        'user_id' => $owner->id,
        'household_id' => $owner->household_id,
        'relationship' => 'spouse',
        'linked_user_id' => null,
        'first_name' => 'Arjun',
        'last_name' => 'Raman',
        'name' => 'Arjun Raman',
        'date_of_birth' => '1977-06-02',
    ]);
}

describe('the is_linked_account predicate', function () {
    it('is false for a spouse row that links to nobody', function () {
        orphanSpouseRow($this->user);

        $response = $this->getJson('/api/user/family-members');

        $spouse = collect($response->json('data.family_members'))
            ->firstWhere('relationship', 'spouse');

        expect($spouse['is_linked_account'])->toBeFalse();
    });

    it('is true once an account is linked, and carries that account email', function () {
        $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => 'arjun@example.com',
            'first_name' => 'Arjun',
            'last_name' => 'Raman',
            'date_of_birth' => '1977-06-02',
        ])->assertStatus(201);

        $response = $this->getJson('/api/user/family-members');

        $spouse = collect($response->json('data.family_members'))
            ->firstWhere('relationship', 'spouse');

        expect($spouse['is_linked_account'])->toBeTrue()
            ->and($spouse['email'])->toBe('arjun@example.com');
    });

    it('does not hand the real spouse email to an unlinked row', function () {
        // Link a spouse properly, then plant an unlinked row beside it — the
        // duplicate state W-0051 reached. The unlinked row must not borrow the
        // linked account's email and present itself as linked.
        $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => 'arjun@example.com',
            'first_name' => 'Arjun',
            'last_name' => 'Raman',
        ])->assertStatus(201);

        $orphan = orphanSpouseRow($this->user->fresh());

        $rows = collect($this->getJson('/api/user/family-members')->json('data.family_members'));
        $orphanRow = $rows->firstWhere('id', $orphan->id);

        expect($orphanRow['is_linked_account'])->toBeFalse()
            ->and($orphanRow['email'] ?? null)->toBeNull();
    });

    it('reports the linked account as gone once that account is deleted', function () {
        $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => 'arjun@example.com',
            'first_name' => 'Arjun',
            'last_name' => 'Raman',
        ])->assertStatus(201);

        User::where('email', 'arjun@example.com')->first()->delete();

        $spouse = FamilyMember::where('user_id', $this->user->id)
            ->where('relationship', 'spouse')
            ->first();

        // The column is retained; the claim is not (deleted-spouse-visibility §1).
        expect($spouse->linked_user_id)->not->toBeNull()
            ->and($spouse->isLinkedAccount())->toBeFalse();
    });
});

describe('POST /api/user/family-members — spouse', function () {
    it('refuses a spouse with no email, because there is nothing to link by', function () {
        $response = $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'first_name' => 'Arjun',
            'last_name' => 'Raman',
            'date_of_birth' => '1977-06-02',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);

        expect(FamilyMember::where('user_id', $this->user->id)->count())->toBe(0);
    });

    it('creates the account, links both sides and accepts both permissions', function () {
        $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => 'arjun@example.com',
            'first_name' => 'Arjun',
            'last_name' => 'Raman',
            'date_of_birth' => '1977-06-02',
        ])->assertStatus(201)->assertJsonPath('data.created', true);

        $spouseUser = User::where('email', 'arjun@example.com')->first();

        expect($spouseUser)->not->toBeNull()
            ->and($this->user->fresh()->spouse_id)->toBe($spouseUser->id)
            ->and($spouseUser->spouse_id)->toBe($this->user->id);

        expect(SpousePermission::where('user_id', $this->user->id)->where('spouse_id', $spouseUser->id)->value('status'))->toBe('accepted')
            ->and(SpousePermission::where('user_id', $spouseUser->id)->where('spouse_id', $this->user->id)->value('status'))->toBe('accepted');

        expect(FamilyMember::where('user_id', $this->user->id)->where('relationship', 'spouse')->count())->toBe(1)
            ->and(FamilyMember::where('user_id', $spouseUser->id)->where('relationship', 'spouse')->value('linked_user_id'))->toBe($this->user->id);
    });

    it('adopts an existing unlinked spouse row instead of adding a second', function () {
        $orphan = orphanSpouseRow($this->user);

        $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => 'arjun@example.com',
            'first_name' => 'Arjun',
            'last_name' => 'Raman',
            'date_of_birth' => '1977-06-02',
        ])->assertStatus(201);

        $spouseUser = User::where('email', 'arjun@example.com')->first();
        $rows = FamilyMember::where('user_id', $this->user->id)->where('relationship', 'spouse')->get();

        expect($rows)->toHaveCount(1)
            ->and($rows->first()->id)->toBe($orphan->id)
            ->and($rows->first()->linked_user_id)->toBe($spouseUser->id);
    });

    it('preserves a civil partnership rather than forcing married', function () {
        $this->user->update(['marital_status' => 'civil_partnership']);

        $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => 'arjun@example.com',
            'first_name' => 'Arjun',
            'last_name' => 'Raman',
        ])->assertStatus(201);

        expect($this->user->fresh()->marital_status)->toBe('civil_partnership')
            ->and(User::where('email', 'arjun@example.com')->value('marital_status'))->toBe('civil_partnership');
    });

    it('refuses an email already linked to another household without saying so', function () {
        $otherHouseholdMember = User::factory()->create(['email' => 'taken@example.com']);
        $otherHouseholdMember->update(['spouse_id' => User::factory()->create()->id]);

        // The message used to be "This user is already linked to another
        // spouse", which confirms to any authenticated caller that an address
        // they merely typed holds a Fynla account AND that it is in a
        // household. A closed account answered differently again. Both now give
        // the same answer as each other (W-0349).
        $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => 'taken@example.com',
            'first_name' => 'Someone',
            'last_name' => 'Else',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'That email address cannot be linked to your household');
    });

    it('gives a closed account the same refusal as one already linked', function () {
        $closed = User::factory()->create(['email' => 'closed@example.com']);
        $closed->delete();

        $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => 'closed@example.com',
            'first_name' => 'Someone',
            'last_name' => 'Else',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'That email address cannot be linked to your household');
    });

    it('refuses the user adding themselves', function () {
        $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => $this->user->email,
            'first_name' => 'Priya',
            'last_name' => 'Raman',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'You cannot add yourself as a spouse');
    });
});

describe('DELETE /api/user/family-members/{id} — spouse', function () {
    it('removes an unlinked spouse record without touching a real link', function () {
        // The household is properly linked...
        $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => 'arjun@example.com',
            'first_name' => 'Arjun',
            'last_name' => 'Raman',
        ])->assertStatus(201);

        $spouseUser = User::where('email', 'arjun@example.com')->first();

        // ...and an unlinked duplicate sits beside it.
        $orphan = orphanSpouseRow($this->user->fresh());

        $this->deleteJson("/api/user/family-members/{$orphan->id}")->assertStatus(200);

        expect(FamilyMember::find($orphan->id))->toBeNull()
            ->and($this->user->fresh()->spouse_id)->toBe($spouseUser->id)
            ->and($spouseUser->fresh()->spouse_id)->toBe($this->user->id)
            ->and(FamilyMember::where('user_id', $spouseUser->id)->where('relationship', 'spouse')->count())->toBe(1)
            ->and(SpousePermission::where('user_id', $this->user->id)->where('spouse_id', $spouseUser->id)->count())->toBe(1);
    });

    it('still unlinks the household when the linked record itself is deleted', function () {
        $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => 'arjun@example.com',
            'first_name' => 'Arjun',
            'last_name' => 'Raman',
        ])->assertStatus(201);

        $spouseUser = User::where('email', 'arjun@example.com')->first();
        $linked = FamilyMember::where('user_id', $this->user->id)->where('relationship', 'spouse')->first();

        $this->deleteJson("/api/user/family-members/{$linked->id}")->assertStatus(200);

        expect($this->user->fresh()->spouse_id)->toBeNull()
            ->and($spouseUser->fresh()->spouse_id)->toBeNull()
            ->and(FamilyMember::where('user_id', $spouseUser->id)->where('relationship', 'spouse')->count())->toBe(0)
            ->and(SpousePermission::where('user_id', $this->user->id)->count())->toBe(0);
    });
});

describe('PUT /api/user/family-members/{id} — spouse', function () {
    it('does not rewrite the real spouse account when an unlinked record is edited', function () {
        $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => 'arjun@example.com',
            'first_name' => 'Arjun',
            'last_name' => 'Raman',
            'date_of_birth' => '1977-06-02',
        ])->assertStatus(201);

        $spouseUser = User::where('email', 'arjun@example.com')->first();
        $originalName = $spouseUser->name;

        $orphan = orphanSpouseRow($this->user->fresh());

        $this->putJson("/api/user/family-members/{$orphan->id}", [
            'first_name' => 'Typo',
            'last_name' => 'Corrected',
            'date_of_birth' => '1980-01-01',
        ])->assertStatus(200);

        expect($orphan->fresh()->first_name)->toBe('Typo')
            ->and($spouseUser->fresh()->name)->toBe($originalName)
            ->and($spouseUser->fresh()->date_of_birth->format('Y-m-d'))->toBe('1977-06-02');
    });

    it('still syncs the spouse account when the linked record is edited', function () {
        $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => 'arjun@example.com',
            'first_name' => 'Arjun',
            'last_name' => 'Raman',
            'date_of_birth' => '1977-06-02',
        ])->assertStatus(201);

        $spouseUser = User::where('email', 'arjun@example.com')->first();
        $linked = FamilyMember::where('user_id', $this->user->id)->where('relationship', 'spouse')->first();

        $this->putJson("/api/user/family-members/{$linked->id}", [
            'first_name' => 'Arjun',
            'last_name' => 'Raman',
            'date_of_birth' => '1980-01-01',
            'gender' => 'male',
            'annual_income' => 51000,
        ])->assertStatus(200);

        expect($spouseUser->fresh()->date_of_birth->format('Y-m-d'))->toBe('1980-01-01')
            ->and($spouseUser->fresh()->gender)->toBe('male')
            ->and((float) $spouseUser->fresh()->annual_employment_income)->toBe(51000.0);
    });

    /**
     * W-0112 — the name used to be synced as `users.name`, a column that does
     * not exist. `name` is an appended accessor over first_name/middle_name/
     * surname and `User::isFillable('name')` is false, so `fill()` dropped it
     * without an error and every rename stopped at the family-member card.
     */
    it('renames the linked spouse account, not just their card', function () {
        $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => 'arjun@example.com',
            'first_name' => 'Arjun',
            'last_name' => 'Raman',
        ])->assertStatus(201);

        $spouseUser = User::where('email', 'arjun@example.com')->first();
        $linked = FamilyMember::where('user_id', $this->user->id)->where('relationship', 'spouse')->first();

        expect($spouseUser->name)->toBe('Arjun Raman');

        $this->putJson("/api/user/family-members/{$linked->id}", [
            'first_name' => 'Arjun',
            'middle_name' => 'Dev',
            'last_name' => 'Raman-Patel',
        ])->assertStatus(200);

        $fresh = $spouseUser->fresh();

        expect($fresh->first_name)->toBe('Arjun')
            ->and($fresh->middle_name)->toBe('Dev')
            ->and($fresh->surname)->toBe('Raman-Patel')
            // The accessor every surface reads — the profile payload, /m and
            // native iOS all render this string.
            ->and($fresh->name)->toBe('Arjun Dev Raman-Patel');
    });

    it('does not push family-member-only fields onto the spouse account', function () {
        $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => 'arjun@example.com',
            'first_name' => 'Arjun',
            'last_name' => 'Raman',
        ])->assertStatus(201);

        $spouseUser = User::where('email', 'arjun@example.com')->first();
        $linked = FamilyMember::where('user_id', $this->user->id)->where('relationship', 'spouse')->first();

        $this->putJson("/api/user/family-members/{$linked->id}", [
            'first_name' => 'Arjun',
            'last_name' => 'Raman',
            'notes' => 'Prefers email over phone',
            'is_dependent' => false,
        ])->assertStatus(200);

        // `notes` and `is_dependent` describe the record, not the person's own
        // account. The map is what keeps them out.
        expect(array_keys(app(SpouseLinkingService::class)->userAttributesFrom([
            'notes' => 'x',
            'is_dependent' => true,
            'relationship' => 'spouse',
            'name' => 'Whole Name',
        ])))->toBe([]);

        expect($linked->fresh()->notes)->toBe('Prefers email over phone');
    });

    /**
     * Rule 20 drift guard. `SpouseLinkingService::createAndLinkNewSpouse()`
     * writes the same correspondence inline at creation time, with creation-only
     * defaults it is not worth folding into the map. This pins the two in
     * agreement, so if either side gains a field the other does not, a test says
     * so rather than a user finding it.
     */
    it('keeps the declared field map in agreement with what the linking service writes', function () {
        $this->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => 'arjun@example.com',
            'first_name' => 'Arjun',
            'middle_name' => 'Dev',
            'last_name' => 'Raman',
            'date_of_birth' => '1977-06-02',
            'gender' => 'male',
            'annual_income' => 42000,
        ])->assertStatus(201);

        $spouseUser = User::where('email', 'arjun@example.com')->first();
        $member = FamilyMember::where('user_id', $this->user->id)->where('relationship', 'spouse')->first();

        foreach (SpouseLinkingService::FAMILY_MEMBER_TO_USER_COLUMNS as $memberColumn => $userColumn) {
            $memberValue = $member->getAttribute($memberColumn);
            $userValue = $spouseUser->getAttribute($userColumn);

            if ($memberValue === null) {
                continue;
            }

            $normalise = static fn ($v) => $v instanceof Carbon
                ? $v->format('Y-m-d')
                : (is_numeric($v) ? (float) $v : $v);

            expect($normalise($userValue))->toBe(
                $normalise($memberValue),
                "creation wrote a different value for {$memberColumn} → {$userColumn}"
            );
        }
    });
});
