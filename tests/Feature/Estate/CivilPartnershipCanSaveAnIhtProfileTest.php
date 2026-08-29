<?php

declare(strict_types=1);

use App\Models\Estate\IHTProfile;
use App\Models\User;
use App\Support\HouseholdPooling;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * W-0509 — a civil partnership can save an Inheritance Tax profile.
 *
 * **Not a wrong figure: a hard block.** Two layers still carried the list as it stood
 * before 2026-04-15, and neither contained a quoted `'married'` literal, which is
 * exactly how both escaped the sweep guard filed under W-0480:
 *
 *  - `IHTController` validated `in:single,married,widowed,divorced`, so the request
 *    never reached the database — a 422 with no route around it.
 *  - `iht_profiles.marital_status` was `enum('single','married','widowed','divorced')`
 *    while `users.marital_status` had accepted `civil_partnership` since April.
 *
 * **These are HTTP tests on purpose.** The validation rule is the outer of the two
 * layers, so a test that wrote the model directly would have gone green against a
 * controller that still rejected every real submission.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

/** A premium household at the given marital status — the estate write routes are full-tier. */
function ihtProfileHousehold(string $status): User
{
    return User::factory()->withActivePremiumSubscription()->create([
        'tier' => 'premium',
        'marital_status' => $status,
        'date_of_birth' => '1968-03-04',
        'gender' => 'male',
    ])->fresh();
}

it('accepts a civil partnership and reads the status back intact', function () {
    // Acceptance 3. Fails before the migration on the column, and before the rule
    // change on the 422 — the two layers fail it independently.
    $user = ihtProfileHousehold('civil_partnership');
    Sanctum::actingAs($user);

    $this->postJson('/api/estate/profile', [
        'marital_status' => 'civil_partnership',
        'has_spouse' => true,
        'own_home' => true,
        'home_value' => 900000,
    ])->assertSuccessful();

    // Read back from the database rather than from the response: the column enum is
    // the layer a passing controller would still hide.
    expect(IHTProfile::where('user_id', $user->id)->value('marital_status'))
        ->toBe('civil_partnership');
});

it('accepts every status the users column accepts', function () {
    // Acceptance 4, as a test rather than another regex. The two columns and the
    // validation rule are three statements of one vocabulary, and this is what holds
    // them together — a status added to the list without widening this column would
    // fail here rather than in production.
    foreach (HouseholdPooling::ALL_MARITAL_STATUSES as $status) {
        $user = ihtProfileHousehold($status);
        Sanctum::actingAs($user);

        $this->postJson('/api/estate/profile', ['marital_status' => $status])
            ->assertSuccessful();

        expect(IHTProfile::where('user_id', $user->id)->value('marital_status'))->toBe($status);
    }
});

it('still refuses a status the application does not recognise', function () {
    // The rule was widened, not removed. Reading a shared list must not become
    // accepting anything.
    $user = ihtProfileHousehold('single');
    Sanctum::actingAs($user);

    $this->postJson('/api/estate/profile', ['marital_status' => 'its_complicated'])
        ->assertStatus(422);
});

it('holds the two columns to the same vocabulary', function () {
    // The defect was a MISMATCH between two tables, so the guard is the comparison —
    // it fails if either column is widened without the other, which is the mistake
    // the 2026-04-15 migration made.
    $values = function (string $table): array {
        $type = collect(DB::select("SHOW COLUMNS FROM {$table} LIKE 'marital_status'"))->first()->Type;
        preg_match_all("/'([^']+)'/", $type, $matches);

        return $matches[1];
    };

    expect($values('iht_profiles'))->toEqualCanonicalizing($values('users'))
        ->and($values('users'))->toEqualCanonicalizing(HouseholdPooling::ALL_MARITAL_STATUSES);
});
