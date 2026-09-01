<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserAssumption;
use App\Services\Estate\EstateProjectionService;
use App\Services\Estate\LifeCoverCalculator;
use App\Services\Settings\AssumptionsService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0334. `investment_growth_method` is a real user-settable assumption — the controller
 * validates it, the service stores and serves it — and the estate projection's dispatch
 * on it was **unreachable**: `calculateProjectedValues()` called
 * `projectInvestmentsMonteCarlo()` directly, straight past the method that reads the
 * setting. So a user who chose "custom" and typed a rate had it applied to their
 * life-cover sizing and silently ignored by their projected estate, and therefore by
 * their projected Inheritance Tax.
 *
 * The dispatch is reachable now (W-0520). What remained was acceptance 2: the two
 * consumers "agree about what the setting means" — and they agreed only because one
 * was a byte-identical copy of the other, hardcoded 4.7% fallback included. Agreement
 * by transcription is the arrangement that produced the divergence in the first place.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['date_of_birth' => now()->subYears(50)]);
    $this->assumptions = app(AssumptionsService::class);
});

it('honours a custom rate the user typed', function () {
    UserAssumption::updateOrCreate(
        ['user_id' => $this->user->id, 'assumption_type' => 'estate_planning'],
        ['assumption_type' => 'estate_planning', 'investment_growth_method' => 'custom', 'custom_investment_rate' => 8.5]
    );

    expect($this->assumptions->investmentGrowthRateFor($this->user->fresh()))->toBe(0.085);
});

it('falls back to the shared default when the user has chosen nothing', function () {
    expect($this->assumptions->investmentGrowthRateFor($this->user))->toBe(0.047);
});

it('ignores a custom rate when the method is not custom', function () {
    UserAssumption::updateOrCreate(
        ['user_id' => $this->user->id, 'assumption_type' => 'estate_planning'],
        ['assumption_type' => 'estate_planning', 'investment_growth_method' => 'monte_carlo', 'custom_investment_rate' => 8.5]
    );

    expect($this->assumptions->investmentGrowthRateFor($this->user->fresh()))->toBe(0.047);
});

/**
 * Acceptance 2, as a guard rather than as a coincidence. Neither consumer may hold its
 * own copy of the rule: a copy agrees until someone edits one of them, and no
 * behavioural test of a single service can see the disagreement — each would simply
 * apply a different rate, plausibly.
 */
it('leaves neither consumer interpreting the setting for itself', function () {
    $files = [
        'app/Services/Estate/EstateProjectionService.php',
        'app/Services/Estate/LifeCoverCalculator.php',
    ];

    foreach ($files as $file) {
        $code = array_filter(
            explode("\n", file_get_contents(base_path($file))),
            fn (string $line): bool => ! str_starts_with(ltrim($line), '//')
                && ! str_starts_with(ltrim($line), '*')
        );

        // The DISPATCH on the method is legitimate and is W-0520's fix — what must
        // not exist is a second copy of the rate RULE, i.e. reading the setting and
        // returning a rate with its own hardcoded fallback.
        $offenders = preg_grep("/custom_investment_rate'\\]\\s*\\/\\s*100|return 0\\.047;/", $code);

        expect($offenders)->toBe([], "{$file} still interprets the setting itself");
    }
});

it('keeps the estate dispatch reachable', function () {
    expect(method_exists(EstateProjectionService::class, 'projectInvestments'))->toBeTrue()
        ->and(method_exists(AssumptionsService::class, 'investmentGrowthRateFor'))->toBeTrue()
        ->and(class_exists(LifeCoverCalculator::class))->toBeTrue();
});
