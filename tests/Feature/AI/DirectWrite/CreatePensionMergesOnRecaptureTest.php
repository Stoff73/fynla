<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\DCPension;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

// TestCase and RefreshDatabase are applied to all of tests/Feature by Pest.php.

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

/**
 * BUG-02/03 (CSJ 2026-08-17) — a re-capture of the same pension must MERGE.
 *
 * Live sequence: turn 1 recorded "Aviva Pension" and, lacking a scheme type, wrote
 * it with the inferred default while asking for the type. The user answered "Sip".
 * Turn 2 reached the capture path and called `create_pension` again with the same
 * scheme name and scheme_type=sipp — and `checkForDuplicate` returned a warning, so
 * the answer landed on a no-op and the row stayed pension_type=occupational.
 *
 * CSJ's requirement is that data entry is deterministic in code, not dependent on the
 * model choosing `update_record`. So an exact-name re-capture merges the fields the
 * new call carries instead of warning and discarding them.
 */
function callCreatePension(User $user, array $input): array
{
    $agent = app(CoordinatingAgent::class);
    $method = new ReflectionMethod(CoordinatingAgent::class, 'handleCreatePension');
    $method->setAccessible(true);

    return $method->invoke($agent, $input, $user, false);
}

it('merges a same-name re-capture instead of warning, correcting the scheme type', function (): void {
    $user = User::factory()->create();

    $first = callCreatePension($user, [
        'pension_category' => 'dc',
        'scheme_name' => 'Aviva Pension',
        'scheme_type' => 'workplace',
        'current_fund_value' => 45000,
    ]);

    expect($first['created'] ?? false)->toBeTrue();
    $id = $first['entity_id'];
    expect(DCPension::find($id)->pension_type)->toBe('occupational');

    // The user answers the outstanding question; the model re-calls create_pension.
    $second = callCreatePension($user, [
        'pension_category' => 'dc',
        'scheme_name' => 'Aviva Pension',
        'scheme_type' => 'sipp',
    ]);

    expect(DCPension::where('user_id', $user->id)->count())->toBe(1, 'A re-capture must not duplicate.');
    expect(DCPension::find($id)->pension_type)->toBe('sipp', "The user's answer must reach the row.");
    expect((float) DCPension::find($id)->current_fund_value)->toBe(45000.0, 'A merge must not wipe known values.');
    expect($second['error'] ?? false)->toBeFalse();
    expect($second['updated'] ?? false)->toBeTrue();
    expect($second['entity_id'])->toBe($id);
});

it('does not let a merge blank a field the new call omits', function (): void {
    $user = User::factory()->create();

    $first = callCreatePension($user, [
        'pension_category' => 'dc',
        'scheme_name' => 'Scottish Widows Pension',
        'scheme_type' => 'workplace',
        'current_fund_value' => 30000,
        'provider' => 'Scottish Widows',
    ]);
    $id = $first['entity_id'];

    callCreatePension($user, [
        'pension_category' => 'dc',
        'scheme_name' => 'Scottish Widows Pension',
        'scheme_type' => 'sipp',
        'current_fund_value' => null,
    ]);

    $row = DCPension::find($id);
    expect($row->pension_type)->toBe('sipp');
    expect((float) $row->current_fund_value)->toBe(30000.0, 'A null in the new call must not erase a known value.');
    expect($row->provider)->toBe('Scottish Widows');
});

it('still refuses to merge across pension categories', function (): void {
    $user = User::factory()->create();

    callCreatePension($user, [
        'pension_category' => 'dc',
        'scheme_name' => 'NHS Pension Scheme',
        'scheme_type' => 'workplace',
        'current_fund_value' => 10000,
    ]);

    // A defined-benefit scheme of the same name is a different record shape, not a
    // field correction — the existing duplicate warning must still apply.
    $result = callCreatePension($user, [
        'pension_category' => 'db',
        'scheme_name' => 'NHS Pension Scheme',
        'scheme_type' => 'final_salary',
        'accrued_annual_pension' => 12000,
    ]);

    expect($result['warning'] ?? false)->toBeTrue();
    expect(DCPension::where('user_id', $user->id)->count())->toBe(1);
});
