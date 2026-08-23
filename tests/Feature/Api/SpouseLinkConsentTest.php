<?php

declare(strict_types=1);

use App\Models\Household;
use App\Models\SpousePermission;
use App\Models\User;
use App\Notifications\SpousePermissionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

/**
 * W-0347 / W-0348 / W-0349 — linking to an account that already exists is a
 * REQUEST, not something the caller can do to them.
 *
 * What this replaced: one authenticated POST plus a victim's email address
 * wrote the victim's `users` row (`spouse_id`, `marital_status`, an
 * attacker-supplied `annual_employment_income`, five address fields), wrote
 * `status => 'accepted'` on BOTH `spouse_permissions` rows, and returned the
 * victim's entire hydrated User model in the 201. The only precondition was
 * that the victim's `spouse_id` was NULL — every unlinked account.
 *
 * The tests are written as an attacker, not as a happy path: the victim is a
 * stranger with their own records, and every assertion is that the attacker got
 * NOTHING. Several assert on ABSENCE deliberately — a payload test that lists
 * what should be present passes for ever while the next column added to `users`
 * quietly ships beside it.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Notification::fake();
    RateLimiter::clear('spouse-invite:1');

    $this->attacker = User::factory()->create([
        'household_id' => Household::factory()->create()->id,
        'first_name' => 'Mallory',
        'surname' => 'Attacker',
        'marital_status' => 'married',
        'address_line_1' => '1 Attacker Way',
        'city' => 'Leeds',
        'postcode' => 'LS1 1AA',
    ]);

    // A stranger with their own account and their own financial facts.
    $this->victim = User::factory()->create([
        'household_id' => Household::factory()->create()->id,
        'email' => 'victim@example.com',
        'first_name' => 'Victim',
        'surname' => 'Person',
        'marital_status' => 'single',
        'annual_employment_income' => 82000,
        'date_of_birth' => '1979-04-02',
        'address_line_1' => '9 Private Road',
        'city' => 'Bristol',
        'postcode' => 'BS1 9ZZ',
    ]);

    $this->actingAs($this->attacker, 'sanctum');
});

function inviteVictim(array $overrides = []): TestResponse
{
    return test()->postJson('/api/user/family-members', array_merge([
        'relationship' => 'spouse',
        'email' => 'victim@example.com',
        'first_name' => 'Victim',
        'last_name' => 'Person',
        'annual_income' => 5,
    ], $overrides));
}

describe('POST /api/user/family-members — inviting an existing account', function () {

    it('writes NOTHING to the other account', function () {
        $before = $this->victim->only([
            'spouse_id', 'marital_status', 'annual_employment_income',
            'address_line_1', 'address_line_2', 'city', 'county', 'postcode',
            'household_id',
        ]);

        inviteVictim()->assertStatus(201);

        expect($this->victim->fresh()->only(array_keys($before)))->toBe($before);
    });

    it('does not link either side', function () {
        inviteVictim()->assertStatus(201);

        expect($this->victim->fresh()->spouse_id)->toBeNull()
            ->and($this->attacker->fresh()->spouse_id)->toBeNull();
    });

    it('records the request as pending, never as accepted', function () {
        inviteVictim()->assertStatus(201);

        $permissions = SpousePermission::all();

        expect($permissions)->toHaveCount(1)
            ->and($permissions->first()->status)->toBe('pending')
            ->and($permissions->first()->user_id)->toBe($this->attacker->id)
            ->and($permissions->first()->spouse_id)->toBe($this->victim->id)
            ->and($permissions->first()->responded_at)->toBeNull();
    });

    it('leaves both reciprocity and permission gates shut', function () {
        inviteVictim()->assertStatus(201);

        $attacker = $this->attacker->fresh();

        expect($attacker->hasReciprocalSpouseLink($this->victim->id))->toBeFalse()
            ->and($attacker->hasAcceptedSpousePermission())->toBeFalse()
            ->and($this->victim->fresh()->hasAcceptedSpousePermission())->toBeFalse();
    });

    it('discloses nothing about the other account in the response', function () {
        $body = inviteVictim()->assertStatus(201)->json();
        $flat = json_encode($body);

        // Absence, not shape. The old response carried the whole User model, so
        // every column added to `users` afterwards would have joined it.
        expect($flat)
            ->not->toContain('82000')            // their income
            ->not->toContain('1979-04-02')       // their date of birth
            ->not->toContain('9 Private Road')   // their address
            ->not->toContain('BS1 9ZZ')
            ->not->toContain('Bristol');

        expect($body['data'])->not->toHaveKey('spouse_user')
            ->and($body['data']['linked'])->toBeFalse()
            ->and($body['data']['invitation_pending'])->toBeTrue();
    });

    it('does not confirm the address is even registered', function () {
        // Same shape of answer whether the address holds an account or not:
        // both create a family-member row and report an invitation.
        $registered = inviteVictim()->assertStatus(201)->json('data');

        RateLimiter::clear('spouse-invite:'.$this->attacker->id);

        $unregistered = test()->postJson('/api/user/family-members', [
            'relationship' => 'spouse',
            'email' => 'nobody-here@example.com',
            'first_name' => 'Nobody',
            'last_name' => 'Here',
        ])->json('data');

        // The remaining difference is deliberate and on the board (W-0349): an
        // address with no account still gets one created, which is a product
        // decision, not an oversight. What must NOT differ is any detail of an
        // account that already exists.
        expect($registered)->not->toHaveKey('spouse_user');
        expect($unregistered['created'] ?? false)->toBeTrue();
    });

    it('notifies the invitee that it is theirs to decide', function () {
        inviteVictim()->assertStatus(201);

        Notification::assertSentTo($this->victim, SpousePermissionRequest::class);
    });

    it('throttles invitations without throttling ordinary family members', function () {
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::clear('spouse-invite:'.$this->attacker->id);
        }
        RateLimiter::clear('spouse-invite:'.$this->attacker->id);

        for ($i = 0; $i < 5; $i++) {
            test()->postJson('/api/user/family-members', [
                'relationship' => 'spouse',
                'email' => "probe{$i}@example.com",
                'first_name' => 'Probe',
                'last_name' => 'Target',
            ]);
        }

        inviteVictim()->assertStatus(429);

        // A large family is not an attack.
        test()->postJson('/api/user/family-members', [
            'relationship' => 'child',
            'first_name' => 'Child',
            'last_name' => 'Person',
            'date_of_birth' => '2015-01-01',
        ])->assertStatus(201);
    });
});

describe('POST /api/spouse-permission/accept — the link is made here or nowhere', function () {

    it('links both accounts only once the invitee accepts', function () {
        inviteVictim()->assertStatus(201);

        $this->actingAs($this->victim, 'sanctum')
            ->postJson('/api/spouse-permission/accept')
            ->assertStatus(200);

        $attacker = $this->attacker->fresh();
        $victim = $this->victim->fresh();

        expect($attacker->spouse_id)->toBe($victim->id)
            ->and($victim->spouse_id)->toBe($attacker->id)
            ->and($attacker->hasReciprocalSpouseLink($victim->id))->toBeTrue()
            ->and($attacker->hasAcceptedSpousePermission())->toBeTrue();
    });

    it('does not overwrite the accepter\'s own financial facts', function () {
        // The old flow pushed the CALLER's figure into the other person's
        // income column. Accepting shares visibility; it does not restate the
        // other person's facts as yours.
        inviteVictim(['annual_income' => 5])->assertStatus(201);

        $this->actingAs($this->victim, 'sanctum')
            ->postJson('/api/spouse-permission/accept')
            ->assertStatus(200);

        expect((float) $this->victim->fresh()->annual_employment_income)->toBe(82000.0);
    });

    it('leaves no link when the invitee declines', function () {
        inviteVictim()->assertStatus(201);

        $this->actingAs($this->victim, 'sanctum')
            ->postJson('/api/spouse-permission/reject')
            ->assertStatus(200);

        expect($this->attacker->fresh()->spouse_id)->toBeNull()
            ->and($this->victim->fresh()->spouse_id)->toBeNull()
            ->and(SpousePermission::first()->status)->toBe('rejected');
    });

    it('refuses to accept an invitation addressed to somebody else', function () {
        inviteVictim()->assertStatus(201);

        $bystander = User::factory()->create();

        $this->actingAs($bystander, 'sanctum')
            ->postJson('/api/spouse-permission/accept')
            ->assertStatus(404);

        expect($this->attacker->fresh()->spouse_id)->toBeNull();
    });
});

describe('GET /api/spouse-permission/status', function () {

    it('shows the invitee the request waiting for them', function () {
        inviteVictim()->assertStatus(201);

        $data = $this->actingAs($this->victim, 'sanctum')
            ->getJson('/api/spouse-permission/status')
            ->assertStatus(200)
            ->json('data');

        // Without this the only person who can answer the request cannot see
        // it: the invitee has no spouse_id, and the endpoint used to report
        // "no spouse" and stop.
        expect($data['has_spouse'])->toBeTrue()
            ->and($data['awaiting_your_response'])->toBeTrue()
            ->and($data['permission']['status'])->toBe('pending')
            ->and($data['can_view_spouse_data'])->toBeFalse();
    });

    it('does not tell the requester who owns the address they typed', function () {
        inviteVictim()->assertStatus(201);

        $data = $this->getJson('/api/spouse-permission/status')
            ->assertStatus(200)
            ->json('data');

        // The requester supplied "Victim Person" themselves; what they must not
        // get back is the account holder's real identity, which would answer
        // "who owns this address?" for anything they cared to type.
        expect($data['awaiting_their_response'])->toBeTrue()
            ->and($data['spouse']['id'])->toBeNull()
            ->and($data['spouse']['email'])->toBeNull();
    });

    it('never returns the other account as a model', function () {
        inviteVictim()->assertStatus(201);

        $this->actingAs($this->victim, 'sanctum')
            ->postJson('/api/spouse-permission/accept')
            ->assertStatus(200);

        $flat = json_encode(
            $this->actingAs($this->attacker, 'sanctum')
                ->getJson('/api/spouse-permission/status')
                ->json('data')
        );

        expect($flat)
            ->not->toContain('82000')
            ->not->toContain('1979-04-02')
            ->not->toContain('9 Private Road')
            ->not->toContain('BS1 9ZZ');
    });
});

describe('DELETE /api/spouse-permission/revoke', function () {

    it('actually stops sharing', function () {
        inviteVictim()->assertStatus(201);
        $this->actingAs($this->victim, 'sanctum')->postJson('/api/spouse-permission/accept');

        expect($this->attacker->fresh()->hasAcceptedSpousePermission())->toBeTrue();

        $this->actingAs($this->victim, 'sanctum')
            ->deleteJson('/api/spouse-permission/revoke')
            ->assertStatus(200);

        // Used to be a no-op twice over: the row was deleted, and an absent row
        // read as "linked before the consent flow existed" — so sharing came
        // straight back on. The withdrawal is recorded now.
        expect($this->attacker->fresh()->hasAcceptedSpousePermission())->toBeFalse()
            ->and($this->victim->fresh()->hasAcceptedSpousePermission())->toBeFalse()
            ->and(SpousePermission::first()->status)->toBe('rejected');
    });

    it('stays revoked, and the link itself survives', function () {
        inviteVictim()->assertStatus(201);
        $this->actingAs($this->victim, 'sanctum')->postJson('/api/spouse-permission/accept');
        $this->actingAs($this->victim, 'sanctum')->deleteJson('/api/spouse-permission/revoke');

        // Revoking withdraws VISIBILITY, not the marriage. `spouse_id` drives
        // nil-rate-band transfer, household net worth and joint ownership —
        // tearing it down here would silently rewrite someone's estate
        // position as a side effect of a privacy choice.
        expect($this->attacker->fresh()->spouse_id)->toBe($this->victim->id)
            ->and($this->victim->fresh()->spouse_id)->toBe($this->attacker->id)
            ->and($this->attacker->fresh()->hasAcceptedSpousePermission())->toBeFalse();
    });

    it('lets the requester withdraw an invitation nobody has answered', function () {
        inviteVictim()->assertStatus(201);

        $this->deleteJson('/api/spouse-permission/revoke')->assertStatus(200);

        expect(SpousePermission::first()->status)->toBe('rejected')
            ->and($this->attacker->fresh()->spouse_id)->toBeNull()
            ->and($this->victim->fresh()->spouse_id)->toBeNull();
    });
});
