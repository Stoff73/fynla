<?php

declare(strict_types=1);

use App\Models\StatePension;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Retirement\StatePensionAgeResolver;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * W-0197. The application held two static keys — `current_spa` (66) and `future_spa`
 * (67) — and both were correct facts about different cohorts. Four services read the
 * first and one read the second, so a visitor could be given one State Pension age by
 * the marketing estimate and a different one by the retirement module after they
 * registered, for the same person.
 *
 * Choosing between them could never have been right: State Pension age is legislated by
 * birth cohort, so a scalar gives a 26-year-old and a 46-year-old the same answer. On a
 * projection running to a second death decades away it is only ever less wrong.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->resolver = app(StatePensionAgeResolver::class);
});

describe('State Pension age comes from the statutory schedule', function () {
    it('gives someone born before the first rise the age already in force', function () {
        // Table 2: born 6 Dec 1953 to 5 Jan 1954, reached on 6 March 2019 (65 and 2 months).
        expect($this->resolver->forDateOfBirth('1954-01-01'))->toBe(65);
        expect($this->resolver->forDateOfBirth('1958-06-30'))->toBe(66);
    });

    it('gives the 2026-2028 cohort 67, after the monthly steps of table 3', function () {
        expect($this->resolver->forDateOfBirth('1961-03-06'))->toBe(67);
        expect($this->resolver->forDateOfBirth('1970-01-01'))->toBe(67);
        expect($this->resolver->forDateOfBirth('1977-04-05'))->toBe(67);
    });

    it('gives the 2044-2046 cohort 68', function () {
        expect($this->resolver->forDateOfBirth('1978-04-06'))->toBe(68);
        expect($this->resolver->forDateOfBirth('1995-01-01'))->toBe(68);
    });

    /**
     * Acceptance 5. This is the whole point: one scalar could not do it.
     */
    it('gives two people in one household different answers', function () {
        $older = $this->resolver->forDateOfBirth('1958-03-01');
        $younger = $this->resolver->forDateOfBirth('1985-03-01');

        expect($older)->toBe(66)
            ->and($younger)->toBe(68)
            ->and($older)->not->toBe($younger);
    });
});

/**
 * CSJ 2026-10-03: "this is empirical we must have the months". Pensions Act 1995
 * Sch 4 para 1 (https://www.legislation.gov.uk/ukpga/1995/26/schedule/4).
 */
describe('State Pension age to the month', function () {
    it('steps up a month at a time for those born 6 April 1960 to 5 March 1961 (table 3)', function () {
        expect($this->resolver->labelForDateOfBirth('1960-04-06'))->toBe('66 years and 1 month')
            ->and($this->resolver->dateForDateOfBirth('1960-04-06')->toDateString())->toBe('2026-05-06')
            ->and($this->resolver->labelForDateOfBirth('1960-08-01'))->toBe('66 years and 4 months')
            ->and($this->resolver->dateForDateOfBirth('1960-08-01')->toDateString())->toBe('2026-12-01')
            ->and($this->resolver->labelForDateOfBirth('1961-03-05'))->toBe('66 years and 11 months')
            ->and($this->resolver->labelForDateOfBirth('1961-03-06'))->toBe('67');
    });

    it('gives the day for those born 6 April 1977 to 5 April 1978 (table 4)', function () {
        expect($this->resolver->dateForDateOfBirth('1977-04-20')->toDateString())->toBe('2044-05-06')
            ->and($this->resolver->dateForDateOfBirth('1978-04-05')->toDateString())->toBe('2046-03-06')
            ->and($this->resolver->dateForDateOfBirth('1978-04-06')->toDateString())->toBe('2046-04-06');
    });

    it('gives men 65 and women 60 before the equalisation, then table 1 for women', function () {
        expect($this->resolver->dateForDateOfBirth('1950-01-10', 'male')->toDateString())->toBe('2015-01-10')
            ->and($this->resolver->dateForDateOfBirth('1950-01-10', 'female')->toDateString())->toBe('2010-01-10')
            ->and($this->resolver->dateForDateOfBirth('1952-06-20', 'female')->toDateString())->toBe('2014-09-06');
    });

    it('pays State Pension for the part of the year after the day it is reached', function () {
        Carbon\Carbon::setTestNow('2026-10-01');
        // Born 1 August 1960: reached 1 December 2026, four months into the year at 66.
        $user = User::factory()->create(['date_of_birth' => '1960-08-01']);

        expect($this->resolver->fractionPaidAtAge($user, 65))->toBe(0.0)
            ->and($this->resolver->fractionPaidAtAge($user, 66))->toEqualWithDelta(243 / 365, 0.001)
            ->and($this->resolver->fractionPaidAtAge($user, 67))->toBe(1.0)
            ->and($this->resolver->isBeforeStatePensionAge($user, 66))->toBeTrue()
            ->and($this->resolver->isBeforeStatePensionAge($user, 67))->toBeFalse();
        Carbon\Carbon::setTestNow();
    });
});

describe('a recorded forecast wins over anything derived', function () {
    /**
     * Acceptance 4. The user may hold a forecast we cannot reproduce; overriding it
     * with our own arithmetic would be telling them their own statement is wrong.
     */
    it('uses the age the user recorded rather than their cohort', function () {
        $user = User::factory()->create(['date_of_birth' => '1985-03-01']);
        StatePension::factory()->create([
            'user_id' => $user->id,
            'state_pension_age' => 66,
        ]);

        expect($this->resolver->forUser($user->fresh()))->toBe(66);
    });

    it('falls to the cohort when the user has recorded nothing', function () {
        $user = User::factory()->create(['date_of_birth' => '1985-03-01']);

        expect($this->resolver->forUser($user))->toBe(68);
    });

    it('takes the age already in force when the date of birth is unknown', function () {
        $user = User::factory()->create(['date_of_birth' => null]);

        expect($this->resolver->forUser($user))->toBe(66);
    });
});

/**
 * The guard is the item. Retiring the two keys is what stops a sixth reader appearing
 * on a scalar, and no behavioural test would catch that — the value would simply be
 * wrong for one cohort, silently.
 */
describe('the two scalars are retired, not left beside the schedule', function () {
    it('no longer seeds current_spa or future_spa', function () {
        $pension = app(TaxConfigService::class)->getPensionAllowances();

        expect($pension['state_pension'])->not->toHaveKey('current_spa')
            ->and($pension['state_pension'])->not->toHaveKey('future_spa')
            ->and($pension['state_pension'])->toHaveKey('age_schedule');
    });

    it('leaves no service reading a retired key', function () {
        $files = [
            'app/Services/Retirement/RetirementIncomeService.php',
            'app/Services/Settings/AssumptionsService.php',
            'app/Http/Controllers/Api/Investment/AssetLocationController.php',
            'app/Services/Marketing/PensionEstimateService.php',
            'app/Services/Estate/HouseholdCashFlowProjector.php',
        ];

        foreach ($files as $file) {
            $source = file_get_contents(base_path($file));
            $reads = preg_grep(
                "/taxConfig->get\('pension\.state_pension\.(current|future)_spa|state_pension'\]\['(current|future)_spa/",
                explode("\n", $source)
            );

            expect($reads)->toBe([], "{$file} still reads a retired State Pension age key");
        }
    });

    it('refuses to guess when the schedule is missing rather than falling back to a scalar', function () {
        TaxConfiguration::query()->update(['is_active' => false]);
        Cache::flush();

        expect(fn () => app(StatePensionAgeResolver::class)->forDateOfBirth('1985-01-01'))
            ->toThrow(RuntimeException::class);
    });
});
