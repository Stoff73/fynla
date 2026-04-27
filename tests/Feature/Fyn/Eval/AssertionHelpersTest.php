<?php

declare(strict_types=1);

use Tests\Feature\Fyn\Eval\AssertionHelpers;

describe('AssertionHelpers — new YAML key support (S1.2.l)', function () {

    describe('assertClassificationShape', function () {
        it('passes when primary, related, and modules all match', function () {
            $expected = [
                'primary' => 'retirement_contribution',
                'related' => ['tax_optimisation', 'savings_emergency', 'affordability'],
                'modules' => ['retirement', 'tax', 'savings', 'income'],
            ];
            $actual = [
                'primary' => 'retirement_contribution',
                'related' => ['affordability', 'savings_emergency', 'tax_optimisation'], // different order
                'modules' => ['income', 'savings', 'retirement', 'tax'],                  // different order
            ];

            [$ok, $msg] = AssertionHelpers::assertClassificationShape($expected, $actual);
            expect($ok)->toBeTrue();
            expect($msg)->toBe('');
        });

        it('fails on primary mismatch', function () {
            $expected = ['primary' => 'protection_cover', 'related' => [], 'modules' => ['protection']];
            $actual = ['primary' => 'general', 'related' => [], 'modules' => []];

            [$ok, $msg] = AssertionHelpers::assertClassificationShape($expected, $actual);
            expect($ok)->toBeFalse();
            expect($msg)->toContain("primary expected 'protection_cover'");
        });

        it('fails on missing related type', function () {
            $expected = ['primary' => 'savings_emergency', 'related' => ['affordability'], 'modules' => ['savings', 'income']];
            $actual = ['primary' => 'savings_emergency', 'related' => [], 'modules' => ['savings', 'income']];

            [$ok, $msg] = AssertionHelpers::assertClassificationShape($expected, $actual);
            expect($ok)->toBeFalse();
            expect($msg)->toContain('related set mismatch');
            expect($msg)->toContain('missing=["affordability"]');
        });

        it('fails on extra module', function () {
            $expected = ['primary' => 'estate_iht', 'related' => ['property'], 'modules' => ['estate', 'property']];
            $actual = ['primary' => 'estate_iht', 'related' => ['property'], 'modules' => ['estate', 'property', 'savings']];

            [$ok, $msg] = AssertionHelpers::assertClassificationShape($expected, $actual);
            expect($ok)->toBeFalse();
            expect($msg)->toContain('modules set mismatch');
            expect($msg)->toContain('extra=["savings"]');
        });
    });

    describe('assertResponseModeMatches', function () {
        it('passes on exact match for valid mode', function () {
            [$ok, $msg] = AssertionHelpers::assertResponseModeMatches('recommendation', 'recommendation');
            expect($ok)->toBeTrue()->and($msg)->toBe('');
        });

        it('fails when expected and actual differ', function () {
            [$ok, $msg] = AssertionHelpers::assertResponseModeMatches('factual', 'recommendation');
            expect($ok)->toBeFalse();
            expect($msg)->toBe("response_mode expected 'factual', got 'recommendation'");
        });

        it('rejects an invalid expected mode', function () {
            [$ok, $msg] = AssertionHelpers::assertResponseModeMatches('chatty', 'chatty');
            expect($ok)->toBeFalse();
            expect($msg)->toContain("'chatty' is not a valid mode");
        });
    });

    describe('assertEngineCallLevelMatches', function () {
        it('passes on holistic = holistic', function () {
            [$ok] = AssertionHelpers::assertEngineCallLevelMatches('holistic', 'holistic');
            expect($ok)->toBeTrue();
        });

        it('fails when level differs', function () {
            [$ok, $msg] = AssertionHelpers::assertEngineCallLevelMatches('module', 'holistic');
            expect($ok)->toBeFalse();
            expect($msg)->toContain("expected 'module', got 'holistic'");
        });
    });

    describe('assertKycState', function () {
        it('passes for state=passed when actual.passed=true and missing empty', function () {
            $actual = ['passed' => true, 'missing' => [], 'prompt_text' => '<kyc_status>...PASSED...</kyc_status>'];
            [$ok] = AssertionHelpers::assertKycState('passed', [], $actual);
            expect($ok)->toBeTrue();
        });

        it('fails for state=passed when actual.passed=false', function () {
            $actual = ['passed' => false, 'missing' => ['Date of birth'], 'prompt_text' => ''];
            [$ok, $msg] = AssertionHelpers::assertKycState('passed', [], $actual);
            expect($ok)->toBeFalse();
            expect($msg)->toContain('actual.passed=true');
        });

        it('passes for state=bypass when passed=true and prompt_text empty', function () {
            $actual = ['passed' => true, 'missing' => [], 'prompt_text' => ''];
            [$ok] = AssertionHelpers::assertKycState('bypass', [], $actual);
            expect($ok)->toBeTrue();
        });

        it('passes for state=blocked with all expected missing items present', function () {
            $expectedMissing = [
                ['label' => 'Risk profile', 'route' => '/investment/risk-profile'],
            ];
            $actual = [
                'passed' => false,
                'missing' => ['A completed risk profile is essential to determine suitable asset allocation and investment recommendations.'],
                'prompt_text' => '<kyc_status>...BLOCKED...</kyc_status>',
            ];
            [$ok] = AssertionHelpers::assertKycState('blocked', $expectedMissing, $actual);
            expect($ok)->toBeTrue();
        });

        it('fails for state=blocked when expected missing label not present', function () {
            $expectedMissing = [['label' => 'Date of birth', 'route' => '/profile']];
            $actual = ['passed' => false, 'missing' => ['Risk profile'], 'prompt_text' => ''];
            [$ok, $msg] = AssertionHelpers::assertKycState('blocked', $expectedMissing, $actual);
            expect($ok)->toBeFalse();
            expect($msg)->toContain("expected missing 'Date of birth' not found");
        });
    });

    describe('assertToolCallsMatchRules', function () {
        it('passes when all required tools appear with matching args + path', function () {
            $expected = [
                ['tool' => 'list_records', 'args' => ['record_type' => 'life_insurance'], 'required' => true, 'result_path' => 'happy'],
                ['tool' => 'get_module_analysis', 'args' => ['module' => 'protection'], 'required' => true, 'result_path' => 'success_false', 'result_message_contains' => 'Protection profile not found'],
            ];
            $actual = [
                ['tool' => 'list_records', 'args' => ['record_type' => 'life_insurance'], 'result_path' => 'happy'],
                ['tool' => 'get_module_analysis', 'args' => ['module' => 'protection'], 'result_path' => 'success_false', 'result' => ['message' => 'Protection profile not found. Please create...']],
            ];
            [$ok] = AssertionHelpers::assertToolCallsMatchRules($expected, $actual);
            expect($ok)->toBeTrue();
        });

        it('ignores conditional tools when absent', function () {
            $expected = [
                ['tool' => 'get_module_analysis', 'args' => ['module' => 'savings'], 'required' => true, 'result_path' => 'happy'],
                ['tool' => 'get_recommendations', 'required' => false, 'condition' => 'fires only when ready'],
            ];
            $actual = [
                ['tool' => 'get_module_analysis', 'args' => ['module' => 'savings'], 'result_path' => 'happy'],
            ];
            [$ok] = AssertionHelpers::assertToolCallsMatchRules($expected, $actual);
            expect($ok)->toBeTrue();
        });

        it('fails when a required tool is absent', function () {
            $expected = [
                ['tool' => 'get_module_analysis', 'args' => ['module' => 'protection'], 'required' => true, 'result_path' => 'happy'],
            ];
            $actual = [];
            [$ok, $msg] = AssertionHelpers::assertToolCallsMatchRules($expected, $actual);
            expect($ok)->toBeFalse();
            expect($msg)->toContain("required tool 'get_module_analysis'");
        });

        it('fails when result_path differs', function () {
            $expected = [
                ['tool' => 'get_module_analysis', 'args' => ['module' => 'retirement'], 'required' => true, 'result_path' => 'success_false'],
            ];
            $actual = [
                ['tool' => 'get_module_analysis', 'args' => ['module' => 'retirement'], 'result_path' => 'happy'],
            ];
            [$ok, $msg] = AssertionHelpers::assertToolCallsMatchRules($expected, $actual);
            expect($ok)->toBeFalse();
            expect($msg)->toContain('result_path=success_false');
        });

        it('rejects an invalid result_path enum', function () {
            $expected = [['tool' => 'list_records', 'required' => true, 'result_path' => 'banana']];
            $actual = [];
            [$ok, $msg] = AssertionHelpers::assertToolCallsMatchRules($expected, $actual);
            expect($ok)->toBeFalse();
            expect($msg)->toContain("invalid result_path 'banana'");
        });
    });

    describe('assertToolCallsAbsent', function () {
        it('passes when none of the forbidden tools appear', function () {
            $forbidden = ['create_protection_policy', 'update_record'];
            $actual = [['tool' => 'get_module_analysis'], ['tool' => 'list_records']];
            [$ok] = AssertionHelpers::assertToolCallsAbsent($forbidden, $actual);
            expect($ok)->toBeTrue();
        });

        it('fails when a forbidden tool was called', function () {
            $forbidden = ['navigate_to_page'];
            $actual = [['tool' => 'list_records'], ['tool' => 'navigate_to_page', 'args' => ['route_path' => '/x']]];
            [$ok, $msg] = AssertionHelpers::assertToolCallsAbsent($forbidden, $actual);
            expect($ok)->toBeFalse();
            expect($msg)->toContain("forbidden tool 'navigate_to_page' was called");
        });
    });

    describe('assertSseStructural', function () {
        it('passes when must_contain_types all present and counts within bounds', function () {
            $rules = [
                'must_contain_types' => ['title', 'content', 'tool_use', 'done'],
                'must_emit_exactly_once' => ['done', 'title'],
                'must_not_emit' => ['persona_state_change', 'handoff'],
                'content_event_minimum' => 5,
                'tool_use_count_min' => 1,
                'tool_use_count_max' => 4,
            ];
            $actual = array_merge(
                [['type' => 'title']],
                array_fill(0, 8, ['type' => 'content']),
                [['type' => 'tool_use'], ['type' => 'tool_use']],
                [['type' => 'done']],
            );
            [$ok] = AssertionHelpers::assertSseStructural($rules, $actual);
            expect($ok)->toBeTrue();
        });

        it('fails when a required type is missing', function () {
            $rules = ['must_contain_types' => ['done']];
            $actual = [['type' => 'content']];
            [$ok, $msg] = AssertionHelpers::assertSseStructural($rules, $actual);
            expect($ok)->toBeFalse();
            expect($msg)->toContain("missing type 'done'");
        });

        it('fails when a forbidden type appears', function () {
            $rules = ['must_not_emit' => ['persona_state_change']];
            $actual = [['type' => 'persona_state_change']];
            [$ok, $msg] = AssertionHelpers::assertSseStructural($rules, $actual);
            expect($ok)->toBeFalse();
            expect($msg)->toContain("forbidden type 'persona_state_change'");
        });

        it('fails when must_emit_exactly count differs', function () {
            $rules = ['must_emit_exactly' => ['entity_created' => 2]];
            $actual = [['type' => 'entity_created']];
            [$ok, $msg] = AssertionHelpers::assertSseStructural($rules, $actual);
            expect($ok)->toBeFalse();
            expect($msg)->toContain("type 'entity_created' expected exactly 2");
        });

        it('fails when content events below minimum', function () {
            $rules = ['content_event_minimum' => 5];
            $actual = [['type' => 'content'], ['type' => 'content']];
            [$ok, $msg] = AssertionHelpers::assertSseStructural($rules, $actual);
            expect($ok)->toBeFalse();
            expect($msg)->toContain('expected ≥5, got 2');
        });

        it('fails when tool_use exceeds max', function () {
            $rules = ['tool_use_count_max' => 1];
            $actual = [['type' => 'tool_use'], ['type' => 'tool_use']];
            [$ok, $msg] = AssertionHelpers::assertSseStructural($rules, $actual);
            expect($ok)->toBeFalse();
            expect($msg)->toContain('expected ≤1, got 2');
        });
    });

    describe('assertAssistantTextRules', function () {
        it('passes when all rules satisfied', function () {
            $rules = [
                'must_contain_substrings' => ['For regulated advice'],
                'must_contain_at_least_one_of' => [
                    ['1.8 months', 'less than 2 months'],
                    ['£4,500', '£4500'],
                ],
                'must_not_contain_substrings' => ['I think you should'],
                'minimum_length_chars' => 50,
                'maximum_length_chars' => 500,
            ];
            $text = 'Your runway is 1.8 months against a £2,500 monthly target. With £4,500 saved, you are below the conventional 3-month buffer. For regulated advice personal to your circumstances, speak to a qualified financial adviser.';
            [$ok] = AssertionHelpers::assertAssistantTextRules($rules, $text);
            expect($ok)->toBeTrue();
        });

        it('fails when a required substring is missing', function () {
            $rules = ['must_contain_substrings' => ['For regulated advice']];
            $text = 'Some other text.';
            [$ok, $msg] = AssertionHelpers::assertAssistantTextRules($rules, $text);
            expect($ok)->toBeFalse();
            expect($msg)->toContain("missing required substring 'For regulated advice'");
        });

        it('fails when a group has zero matches', function () {
            $rules = ['must_contain_at_least_one_of' => [['retirement profile', 'target retirement age']]];
            $text = 'Generic text without retirement details.';
            [$ok, $msg] = AssertionHelpers::assertAssistantTextRules($rules, $text);
            expect($ok)->toBeFalse();
            expect($msg)->toContain('none of group');
        });

        it('fails when a forbidden substring appears', function () {
            $rules = ['must_not_contain_substrings' => ['I think you should']];
            $text = 'I think you should top up your ISA.';
            [$ok, $msg] = AssertionHelpers::assertAssistantTextRules($rules, $text);
            expect($ok)->toBeFalse();
            expect($msg)->toContain("forbidden substring 'I think you should'");
        });

        it('fails when length below minimum', function () {
            $rules = ['minimum_length_chars' => 100];
            $text = 'short';
            [$ok, $msg] = AssertionHelpers::assertAssistantTextRules($rules, $text);
            expect($ok)->toBeFalse();
            expect($msg)->toContain('below minimum 100');
        });

        it('passes exact_match against the canonical refusal', function () {
            $refusal = "I'm able to help you with your finances. Medical advice is out of scope.";
            $rules = ['exact_match' => $refusal];
            [$ok] = AssertionHelpers::assertAssistantTextRules($rules, $refusal);
            expect($ok)->toBeTrue();
        });

        it('fails exact_match on any deviation', function () {
            $rules = ['exact_match' => 'Hello.'];
            [$ok, $msg] = AssertionHelpers::assertAssistantTextRules($rules, 'Hello!');
            expect($ok)->toBeFalse();
            expect($msg)->toContain('exact_match failed');
        });
    });

    describe('assertTimingBudgetWithinPath', function () {
        it('passes when actual ms is below the per-path budget', function () {
            $budget = ['anthropic' => ['happy' => 7000, 'kyc_blocked' => 5000], 'xai' => ['happy' => 16000]];
            [$ok] = AssertionHelpers::assertTimingBudgetWithinPath($budget, 'anthropic', 'happy', 5500.0);
            expect($ok)->toBeTrue();
        });

        it('fails when actual exceeds the budget', function () {
            $budget = ['anthropic' => ['happy' => 7000], 'xai' => ['happy' => 16000]];
            [$ok, $msg] = AssertionHelpers::assertTimingBudgetWithinPath($budget, 'xai', 'happy', 18000.0);
            expect($ok)->toBeFalse();
            expect($msg)->toContain('xai/happy actual 18000ms exceeds budget 16000ms');
        });

        it('fails when provider is missing from the budget', function () {
            $budget = ['anthropic' => ['happy' => 7000]];
            [$ok, $msg] = AssertionHelpers::assertTimingBudgetWithinPath($budget, 'xai', 'happy', 1.0);
            expect($ok)->toBeFalse();
            expect($msg)->toContain("missing provider 'xai'");
        });

        it('fails when path is missing for a provider', function () {
            $budget = ['anthropic' => ['happy' => 7000]];
            [$ok, $msg] = AssertionHelpers::assertTimingBudgetWithinPath($budget, 'anthropic', 'kyc_blocked', 1.0);
            expect($ok)->toBeFalse();
            expect($msg)->toContain("missing path 'kyc_blocked'");
        });
    });

    describe('assertNoDeprecatedKeys', function () {
        it('passes on a clean payload', function () {
            $payload = [
                'expected_classification_shape' => ['primary' => 'savings_emergency'],
                'expected_sse_events' => ['must_contain_types' => ['done']],
                'timing_budget_ms' => ['anthropic' => ['happy' => 7000]],
            ];
            [$ok] = AssertionHelpers::assertNoDeprecatedKeys($payload);
            expect($ok)->toBeTrue();
        });

        it('rejects expected_advice_response', function () {
            $payload = ['expected_advice_response' => ['signposting_suffix_present' => true]];
            [$ok, $msg] = AssertionHelpers::assertNoDeprecatedKeys($payload);
            expect($ok)->toBeFalse();
            expect($msg)->toContain("expected_advice_response");
            expect($msg)->toContain('S1.6.a');
        });

        it('rejects flat expected_classifications', function () {
            $payload = ['expected_classifications' => ['protection_cover']];
            [$ok, $msg] = AssertionHelpers::assertNoDeprecatedKeys($payload);
            expect($ok)->toBeFalse();
            expect($msg)->toContain('expected_classification_shape');
        });

        it('rejects single-int timing_budget_ms', function () {
            $payload = ['timing_budget_ms' => 5000];
            [$ok, $msg] = AssertionHelpers::assertNoDeprecatedKeys($payload);
            expect($ok)->toBeFalse();
            expect($msg)->toContain('per-provider per-path map');
        });

        it('rejects expected_sse_events as a flat list', function () {
            $payload = ['expected_sse_events' => [['type' => 'content'], ['type' => 'done']]];
            [$ok, $msg] = AssertionHelpers::assertNoDeprecatedKeys($payload);
            expect($ok)->toBeFalse();
            expect($msg)->toContain('structural rules block');
        });
    });

    describe('integration — the 10 rewritten YAMLs all pass assertNoDeprecatedKeys', function () {
        it('every rewritten YAML in 01-query-types and 03-multi-entity has no legacy keys', function () {
            $base = __DIR__.'/scenarios';
            $paths = array_merge(
                glob("{$base}/01-query-types/advice_*.yaml") ?: [],
                glob("{$base}/03-multi-entity/*.yaml") ?: [],
            );

            expect($paths)->not->toBeEmpty();

            foreach ($paths as $path) {
                $payload = Symfony\Component\Yaml\Yaml::parseFile($path);
                [$ok, $msg] = AssertionHelpers::assertNoDeprecatedKeys($payload);
                expect($ok)->toBeTrue("{$path}: {$msg}");
            }
        });
    });

});
