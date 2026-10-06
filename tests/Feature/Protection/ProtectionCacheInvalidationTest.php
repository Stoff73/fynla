<?php

declare(strict_types=1);

use App\Agents\ProtectionAgent;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Cache\CacheInvalidationService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Seed tax configuration - required for ProtectionAgent
    $this->seed(TaxConfigurationSeeder::class);
    // Clear all cache before each test
    Cache::flush();
});

describe('Protection Cache Invalidation', function () {
    it('invalidates protection analysis cache when user income is updated', function () {
        // Create a user with income
        $user = User::factory()->create([
            'annual_employment_income' => 50000,
            'date_of_birth' => now()->subYears(35),
        ]);

        // Create protection profile
        ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'retirement_age' => 67,
        ]);

        // First analysis - should calculate and cache
        $agent = app(ProtectionAgent::class);
        $firstAnalysis = $agent->analyze($user->id);

        // The income protection need moves with income (item 8b: the life need's
        // income part comes from spending, not income).
        expect($firstAnalysis['data']['needs']['income_protection_need'])->toEqual(30000.0);
        $firstNeed = $firstAnalysis['data']['needs']['income_protection_need'];

        // Update user income via API
        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/income-occupation', [
                'annual_employment_income' => 75000, // Increased income
                'annual_self_employment_income' => 0,
                'annual_rental_income' => 0,
                'annual_dividend_income' => 0,
                'annual_other_income' => 0,
                'occupation' => 'Software Engineer',
            ]);

        $response->assertStatus(200);

        // Second analysis - should recalculate with new income (NOT use cached value)
        $secondAnalysis = $agent->analyze($user->id);
        // 60% of the first £60,000 and 50% above: £36,000 + £7,500.
        expect($secondAnalysis['data']['needs']['income_protection_need'])->toEqual(43500.0)
            ->and($secondAnalysis['data']['needs']['income_protection_need'])->toBeGreaterThan($firstNeed);
    });

    it('caches protection analysis results', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 50000,
            'date_of_birth' => now()->subYears(35),
        ]);

        ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'retirement_age' => 67,
        ]);

        // Analyze to cache the result
        $agent = app(ProtectionAgent::class);
        $agent->analyze($user->id);

        // Verify cache exists using key-based caching
        $cachedValue = Cache::get('protection_analysis_'.$user->id);

        expect($cachedValue)->not->toBeNull();
    });

    it('invalidates cache using CacheInvalidationService', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 50000,
            'date_of_birth' => now()->subYears(35),
        ]);

        ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'retirement_age' => 67,
        ]);

        // Cache the analysis
        $agent = app(ProtectionAgent::class);
        $agent->analyze($user->id);

        // Verify cache exists
        $cachedBefore = Cache::get('protection_analysis_'.$user->id);
        expect($cachedBefore)->not->toBeNull();

        // Invalidate using the centralised service
        $service = app(CacheInvalidationService::class);
        $service->invalidateForUser($user->id);

        // Verify cache was cleared
        $cachedAfter = Cache::get('protection_analysis_'.$user->id);
        expect($cachedAfter)->toBeNull();
    });

    it('invalidates spouse protection cache when user income changes', function () {
        // Create married couple
        $user = User::factory()->create([
            'annual_employment_income' => 50000,
            'date_of_birth' => now()->subYears(35),
            'marital_status' => 'married',
        ]);

        $spouse = User::factory()->create([
            'annual_employment_income' => 40000,
            'date_of_birth' => now()->subYears(33),
            'marital_status' => 'married',
        ]);

        // Link them as spouses
        $user->spouse_id = $spouse->id;
        $user->save();
        $spouse->spouse_id = $user->id;
        $spouse->save();

        // Create protection profiles for both
        ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'retirement_age' => 67,
        ]);

        ProtectionProfile::factory()->create([
            'user_id' => $spouse->id,
            'annual_income' => 40000,
            'retirement_age' => 67,
        ]);

        // Cache both analyses
        $agent = app(ProtectionAgent::class);
        $agent->analyze($user->id);
        $agent->analyze($spouse->id);

        // Update user income via API
        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/user/profile/income-occupation', [
                'annual_employment_income' => 75000,
                'annual_self_employment_income' => 0,
                'annual_rental_income' => 0,
                'annual_dividend_income' => 0,
                'annual_other_income' => 0,
                'occupation' => 'Software Engineer',
            ]);

        $response->assertStatus(200);

        // Verify both user AND spouse caches were cleared
        $userCache = Cache::get('protection_analysis_'.$user->id);
        $spouseCache = Cache::get('protection_analysis_'.$spouse->id);

        expect($userCache)->toBeNull();
        expect($spouseCache)->toBeNull();
    });
});
