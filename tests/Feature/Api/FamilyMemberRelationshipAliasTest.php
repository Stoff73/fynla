<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\User;
use App\Services\Risk\AutoRiskCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * W-0114 — the family form offers six relationships and the column holds four.
 * `partner` and `step_child` raised SQLSTATE[01000] 1265 under strict mode, so
 * both returned HTTP 500 with the raw SQL in the message — on the same modal
 * that serves `/settings/family` AND onboarding step 2.
 *
 * W-0111 — only a spouse gets an account, so only a spouse takes an email.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->household = Household::factory()->create();
    $this->user = User::factory()->create(['household_id' => $this->household->id, 'is_preview_user' => false]);
    $this->actingAs($this->user, 'sanctum');
});

it('confirms the column still holds only the four values this translation exists for', function () {
    $type = DB::select("SHOW COLUMNS FROM family_members LIKE 'relationship'")[0]->Type;

    // If this fails, the enum gained or lost a value and
    // FamilyMember::RELATIONSHIP_ALIASES needs revisiting — the mapping is only
    // correct relative to what the column can store.
    expect($type)->toBe("enum('spouse','child','parent','other_dependent')");
});

it('stores a step child instead of raising a database error', function () {
    $response = $this->postJson('/api/user/family-members', [
        'relationship' => 'step_child',
        'first_name' => 'Meera',
        'last_name' => 'Raman',
        'date_of_birth' => '2015-04-11',
    ]);

    $response->assertStatus(201);

    $member = FamilyMember::find($response->json('data.family_member.id'));

    expect($member->relationship)->toBe('child')
        ->and($member->notes)->toContain('Step child');
});

it('stores a partner instead of raising a database error', function () {
    $response = $this->postJson('/api/user/family-members', [
        'relationship' => 'partner',
        'first_name' => 'Sam',
        'last_name' => 'Okafor',
        'date_of_birth' => '1988-09-01',
    ]);

    $response->assertStatus(201);

    $member = FamilyMember::find($response->json('data.family_member.id'));

    expect($member->relationship)->toBe('other_dependent')
        ->and($member->notes)->toContain('Partner (unmarried)');
});

describe('the stated relationship is recorded and shown', function () {
    it('never tells the user their partner is a dependent', function () {
        $response = $this->postJson('/api/user/family-members', [
            'relationship' => 'partner',
            'first_name' => 'Sam',
            'last_name' => 'Okafor',
        ])->assertStatus(201);

        $member = FamilyMember::find($response->json('data.family_member.id'));

        // Stored as the alias the column can hold...
        expect($member->relationship)->toBe('other_dependent')
            // ...and remembered as what was actually chosen.
            ->and($member->stated_relationship)->toBe('partner')
            // The one thing every surface renders.
            ->and($member->display_relationship)->toBe('partner')
            ->and($member->display_relationship)->not->toContain('dependent');
    });

    it('calls a step child a step child', function () {
        $response = $this->postJson('/api/user/family-members', [
            'relationship' => 'step_child',
            'first_name' => 'Meera',
            'last_name' => 'Raman',
        ])->assertStatus(201);

        $member = FamilyMember::find($response->json('data.family_member.id'));

        expect($member->relationship)->toBe('child')
            ->and($member->stated_relationship)->toBe('step_child')
            ->and($member->display_relationship)->toBe('step child');
    });

    it('leaves the column null when nothing was translated, so no backfill is needed', function () {
        $response = $this->postJson('/api/user/family-members', [
            'relationship' => 'child',
            'first_name' => 'Meera',
        ])->assertStatus(201);

        $member = FamilyMember::find($response->json('data.family_member.id'));

        expect($member->stated_relationship)->toBeNull()
            ->and($member->display_relationship)->toBe('child');
    });

    it('ships the display value on the payload every surface reads', function () {
        $this->postJson('/api/user/family-members', [
            'relationship' => 'partner',
            'first_name' => 'Sam',
        ])->assertStatus(201);

        $row = collect($this->getJson('/api/user/family-members')->json('data.family_members'))
            ->firstWhere('first_name', 'Sam');

        expect($row['display_relationship'])->toBe('partner')
            ->and($row['stated_relationship'])->toBe('partner')
            ->and($row['relationship'])->toBe('other_dependent');
    });

    it('records the stated relationship on the Fyn path too', function () {
        $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
            'relationship' => 'partner',
            'first_name' => 'Sam',
            'surname' => 'Okafor',
        ], $this->user);

        expect(FamilyMember::find($result['entity_id'])->display_relationship)->toBe('partner');
    });

    it('spells the noun the British way, once, on the server', function () {
        // "Dependent" is the adjective; the noun is "dependant", and CLAUDE.md
        // requires British user-facing text. Computed here so web, /m and native
        // inherit one spelling without a second edit (W-0115).
        $member = FamilyMember::factory()->create([
            'user_id' => $this->user->id,
            'relationship' => 'other_dependent',
            'stated_relationship' => null,
        ]);

        expect($member->display_relationship)->toBe('other dependant')
            ->and($member->relationship)->toBe('other_dependent');
    });

    it('reaches the risk factor payload as what the user chose', function () {
        // The one page still printing "Other Dependent" for a partner — and the
        // worst place for it, being the page about the user's financial
        // dependants. The payload hand-builds its rows, so it has to carry the
        // computed value explicitly (W-0115).
        FamilyMember::factory()->create([
            'user_id' => $this->user->id,
            'relationship' => 'other_dependent',
            'stated_relationship' => 'partner',
            'is_dependent' => true,
            'first_name' => 'Sam',
        ]);

        $factor = collect(app(AutoRiskCalculator::class)
            ->calculateRiskProfile($this->user->fresh())['factor_breakdown'])
            ->firstWhere('factor', 'dependants');

        $sam = collect($factor['components']['dependants'])->firstWhere('name', 'Sam');

        expect($sam['display_relationship'])->toBe('partner')
            ->and($sam['display_relationship'])->not->toContain('dependent')
            // The raw value stays available for anything that needs to branch.
            ->and($sam['relationship'])->toBe('other_dependent');
    });

    it('clears the stated relationship when an edit moves the row to a plain value', function () {
        $response = $this->postJson('/api/user/family-members', [
            'relationship' => 'step_child',
            'first_name' => 'Meera',
        ])->assertStatus(201);

        $id = $response->json('data.family_member.id');

        $this->putJson("/api/user/family-members/{$id}", [
            'relationship' => 'child',
        ])->assertStatus(200);

        expect(FamilyMember::find($id)->stated_relationship)->toBeNull()
            ->and(FamilyMember::find($id)->display_relationship)->toBe('child');
    });
});

it('keeps the note the user wrote alongside the relationship note', function () {
    $response = $this->postJson('/api/user/family-members', [
        'relationship' => 'step_child',
        'first_name' => 'Meera',
        'last_name' => 'Raman',
        'notes' => 'Lives with us during term time',
    ]);

    expect(FamilyMember::find($response->json('data.family_member.id'))->notes)
        ->toBe('Step child. Lives with us during term time');
});

it('resolves an alias on update too, where the same enum would have 500ed', function () {
    $member = FamilyMember::factory()->create([
        'user_id' => $this->user->id,
        'relationship' => 'child',
        'first_name' => 'Meera',
        'last_name' => 'Raman',
    ]);

    $this->putJson("/api/user/family-members/{$member->id}", [
        'relationship' => 'step_child',
    ])->assertStatus(200);

    expect($member->fresh()->relationship)->toBe('child')
        ->and($member->fresh()->notes)->toContain('Step child');
});

it('leaves an unaliased relationship exactly as sent', function () {
    foreach (['child', 'parent', 'other_dependent'] as $relationship) {
        $response = $this->postJson('/api/user/family-members', [
            'relationship' => $relationship,
            'first_name' => 'Person',
            'last_name' => ucfirst($relationship),
        ]);

        $member = FamilyMember::find($response->json('data.family_member.id'));

        expect($member->relationship)->toBe($relationship)
            ->and($member->notes)->toBeNull();
    }
});

/**
 * Rule 20 — the mapping used to live only inside
 * CoordinatingAgent::handleCreateFamilyMember, which is why Fyn could add a
 * step-child and the form could not. Both now read FamilyMember, so both must
 * land the same row.
 */
it('lands the same row whichever path adds the step child', function () {
    $viaForm = $this->postJson('/api/user/family-members', [
        'relationship' => 'step_child',
        'first_name' => 'Meera',
        'last_name' => 'Raman',
    ])->json('data.family_member.id');

    $viaFyn = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'relationship' => 'step_child',
        'first_name' => 'Meera',
        'surname' => 'Raman',
    ], $this->user)['entity_id'];

    $shape = fn (int $id) => FamilyMember::find($id)->only(['relationship', 'stated_relationship', 'notes', 'first_name', 'last_name']);

    expect($shape($viaForm))->toBe($shape($viaFyn));
});

describe('W-0111 — only a spouse takes an email', function () {
    it('refuses an email for a partner rather than accepting and discarding it', function () {
        $this->postJson('/api/user/family-members', [
            'relationship' => 'partner',
            'first_name' => 'Sam',
            'last_name' => 'Okafor',
            'email' => 'sam@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        expect(FamilyMember::where('user_id', $this->user->id)->count())->toBe(0)
            ->and(User::where('email', 'sam@example.com')->exists())->toBeFalse();
    });

    it('refuses an email for a child too', function () {
        $this->postJson('/api/user/family-members', [
            'relationship' => 'child',
            'first_name' => 'Meera',
            'email' => 'meera@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    });

    it('still accepts a partner with no email', function () {
        $this->postJson('/api/user/family-members', [
            'relationship' => 'partner',
            'first_name' => 'Sam',
            'last_name' => 'Okafor',
        ])->assertStatus(201);

        expect($this->user->fresh()->spouse_id)->toBeNull();
    });
});
