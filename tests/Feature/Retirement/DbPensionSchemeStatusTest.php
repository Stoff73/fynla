<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\DBPension;
use App\Models\User;
use App\Services\Stores\Normalisers\PensionNormaliser;
use App\Services\UserProfile\UserProfileService;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * W-0032. Both Defined Benefit pension forms asked whether a scheme was Active,
 * Deferred or In Payment, and the answer was discarded on every save because no
 * column existed. Fyn's `create_pension` schema asked for it too and its answer
 * went the same way — the app telling the user it had recorded something it had
 * not.
 *
 * The field is not decorative. W-0036 made `DBPension::isInPayment()` decide
 * whether an accrued pension counts as income today, and without a stated status
 * it can only compare the user's age against the scheme's Normal Retirement Age.
 * That heuristic is wrong in both directions for cases common in Fynla's audience:
 * someone drawing at 57 against a scheme age of 60 has real income counted as
 * zero, and someone at 62 deferring a scheme age of 60 has no income counted in
 * full. Both directions are pinned below.
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

function schemeStatusUser(int $age): User
{
    return User::factory()->create([
        'date_of_birth' => now()->subYears($age)->subMonths(2)->format('Y-m-d'),
        'annual_employment_income' => 120000,
    ]);
}

function schemeStatusPension(User $user, ?string $status, ?int $normalRetirementAge = 60): DBPension
{
    return DBPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => 'NHS Pension Scheme',
        'accrued_annual_pension' => 35000,
        'normal_retirement_age' => $normalRetirementAge,
        'scheme_status' => $status,
    ]);
}

it('persists the scheme status the form sends', function (): void {
    $user = schemeStatusUser(48);
    Sanctum::actingAs($user);

    $this->postJson('/api/retirement/pensions/db', [
        'scheme_name' => 'NHS Pension Scheme',
        'scheme_type' => 'career_average',
        'scheme_status' => 'in_payment',
        'accrued_annual_pension' => 35000,
        'normal_retirement_age' => 60,
    ])->assertStatus(201);

    $this->assertDatabaseHas('db_pensions', [
        'user_id' => $user->id,
        'scheme_name' => 'NHS Pension Scheme',
        'scheme_status' => 'in_payment',
    ]);
});

it('leaves a stored status alone when an edit does not mention it', function (): void {
    $user = schemeStatusUser(48);
    $pension = schemeStatusPension($user, 'deferred');
    Sanctum::actingAs($user);

    $this->putJson("/api/retirement/pensions/db/{$pension->id}", [
        'accrued_annual_pension' => 36000,
    ])->assertOk();

    expect($pension->fresh()->scheme_status)->toBe('deferred');
});

it('rejects a status outside the stored vocabulary rather than writing it', function (): void {
    Sanctum::actingAs(schemeStatusUser(48));

    $this->postJson('/api/retirement/pensions/db', [
        'scheme_name' => 'NHS Pension Scheme',
        'scheme_type' => 'career_average',
        'scheme_status' => 'Retired',
        'accrued_annual_pension' => 35000,
    ])->assertStatus(422)->assertJsonValidationErrors('scheme_status');
});

/**
 * Fyn's tool schema declares the title-case enum. Normalising it in
 * PensionNormaliser — the layer every write path already passes through — is what
 * keeps one stored vocabulary instead of one per writer (Rule 20).
 */
it('maps the title-case status Fyn sends onto the stored vocabulary', function (): void {
    $canonical = app(PensionNormaliser::class)->fromFynPension([
        'pension_category' => 'db',
        'scheme_name' => 'NHS Pension Scheme',
        'scheme_type' => 'career_average',
        'scheme_status' => 'In Payment',
        'accrued_annual_pension' => 35000,
    ]);

    expect($canonical['scheme_status'])->toBe('in_payment');
});

it('drops a status it does not recognise instead of guessing one', function (): void {
    $normaliser = app(PensionNormaliser::class);

    $base = [
        'pension_category' => 'db',
        'scheme_name' => 'NHS Pension Scheme',
        'scheme_type' => 'career_average',
    ];

    expect($normaliser->fromFynPension([...$base, 'scheme_status' => 'Retired']))
        ->not->toHaveKey('scheme_status')
        ->and($normaliser->fromFynPension([...$base, 'scheme_status' => null]))
        ->not->toHaveKey('scheme_status')
        ->and($normaliser->fromFynPension($base))
        ->not->toHaveKey('scheme_status');
});

it('takes a stated status over the age heuristic, in both directions', function (): void {
    // Early retirement: drawing at 48 against a scheme age of 60. The heuristic
    // says no income; the user says otherwise, and the user is right.
    $early = schemeStatusUser(48);
    expect(schemeStatusPension($early, 'in_payment')->isInPayment())->toBeTrue();

    // Deferral: 70 years old, scheme age 60, not yet claimed. The heuristic would
    // count £35,000 that is not being paid.
    $deferred = schemeStatusUser(70);
    expect(schemeStatusPension($deferred, 'deferred')->isInPayment())->toBeFalse();

    // Still contributing past the scheme age is likewise not income.
    $active = schemeStatusUser(70);
    expect(schemeStatusPension($active, 'active')->isInPayment())->toBeFalse();
});

it('keeps the age heuristic for rows that predate the column', function (): void {
    // Deliberately not backfilled: an unknown status must not become a guessed one.
    expect(schemeStatusPension(schemeStatusUser(48), null)->isInPayment())->toBeFalse()
        ->and(schemeStatusPension(schemeStatusUser(61), null)->isInPayment())->toBeTrue();
});

it('moves the income the household is told it has', function (): void {
    // The whole point of the column: this figure feeds income tax, the Personal
    // Allowance taper and Child Benefit, not just a retirement screen (W-0036).
    $early = schemeStatusUser(48);
    schemeStatusPension($early, 'in_payment');

    $profile = app(UserProfileService::class)->getCompleteProfile($early->fresh());

    expect((float) $profile['income_occupation']['annual_pension_income'])->toBe(35000.0)
        ->and((float) $profile['income_occupation']['total_annual_income'])->toBe(155000.0);

    $deferred = schemeStatusUser(70);
    schemeStatusPension($deferred, 'deferred');

    $deferredProfile = app(UserProfileService::class)->getCompleteProfile($deferred->fresh());

    expect((float) $deferredProfile['income_occupation']['annual_pension_income'])->toBe(0.0)
        ->and((float) $deferredProfile['income_occupation']['total_annual_income'])->toBe(120000.0);
});

/**
 * /m and native have no Defined Benefit form — Fyn is their entry route — so a
 * status they can state but never correct is only half a field. W-0017 closed the
 * same gap for the scheme type, spouse pension, inflation protection and lump sum.
 */
it('lets Fyn correct a stated status, mapping its title case on the way', function (): void {
    $user = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => '1978-06-15']);
    $pension = schemeStatusPension($user, 'active');

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'db_pension',
        'entity_id' => $pension->id,
        'fields' => ['scheme_status' => 'In Payment'],
    ], $user);

    expect($result['error'] ?? false)->toBeFalse()
        ->and($pension->fresh()->scheme_status)->toBe('in_payment');
});

it('returns the stored status to every surface that reads the pension', function (): void {
    // /m and native both render `db_pensions` straight off GET /api/retirement.
    $user = schemeStatusUser(48);
    schemeStatusPension($user, 'in_payment');
    Sanctum::actingAs($user);

    $this->getJson('/api/retirement')
        ->assertOk()
        ->assertJsonPath('data.db_pensions.0.scheme_status', 'in_payment');
});
