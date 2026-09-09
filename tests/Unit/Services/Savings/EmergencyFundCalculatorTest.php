<?php

declare(strict_types=1);

use App\Services\Savings\EmergencyFundCalculator;

describe('EmergencyFundCalculator', function () {
    describe('calculateRunway', function () {
        it('calculates runway correctly with positive values', function () {
            $calculator = new EmergencyFundCalculator;
            $runway = $calculator->calculateRunway(12000, 2000);
            expect($runway)->toBe(6.0);
        });

        // W-0495. These two asserted 0.0, which is the defect written down as a
        // contract: a household with £12,000 of cash and no recorded spending
        // was told it had ZERO months of cover, and every consumer that treats a
        // low runway as urgent then raised a false alarm against it. "Cannot be
        // calculated" is null; "no runway at all" is 0.0. They are different
        // answers and the caller must be able to tell them apart.
        it('cannot state a runway when no expenditure is recorded', function () {
            $calculator = new EmergencyFundCalculator;
            $runway = $calculator->calculateRunway(12000, 0);
            expect($runway)->toBeNull();
        });

        it('cannot state a runway from a negative expenditure either', function () {
            $calculator = new EmergencyFundCalculator;
            $runway = $calculator->calculateRunway(12000, -100);
            expect($runway)->toBeNull();
        });

        it('reports no adequacy and no shortfall for an unknown runway', function () {
            $calculator = new EmergencyFundCalculator;
            $adequacy = $calculator->calculateAdequacy(null, 6);

            // A 100% score would claim the fund is ample and a 0 would claim it
            // is empty. Both assert something nobody measured.
            expect($adequacy['runway'])->toBeNull()
                ->and($adequacy['adequacy_score'])->toBeNull()
                ->and($adequacy['shortfall'])->toBeNull()
                ->and($adequacy['target'])->toBe(6);
        });

        it('handles decimal results', function () {
            $calculator = new EmergencyFundCalculator;
            $runway = $calculator->calculateRunway(5500, 2000);
            expect($runway)->toBe(2.75);
        });
    });

    describe('calculateAdequacy', function () {
        it('returns 100% adequacy when runway meets target', function () {
            $calculator = new EmergencyFundCalculator;
            $adequacy = $calculator->calculateAdequacy(6.0, 6);
            expect($adequacy['adequacy_score'])->toBe(100.0);
            expect($adequacy['shortfall'])->toBe(0.0);
        });

        it('returns 50% adequacy when runway is half of target', function () {
            $calculator = new EmergencyFundCalculator;
            $adequacy = $calculator->calculateAdequacy(3.0, 6);
            expect($adequacy['adequacy_score'])->toBe(50.0);
            expect($adequacy['shortfall'])->toBe(3.0);
        });

        it('caps adequacy score at 100% when runway exceeds target', function () {
            $calculator = new EmergencyFundCalculator;
            $adequacy = $calculator->calculateAdequacy(12.0, 6);
            expect($adequacy['adequacy_score'])->toBe(100.0);
            expect($adequacy['shortfall'])->toBe(0.0);
        });

        it('returns correct structure', function () {
            $calculator = new EmergencyFundCalculator;
            $adequacy = $calculator->calculateAdequacy(3.0, 6);
            expect($adequacy)->toHaveKeys(['runway', 'target', 'adequacy_score', 'shortfall']);
        });
    });

    describe('calculateMonthlyTopUp', function () {
        it('calculates monthly top-up correctly', function () {
            $calculator = new EmergencyFundCalculator;
            $topUp = $calculator->calculateMonthlyTopUp(12000, 12);
            expect($topUp)->toBe(1000.0);
        });

        it('returns zero when months is zero', function () {
            $calculator = new EmergencyFundCalculator;
            $topUp = $calculator->calculateMonthlyTopUp(12000, 0);
            expect($topUp)->toBe(0.0);
        });

        it('handles negative months gracefully', function () {
            $calculator = new EmergencyFundCalculator;
            $topUp = $calculator->calculateMonthlyTopUp(12000, -5);
            expect($topUp)->toBe(0.0);
        });
    });

});

describe('getTargetMonths is the one month table', function () {
    it('maps every employment status the users enum allows', function () {
        $calculator = new EmergencyFundCalculator;
        expect($calculator->getTargetMonths('employed'))->toBe(6)
            ->and($calculator->getTargetMonths('full_time'))->toBe(6)
            ->and($calculator->getTargetMonths('part_time'))->toBe(6)
            ->and($calculator->getTargetMonths('self_employed'))->toBe(9)
            ->and($calculator->getTargetMonths('contractor'))->toBe(9)
            ->and($calculator->getTargetMonths('freelance'))->toBe(9)
            ->and($calculator->getTargetMonths('retired'))->toBe(3)
            ->and($calculator->getTargetMonths('unemployed'))->toBe(6)
            ->and($calculator->getTargetMonths(null))->toBe(6);
    });
});
