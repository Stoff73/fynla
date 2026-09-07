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
        expect($this->resolver->forDateOfBirth('1954-01-01'))->toBe(66);
        expect($this->resolver->forDateOfBirth('1958-06-30'))->toBe(66);
    });

    it('gives the 2026-2028 cohort 67', function () {
        expect($this->resolver->forDateOfBirth('1960-04-06'))->toBe(67);
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
