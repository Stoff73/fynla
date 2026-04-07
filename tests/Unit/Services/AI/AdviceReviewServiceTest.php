<?php

declare(strict_types=1);

use App\Models\AiAdviceLog;
use App\Models\User;
use App\Services\AI\AdviceReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->service = app(AdviceReviewService::class);
});

describe('AdviceReviewService', function () {
    describe('data change detection', function () {
        it('returns no changes when no advice log exists', function () {
            $user = User::factory()->create();
            $result = $this->service->checkForChanges($user);

            expect($result['changes'])->toBe([]);
            expect($result['reviews_due'])->toBe([]);
        });

        it('flags income change since last advice', function () {
            $user = User::factory()->create([
                'annual_employment_income' => 80000,
                'annual_self_employment_income' => 0,
                'monthly_expenditure' => 2000,
                'employment_status' => 'employed',
                'marital_status' => 'single',
            ]);

            AiAdviceLog::create([
                'user_id' => $user->id,
                'query_type' => 'retirement_contribution',
                'classification' => ['primary' => 'retirement_contribution', 'related' => [], 'modules' => ['retirement']],
                'user_data_snapshot' => [
                    'income' => 50000,
                    'expenditure' => 2000,
                    'employment_status' => 'employed',
                    'marital_status' => 'single',
                ],
            ]);

            $result = $this->service->checkForChanges($user);

            $incomeChange = collect($result['changes'])->firstWhere('field', 'income');
            expect($incomeChange)->not->toBeNull();
            expect($incomeChange['previous'])->toBe(50000.0);
            expect($incomeChange['current'])->toBe(80000.0);
        });

        it('flags employment status change', function () {
            $user = User::factory()->create([
                'annual_employment_income' => 50000,
                'annual_self_employment_income' => 0,
                'employment_status' => 'self_employed',
            ]);

            AiAdviceLog::create([
                'user_id' => $user->id,
                'query_type' => 'retirement_contribution',
                'user_data_snapshot' => [
                    'income' => 50000,
                    'expenditure' => 2000,
                    'employment_status' => 'employed',
                    'marital_status' => 'single',
                ],
            ]);

            $result = $this->service->checkForChanges($user);

            $employmentChange = collect($result['changes'])->firstWhere('field', 'employment_status');
            expect($employmentChange)->not->toBeNull();
            expect($employmentChange['previous'])->toBe('employed');
            expect($employmentChange['current'])->toBe('self_employed');
        });

        it('returns no changes when data is the same', function () {
            $user = User::factory()->create([
                'annual_employment_income' => 50000,
                'annual_self_employment_income' => 0,
                'monthly_expenditure' => 2000,
                'employment_status' => 'employed',
                'marital_status' => 'single',
            ]);

            AiAdviceLog::create([
                'user_id' => $user->id,
                'query_type' => 'savings_emergency',
                'user_data_snapshot' => [
                    'income' => 50000,
                    'expenditure' => 2000,
                    'employment_status' => 'employed',
                    'marital_status' => 'single',
                ],
            ]);

            $result = $this->service->checkForChanges($user);

            expect($result['changes'])->toBe([]);
        });
    });

    describe('annual review detection', function () {
        it('flags module overdue for review (>12 months)', function () {
            $user = User::factory()->create();

            $log = AiAdviceLog::create([
                'user_id' => $user->id,
                'query_type' => 'protection_cover',
                'classification' => ['primary' => 'protection_cover', 'related' => [], 'modules' => ['protection']],
                'user_data_snapshot' => ['income' => 50000],
            ]);
            // Force old timestamp via DB query (Eloquent update won't change created_at)
            \Illuminate\Support\Facades\DB::table('ai_advice_logs')
                ->where('id', $log->id)
                ->update(['created_at' => now()->subMonths(14)]);

            $result = $this->service->getModulesOverdueForReview($user);

            expect($result)->toHaveCount(1);
            expect($result[0]['module'])->toBe('protection');
            expect($result[0]['months_ago'])->toBe(14);
        });

        it('does not flag recent advice as overdue', function () {
            $user = User::factory()->create();

            AiAdviceLog::create([
                'user_id' => $user->id,
                'query_type' => 'retirement_contribution',
                'classification' => ['primary' => 'retirement_contribution', 'related' => [], 'modules' => ['retirement']],
                'user_data_snapshot' => ['income' => 50000],
                'created_at' => now()->subMonths(6),
                'updated_at' => now()->subMonths(6),
            ]);

            $result = $this->service->getModulesOverdueForReview($user);

            expect($result)->toBe([]);
        });
    });
});
