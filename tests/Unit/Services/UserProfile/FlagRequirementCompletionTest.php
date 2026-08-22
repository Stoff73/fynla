<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\UserProfile\ModuleDataRequirementsService;
use Illuminate\Database\Eloquent\Model;

/**
 * W-0177 — the readiness panel read "COMPLETED (9)" with "Income needs updating"
 * as its first entry and "OUTSTANDING (0) — All items complete" beneath it.
 *
 * `income_needs_update` is a staleness flag raised when employment status
 * changes, not a piece of data a user supplies once. Treated as one, a lowered
 * flag counted as a completed requirement and the panel printed the opposite of
 * the truth on the one surface whose job is saying what is still missing.
 */
beforeEach(function () {
    $this->modelEventDispatcher = Model::getEventDispatcher();
    Model::unsetEventDispatcher();

    $this->service = app(ModuleDataRequirementsService::class);
});

afterEach(function () {
    Model::setEventDispatcher($this->modelEventDispatcher);
});

/**
 * @return list<string>
 */
function labels(array $requirements): array
{
    return array_map(static fn (array $r): string => $r['label'], $requirements);
}

describe('a lowered flag is not a completed requirement', function () {
    it('leaves it out of the completed list entirely', function () {
        $user = User::factory()->create(['income_needs_update' => false]);

        $result = $this->service->getRequirementsForModule($user, 'profile');

        expect(labels($result['filled']))->not->toContain('Income needs updating')
            ->and(labels($result['all_requirements']))->not->toContain('Income needs updating');
    });

    it('does not count it towards the total, so a complete profile reads 100%', function () {
        $user = User::factory()->create([
            'income_needs_update' => false,
            'date_of_birth' => '1980-01-01',
            'annual_employment_income' => 60_000,
            'monthly_expenditure' => 2_000,
            'marital_status' => 'married',
            'occupation' => 'Engineer',
            'target_retirement_age' => 65,
            'domicile_status' => 'uk_domiciled',
        ]);
        $user->familyMembers()->create(['relationship' => 'child', 'name' => 'Child']);

        $result = $this->service->getRequirementsForModule($user->fresh(), 'profile');

        expect($result['total_count'])->toBe(8)
            ->and($result['filled_count'])->toBe(8)
            ->and($result['missing'])->toBeEmpty()
            ->and((float) $result['completion_percentage'])->toBe(100.0);
    });
});

describe('a raised flag is outstanding', function () {
    it('appears under missing, not filled', function () {
        $user = User::factory()->create(['income_needs_update' => true]);

        $result = $this->service->getRequirementsForModule($user, 'profile');

        expect(labels($result['missing']))->toContain('Income needs updating')
            ->and(labels($result['filled']))->not->toContain('Income needs updating')
            ->and($result['total_count'])->toBe(9);
    });

    it('applies the same rule to the protection module', function () {
        $raised = User::factory()->create(['income_needs_update' => true]);
        $lowered = User::factory()->create(['income_needs_update' => false]);

        expect(labels($this->service->getRequirementsForModule($raised, 'protection')['missing']))
            ->toContain('Income needs updating')
            ->and(labels($this->service->getRequirementsForModule($lowered, 'protection')['all_requirements']))
            ->not->toContain('Income needs updating');
    });

    it('drops off the panel the moment the flag is lowered', function () {
        $user = User::factory()->create(['income_needs_update' => true]);

        expect($this->service->getRequirementsForModule($user, 'profile')['total_count'])->toBe(9);

        $user->update(['income_needs_update' => false]);

        expect($this->service->getRequirementsForModule($user->fresh(), 'profile')['total_count'])->toBe(8);
    });
});

describe('ordinary requirements are untouched', function () {
    it('still reports a genuinely missing field as outstanding', function () {
        $user = User::factory()->create([
            'income_needs_update' => false,
            'occupation' => null,
        ]);

        expect(labels($this->service->getRequirementsForModule($user, 'profile')['missing']))
            ->toContain('Your occupation');
    });
});
