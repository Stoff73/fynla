<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Shared\DependantsReach;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0275. W-0272 built `DependantsReach` as the one home and routed exactly one
 * consumer to it. Eight more still ran the query that produced the defect, so the same
 * household still got two answers depending on which page it was on: Sarah was told
 * "No dependants means you can afford to take more investment risk" about the same two
 * children that told her husband "Multiple dependants means financial stability is a
 * priority".
 *
 * `family_members.user_id` records **who typed the row**, not **whose children they
 * are**.
 */
beforeEach(function () {
    $this->david = User::factory()->create(['marital_status' => 'married']);
    $this->sarah = User::factory()->create([
        'marital_status' => 'married',
        'spouse_id' => $this->david->id,
    ]);
    $this->david->update(['spouse_id' => $this->sarah->id]);

    // Both children entered on David's account, as one parent doing the data entry.
    FamilyMember::factory()->create([
        'user_id' => $this->david->id,
        'relationship' => 'child',
        'is_dependent' => true,
        'name' => 'William',
        'date_of_birth' => now()->subYears(9),
    ]);
    FamilyMember::factory()->create([
        'user_id' => $this->david->id,
        'relationship' => 'child',
        'is_dependent' => true,
        'name' => 'Emily',
        'date_of_birth' => now()->subYears(6),
    ]);

    $this->reach = app(DependantsReach::class);
});

describe('both parents see the same household', function () {
    it('reaches the children from the account that did not type them', function () {
        expect($this->reach->countFor($this->david->fresh()))->toBe(2)
            ->and($this->reach->countFor($this->sarah->fresh()))->toBe(2);
    });

    it('reaches them for the family question too, not only the dependants one', function () {
        expect($this->reach->householdFamilyOf($this->sarah->fresh(), ['child']))->toHaveCount(2);
    });
});

/**
 * Acceptance 3. Intestacy distributes to CHILDREN under the Administration of Estates
 * Act 1925 — a grown, self-supporting child inherits exactly as a dependent one does.
 * Routing it through the dependants filter would disinherit them.
 */
describe('the family reach is not the dependants reach', function () {
    it('includes a grown child the dependants filter excludes', function () {
        FamilyMember::factory()->create([
            'user_id' => $this->david->id,
            'relationship' => 'child',
            'is_dependent' => false,
            'name' => 'Grown Child',
            'date_of_birth' => now()->subYears(31),
        ]);

        expect($this->reach->countFor($this->david->fresh()))->toBe(2)
            ->and($this->reach->householdFamilyOf($this->david->fresh(), ['child']))->toHaveCount(3);
    });

    it('does not count the same child twice when both parents typed them', function () {
        FamilyMember::factory()->create([
            'user_id' => $this->sarah->id,
            'relationship' => 'child',
            'is_dependent' => true,
            'name' => 'William',
            'date_of_birth' => now()->subYears(9),
        ]);

        expect($this->reach->countFor($this->david->fresh()))->toBe(2)
            ->and($this->reach->householdFamilyOf($this->sarah->fresh(), ['child']))->toHaveCount(2);
    });
});

/**
 * The guard is the item. Routing eight consumers without one just resets the clock:
 * the ninth naive query lands next week, and no behavioural test on a single service
 * can see it — each one would simply answer for half a household, plausibly.
 */
describe('no consumer asks the question for itself', function () {
    it('leaves no read-path FamilyMember user_id query in the routed consumers', function () {
        $files = [
            'app/Services/Protection/ComprehensiveProtectionPlanService.php',
            'app/Services/AI/MemoryRetrieverService.php',
            'app/Services/Coordination/PlanSources/ModuleAvailabilityProvider.php',
            'app/Services/Estate/IntestacyCalculator.php',
            'app/Services/AI/AdvicePromptBuilder.php',
            'app/Services/Savings/SavingsActionDefinitionService.php',
            'app/Services/Estate/ComprehensiveEstatePlanService.php',
            'app/Services/UserProfile/ProfileCompletenessChecker.php',
        ];

        foreach ($files as $file) {
            $code = array_filter(
                explode("\n", file_get_contents(base_path($file))),
                fn (string $line): bool => ! str_starts_with(ltrim($line), '//')
                    && ! str_starts_with(ltrim($line), '*')
            );

            $offenders = preg_grep('/FamilyMember::where\(\'user_id\'/', $code);

            expect($offenders)->toBe([], "{$file} still asks the question for itself");
        }
    });
});
