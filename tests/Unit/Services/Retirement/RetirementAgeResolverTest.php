<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Models\User;
use App\Services\Goals\GoalsProjectionService;
use App\Services\Retirement\PensionProjector;
use App\Services\Retirement\RetirementAgeResolver;
use App\Services\Settings\AssumptionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0196. Seven private `DEFAULT_RETIREMENT_AGE` constants held two different numbers
 * and four independent copies of the priority chain disagreed on order, so a household
 * with a target on the retirement profile and a different one on the user record got
 * different answers from different modules and nothing revealed it.
 *
 * The trap this cover is written against (`tests/CLAUDE.md` §4, Collision): on the live
 * database one persona's `users.target_retirement_age` and their pension's
 * `retirement_age` are the SAME number, so a test built on real persona data cannot
 * tell the correct source from the wrong one. Every case below uses three mutually
 * distinct values.
 */
beforeEach(function () {
    $this->resolver = app(RetirementAgeResolver::class);
});

describe('the priority chain has one order', function () {
    it('prefers the retirement profile over the user record and the pension', function () {
        $user = User::factory()->create(['target_retirement_age' => 58]);
        RetirementProfile::factory()->create([
            'user_id' => $user->id,
            'target_retirement_age' => 62,
        ]);
        DCPension::factory()->create(['user_id' => $user->id, 'retirement_age' => 55]);

        $result = $this->resolver->withSource($user->fresh());

        expect($result['age'])->toBe(62)
            ->and($result['source'])->toBe(RetirementAgeResolver::SOURCE_RETIREMENT_PROFILE);
    });

    it('falls to the user record when the retirement profile has no target', function () {
        $user = User::factory()->create(['target_retirement_age' => 58]);
        DCPension::factory()->create(['user_id' => $user->id, 'retirement_age' => 55]);

        $result = $this->resolver->withSource($user->fresh());

        expect($result['age'])->toBe(58)
            ->and($result['source'])->toBe(RetirementAgeResolver::SOURCE_USER_PROFILE);
    });

    it('falls to a pension when the user has stated nothing', function () {
        $user = User::factory()->create(['target_retirement_age' => null]);
        DCPension::factory()->create(['user_id' => $user->id, 'retirement_age' => 55]);

        $result = $this->resolver->withSource($user->fresh());

        expect($result['age'])->toBe(55)
            ->and($result['source'])->toBe(RetirementAgeResolver::SOURCE_PENSION);
    });

    it('says the age was assumed when no source holds one', function () {
        $user = User::factory()->create(['target_retirement_age' => null]);

        $result = $this->resolver->withSource($user);

        expect($result['age'])->toBe(67)
            ->and($result['source'])->toBe(RetirementAgeResolver::SOURCE_ASSUMED);
    });
});

/**
 * The guard is the item. Six services and a model each held their own copy of the
 * number, and two of them held a different one — which is invisible to any test that
 * exercises a single service. This asserts they are the SAME constant, so re-declaring
 * a literal anywhere turns it red.
 */
describe('one default, read by everything that needs it', function () {
    it('binds every consumer to the one home', function () {
        expect(PensionProjector::DEFAULT_RETIREMENT_AGE)
            ->toBe(RetirementAgeResolver::DEFAULT_RETIREMENT_AGE);
        expect(DBPension::DEFAULT_NORMAL_RETIREMENT_AGE)
            ->toBe(RetirementAgeResolver::DEFAULT_RETIREMENT_AGE);
    });

    it('leaves no service holding a private literal', function () {
        $files = [
            'app/Services/Settings/AssumptionsService.php',
            'app/Services/Goals/GoalsProjectionService.php',
            'app/Services/Retirement/RequiredCapitalCalculator.php',
            'app/Services/Retirement/RetirementProjectionService.php',
            'app/Services/Retirement/RetirementIncomeService.php',
            'app/Services/Retirement/PensionProjector.php',
        ];

        foreach ($files as $file) {
            expect(file_get_contents(base_path($file)))
                ->not->toMatch('/DEFAULT_RETIREMENT_AGE\s*=\s*\d+/');
        }
    });

    it('is 67, the value anchored to the pension projection (W-0036)', function () {
        expect(RetirementAgeResolver::DEFAULT_RETIREMENT_AGE)->toBe(67);
    });
});

/**
 * The private constants are unreachable from a test, so this reaches them through the
 * services that read them — proving the two that held 68 now answer 67.
 */
describe('the two outliers no longer answer 68', function () {
    it('resolves the goals projection to the shared default', function () {
        $user = User::factory()->create(['target_retirement_age' => null]);

        $method = new ReflectionMethod(GoalsProjectionService::class, 'getRetirementAge');
        $method->setAccessible(true);

        expect($method->invoke(app(GoalsProjectionService::class), $user))->toBe(67);
    });

    it('no longer holds its own number in the assumptions service', function () {
        $constant = new ReflectionClassConstant(AssumptionsService::class, 'DEFAULT_RETIREMENT_AGE');

        expect($constant->getValue())->toBe(67);
    });
});
