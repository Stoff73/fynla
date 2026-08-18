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
 * BUG-02/03 (CSJ 2026-08-17) — re-capturing a pension that already exists.
 *
 * CSJ's rule, which supersedes the first version of this behaviour: "merging a
 * correction on an assumption or a simple comparison is WRONG and will lead to
 * errors. An edit, amendment or change must be explicit. If there is any ambiguity
 * Fyn must ask 'are we editing {plan}' before making any changes."
 *
 * So a same-name re-capture splits three ways:
 *
 *   fill      the record has no value for that field yet — unambiguous, apply it.
 *             This is the live case: turn 1 recorded "Aviva Pension" and asked for
 *             the scheme type; the user answered "Sip".
 *   conflict  the record holds a DIFFERENT value — ambiguous. Nothing is written and
 *             Fyn is told to ask whether this is an edit or a separate pension.
 *             "I have an Aviva pension worth 60000" must never silently overwrite 45000.
 *   identical everything already matches — do NOT assume a duplicate. Ask whether it
 *             is a separate pension (added under a distinguishing name) or the same one.
 */
function callCreatePension(User $user, array $input): array
{
    $method = new ReflectionMethod(CoordinatingAgent::class, 'handleCreatePension');
    $method->setAccessible(true);

    return $method->invoke(app(CoordinatingAgent::class), $input, $user, false);
}

it('fills a blank field on re-capture — the answer to an outstanding question lands', function (): void {
    $user = User::factory()->create();

    $first = callCreatePension($user, [
        'pension_category' => 'dc',
        'scheme_name' => 'Aviva Pension',
        'scheme_type' => 'workplace',
        'current_fund_value' => null,
    ]);
    $id = $first['entity_id'];

    $second = callCreatePension($user, [
        'pension_category' => 'dc',
        'scheme_name' => 'Aviva Pension',
        'current_fund_value' => 45000,
    ]);

    expect(DCPension::where('user_id', $user->id)->count())->toBe(1);
    expect($second['updated'] ?? false)->toBeTrue();
    expect((float) DCPension::find($id)->current_fund_value)->toBe(45000.0);
});

it('never overwrites a set value — it asks whether this is an edit', function (): void {
    $user = User::factory()->create();

    $first = callCreatePension($user, [
        'pension_category' => 'dc',
        'scheme_name' => 'Aviva Pension',
        'scheme_type' => 'workplace',
        'current_fund_value' => 45000,
    ]);
    $id = $first['entity_id'];

    // Could be a correction, a second Aviva pension, or a typo. Ambiguous.
    $second = callCreatePension($user, [
        'pension_category' => 'dc',
        'scheme_name' => 'Aviva Pension',
        'current_fund_value' => 60000,
    ]);

    expect($second['error_type'] ?? null)->toBe('confirm_edit_required');
    expect($second['conflicts'])->toHaveKey('current_fund_value');
    expect((float) DCPension::find($id)->current_fund_value)
        ->toBe(45000.0, 'Nothing may be written until the user confirms the edit.');
    expect(DCPension::where('user_id', $user->id)->count())->toBe(1, 'And it must not duplicate either.');
});

it('asks rather than assuming a duplicate when everything already matches', function (): void {
    $user = User::factory()->create();

    $input = [
        'pension_category' => 'dc',
        'scheme_name' => 'Aviva Pension',
        'scheme_type' => 'workplace',
        'current_fund_value' => 45000,
    ];

    callCreatePension($user, $input);
    $second = callCreatePension($user, $input);

    expect($second['error_type'] ?? null)->toBe('confirm_duplicate_required');
    expect(DCPension::where('user_id', $user->id)->count())->toBe(1);
});

it('does not let a re-capture blank a field the new call omits', function (): void {
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
        'current_fund_value' => null,
    ]);

    $row = DCPension::find($id);
    expect((float) $row->current_fund_value)->toBe(30000.0);
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

    $result = callCreatePension($user, [
        'pension_category' => 'db',
        'scheme_name' => 'NHS Pension Scheme',
        'scheme_type' => 'final_salary',
        'accrued_annual_pension' => 12000,
    ]);

    expect($result['warning'] ?? false)->toBeTrue();
    expect(DCPension::where('user_id', $user->id)->count())->toBe(1);
});

it('applies a conflicting value when the user is answering Fyns own question', function (): void {
    $user = User::factory()->create();
    $agent = app(CoordinatingAgent::class);

    $method = new ReflectionMethod(CoordinatingAgent::class, 'handleCreatePension');
    $method->setAccessible(true);

    $first = $method->invoke($agent, [
        'pension_category' => 'dc',
        'scheme_name' => 'Aviva Pension',
        'scheme_type' => 'workplace',
        'current_fund_value' => 45000,
    ], $user, false);
    $id = $first['entity_id'];

    // Fyn asked "workplace or Self-Invested Personal Pension?"; the user said "Sip".
    // That answer is explicit, so it lands rather than asking a second question.
    // The permission names the record the question was about — see the test below
    // for why it cannot be granted for the type at large.
    $agent->setExplicitEditEntityType('pension', $id);

    $second = $method->invoke($agent, [
        'pension_category' => 'dc',
        'scheme_name' => 'Aviva Pension',
        'scheme_type' => 'sipp',
    ], $user, false);

    expect($second['updated'] ?? false)->toBeTrue();
    expect(DCPension::find($id)->pension_type)->toBe('sipp');
    expect((float) DCPension::find($id)->current_fund_value)->toBe(45000.0);
    expect(DCPension::where('user_id', $user->id)->count())->toBe(1);

    $agent->setExplicitEditEntityType(null);
});

it('does not let an edit permission for one pension amend a different one', function (): void {
    // Live 2026-08-17: answering a question about a NEW goal ("high priority,
    // 300 a month") carried a permission scoped to the TYPE, and the recapture
    // guard applied it to a pre-existing goal of the same name — overwriting a
    // £25,000 target with £20,000, silently. CSJ's rule licenses amending the
    // record Fyn asked about, and only that one.
    $user = User::factory()->create();
    $agent = app(CoordinatingAgent::class);
    $method = new ReflectionMethod(CoordinatingAgent::class, 'handleCreatePension');
    $method->setAccessible(true);

    $asked = $method->invoke($agent, [
        'pension_category' => 'dc',
        'scheme_name' => 'Aviva Pension',
        'scheme_type' => 'workplace',
        'current_fund_value' => 45000,
    ], $user, false);

    $other = $method->invoke($agent, [
        'pension_category' => 'dc',
        'scheme_name' => 'Standard Life Pension',
        'scheme_type' => 'workplace',
        'current_fund_value' => 30000,
    ], $user, false);

    // Permission granted for the Aviva pension Fyn asked about.
    $agent->setExplicitEditEntityType('pension', $asked['entity_id']);

    // A re-capture of the OTHER pension with a different value must still ask.
    $result = $method->invoke($agent, [
        'pension_category' => 'dc',
        'scheme_name' => 'Standard Life Pension',
        'current_fund_value' => 1000,
    ], $user, false);

    expect($result['error_type'] ?? null)->toBe('confirm_edit_required');
    expect((float) DCPension::find($other['entity_id'])->current_fund_value)->toBe(30000.0);

    $agent->setExplicitEditEntityType(null);
});

it('reverts to asking once the explicit-edit permission is cleared', function (): void {
    $user = User::factory()->create();
    $agent = app(CoordinatingAgent::class);
    $method = new ReflectionMethod(CoordinatingAgent::class, 'handleCreatePension');
    $method->setAccessible(true);

    $method->invoke($agent, [
        'pension_category' => 'dc',
        'scheme_name' => 'Aviva Pension',
        'scheme_type' => 'workplace',
        'current_fund_value' => 45000,
    ], $user, false);

    // No permission set — a later unrelated turn must not inherit it.
    $second = $method->invoke($agent, [
        'pension_category' => 'dc',
        'scheme_name' => 'Aviva Pension',
        'scheme_type' => 'sipp',
    ], $user, false);

    expect($second['error_type'] ?? null)->toBe('confirm_edit_required');
});
