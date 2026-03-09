<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\Property;
use App\Models\User;
use App\Services\Coordination\RecommendationPersonaliser;
use App\Services\TaxConfigService;

function createPersonaliser(): RecommendationPersonaliser
{
    $taxConfig = Mockery::mock(TaxConfigService::class);
    $taxConfig->shouldReceive('getInheritanceTax')->andReturn([
        'nil_rate_band' => 325000,
        'residence_nil_rate_band' => 175000,
        'standard_rate' => 0.40,
        'annual_exemption' => 3000,
        'rnrb_taper_threshold' => 2000000,
    ]);
    $taxConfig->shouldReceive('getGiftingExemptions')->andReturn([
        'annual_exemption' => 3000,
    ]);
    $taxConfig->shouldReceive('getISAAllowances')->andReturn([
        'annual_allowance' => 20000,
    ]);

    return new RecommendationPersonaliser($taxConfig);
}

afterEach(function () {
    Mockery::close();
});

describe('Protection personalisation', function () {
    it('includes family context for life cover recommendations', function () {
        $personaliser = createPersonaliser();

        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(35),
            'annual_employment_income' => 60000,
        ]);

        FamilyMember::factory()->child()->create([
            'user_id' => $user->id,
            'first_name' => 'Sophie',
            'date_of_birth' => now()->subYears(5),
        ]);

        FamilyMember::factory()->child()->create([
            'user_id' => $user->id,
            'first_name' => 'James',
            'date_of_birth' => now()->subYears(10),
        ]);

        $recommendation = [
            'priority' => 1,
            'category' => 'Life Insurance',
            'action' => 'Increase life insurance coverage',
            'rationale' => 'Coverage falls short by £200,000.',
            'impact' => 'High',
            'estimated_cost' => 50.00,
        ];

        $result = $personaliser->personaliseRecommendation($recommendation, $user);

        expect($result)->toHaveKey('personalised_context')
            ->and($result['personalised_context'])->toBeArray()
            ->and($result['personalised_context'])->not->toBeEmpty();

        // Should mention children
        $contextText = implode(' ', $result['personalised_context']);
        expect($contextText)->toContain('children')
            ->and($contextText)->toContain('aged');
    });

    it('includes partner income context when spouse exists', function () {
        $personaliser = createPersonaliser();

        $spouse = User::factory()->create([
            'annual_employment_income' => 45000,
            'marital_status' => 'married',
        ]);

        $user = User::factory()->create([
            'annual_employment_income' => 60000,
            'marital_status' => 'married',
            'spouse_id' => $spouse->id,
        ]);

        $spouse->update(['spouse_id' => $user->id]);

        $recommendation = [
            'priority' => 1,
            'category' => 'Life Insurance',
            'action' => 'Increase life insurance coverage',
            'rationale' => 'Coverage gap detected.',
            'impact' => 'High',
            'estimated_cost' => 50.00,
        ];

        $result = $personaliser->personaliseRecommendation($recommendation, $user);

        expect($result)->toHaveKey('personalised_context');
        $contextText = implode(' ', $result['personalised_context']);
        expect($contextText)->toContain('partner');
    });

    it('includes self-employment context for income protection', function () {
        $personaliser = createPersonaliser();

        $user = User::factory()->create([
            'annual_self_employment_income' => 80000,
            'annual_employment_income' => 0,
        ]);

        $recommendation = [
            'priority' => 1,
            'category' => 'Income Protection',
            'action' => 'Add income protection insurance',
            'rationale' => 'No income protection in place.',
            'impact' => 'High',
            'estimated_cost' => 120.00,
        ];

        $result = $personaliser->personaliseRecommendation($recommendation, $user);

        expect($result)->toHaveKey('personalised_context');
        $contextText = implode(' ', $result['personalised_context']);
        expect($contextText)->toContain('self-employed');
    });
});

describe('Estate personalisation', function () {
    it('includes specific estate value for IHT recommendations', function () {
        $personaliser = createPersonaliser();

        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(60),
            'marital_status' => 'single',
        ]);

        $recommendation = [
            'category' => 'liquidity',
            'title' => 'Liquidity Risk Identified',
            'description' => 'Your liquid assets may not cover the IHT liability.',
            'net_estate' => 800000,
            'module' => 'estate',
        ];

        $result = $personaliser->personaliseRecommendation($recommendation, $user);

        expect($result)->toHaveKey('personalised_context');
        $contextText = implode(' ', $result['personalised_context']);
        expect($contextText)->toContain('nil-rate band');
    });

    it('mentions RNRB for parent with main residence', function () {
        $personaliser = createPersonaliser();

        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(65),
            'marital_status' => 'single',
        ]);

        FamilyMember::factory()->child()->create([
            'user_id' => $user->id,
            'date_of_birth' => now()->subYears(30),
        ]);

        Property::factory()->create([
            'user_id' => $user->id,
            'property_type' => 'main_residence',
            'current_value' => 500000,
        ]);

        $recommendation = [
            'category' => 'planning',
            'title' => 'Inheritance Tax Mitigation',
            'description' => 'Your estate exceeds IHT thresholds.',
            'net_estate' => 800000,
            'module' => 'estate',
        ];

        $result = $personaliser->personaliseRecommendation($recommendation, $user);

        expect($result)->toHaveKey('personalised_context');
        $contextText = implode(' ', $result['personalised_context']);
        expect($contextText)->toContain('residence nil-rate band');
    });

    it('mentions combined nil-rate bands for married users', function () {
        $personaliser = createPersonaliser();

        $spouse = User::factory()->create([
            'marital_status' => 'married',
        ]);

        $user = User::factory()->create([
            'marital_status' => 'married',
            'spouse_id' => $spouse->id,
            'date_of_birth' => now()->subYears(55),
        ]);

        $spouse->update(['spouse_id' => $user->id]);

        $recommendation = [
            'category' => 'charitable_bequest',
            'title' => 'Charitable Bequest Opportunity',
            'description' => 'Increase charitable giving to qualify for the reduced IHT rate.',
            'net_estate' => 1200000,
            'module' => 'estate',
        ];

        $result = $personaliser->personaliseRecommendation($recommendation, $user);

        expect($result)->toHaveKey('personalised_context');
        $contextText = implode(' ', $result['personalised_context']);
        expect($contextText)->toContain('combine nil-rate bands');
    });

    it('includes trust context for minor children', function () {
        $personaliser = createPersonaliser();

        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(45),
        ]);

        FamilyMember::factory()->child()->create([
            'user_id' => $user->id,
            'first_name' => 'Tom',
            'date_of_birth' => now()->subYears(8),
        ]);

        $recommendation = [
            'category' => 'clt_trust',
            'title' => 'Discretionary Trust Planning',
            'description' => 'Transfer assets into discretionary trusts.',
            'module' => 'estate',
        ];

        $result = $personaliser->personaliseRecommendation($recommendation, $user);

        expect($result)->toHaveKey('personalised_context');
        $contextText = implode(' ', $result['personalised_context']);
        expect($contextText)->toContain('trust')
            ->and($contextText)->toContain('adulthood');
    });
});

describe('Investment personalisation', function () {
    it('factors property exposure for asset allocation recommendations', function () {
        $personaliser = createPersonaliser();

        $user = User::factory()->create();

        Property::factory()->create([
            'user_id' => $user->id,
            'property_type' => 'main_residence',
            'current_value' => 450000,
        ]);

        Property::factory()->create([
            'user_id' => $user->id,
            'property_type' => 'buy_to_let',
            'current_value' => 250000,
        ]);

        $recommendation = [
            'category' => 'asset_allocation',
            'action' => 'Review asset allocation',
            'description' => 'Your portfolio allocation needs adjustment.',
            'module' => 'investment',
        ];

        $result = $personaliser->personaliseRecommendation($recommendation, $user);

        expect($result)->toHaveKey('personalised_context');
        $contextText = implode(' ', $result['personalised_context']);
        expect($contextText)->toContain('property holdings');
    });
});

describe('General behaviour', function () {
    it('returns unmodified recommendation when no personalisation applies', function () {
        $personaliser = createPersonaliser();

        $user = User::factory()->create([
            'marital_status' => 'single',
        ]);

        $recommendation = [
            'category' => 'unknown_category',
            'action' => 'Something generic',
            'rationale' => 'No specific module.',
        ];

        $result = $personaliser->personaliseRecommendation($recommendation, $user);

        expect($result)->not->toHaveKey('personalised_context')
            ->and($result['category'])->toBe('unknown_category')
            ->and($result['action'])->toBe('Something generic');
    });

    it('single user gets different context than married user', function () {
        $personaliser = createPersonaliser();

        $singleUser = User::factory()->create([
            'marital_status' => 'single',
            'date_of_birth' => now()->subYears(55),
        ]);

        $spouse = User::factory()->create([
            'marital_status' => 'married',
            'annual_employment_income' => 40000,
        ]);

        $marriedUser = User::factory()->create([
            'marital_status' => 'married',
            'spouse_id' => $spouse->id,
            'date_of_birth' => now()->subYears(55),
        ]);

        $spouse->update(['spouse_id' => $marriedUser->id]);

        $recommendation = [
            'category' => 'annual_gifting',
            'title' => 'Annual Gifting Strategy',
            'description' => 'Use your annual gift exemption.',
            'module' => 'estate',
        ];

        $singleResult = $personaliser->personaliseRecommendation($recommendation, $singleUser);
        $marriedResult = $personaliser->personaliseRecommendation($recommendation, $marriedUser);

        // Married user should get spouse-related context that single user does not
        $marriedContext = implode(' ', $marriedResult['personalised_context'] ?? []);
        $singleContext = implode(' ', $singleResult['personalised_context'] ?? []);

        expect($marriedContext)->toContain('spouse')
            ->and($singleContext)->not->toContain('spouse');
    });

    it('personalises a batch of recommendations', function () {
        $personaliser = createPersonaliser();

        $user = User::factory()->create([
            'annual_self_employment_income' => 60000,
            'annual_employment_income' => 0,
        ]);

        $recommendations = [
            [
                'category' => 'Income Protection',
                'action' => 'Add income protection insurance',
                'rationale' => 'No protection in place.',
                'priority' => 1,
                'impact' => 'High',
                'estimated_cost' => 100,
            ],
            [
                'category' => 'unknown',
                'action' => 'Generic action',
                'rationale' => 'N/A',
                'priority' => 5,
                'impact' => 'Low',
                'estimated_cost' => 0,
            ],
        ];

        $results = $personaliser->personaliseRecommendations($recommendations, $user);

        expect($results)->toHaveCount(2);
        // First should have personalisation (income protection for self-employed)
        expect($results[0])->toHaveKey('personalised_context');
        // Second should not (unknown category)
        expect($results[1])->not->toHaveKey('personalised_context');
    });
});
