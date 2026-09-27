<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\TaxConfigService;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Set a fixed "now" time for consistent testing
    Carbon::setTestNow(Carbon::create(2025, 10, 27));
    $this->seed(TaxConfigurationSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow(); // Reset
});

describe('User Domicile Calculation', function () {
    it('calculates years UK resident correctly for user who arrived 10 years ago', function () {
        $user = User::factory()->create([
            'uk_arrival_date' => Carbon::now()->subYears(10)->toDateString(),
        ]);

        expect($user->calculateYearsUKResident())->toBe(10);
    });

    it('calculates years UK resident correctly for user who arrived 15 years ago', function () {
        $user = User::factory()->create([
            'uk_arrival_date' => Carbon::now()->subYears(15)->toDateString(),
        ]);

        expect($user->calculateYearsUKResident())->toBe(15);
    });

    it('calculates years UK resident correctly for user who arrived 20 years ago', function () {
        $user = User::factory()->create([
            'uk_arrival_date' => Carbon::now()->subYears(20)->toDateString(),
        ]);

        expect($user->calculateYearsUKResident())->toBe(20);
    });

    it('returns null for years UK resident when no arrival date is set', function () {
        $user = User::factory()->create([
            'uk_arrival_date' => null,
        ]);

        expect($user->calculateYearsUKResident())->toBeNull();
    });

    it('handles partial years correctly (rounds down to complete years)', function () {
        $user = User::factory()->create([
            'uk_arrival_date' => Carbon::now()->subYears(10)->subMonths(6)->toDateString(),
        ]);

        expect($user->calculateYearsUKResident())->toBe(10);
    });
});

/*
 * Long-term UK residence (IHTA 1984 s6A): UK resident in at least 10 of the 20
 * tax years before the current one. https://www.gov.uk/hmrc-internal-manuals/inheritance-tax-manual/ihtm47020
 * "Now" is 27 October 2025, in tax year 2025/26 (from 6 April 2025).
 */
describe('Long-term UK residence (IHTA 1984 s6A)', function () {
    $arrived = fn (string $date) => User::factory()->create(['domicile_status' => 'non_uk_domiciled', 'country_of_birth' => 'Australia', 'uk_arrival_date' => $date]);

    it('is long-term resident after ten whole tax years before this one', function () use ($arrived) {
        // Arrived in 2015/16; 2015/16..2024/25 is ten tax years.
        $info = $arrived('2015-06-01')->getDomicileInfo();

        expect($info['is_long_term_uk_resident'])->toBeTrue()
            ->and($info['years_resident_in_lookback'])->toBe(10)
            ->and($info['long_term_resident_from'])->toBe('2025-04-06');
    });

    it('is not yet long-term resident on nine, and says from when it will be', function () use ($arrived) {
        // Arrived in 2016/17: nine tax years before 2025/26.
        $info = $arrived('2016-06-01')->getDomicileInfo();

        expect($info['is_long_term_uk_resident'])->toBeFalse()
            ->and($info['years_resident_in_lookback'])->toBe(9)
            ->and($info['long_term_resident_from'])->toBe('2026-04-06')
            ->and($info['explanation'])->toContain('6 April 2026')
            ->and($info['explanation'])->not->toContain('domicil');
    });

    it('counts the tax year of arrival from 6 April, not the calendar year', function () use ($arrived) {
        // 5 April 2016 is in 2015/16; 6 April 2016 starts 2016/17.
        expect($arrived('2016-04-05')->getDomicileInfo()['years_resident_in_lookback'])->toBe(10)
            ->and($arrived('2016-04-06')->getDomicileInfo()['years_resident_in_lookback'])->toBe(9);
    });

    it('is no longer the old 15-year deemed-domicile rule', function () use ($arrived) {
        // Twelve tax years: long-term resident now; the pre-2025 rule said no.
        expect($arrived('2013-10-27')->getDomicileInfo()['is_long_term_uk_resident'])->toBeTrue();
    });

    it('reads the numbers from tax config', function () use ($arrived) {
        $config = app(TaxConfigService::class)->getDomicile()['long_term_residence'];

        expect($config['qualifying_years'])->toBe(10)
            ->and($config['lookback_years'])->toBe(20)
            ->and($arrived('2015-06-01')->getDomicileInfo()['qualifying_years'])->toBe($config['qualifying_years']);
    });

    it('counts a UK-domiciled user from birth when no arrival date is recorded', function () {
        $adult = User::factory()->create(['domicile_status' => 'uk_domiciled', 'country_of_birth' => 'United Kingdom', 'uk_arrival_date' => null, 'date_of_birth' => '1980-01-01']);
        $child = User::factory()->create(['domicile_status' => 'uk_domiciled', 'country_of_birth' => 'United Kingdom', 'uk_arrival_date' => null, 'date_of_birth' => '2019-01-01']);

        expect($adult->getDomicileInfo()['is_long_term_uk_resident'])->toBeTrue()
            ->and($child->getDomicileInfo()['is_long_term_uk_resident'])->toBeFalse();
    });

    it('says it cannot tell when there is no arrival date and no UK domicile', function () {
        $info = User::factory()->create(['domicile_status' => 'non_uk_domiciled', 'country_of_birth' => 'France', 'uk_arrival_date' => null])->getDomicileInfo();

        expect($info['is_long_term_uk_resident'])->toBeNull()
            ->and($info['explanation'])->toContain('when you came to live in the UK');
    });
});
