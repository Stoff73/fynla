<?php

declare(strict_types=1);

use App\Models\RetirementProfile;
use App\Models\User;
use App\Services\Estate\FutureValueCalculator;
use Database\Seeders\ActuarialLifeTablesSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0198. Two columns held one fact — "how long does this person expect to live" —
 * and four call sites combined them differently: retirement and decumulation read
 * `override ?? retirement_profiles.life_expectancy ?? 85`, the estate agent read
 * `override ?? 85` and could not see the profile figure at all, and the estate's own
 * calculator read `override ?? the actuarial tables`.
 *
 * So a household that filled in the retirement module but never touched the override
 * was answered by their own number in retirement and by 85, or by the tables, in the
 * estate. The horizon scales the estate, the tax on it, life-cover sizing and every
 * decumulation plan — two modules answering "when do I die" differently is not a
 * rounding difference.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(ActuarialLifeTablesSeeder::class);
    $this->calculator = app(FutureValueCalculator::class);
});

describe('one answer, with a stated precedence', function () {
    it('lets the override the user typed beat everything', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
            'life_expectancy_override' => 92,
        ]);
        RetirementProfile::factory()->create([
            'user_id' => $user->id,
            'life_expectancy' => 88,
        ]);

        $result = $this->calculator->getLifeExpectancy($user->fresh());

        expect($result['death_age'])->toBe(92)
            ->and($result['source'])->toBe('user_override');
    });

    /**
     * The gap this item exists to close: this figure IS the user's own statement, and
     * the estate could not see it.
     */
    it('uses the retirement profile figure when there is no override', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
            'life_expectancy_override' => null,
        ]);
        RetirementProfile::factory()->create([
            'user_id' => $user->id,
            'life_expectancy' => 88,
        ]);

        $result = $this->calculator->getLifeExpectancy($user->fresh());

        expect($result['death_age'])->toBe(88)
            ->and($result['source'])->toBe('retirement_profile');
    });

    it('falls to the actuarial tables only when the user has stated nothing', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
            'life_expectancy_override' => null,
            'gender' => 'female',
        ]);

        $result = $this->calculator->getLifeExpectancy($user);

        expect($result['source'] ?? null)->toBeNull()
            ->and($result['death_age'])->toBeGreaterThan(50);
    });
});

/**
 * Acceptance 4. The column was captured, prompted for, and read by nobody as a number.
 */
describe('the spouse figure is wired up, not left unread', function () {
    it('reads the spouse life expectancy the user entered', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
            'marital_status' => 'married',
        ]);
        RetirementProfile::factory()->create([
            'user_id' => $user->id,
            'spouse_life_expectancy' => 90,
        ]);

        expect($this->calculator->getSpouseLifeExpectancy($user->fresh()))->toBe(90);
    });

    it('resolves a linked spouse as themselves rather than by proxy', function () {
        $spouse = User::factory()->create([
            'date_of_birth' => now()->subYears(48),
            'life_expectancy_override' => 95,
        ]);
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
            'marital_status' => 'married',
            'spouse_id' => $spouse->id,
        ]);
        RetirementProfile::factory()->create([
            'user_id' => $user->id,
            'spouse_life_expectancy' => 80,
        ]);

        expect($this->calculator->getSpouseLifeExpectancy($user->fresh()))->toBe(95);
    });

    /**
     * The presence of an optional field is not the same question as whether there is a
     * spouse. Both call sites conflated them, so a married user who left the field
     * blank was compared against a single-life annuity.
     */
    it('knows a married user has a spouse even with the field blank', function () {
        $user = User::factory()->create(['marital_status' => 'married']);
        RetirementProfile::factory()->create([
            'user_id' => $user->id,
            'spouse_life_expectancy' => null,
        ]);

        expect($this->calculator->hasSpouse($user->fresh()))->toBeTrue();
    });

    it('counts a civil partnership, not only a marriage (W-0508)', function () {
        $user = User::factory()->create(['marital_status' => 'civil_partnership']);

        expect($this->calculator->hasSpouse($user))->toBeTrue();
    });

    it('does not invent a spouse for a single user', function () {
        $user = User::factory()->create(['marital_status' => 'single', 'spouse_id' => null]);

        expect($this->calculator->hasSpouse($user))->toBeFalse();
    });
});

/**
 * The guard. Acceptance 2 is about agreement between modules, and no single-service
 * test can see a disagreement — which is why four different combinations survived.
 */
describe('no consumer combines the two columns for itself', function () {
    it('leaves no ad-hoc combination in any consumer', function () {
        $files = [
            'app/Agents/RetirementAgent.php',
            'app/Agents/EstateAgent.php',
            'app/Http/Controllers/Api/Retirement/DecumulationController.php',
        ];

        foreach ($files as $file) {
            // Code lines only — the comments at these sites quote the old expressions
            // deliberately, so that a reader arriving at the line learns what was wrong
            // rather than only what is there now.
            $code = array_filter(
                explode("\n", file_get_contents(base_path($file))),
                fn (string $line): bool => ! str_starts_with(ltrim($line), '//')
                    && ! str_starts_with(ltrim($line), '*')
                    && ! str_starts_with(ltrim($line), '/*')
            );

            $offenders = preg_grep(
                '/life_expectancy_override\s*\?\?|spouse_life_expectancy\s*!==\s*null/',
                $code
            );

            expect($offenders)->toBe([], "{$file} still combines the two columns itself");
        }
    });
});
