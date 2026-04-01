<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
use App\Models\User;
use App\Services\AI\KycGateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->checker = app(KycGateChecker::class);
});

describe('KycGateChecker', function () {
    describe('bypass types', function () {
        it('always passes for data_entry regardless of missing data', function () {
            $user = User::factory()->create([
                'date_of_birth' => null,
                'marital_status' => null,
                'employment_status' => null,
            ]);

            $result = $this->checker->check($user, [
                'primary' => QuerySchemas::DATA_ENTRY,
                'related' => [],
                'modules' => [],
            ]);

            expect($result['passed'])->toBeTrue();
            expect($result['missing'])->toBe([]);
            expect($result['prompt_text'])->toBe('');
        });

        it('always passes for navigation regardless of missing data', function () {
            $user = User::factory()->create([
                'date_of_birth' => null,
            ]);

            $result = $this->checker->check($user, [
                'primary' => QuerySchemas::NAVIGATION,
                'related' => [],
                'modules' => [],
            ]);

            expect($result['passed'])->toBeTrue();
        });

        it('always passes for general queries', function () {
            $user = User::factory()->create([
                'date_of_birth' => null,
            ]);

            $result = $this->checker->check($user, [
                'primary' => QuerySchemas::GENERAL,
                'related' => [],
                'modules' => [],
            ]);

            expect($result['passed'])->toBeTrue();
        });
    });

    describe('universal requirements', function () {
        it('blocks when date of birth is missing', function () {
            $user = User::factory()->create([
                'date_of_birth' => null,
                'marital_status' => 'single',
                'employment_status' => 'employed',
                'annual_employment_income' => 50000,
                'monthly_expenditure' => 2000,
            ]);

            $result = $this->checker->check($user, [
                'primary' => QuerySchemas::SAVINGS_EMERGENCY,
                'related' => [QuerySchemas::AFFORDABILITY],
                'modules' => ['savings'],
            ]);

            expect($result['passed'])->toBeFalse();
            expect($result['missing'])->toContain('Date of birth');
        });

        it('blocks when income is missing', function () {
            $user = User::factory()->create([
                'date_of_birth' => '1990-01-15',
                'marital_status' => 'single',
                'employment_status' => 'employed',
                'annual_employment_income' => 0,
                'annual_self_employment_income' => 0,
                'annual_rental_income' => 0,
                'annual_dividend_income' => 0,
                'annual_interest_income' => 0,
                'annual_other_income' => 0,
                'annual_trust_income' => 0,
                'monthly_expenditure' => 2000,
            ]);

            $result = $this->checker->check($user, [
                'primary' => QuerySchemas::RETIREMENT_CONTRIBUTION,
                'related' => [],
                'modules' => ['retirement'],
            ]);

            expect($result['passed'])->toBeFalse();
            expect($result['missing'])->toContain('Annual income (at least one income source)');
        });

        it('blocks when expenditure is missing', function () {
            $user = User::factory()->create([
                'date_of_birth' => '1990-01-15',
                'marital_status' => 'single',
                'employment_status' => 'employed',
                'annual_employment_income' => 50000,
                'monthly_expenditure' => null,
                'annual_expenditure' => null,
            ]);

            $result = $this->checker->check($user, [
                'primary' => QuerySchemas::RETIREMENT_CONTRIBUTION,
                'related' => [],
                'modules' => ['retirement'],
            ]);

            expect($result['passed'])->toBeFalse();
            expect($result['missing'])->toContain('Monthly expenditure');
        });
    });

    describe('module-specific requirements', function () {
        it('passes when all data present for savings query', function () {
            $user = User::factory()->create([
                'date_of_birth' => '1990-01-15',
                'marital_status' => 'single',
                'employment_status' => 'employed',
                'annual_employment_income' => 50000,
                'monthly_expenditure' => 2000,
            ]);

            $result = $this->checker->check($user, [
                'primary' => QuerySchemas::SAVINGS_EMERGENCY,
                'related' => [QuerySchemas::AFFORDABILITY],
                'modules' => ['savings'],
            ]);

            expect($result['passed'])->toBeTrue();
            expect($result['prompt_text'])->toContain('KYC CHECK: PASSED');
        });
    });

    describe('prompt text', function () {
        it('includes BLOCKED instruction when KYC fails', function () {
            $user = User::factory()->create([
                'date_of_birth' => null,
                'marital_status' => null,
            ]);

            $result = $this->checker->check($user, [
                'primary' => QuerySchemas::RETIREMENT_CONTRIBUTION,
                'related' => [],
                'modules' => ['retirement'],
            ]);

            expect($result['prompt_text'])->toContain('KYC CHECK: BLOCKED');
            expect($result['prompt_text'])->toContain('Do NOT give advice');
        });

        it('includes PASSED status when KYC passes', function () {
            $user = User::factory()->create([
                'date_of_birth' => '1990-01-15',
                'marital_status' => 'single',
                'employment_status' => 'employed',
                'annual_employment_income' => 50000,
                'monthly_expenditure' => 2000,
            ]);

            $result = $this->checker->check($user, [
                'primary' => QuerySchemas::SAVINGS_EMERGENCY,
                'related' => [],
                'modules' => ['savings'],
            ]);

            expect($result['prompt_text'])->toContain('KYC CHECK: PASSED');
            expect($result['prompt_text'])->toContain('savings');
        });
    });
});
