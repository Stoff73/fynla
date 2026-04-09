<?php

declare(strict_types=1);

use App\Services\Protection\AdequacyScorer;
use App\Services\TaxConfigService;

beforeEach(function () {
    $mockTaxConfig = Mockery::mock(TaxConfigService::class);
    $mockTaxConfig->shouldReceive('get')
        ->with('protection.income_multipliers.critical_illness', Mockery::any())
        ->andReturn(3);

    $this->scorer = new AdequacyScorer($mockTaxConfig);
});

afterEach(function () {
    Mockery::close();
});

describe('calculateAdequacyScore', function () {
    it('returns 100 when fully covered', function () {
        $gaps = ['total_gap' => 0];
        $needs = ['total_need' => 1000000];

        $result = $this->scorer->calculateAdequacyScore($gaps, $needs);

        expect($result)->toBe(100);
    });

    it('calculates score correctly for partial coverage', function () {
        $gaps = ['total_gap' => 500000];
        $needs = ['total_need' => 1000000];

        // Coverage = 1000000 - 500000 = 500000
        // Score = (500000 / 1000000) * 100 = 50
        $result = $this->scorer->calculateAdequacyScore($gaps, $needs);

        expect($result)->toBe(50);
    });

    it('calculates score correctly for 25% coverage', function () {
        $gaps = ['total_gap' => 750000];
        $needs = ['total_need' => 1000000];

        $result = $this->scorer->calculateAdequacyScore($gaps, $needs);

        expect($result)->toBe(25);
    });

    it('calculates score correctly for 75% coverage', function () {
        $gaps = ['total_gap' => 250000];
        $needs = ['total_need' => 1000000];

        $result = $this->scorer->calculateAdequacyScore($gaps, $needs);

        expect($result)->toBe(75);
    });

    it('returns 0 when no coverage', function () {
        $gaps = ['total_gap' => 1000000];
        $needs = ['total_need' => 1000000];

        $result = $this->scorer->calculateAdequacyScore($gaps, $needs);

        expect($result)->toBe(0);
    });

    it('returns 100 when total need is zero or negative', function () {
        $gaps = ['total_gap' => 0];
        $needs = ['total_need' => 0];

        $result = $this->scorer->calculateAdequacyScore($gaps, $needs);

        expect($result)->toBe(100);
    });

    it('caps score at 100', function () {
        // Over-coverage scenario (gap is negative)
        $gaps = ['total_gap' => -100000]; // Negative gap means over-coverage
        $needs = ['total_need' => 1000000];

        $result = $this->scorer->calculateAdequacyScore($gaps, $needs);

        expect($result)->toBe(100);
    });

    it('caps score at 0 for negative cases', function () {
        // If somehow gap exceeds need
        $gaps = ['total_gap' => 1500000];
        $needs = ['total_need' => 1000000];

        $result = $this->scorer->calculateAdequacyScore($gaps, $needs);

        expect($result)->toBe(0);
    });

    it('rounds score to nearest integer', function () {
        // 33.33% coverage
        $gaps = ['total_gap' => 666667];
        $needs = ['total_need' => 1000000];

        $result = $this->scorer->calculateAdequacyScore($gaps, $needs);

        expect($result)->toBe(33); // Rounds 33.33 down
    });
});

describe('categorizeScore', function () {
    it('returns Excellent for score 100', function () {
        $result = $this->scorer->categorizeScore(100);
        expect($result)->toBe('Excellent');
    });

    it('returns Excellent for score 80', function () {
        $result = $this->scorer->categorizeScore(80);
        expect($result)->toBe('Excellent');
    });

    it('returns Excellent for score 90', function () {
        $result = $this->scorer->categorizeScore(90);
        expect($result)->toBe('Excellent');
    });

    it('returns Good for score 79', function () {
        $result = $this->scorer->categorizeScore(79);
        expect($result)->toBe('Good');
    });

    it('returns Good for score 60', function () {
        $result = $this->scorer->categorizeScore(60);
        expect($result)->toBe('Good');
    });

    it('returns Good for score 70', function () {
        $result = $this->scorer->categorizeScore(70);
        expect($result)->toBe('Good');
    });

    it('returns Fair for score 59', function () {
        $result = $this->scorer->categorizeScore(59);
        expect($result)->toBe('Fair');
    });

    it('returns Fair for score 40', function () {
        $result = $this->scorer->categorizeScore(40);
        expect($result)->toBe('Fair');
    });

    it('returns Fair for score 50', function () {
        $result = $this->scorer->categorizeScore(50);
        expect($result)->toBe('Fair');
    });

    it('returns Critical for score 39', function () {
        $result = $this->scorer->categorizeScore(39);
        expect($result)->toBe('Critical');
    });

    it('returns Critical for score 0', function () {
        $result = $this->scorer->categorizeScore(0);
        expect($result)->toBe('Critical');
    });

    it('returns Critical for score 25', function () {
        $result = $this->scorer->categorizeScore(25);
        expect($result)->toBe('Critical');
    });
});

describe('getScoreColor', function () {
    it('returns green for score 100', function () {
        $result = $this->scorer->getScoreColor(100);
        expect($result)->toBe('green');
    });

    it('returns green for score 80', function () {
        $result = $this->scorer->getScoreColor(80);
        expect($result)->toBe('green');
    });

    it('returns blue for score 79', function () {
        $result = $this->scorer->getScoreColor(79);
        expect($result)->toBe('blue');
    });

    it('returns blue for score 60', function () {
        $result = $this->scorer->getScoreColor(60);
        expect($result)->toBe('blue');
    });

    it('returns blue for score 59', function () {
        $result = $this->scorer->getScoreColor(59);
        expect($result)->toBe('blue');
    });

    it('returns blue for score 40', function () {
        $result = $this->scorer->getScoreColor(40);
        expect($result)->toBe('blue');
    });

    it('returns red for score 39', function () {
        $result = $this->scorer->getScoreColor(39);
        expect($result)->toBe('red');
    });

    it('returns red for score 0', function () {
        $result = $this->scorer->getScoreColor(0);
        expect($result)->toBe('red');
    });
});

describe('generateScoreInsights', function () {
    it('generates insights for excellent score', function () {
        $score = 90;
        $gaps = [
            'gaps_by_category' => [
                'human_capital_gap' => 0,
                'income_protection_gap' => 0,
            ],
        ];

        $result = $this->scorer->generateScoreInsights($score, $gaps);

        expect($result)->toHaveKeys(['score', 'category', 'color', 'insights']);
        expect($result['score'])->toBe(90);
        expect($result['category'])->toBe('Excellent');
        expect($result['color'])->toBe('green');
        expect($result['insights'])->toBeArray();
        expect($result['insights'])->toContain('Your protection coverage is excellent. You have comprehensive protection in place.');
    });

    it('generates insights for good score', function () {
        $score = 70;
        $gaps = [
            'gaps_by_category' => [
                'human_capital_gap' => 0,
                'income_protection_gap' => 0,
            ],
        ];

        $result = $this->scorer->generateScoreInsights($score, $gaps);

        expect($result['category'])->toBe('Good');
        expect($result['color'])->toBe('blue');
        expect($result['insights'])->toContain('Your protection coverage is good, but there are some areas for improvement.');
    });

    it('generates insights for fair score', function () {
        $score = 50;
        $gaps = [
            'gaps_by_category' => [
                'human_capital_gap' => 0,
                'income_protection_gap' => 0,
            ],
        ];

        $result = $this->scorer->generateScoreInsights($score, $gaps);

        expect($result['category'])->toBe('Fair');
        expect($result['color'])->toBe('blue');
        expect($result['insights'])->toContain('Your protection coverage is fair. Consider increasing coverage to improve your financial security.');
    });

    it('generates insights for critical score', function () {
        $score = 25;
        $gaps = [
            'gaps_by_category' => [
                'human_capital_gap' => 0,
                'income_protection_gap' => 0,
            ],
        ];

        $result = $this->scorer->generateScoreInsights($score, $gaps);

        expect($result['category'])->toBe('Critical');
        expect($result['color'])->toBe('red');
        expect($result['insights'])->toContain('Your protection coverage is critical. Immediate action is recommended to address these gaps.');
    });

    it('adds insight for human capital gap', function () {
        $score = 50;
        $gaps = [
            'gaps_by_category' => [
                'human_capital_gap' => 500000,
                'income_protection_gap' => 0,
            ],
        ];

        $result = $this->scorer->generateScoreInsights($score, $gaps);

        expect($result['insights'])->toContain('There is a significant gap in life insurance coverage.');
    });

    it('adds insight for income protection gap', function () {
        $score = 50;
        $gaps = [
            'gaps_by_category' => [
                'human_capital_gap' => 0,
                'income_protection_gap' => 30000,
            ],
        ];

        $result = $this->scorer->generateScoreInsights($score, $gaps);

        expect($result['insights'])->toContain('Consider adding income protection to cover loss of earnings.');
    });

    it('adds multiple insights when multiple gaps exist', function () {
        $score = 30;
        $gaps = [
            'gaps_by_category' => [
                'human_capital_gap' => 500000,
                'income_protection_gap' => 30000,
            ],
        ];

        $result = $this->scorer->generateScoreInsights($score, $gaps);

        expect($result['insights'])->toHaveCount(3);
        expect($result['insights'])->toContain('Your protection coverage is critical. Immediate action is recommended to address these gaps.');
        expect($result['insights'])->toContain('There is a significant gap in life insurance coverage.');
        expect($result['insights'])->toContain('Consider adding income protection to cover loss of earnings.');
    });

    it('does not add gap insights when no gaps exist', function () {
        $score = 95;
        $gaps = [
            'gaps_by_category' => [
                'human_capital_gap' => 0,
                'income_protection_gap' => 0,
            ],
        ];

        $result = $this->scorer->generateScoreInsights($score, $gaps);

        expect($result['insights'])->toHaveCount(1);
        expect($result['insights'][0])->toContain('excellent');
    });
});
