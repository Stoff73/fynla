<?php

declare(strict_types=1);

use App\Services\Coordination\RecommendationsAggregatorService;

describe('composeId', function () {
    it('keeps the bare id for a rule that names no record', function () {
        expect(RecommendationsAggregatorService::composeId('estate', ['type' => 'no_will']))
            ->toBe('estate_no_will');
    });

    it('scopes a per-record rule by the record it is about', function () {
        expect(RecommendationsAggregatorService::composeId('savings', ['type' => 'zero_rate_account', 'account_id' => 110]))
            ->toBe('savings_zero_rate_account_a110');
        expect(RecommendationsAggregatorService::composeId('savings', ['type' => 'goal_underfunded', 'goal_id' => '7']))
            ->toBe('savings_goal_underfunded_g7');
        expect(RecommendationsAggregatorService::composeId('savings', ['type' => 'child_no_jisa', 'family_member_id' => 3]))
            ->toBe('savings_child_no_jisa_m3');
        expect(RecommendationsAggregatorService::composeId('savings', ['type' => 'life_event_cash_buffer', 'life_event_id' => 9]))
            ->toBe('savings_life_event_cash_buffer_e9');
        expect(RecommendationsAggregatorService::composeId('estate', ['type' => 'policy_not_in_trust', 'policy_id' => 42]))
            ->toBe('estate_policy_not_in_trust_p42');
    });

    it('ignores a scope key that is not a record id', function () {
        expect(RecommendationsAggregatorService::composeId('savings', ['type' => 'zero_rate_account', 'account_id' => null]))
            ->toBe('savings_zero_rate_account');
    });
});

describe('disambiguate', function () {
    it('gives colliding ids a deterministic headline suffix and leaves unique ids alone', function () {
        $recs = [
            ['recommendation_id' => 'retirement_increase_pension_contribution', 'recommendation_text' => 'Maximise Employer Pension Match — Increase by 1%'],
            ['recommendation_id' => 'retirement_increase_pension_contribution', 'recommendation_text' => 'Increase Pension Contributions — To meet your target'],
            ['recommendation_id' => 'retirement_state_pension', 'recommendation_text' => 'Check your State Pension — forecast'],
        ];

        $once = RecommendationsAggregatorService::disambiguate($recs);
        $twice = RecommendationsAggregatorService::disambiguate($recs);

        expect($once[2]['recommendation_id'])->toBe('retirement_state_pension')
            ->and($once[0]['recommendation_id'])->not->toBe($once[1]['recommendation_id'])
            ->and($once[0]['recommendation_id'])->toStartWith('retirement_increase_pension_contribution_')
            ->and($once)->toBe($twice);
    });

    it('hashes the headline only, so a changing description does not move the id', function () {
        $a = RecommendationsAggregatorService::disambiguate([
            ['recommendation_id' => 'x_y', 'recommendation_text' => 'Same headline — holds £2,000'],
            ['recommendation_id' => 'x_y', 'recommendation_text' => 'Other headline — holds £1'],
        ]);
        $b = RecommendationsAggregatorService::disambiguate([
            ['recommendation_id' => 'x_y', 'recommendation_text' => 'Same headline — holds £9,999'],
            ['recommendation_id' => 'x_y', 'recommendation_text' => 'Other headline — holds £1'],
        ]);

        expect($a[0]['recommendation_id'])->toBe($b[0]['recommendation_id']);
    });
});
