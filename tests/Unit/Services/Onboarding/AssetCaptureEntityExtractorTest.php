<?php

declare(strict_types=1);

use App\Services\Onboarding\AssetCaptureEntityExtractor;

/**
 * B-1 — deterministic multi-entity extractor unit tests.
 *
 * The original top-priority bug was that the xAI model would drop the
 * second entity in a multi-entity message ("Aviva life £300k AND Vitality
 * critical illness £100k" only emitted Aviva life). These tests pin the
 * extractor's behaviour so the server-side gap-fill that backstops the
 * LLM stays correct.
 *
 * Protection is the heavily-tested focus because it is the one that
 * motivated the fix. Savings, retirement, and investment get smoke-test
 * coverage — enough to prove the pattern generalises without freezing
 * the exact regex behaviour.
 */

beforeEach(function () {
    $this->extractor = new AssetCaptureEntityExtractor;
});

// ─── Protection — the canonical B-1 case ────────────────────────────

describe('extractProtectionPolicies', function () {
    it('extracts both life insurance and critical illness from one message', function () {
        $out = $this->extractor->extractProtectionPolicies(
            'I have Aviva life insurance £300,000 and Vitality critical illness £100,000'
        );

        expect($out)->toHaveCount(2);

        // Order follows the original message order.
        expect($out[0]['policy_type'])->toBe('term');
        expect($out[0]['provider'])->toBe('Aviva');
        expect($out[0]['sum_assured'])->toEqual(300000.0);

        expect($out[1]['policy_type'])->toBe('standalone_ci');
        expect($out[1]['provider'])->toBe('Vitality');
        expect($out[1]['sum_assured'])->toEqual(100000.0);
    });

    it('handles £k shorthand', function () {
        $out = $this->extractor->extractProtectionPolicies(
            'Aviva life cover £300k and Vitality CI £100k'
        );

        expect($out)->toHaveCount(2);
        expect($out[0]['sum_assured'])->toEqual(300000.0);
        expect($out[1]['sum_assured'])->toEqual(100000.0);
    });

    it('recognises income protection as a distinct type', function () {
        $out = $this->extractor->extractProtectionPolicies(
            'L&G income protection £3,000/mo and Aviva life £500k'
        );

        expect($out)->toHaveCount(2);

        // Order check — income protection came first in the message.
        $types = array_column($out, 'policy_type');
        expect($types)->toContain('income_protection');
        expect($types)->toContain('term');

        $ipPolicy = collect($out)->firstWhere('policy_type', 'income_protection');
        expect($ipPolicy['provider'])->toBe('Legal & General');
        expect($ipPolicy['benefit_amount'])->toEqual(3000.0);
    });

    it('recognises whole of life', function () {
        $out = $this->extractor->extractProtectionPolicies(
            'a Royal London whole of life policy worth £200k'
        );

        expect($out)->toHaveCount(1);
        expect($out[0]['policy_type'])->toBe('whole_of_life');
        expect($out[0]['provider'])->toBe('Royal London');
    });

    it('splits on commas as well as and', function () {
        $out = $this->extractor->extractProtectionPolicies(
            'Aviva life £300k, Vitality critical illness £100k, Prudential income protection £2,500/month'
        );

        expect($out)->toHaveCount(3);
    });

    it('returns empty for messages with no recognised policy type', function () {
        $out = $this->extractor->extractProtectionPolicies(
            'I have a car, a dog, and a mortgage'
        );

        expect($out)->toBe([]);
    });

    it('returns empty when the message is blank', function () {
        expect($this->extractor->extractProtectionPolicies(''))->toBe([]);
        expect($this->extractor->extractProtectionPolicies('   '))->toBe([]);
    });

    it('does not split Legal & General in half', function () {
        $out = $this->extractor->extractProtectionPolicies(
            'Legal & General life insurance £400,000'
        );

        expect($out)->toHaveCount(1);
        expect($out[0]['provider'])->toBe('Legal & General');
        expect($out[0]['sum_assured'])->toEqual(400000.0);
    });

    it('does not match years as amounts', function () {
        // "2024 policy" should NOT become £2,024. Amounts need £ or k/m suffix
        // unless they are comma-grouped (e.g. 300,000).
        $out = $this->extractor->extractProtectionPolicies(
            'Aviva life insurance policy from 2024'
        );

        expect($out)->toHaveCount(1);
        expect($out[0]['policy_type'])->toBe('term');
        expect($out[0])->not->toHaveKey('sum_assured');
    });

    it('extracts a single policy without tripping the multi-entity code path', function () {
        $out = $this->extractor->extractProtectionPolicies(
            'Aviva life insurance for £500,000'
        );

        expect($out)->toHaveCount(1);
        expect($out[0]['policy_type'])->toBe('term');
        expect($out[0]['provider'])->toBe('Aviva');
        expect($out[0]['sum_assured'])->toEqual(500000.0);
    });
});

// ─── findMissing — the gap-fill decision engine ─────────────────────

describe('findMissing (protection)', function () {
    it('returns the second policy when the LLM only emitted the first', function () {
        $extracted = $this->extractor->extractProtectionPolicies(
            'Aviva life insurance £300k and Vitality critical illness £100k'
        );

        // fill_form payload shape (what the frontend receives).
        $llmEmitted = [
            [
                'policyType' => 'life',
                'provider' => 'Aviva',
                'coverage_amount' => 300000.0,
            ],
        ];

        $missing = $this->extractor->findMissing('protection', $extracted, $llmEmitted);

        expect($missing)->toHaveCount(1);
        expect($missing[0]['policy_type'])->toBe('standalone_ci');
        expect($missing[0]['provider'])->toBe('Vitality');
    });

    it('returns empty when the LLM emitted both policies', function () {
        $extracted = $this->extractor->extractProtectionPolicies(
            'Aviva life insurance £300k and Vitality critical illness £100k'
        );

        $llmEmitted = [
            ['policyType' => 'life', 'provider' => 'Aviva', 'coverage_amount' => 300000.0],
            ['policyType' => 'criticalIllness', 'provider' => 'Vitality', 'coverage_amount' => 100000.0],
        ];

        expect($this->extractor->findMissing('protection', $extracted, $llmEmitted))->toBe([]);
    });

    it('deduplicates extractor-side duplicates', function () {
        // Pathological user input — mentioning the same policy twice should
        // still only result in a single record, not two.
        $extracted = [
            ['policy_type' => 'term', 'provider' => 'Aviva', 'sum_assured' => 300000.0],
            ['policy_type' => 'level_term', 'provider' => 'Aviva', 'sum_assured' => 300000.0],
        ];

        expect($this->extractor->findMissing('protection', $extracted, []))->toHaveCount(1);
    });

    it('considers L&G and Legal & General the same provider', function () {
        $extracted = [
            ['policy_type' => 'term', 'provider' => 'Legal & General', 'sum_assured' => 400000.0],
        ];

        // LLM used the raw "L&G" string in its fill_form.
        $llmEmitted = [['policyType' => 'life', 'provider' => 'L&G', 'coverage_amount' => 400000.0]];

        expect($this->extractor->findMissing('protection', $extracted, $llmEmitted))->toBe([]);
    });

    it('returns empty when nothing was extracted', function () {
        expect($this->extractor->findMissing('protection', [], []))->toBe([]);
    });
});

// ─── toolNameForFocus ───────────────────────────────────────────────

describe('toolNameForFocus', function () {
    it('maps known focuses', function () {
        expect($this->extractor->toolNameForFocus('protection'))->toBe('create_protection_policy');
        expect($this->extractor->toolNameForFocus('savings'))->toBe('create_savings_account');
        expect($this->extractor->toolNameForFocus('budgeting'))->toBe('create_savings_account');
        expect($this->extractor->toolNameForFocus('retirement'))->toBe('create_pension');
        expect($this->extractor->toolNameForFocus('investment'))->toBe('create_investment_account');
    });

    it('returns null for unsupported focuses so the director skips gap-fill', function () {
        expect($this->extractor->toolNameForFocus('estate'))->toBeNull();
        expect($this->extractor->toolNameForFocus('business'))->toBeNull();
        expect($this->extractor->toolNameForFocus('goals'))->toBeNull();
        expect($this->extractor->toolNameForFocus('family'))->toBeNull();
    });
});

// ─── Savings ────────────────────────────────────────────────────────

describe('extractSavingsAccounts', function () {
    it('extracts Cash ISA + easy access saver from one message', function () {
        $out = $this->extractor->extractSavingsAccounts(
            'I have a Halifax Cash ISA with £15,000 and a Nationwide easy access saver with £5,000'
        );

        expect($out)->toHaveCount(2);

        $isa = collect($out)->firstWhere('is_isa', true);
        expect($isa)->not->toBeNull();
        expect($isa['institution'])->toBe('Halifax');
        expect($isa['current_balance'])->toEqual(15000.0);

        $ea = collect($out)->first(fn (array $a): bool => ($a['institution'] ?? null) === 'Nationwide');
        expect($ea)->not->toBeNull();
        expect($ea['account_type'])->toBe('easy_access');
        expect($ea['current_balance'])->toEqual(5000.0);
    });

    it('ignores amounts without a savings signal', function () {
        $out = $this->extractor->extractSavingsAccounts('£10,000 in my wallet');

        expect($out)->toBe([]);
    });

    it('detects fixed term bonds', function () {
        $out = $this->extractor->extractSavingsAccounts(
            'Santander 2 year fixed rate bond £20k'
        );

        expect($out)->toHaveCount(1);
        expect($out[0]['account_type'])->toBe('fixed_term');
    });
});

// ─── Pensions ───────────────────────────────────────────────────────

describe('extractPensions', function () {
    it('extracts DC and DB from one message', function () {
        $out = $this->extractor->extractPensions(
            'An Aviva workplace pension worth £50k and an NHS defined benefit pension'
        );

        expect($out)->toHaveCount(2);

        $dc = collect($out)->firstWhere('pension_category', 'dc');
        expect($dc['provider'])->toBe('Aviva');
        expect($dc['scheme_type'])->toBe('workplace');
        expect($dc['current_fund_value'])->toEqual(50000.0);

        $db = collect($out)->firstWhere('pension_category', 'db');
        expect($db['scheme_name'])->toContain('NHS');
    });

    it('recognises a Self-Invested Personal Pension', function () {
        $out = $this->extractor->extractPensions('a Hargreaves Lansdown SIPP £125,000');

        expect($out)->toHaveCount(1);
        expect($out[0]['scheme_type'])->toBe('sipp');
        expect($out[0]['provider'])->toBe('Hargreaves Lansdown');
        expect($out[0]['current_fund_value'])->toEqual(125000.0);
    });
});

// ─── Investments ────────────────────────────────────────────────────

describe('extractInvestmentAccounts', function () {
    it('extracts Stocks & Shares ISA + GIA from one message', function () {
        $out = $this->extractor->extractInvestmentAccounts(
            'a Vanguard Stocks & Shares ISA £40,000 and a Hargreaves Lansdown GIA £25,000'
        );

        expect($out)->toHaveCount(2);

        $isa = collect($out)->firstWhere('account_type', 'stocks_shares_isa');
        expect($isa['provider'])->toBe('Vanguard');
        expect($isa['current_value'])->toEqual(40000.0);

        $gia = collect($out)->firstWhere('account_type', 'personal_investment_account');
        expect($gia['provider'])->toBe('Hargreaves Lansdown');
        expect($gia['current_value'])->toEqual(25000.0);
    });

    it('does not misclassify pensions as investments', function () {
        $out = $this->extractor->extractInvestmentAccounts(
            'an Aviva SIPP £100,000'
        );

        expect($out)->toBe([]);
    });

    it('recognises Venture Capital Trusts', function () {
        $out = $this->extractor->extractInvestmentAccounts('Octopus VCT £10,000');

        expect($out)->toHaveCount(1);
        expect($out[0]['account_type'])->toBe('vct');
    });
});

// ─── extractForFocus dispatch ───────────────────────────────────────

describe('extractForFocus', function () {
    it('routes to the right focus extractor', function () {
        $message = 'Aviva life £300k';

        expect($this->extractor->extractForFocus('protection', $message))->toHaveCount(1);
    });

    it('returns empty for unsupported focuses', function () {
        expect($this->extractor->extractForFocus('estate', 'a £500k property'))->toBe([]);
    });
});
