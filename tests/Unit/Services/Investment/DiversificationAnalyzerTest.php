<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Services\Investment\DiversificationAnalyzer;

beforeEach(function () {
    $this->analyzer = new DiversificationAnalyzer;
});

describe('calculateHHI', function () {
    it('returns 0 for empty holdings', function () {
        $holdings = collect([]);

        $hhi = $this->analyzer->calculateHHI($holdings);

        expect($hhi)->toBe(0.0);
    });

    it('returns 1 for single holding (maximum concentration)', function () {
        $holdings = collect([
            new Holding(['current_value' => 10000]),
        ]);

        $hhi = $this->analyzer->calculateHHI($holdings);

        expect($hhi)->toBe(1.0);
    });

    it('returns 0.5 for two equal holdings', function () {
        $holdings = collect([
            new Holding(['current_value' => 5000]),
            new Holding(['current_value' => 5000]),
        ]);

        $hhi = $this->analyzer->calculateHHI($holdings);

        expect($hhi)->toBe(0.5);
    });

    it('returns lower value for more diversified portfolio', function () {
        $holdings = collect([
            new Holding(['current_value' => 2500]),
            new Holding(['current_value' => 2500]),
            new Holding(['current_value' => 2500]),
            new Holding(['current_value' => 2500]),
        ]);

        $hhi = $this->analyzer->calculateHHI($holdings);

        expect($hhi)->toBe(0.25);
    });

    it('calculates correctly for uneven holdings', function () {
        // 60% + 40% = 0.36 + 0.16 = 0.52
        $holdings = collect([
            new Holding(['current_value' => 6000]),
            new Holding(['current_value' => 4000]),
        ]);

        $hhi = $this->analyzer->calculateHHI($holdings);

        expect($hhi)->toBe(0.52);
    });
});

describe('getHHILabel', function () {
    it('returns Well Diversified for low HHI', function () {
        expect($this->analyzer->getHHILabel(0.10))->toBe('Well Diversified');
        expect($this->analyzer->getHHILabel(0.14))->toBe('Well Diversified');
    });

    it('returns Moderate Concentration for medium HHI', function () {
        expect($this->analyzer->getHHILabel(0.15))->toBe('Moderate Concentration');
        expect($this->analyzer->getHHILabel(0.25))->toBe('Moderate Concentration');
    });

    it('returns High Concentration for high HHI', function () {
        expect($this->analyzer->getHHILabel(0.26))->toBe('High Concentration');
        expect($this->analyzer->getHHILabel(0.50))->toBe('High Concentration');
        expect($this->analyzer->getHHILabel(1.0))->toBe('High Concentration');
    });
});

describe('calculateConcentration', function () {
    it('returns zeros for empty holdings', function () {
        $holdings = collect([]);

        $concentration = $this->analyzer->calculateConcentration($holdings);

        expect($concentration['top_holding_percent'])->toBe(0.0)
            ->and($concentration['top_3_holdings_percent'])->toBe(0.0)
            ->and($concentration['holdings_over_10_percent'])->toBe(0)
            ->and($concentration['holdings_over_5_percent'])->toBe(0);
    });

    it('calculates top holding percentage correctly', function () {
        $holdings = collect([
            new Holding(['current_value' => 5000]),
            new Holding(['current_value' => 3000]),
            new Holding(['current_value' => 2000]),
        ]);

        $concentration = $this->analyzer->calculateConcentration($holdings);

        expect($concentration['top_holding_percent'])->toBe(50.0);
    });

    it('calculates top 3 holdings percentage correctly', function () {
        $holdings = collect([
            new Holding(['current_value' => 4000]),
            new Holding(['current_value' => 3000]),
            new Holding(['current_value' => 2000]),
            new Holding(['current_value' => 1000]),
        ]);

        $concentration = $this->analyzer->calculateConcentration($holdings);

        // Top 3: 40% + 30% + 20% = 90%
        expect($concentration['top_3_holdings_percent'])->toBe(90.0);
    });

    it('counts holdings over thresholds correctly', function () {
        $holdings = collect([
            new Holding(['current_value' => 3500]), // 35%
            new Holding(['current_value' => 2500]), // 25%
            new Holding(['current_value' => 1500]), // 15%
            new Holding(['current_value' => 800]),  // 8%
            new Holding(['current_value' => 700]),  // 7%
            new Holding(['current_value' => 500]),  // 5%
            new Holding(['current_value' => 500]),  // 5%
        ]);

        $concentration = $this->analyzer->calculateConcentration($holdings);

        expect($concentration['holdings_over_10_percent'])->toBe(3)
            ->and($concentration['holdings_over_5_percent'])->toBe(5);
    });
});

describe('getConcentrationWarnings', function () {
    it('returns warning when top holding exceeds 25%', function () {
        $concentration = [
            'top_holding_percent' => 30.0,
            'top_3_holdings_percent' => 50.0,
            'holdings_over_10_percent' => 2,
            'holdings_over_5_percent' => 3,
        ];

        $warnings = $this->analyzer->getConcentrationWarnings($concentration);

        expect($warnings)->toHaveCount(1)
            ->and($warnings[0]['type'])->toBe('warning')
            ->and($warnings[0]['message'])->toContain('25%');
    });

    it('returns warning when top 3 exceed 60%', function () {
        $concentration = [
            'top_holding_percent' => 20.0,
            'top_3_holdings_percent' => 70.0,
            'holdings_over_10_percent' => 2,
            'holdings_over_5_percent' => 3,
        ];

        $warnings = $this->analyzer->getConcentrationWarnings($concentration);

        expect($warnings)->toHaveCount(1)
            ->and($warnings[0]['message'])->toContain('60%');
    });

    it('returns info when too many holdings over 10%', function () {
        $concentration = [
            'top_holding_percent' => 15.0,
            'top_3_holdings_percent' => 40.0,
            'holdings_over_10_percent' => 5,
            'holdings_over_5_percent' => 6,
        ];

        $warnings = $this->analyzer->getConcentrationWarnings($concentration);

        expect($warnings)->toHaveCount(1)
            ->and($warnings[0]['type'])->toBe('info');
    });

    it('returns empty array for well-diversified portfolio', function () {
        $concentration = [
            'top_holding_percent' => 10.0,
            'top_3_holdings_percent' => 25.0,
            'holdings_over_10_percent' => 1,
            'holdings_over_5_percent' => 3,
        ];

        $warnings = $this->analyzer->getConcentrationWarnings($concentration);

        expect($warnings)->toBeEmpty();
    });
});

describe('getAssetClassBreakdown', function () {
    it('returns zeros for empty holdings', function () {
        $holdings = collect([]);

        $breakdown = $this->analyzer->getAssetClassBreakdown($holdings);

        expect($breakdown['equities'])->toBe(0.0)
            ->and($breakdown['bonds'])->toBe(0.0)
            ->and($breakdown['cash'])->toBe(0.0)
            ->and($breakdown['alternatives'])->toBe(0.0);
    });

    it('groups equity types correctly', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'uk_equity', 'current_value' => 3000]),
            new Holding(['asset_type' => 'us_equity', 'current_value' => 4000]),
            new Holding(['asset_type' => 'international_equity', 'current_value' => 3000]),
        ]);

        $breakdown = $this->analyzer->getAssetClassBreakdown($holdings);

        expect($breakdown['equities'])->toBe(100.0)
            ->and($breakdown['bonds'])->toBe(0.0);
    });

    it('calculates mixed portfolio correctly', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'equity', 'current_value' => 5000]),
            new Holding(['asset_type' => 'bond', 'current_value' => 3000]),
            new Holding(['asset_type' => 'cash', 'current_value' => 1500]),
            new Holding(['asset_type' => 'alternative', 'current_value' => 500]),
        ]);

        $breakdown = $this->analyzer->getAssetClassBreakdown($holdings);

        expect($breakdown['equities'])->toBe(50.0)
            ->and($breakdown['bonds'])->toBe(30.0)
            ->and($breakdown['cash'])->toBe(15.0)
            ->and($breakdown['alternatives'])->toBe(5.0);
    });

    it('defaults unknown types to equities', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'unknown_type', 'current_value' => 5000]),
            new Holding(['asset_type' => 'bond', 'current_value' => 5000]),
        ]);

        $breakdown = $this->analyzer->getAssetClassBreakdown($holdings);

        expect($breakdown['equities'])->toBe(50.0)
            ->and($breakdown['bonds'])->toBe(50.0);
    });
});

describe('compareToTarget', function () {
    it('compares to risk level 1 targets correctly', function () {
        $currentAllocation = [
            'equities' => 20,
            'bonds' => 60,
            'cash' => 15,
            'alternatives' => 5,
        ];

        $comparison = $this->analyzer->compareToTarget($currentAllocation, 1);

        // Risk 1 target: equities=10, bonds=70, cash=20, alternatives=0
        expect($comparison['equities']['deviation'])->toBe(10.0)  // 20 - 10
            ->and($comparison['bonds']['deviation'])->toBe(-10.0) // 60 - 70
            ->and($comparison['cash']['deviation'])->toBe(-5.0)   // 15 - 20
            ->and($comparison['alternatives']['deviation'])->toBe(5.0); // 5 - 0
    });

    it('compares to risk level 5 targets correctly', function () {
        $currentAllocation = [
            'equities' => 80,
            'bonds' => 10,
            'cash' => 5,
            'alternatives' => 5,
        ];

        $comparison = $this->analyzer->compareToTarget($currentAllocation, 5);

        // Risk 5 target: equities=90, bonds=5, cash=0, alternatives=5
        expect($comparison['equities']['deviation'])->toBe(-10.0) // 80 - 90
            ->and($comparison['bonds']['deviation'])->toBe(5.0)   // 10 - 5
            ->and($comparison['cash']['deviation'])->toBe(5.0)    // 5 - 0
            ->and($comparison['alternatives']['deviation'])->toBe(0.0); // 5 - 5
    });

    it('clamps risk level to valid range', function () {
        $currentAllocation = ['equities' => 50, 'bonds' => 35, 'cash' => 10, 'alternatives' => 5];

        $comparison0 = $this->analyzer->compareToTarget($currentAllocation, 0);
        $comparison6 = $this->analyzer->compareToTarget($currentAllocation, 6);

        // Level 0 should be treated as 1, level 6 as 5
        expect($comparison0['equities']['target'])->toBe(10.0)
            ->and($comparison6['equities']['target'])->toBe(90.0);
    });

    it('assigns severity correctly', function () {
        // Risk 3 target: equities=50, bonds=40, cash=5, alternatives=5
        $currentAllocation = [
            'equities' => 50,
            'bonds' => 40,
            'cash' => 5,
            'alternatives' => 5,
        ];

        $comparison = $this->analyzer->compareToTarget($currentAllocation, 3);

        expect($comparison['equities']['severity'])->toBe('aligned')
            ->and($comparison['bonds']['severity'])->toBe('aligned')
            ->and($comparison['cash']['severity'])->toBe('aligned');
    });
});

describe('getDeviationSeverity', function () {
    it('returns aligned for small deviations', function () {
        expect($this->analyzer->getDeviationSeverity(0))->toBe('aligned');
        expect($this->analyzer->getDeviationSeverity(4.9))->toBe('aligned');
    });

    it('returns minor for moderate deviations', function () {
        expect($this->analyzer->getDeviationSeverity(5))->toBe('minor');
        expect($this->analyzer->getDeviationSeverity(10))->toBe('minor');
    });

    it('returns significant for large deviations', function () {
        expect($this->analyzer->getDeviationSeverity(10.1))->toBe('significant');
        expect($this->analyzer->getDeviationSeverity(25))->toBe('significant');
    });
});

describe('normalizeRiskLevel', function () {
    it('handles integer values', function () {
        expect($this->analyzer->normalizeRiskLevel(1))->toBe(1);
        expect($this->analyzer->normalizeRiskLevel(5))->toBe(5);
    });

    it('clamps integers to valid range', function () {
        expect($this->analyzer->normalizeRiskLevel(0))->toBe(1);
        expect($this->analyzer->normalizeRiskLevel(10))->toBe(5);
    });

    it('converts string risk levels', function () {
        expect($this->analyzer->normalizeRiskLevel('low'))->toBe(1);
        expect($this->analyzer->normalizeRiskLevel('medium'))->toBe(3);
        expect($this->analyzer->normalizeRiskLevel('high'))->toBe(5);
        expect($this->analyzer->normalizeRiskLevel('balanced'))->toBe(3);
        expect($this->analyzer->normalizeRiskLevel('upper_medium'))->toBe(4);
    });

    it('defaults unknown values to 3', function () {
        expect($this->analyzer->normalizeRiskLevel('unknown'))->toBe(3);
        expect($this->analyzer->normalizeRiskLevel(null))->toBe(3);
    });
});

describe('calculateDiversificationScore', function () {
    it('returns high score for well-diversified portfolio', function () {
        $hhi = 0.10;
        $concentration = [
            'top_holding_percent' => 15,
            'top_3_holdings_percent' => 40,
            'holdings_over_10_percent' => 2,
            'holdings_over_5_percent' => 5,
        ];
        $assetClass = ['equities' => 40, 'bonds' => 35, 'cash' => 15, 'alternatives' => 10];

        $score = $this->analyzer->calculateDiversificationScore($hhi, $concentration, $assetClass);

        // No HHI penalty, no concentration penalty, 4 classes = +10
        expect($score)->toBeGreaterThanOrEqual(100);
    });

    it('penalizes high HHI', function () {
        $concentration = [
            'top_holding_percent' => 15,
            'top_3_holdings_percent' => 40,
            'holdings_over_10_percent' => 2,
            'holdings_over_5_percent' => 5,
        ];
        $assetClass = ['equities' => 100, 'bonds' => 0, 'cash' => 0, 'alternatives' => 0];

        $lowHHI = $this->analyzer->calculateDiversificationScore(0.10, $concentration, $assetClass);
        $highHHI = $this->analyzer->calculateDiversificationScore(0.50, $concentration, $assetClass);

        expect($lowHHI)->toBeGreaterThan($highHHI);
    });

    it('penalizes concentration', function () {
        $hhi = 0.10;
        $assetClass = ['equities' => 50, 'bonds' => 30, 'cash' => 10, 'alternatives' => 10];

        $diversified = [
            'top_holding_percent' => 10,
            'top_3_holdings_percent' => 25,
            'holdings_over_10_percent' => 1,
            'holdings_over_5_percent' => 3,
        ];
        $concentrated = [
            'top_holding_percent' => 45,
            'top_3_holdings_percent' => 85,
            'holdings_over_10_percent' => 3,
            'holdings_over_5_percent' => 5,
        ];

        $diversifiedScore = $this->analyzer->calculateDiversificationScore($hhi, $diversified, $assetClass);
        $concentratedScore = $this->analyzer->calculateDiversificationScore($hhi, $concentrated, $assetClass);

        expect($diversifiedScore)->toBeGreaterThan($concentratedScore);
    });

    it('penalizes single asset class', function () {
        $hhi = 0.10;
        $concentration = [
            'top_holding_percent' => 10,
            'top_3_holdings_percent' => 25,
            'holdings_over_10_percent' => 1,
            'holdings_over_5_percent' => 3,
        ];

        $oneClass = ['equities' => 100, 'bonds' => 0, 'cash' => 0, 'alternatives' => 0];
        $fourClasses = ['equities' => 40, 'bonds' => 35, 'cash' => 15, 'alternatives' => 10];

        $oneClassScore = $this->analyzer->calculateDiversificationScore($hhi, $concentration, $oneClass);
        $fourClassScore = $this->analyzer->calculateDiversificationScore($hhi, $concentration, $fourClasses);

        expect($fourClassScore)->toBeGreaterThan($oneClassScore);
    });

    it('clamps score to 0-100 range', function () {
        $hhi = 0.10;
        $concentration = [
            'top_holding_percent' => 5,
            'top_3_holdings_percent' => 15,
            'holdings_over_10_percent' => 0,
            'holdings_over_5_percent' => 0,
        ];
        $assetClass = ['equities' => 25, 'bonds' => 25, 'cash' => 25, 'alternatives' => 25];

        $score = $this->analyzer->calculateDiversificationScore($hhi, $concentration, $assetClass);

        expect($score)->toBeLessThanOrEqual(100)
            ->and($score)->toBeGreaterThanOrEqual(0);
    });
});

describe('getScoreLabel', function () {
    it('returns correct labels for score ranges', function () {
        expect($this->analyzer->getScoreLabel(90))->toBe('Excellent');
        expect($this->analyzer->getScoreLabel(80))->toBe('Excellent');
        expect($this->analyzer->getScoreLabel(70))->toBe('Good');
        expect($this->analyzer->getScoreLabel(60))->toBe('Good');
        expect($this->analyzer->getScoreLabel(50))->toBe('Fair');
        expect($this->analyzer->getScoreLabel(40))->toBe('Fair');
        expect($this->analyzer->getScoreLabel(30))->toBe('Poor');
        expect($this->analyzer->getScoreLabel(0))->toBe('Poor');
    });
});

describe('generateRecommendations', function () {
    it('recommends reducing concentration for high HHI', function () {
        $hhi = 0.30;
        $concentration = ['top_holding_percent' => 20, 'top_3_holdings_percent' => 50];
        $comparison = [
            'equities' => ['deviation' => 0, 'severity' => 'aligned'],
            'bonds' => ['deviation' => 0, 'severity' => 'aligned'],
            'cash' => ['deviation' => 0, 'severity' => 'aligned'],
            'alternatives' => ['deviation' => 0, 'severity' => 'aligned'],
        ];

        $recommendations = $this->analyzer->generateRecommendations($hhi, $concentration, $comparison);

        expect($recommendations)->toHaveCount(1)
            ->and($recommendations[0]['type'])->toBe('warning')
            ->and($recommendations[0]['message'])->toContain('concentration');
    });

    it('recommends reducing top holding when over 25%', function () {
        $hhi = 0.10;
        $concentration = ['top_holding_percent' => 35, 'top_3_holdings_percent' => 50];
        $comparison = [
            'equities' => ['deviation' => 0, 'severity' => 'aligned'],
            'bonds' => ['deviation' => 0, 'severity' => 'aligned'],
            'cash' => ['deviation' => 0, 'severity' => 'aligned'],
            'alternatives' => ['deviation' => 0, 'severity' => 'aligned'],
        ];

        $recommendations = $this->analyzer->generateRecommendations($hhi, $concentration, $comparison);

        expect($recommendations)->toHaveCount(1)
            ->and($recommendations[0]['message'])->toContain('35.0%')
            ->and($recommendations[0]['message'])->toContain('below 25%');
    });

    it('recommends allocation adjustments for significant deviations', function () {
        $hhi = 0.10;
        $concentration = ['top_holding_percent' => 10, 'top_3_holdings_percent' => 25];
        $comparison = [
            'equities' => ['deviation' => 20, 'severity' => 'significant'],
            'bonds' => ['deviation' => -15, 'severity' => 'significant'],
            'cash' => ['deviation' => 0, 'severity' => 'aligned'],
            'alternatives' => ['deviation' => 0, 'severity' => 'aligned'],
        ];

        $recommendations = $this->analyzer->generateRecommendations($hhi, $concentration, $comparison);

        expect($recommendations)->toHaveCount(2);

        $equityRec = collect($recommendations)->first(fn ($r) => str_contains($r['message'], 'Equities'));
        $bondRec = collect($recommendations)->first(fn ($r) => str_contains($r['message'], 'Bonds'));

        expect($equityRec['message'])->toContain('overweight')
            ->and($bondRec['message'])->toContain('underweight');
    });

    it('returns success message for well-diversified portfolio', function () {
        $hhi = 0.10;
        $concentration = ['top_holding_percent' => 10, 'top_3_holdings_percent' => 25];
        $comparison = [
            'equities' => ['deviation' => 2, 'severity' => 'aligned'],
            'bonds' => ['deviation' => -1, 'severity' => 'aligned'],
            'cash' => ['deviation' => 0, 'severity' => 'aligned'],
            'alternatives' => ['deviation' => -1, 'severity' => 'aligned'],
        ];

        $recommendations = $this->analyzer->generateRecommendations($hhi, $concentration, $comparison);

        expect($recommendations)->toHaveCount(1)
            ->and($recommendations[0]['type'])->toBe('success')
            ->and($recommendations[0]['message'])->toContain('well diversified');
    });
});

describe('analyze', function () {
    it('returns complete analysis structure', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'equity', 'current_value' => 6000]),
            new Holding(['asset_type' => 'bond', 'current_value' => 3000]),
            new Holding(['asset_type' => 'cash', 'current_value' => 1000]),
        ]);

        $analysis = $this->analyzer->analyze($holdings, 3, null);

        expect($analysis)->toHaveKeys([
            'diversification_score',
            'diversification_label',
            'hhi',
            'hhi_label',
            'concentration',
            'concentration_warnings',
            'asset_class_breakdown',
            'risk_profile',
            'recommendations',
            'holdings_count',
        ]);
    });

    it('uses account risk level when provided', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'equity', 'current_value' => 5000]),
            new Holding(['asset_type' => 'bond', 'current_value' => 5000]),
        ]);

        $analysis = $this->analyzer->analyze($holdings, 3, 5);

        expect($analysis['risk_profile']['user_level'])->toBe(3)
            ->and($analysis['risk_profile']['account_level'])->toBe(5)
            ->and($analysis['risk_profile']['effective_level'])->toBe(5)
            ->and($analysis['risk_profile']['using_custom'])->toBeTrue();
    });

    it('uses user risk level when no account override', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'equity', 'current_value' => 5000]),
        ]);

        $analysis = $this->analyzer->analyze($holdings, 4, null);

        expect($analysis['risk_profile']['effective_level'])->toBe(4)
            ->and($analysis['risk_profile']['using_custom'])->toBeFalse();
    });

    it('returns correct holdings count', function () {
        $holdings = collect([
            new Holding(['asset_type' => 'equity', 'current_value' => 1000]),
            new Holding(['asset_type' => 'equity', 'current_value' => 2000]),
            new Holding(['asset_type' => 'bond', 'current_value' => 3000]),
        ]);

        $analysis = $this->analyzer->analyze($holdings, 3, null);

        expect($analysis['holdings_count'])->toBe(3);
    });

    it('handles empty holdings gracefully', function () {
        $holdings = collect([]);

        $analysis = $this->analyzer->analyze($holdings, 3, null);

        expect($analysis['hhi'])->toBe(0.0)
            ->and($analysis['holdings_count'])->toBe(0)
            ->and($analysis['concentration']['top_holding_percent'])->toBe(0.0);
    });
});
