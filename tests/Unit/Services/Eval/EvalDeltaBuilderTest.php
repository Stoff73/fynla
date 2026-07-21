<?php

declare(strict_types=1);

use App\Models\EvalProviderRun;
use App\Services\Eval\EvalDeltaBuilder;

/**
 * Unit tests for EvalDeltaBuilder — the live grader that compares a
 * recorded EvalProviderRun against the new-shape YAML expectations.
 *
 * No DB seeding: EvalProviderRun is constructed in memory with
 * forceFill so the builder sees realistic shape without requiring
 * RefreshDatabase. The QueryClassifier dependency is real (it's
 * stateless and cheap).
 */
beforeEach(function () {
    $this->builder = app(EvalDeltaBuilder::class);
});

function makeRun(array $overrides = []): EvalProviderRun
{
    $defaults = [
        'provider' => 'anthropic',
        'model' => 'claude-haiku-4-5-20251001',
        'user_message' => 'Am I covered enough for protection?',
        'assistant_text' => 'For regulated advice personal to your circumstances, speak to a qualified financial adviser. You need a protection profile.',
        'tool_calls' => [
            ['name' => 'list_records', 'args' => ['record_type' => 'life_insurance'], 'result' => 'records: [...]'],
            ['name' => 'get_module_analysis', 'args' => ['module' => 'protection'], 'result' => 'module: protection, success: false, message: Protection profile not found. Please create a protection profile first.'],
        ],
        'sse_event_types' => ['title' => 1, 'content' => 12, 'tool_use' => 2, 'done' => 1],
        'sse_event_count' => 16,
        'forbidden_hits' => [],
        'duration_ms' => 5500,
    ];

    $run = new EvalProviderRun;
    $run->forceFill(array_merge($defaults, $overrides));

    return $run;
}

function loadScenarioJson(string $relativePath): array
{
    $base = __DIR__.'/../../../Feature/Fyn/Eval/scenarios';

    return json_decode((string) file_get_contents("{$base}/{$relativePath}"), true);
}

describe('EvalDeltaBuilder', function () {

    describe('legacy YAML rejection', function () {
        it('short-circuits on expected_advice_response and surfaces a deprecation hint', function () {
            $expected = ['expected_advice_response' => ['signposting_suffix_present' => true]];
            $delta = $this->builder->build(makeRun(), $expected);

            expect($delta['passes'])->toBeFalse();
            expect($delta['failures'])->toHaveKey('deprecated_yaml');
            expect($delta['failures']['deprecated_yaml'])->toContain('expected_advice_response');
            expect($delta['suggested_fixes'][0])->toContain('Re-record');
        });

        it('short-circuits on flat expected_classifications', function () {
            $expected = ['expected_classifications' => ['protection_cover']];
            $delta = $this->builder->build(makeRun(), $expected);

            expect($delta['passes'])->toBeFalse();
            expect($delta['failures']['deprecated_yaml'])->toContain('expected_classification_shape');
        });

        it('short-circuits on single-int timing_budget_ms', function () {
            $expected = ['timing_budget_ms' => 5000];
            $delta = $this->builder->build(makeRun(), $expected);

            expect($delta['passes'])->toBeFalse();
            expect($delta['failures']['deprecated_yaml'])->toContain('per-provider per-path');
        });
    });

    describe('classification + response_mode + engine_call_level', function () {
        it('passes when actual classification matches expected shape', function () {
            $expected = [
                'expected_classification_shape' => [
                    'primary' => 'protection_cover',
                    'related' => [],
                    'modules' => ['protection'],
                ],
                'expected_response_mode' => 'recommendation',
                'expected_engine_call_level' => 'module',
            ];
            $delta = $this->builder->build(makeRun(), $expected);

            expect($delta['failures'])->not->toHaveKey('classification_shape');
            expect($delta['failures'])->not->toHaveKey('response_mode');
            expect($delta['failures'])->not->toHaveKey('engine_call_level');
            expect($delta['actual_classification']['primary'])->toBe('protection_cover');
            expect($delta['actual_response_mode'])->toBe('recommendation');
            expect($delta['actual_engine_call_level'])->toBe('module');
        });

        it('fails when classification shape mismatches', function () {
            $expected = [
                'expected_classification_shape' => [
                    'primary' => 'savings_emergency',     // wrong primary for the user message
                    'related' => [],
                    'modules' => ['savings'],
                ],
            ];
            $delta = $this->builder->build(makeRun(), $expected);

            expect($delta['passes'])->toBeFalse();
            expect($delta['failures'])->toHaveKey('classification_shape');
            expect($delta['failures']['classification_shape'])->toContain("primary expected 'savings_emergency'");
        });
    });

    describe('tool calls — required + result_path', function () {
        it('passes when required tool fired with matching args + result_path', function () {
            $expected = [
                'expected_tool_calls' => [
                    ['tool' => 'list_records', 'args' => ['record_type' => 'life_insurance'], 'required' => true, 'result_path' => 'happy'],
                    ['tool' => 'get_module_analysis', 'args' => ['module' => 'protection'], 'required' => true, 'result_path' => 'success_false', 'result_message_contains' => 'Protection profile not found'],
                    ['tool' => 'get_recommendations', 'required' => false],
                ],
            ];
            $delta = $this->builder->build(makeRun(), $expected);

            expect($delta['failures'])->not->toHaveKey('tool_calls');
            expect($delta['detected_result_path'])->toBe('success_false');
        });

        it('fails when result_path differs (success_false vs happy)', function () {
            // Run reports success_false, YAML expects happy.
            $expected = [
                'expected_tool_calls' => [
                    ['tool' => 'get_module_analysis', 'args' => ['module' => 'protection'], 'required' => true, 'result_path' => 'happy'],
                ],
            ];
            $delta = $this->builder->build(makeRun(), $expected);

            expect($delta['passes'])->toBeFalse();
            expect($delta['failures'])->toHaveKey('tool_calls');
            expect($delta['failures']['tool_calls'])->toContain('result_path=happy');
        });
    });

    describe('forbidden tools + outputs', function () {
        it('flags forbidden tools that fired', function () {
            $run = makeRun([
                'tool_calls' => [
                    ['name' => 'create_protection_policy', 'args' => ['provider' => 'X', 'sum_assured' => 1], 'result' => 'created'],
                ],
            ]);
            $expected = ['forbidden_tools' => ['create_protection_policy']];
            $delta = $this->builder->build($run, $expected);

            expect($delta['passes'])->toBeFalse();
            expect($delta['failures'])->toHaveKey('forbidden_tools');
            expect($delta['forbidden_tool_hits'])->toContain('create_protection_policy');
        });

        it('flags forbidden phrases from forbidden_hits', function () {
            $run = makeRun(['forbidden_hits' => ['I think you should']]);
            $delta = $this->builder->build($run, []);

            expect($delta['failures'])->toHaveKey('forbidden_outputs');
            expect($delta['forbidden_output_hits'])->toContain('I think you should');
        });
    });

    describe('expected_tool_calls_absent', function () {
        it('flags when a tool that should have been suppressed actually fired', function () {
            $run = makeRun([
                'tool_calls' => [
                    ['name' => 'get_module_analysis', 'args' => ['module' => 'investment'], 'result' => 'module: investment, ...'],
                ],
            ]);
            $expected = ['expected_tool_calls_absent' => ['get_module_analysis', 'get_recommendations']];
            $delta = $this->builder->build($run, $expected);

            expect($delta['passes'])->toBeFalse();
            expect($delta['failures'])->toHaveKey('tool_calls_absent');
        });
    });

    describe('per-provider per-path timing budget', function () {
        it('passes when actual ms within the path budget', function () {
            $run = makeRun(['provider' => 'anthropic', 'duration_ms' => 5500]);
            $expected = [
                'timing_budget_ms' => [
                    'anthropic' => ['success_false' => 6000, 'happy' => 7000],
                    'xai' => ['success_false' => 14000],
                ],
            ];
            $delta = $this->builder->build($run, $expected);

            expect($delta['failures'])->not->toHaveKey('timing_budget');
        });

        it('fails when actual ms exceeds the path budget', function () {
            $run = makeRun(['provider' => 'xai', 'duration_ms' => 15000]);
            $expected = [
                'timing_budget_ms' => [
                    'anthropic' => ['success_false' => 6000],
                    'xai' => ['success_false' => 14000],
                ],
            ];
            $delta = $this->builder->build($run, $expected);

            expect($delta['passes'])->toBeFalse();
            expect($delta['failures'])->toHaveKey('timing_budget');
            expect($delta['failures']['timing_budget'])->toContain('xai/success_false');
        });
    });

    describe('SSE structural rules', function () {
        it('passes the structural rules against captured event counts', function () {
            $expected = [
                'expected_sse_events' => [
                    'must_contain_types' => ['title', 'content', 'tool_use', 'done'],
                    'must_emit_exactly_once' => ['done', 'title'],
                    'must_not_emit' => ['persona_state_change'],
                    'content_event_minimum' => 5,
                    'tool_use_count_min' => 1,
                    'tool_use_count_max' => 4,
                ],
            ];
            $delta = $this->builder->build(makeRun(), $expected);

            expect($delta['failures'])->not->toHaveKey('sse_events');
        });

        it('fails when forbidden SSE event appears', function () {
            $run = makeRun(['sse_event_types' => ['done' => 1, 'persona_state_change' => 1]]);
            $expected = ['expected_sse_events' => ['must_not_emit' => ['persona_state_change']]];
            $delta = $this->builder->build($run, $expected);

            expect($delta['failures'])->toHaveKey('sse_events');
            expect($delta['failures']['sse_events'])->toContain('persona_state_change');
        });
    });

    describe('assistant text rules', function () {
        it('passes when text contains required substring + at-least-one group', function () {
            $expected = [
                'expected_assistant_text' => [
                    'must_contain_substrings' => ['For regulated advice'],
                    'must_contain_at_least_one_of' => [['protection profile', 'spouse', 'dependants']],
                    'must_not_contain_substrings' => ['I think you should'],
                    'minimum_length_chars' => 50,
                ],
            ];
            $delta = $this->builder->build(makeRun(), $expected);

            expect($delta['failures'])->not->toHaveKey('assistant_text');
        });

        it('fails when forbidden phrase appears', function () {
            $run = makeRun(['assistant_text' => 'I think you should buy more cover.']);
            $expected = ['expected_assistant_text' => ['must_not_contain_substrings' => ['I think you should']]];
            $delta = $this->builder->build($run, $expected);

            expect($delta['failures'])->toHaveKey('assistant_text');
        });
    });

    describe('detected_result_path picks the dominant blocking path', function () {
        it('returns kyc_blocked when YAML state is blocked', function () {
            $expected = ['expected_kyc_state' => 'blocked'];
            $delta = $this->builder->build(makeRun(), $expected);
            expect($delta['detected_result_path'])->toBe('kyc_blocked');
        });

        it('returns success_false when any captured tool result has success: false', function () {
            $delta = $this->builder->build(makeRun(), []);
            expect($delta['detected_result_path'])->toBe('success_false');
        });

        it('returns happy when all results are clean', function () {
            $run = makeRun([
                'tool_calls' => [['name' => 'list_records', 'args' => ['record_type' => 'savings_account'], 'result' => 'records: [...]']],
            ]);
            $delta = $this->builder->build($run, []);
            expect($delta['detected_result_path'])->toBe('happy');
        });
    });

    describe('legacy delta fields preserved for existing Vue dashboard', function () {
        it('returns expected_tools, actual_tools, missing_tools, extra_tools', function () {
            $expected = [
                'expected_tool_calls' => [
                    ['tool' => 'list_records', 'args' => ['entity_type' => 'life_insurance'], 'required' => true],
                    ['tool' => 'get_module_analysis', 'args' => ['module' => 'protection'], 'required' => true],
                    ['tool' => 'get_recommendations', 'required' => false],
                ],
            ];
            $delta = $this->builder->build(makeRun(), $expected);

            expect($delta)->toHaveKey('expected_tools');
            expect($delta)->toHaveKey('actual_tools');
            expect($delta)->toHaveKey('missing_tools');
            expect($delta)->toHaveKey('extra_tools');
            expect($delta['expected_tools'])->toBe(['list_records', 'get_module_analysis']);
            expect($delta['actual_tools'])->toContain('list_records', 'get_module_analysis');
            expect($delta['missing_tools'])->toBe([]);
        });

        it('only flags REQUIRED tools as missing (conditional tools never fail the legacy missing_tools check)', function () {
            $expected = [
                'expected_tool_calls' => [
                    ['tool' => 'get_module_analysis', 'args' => ['module' => 'protection'], 'required' => true],
                    ['tool' => 'get_recommendations', 'required' => false],
                ],
            ];
            $run = makeRun([
                'tool_calls' => [
                    ['name' => 'get_module_analysis', 'args' => ['module' => 'protection'], 'result' => 'happy'],
                ],
            ]);
            $delta = $this->builder->build($run, $expected);

            expect($delta['missing_tools'])->toBe([])    // get_recommendations is conditional
                ->and($delta['expected_tools'])->toBe(['get_module_analysis']);    // only required
        });

        it('returns timing_status + timing_budget_ms (single int) + timing_overage_ms from per-path budget', function () {
            $expected = [
                'timing_budget_ms' => [
                    'anthropic' => ['success_false' => 8000],
                    'xai' => ['success_false' => 32000],
                ],
            ];
            $delta = $this->builder->build(makeRun(['provider' => 'anthropic', 'duration_ms' => 6804]), $expected);

            expect($delta['timing_status'])->toBe('within_budget');
            expect($delta['timing_budget_ms'])->toBe(8000);
            expect($delta['timing_overage_ms'])->toBe(0);
        });

        it('returns expected_sse_event_types + missing_sse_event_types from must_contain_types', function () {
            $expected = [
                'expected_sse_events' => [
                    'must_contain_types' => ['title', 'content', 'tool_use', 'done'],
                ],
            ];
            $delta = $this->builder->build(makeRun(), $expected);

            expect($delta['expected_sse_event_types'])->toBe(['title', 'content', 'tool_use', 'done']);
            expect($delta['missing_sse_event_types'])->toBe([]);
        });

        it('falls back to legacy [{type: T}] list shape when YAML still uses old SSE shape', function () {
            $expected = [
                'expected_sse_events' => [
                    ['type' => 'content'],
                    ['type' => 'done'],
                ],
            ];
            // Bypass the assertNoDeprecatedKeys gate by setting only one of
            // the deprecated shapes — old expected_sse_events list will
            // trip assertNoDeprecatedKeys, so this test just confirms the
            // shellDelta still surfaces a sensible structure on rejection.
            $delta = $this->builder->build(makeRun(), $expected);
            expect($delta)->toHaveKey('expected_sse_event_types');
            expect($delta['failures'])->toHaveKey('deprecated_yaml');
        });

        it('shellDelta on deprecation still populates legacy fields so Vue does not crash on undefined reads', function () {
            $expected = ['expected_advice_response' => ['signposting_suffix_present' => true]];
            $delta = $this->builder->build(makeRun(), $expected);

            // Even on deprecation short-circuit, every legacy key the
            // dashboard reads must be present (with safe defaults).
            expect($delta)->toHaveKeys([
                'expected_tools', 'actual_tools', 'missing_tools', 'extra_tools',
                'timing_budget_ms', 'timing_status', 'timing_overage_ms',
                'expected_sse_event_types', 'missing_sse_event_types',
                'forbidden_tool_hits', 'forbidden_output_hits',
                'duration_ms', 'diagnosis_hints', 'suggested_fixes',
            ]);
            expect($delta['actual_tools'])->toContain('get_module_analysis', 'list_records');
        });

        it('accepts the full controller-shape input ({_full: parsedYaml, ...legacy}) and reads from _full', function () {
            $controllerExpected = [
                // Legacy subset (the controller exposes this on the response payload).
                'tool_calls' => [['tool' => 'whatever']],
                'forbidden_tools' => ['x'],
                // Full parsed YAML hidden under _full — builder reads new keys from here.
                '_full' => [
                    'expected_tool_calls' => [
                        ['tool' => 'get_module_analysis', 'args' => ['module' => 'protection'], 'required' => true, 'result_path' => 'success_false', 'result_message_contains' => 'Protection profile not found'],
                    ],
                ],
            ];
            $delta = $this->builder->build(makeRun(), $controllerExpected);

            expect($delta['failures'])->not->toHaveKey('tool_calls');
            expect($delta['detected_result_path'])->toBe('success_false');
        });
    });

    describe('gradeEngineTrace', function () {
        it('flags must_contain entries the trace does not match', function () {
            $trace = [
                ['event' => 'GateChecked', 'gate' => 'kyc', 'module' => 'global', 'passed' => true],
            ];
            $expected = [
                'must_contain' => [
                    ['event' => 'GateChecked', 'gate' => 'kyc', 'module' => 'global', 'passed' => true],
                    ['event' => 'EngineCalled', 'engine' => 'protection_analysis'],
                ],
            ];

            $result = $this->builder->gradeEngineTrace($trace, $expected);

            expect($result['must_contain_misses'])->toHaveCount(1)
                ->and($result['must_contain_misses'][0]['engine'])->toBe('protection_analysis')
                ->and($result['must_not_contain_hits'])->toBeEmpty()
                ->and($result['ordered_violations'])->toBeEmpty();
        });

        it('flags must_not_contain entries the trace does match', function () {
            $trace = [
                ['event' => 'EngineCalled', 'engine' => 'orchestrate_analysis'],
            ];
            $expected = [
                'must_not_contain' => [
                    ['event' => 'EngineCalled', 'engine' => 'orchestrate_analysis'],
                ],
            ];

            $result = $this->builder->gradeEngineTrace($trace, $expected);

            expect($result['must_not_contain_hits'])->toHaveCount(1)
                ->and($result['must_contain_misses'])->toBeEmpty();
        });

        it('flags ordering violations when expected events appear out of order', function () {
            $trace = [
                ['event' => 'EngineCalled', 'engine' => 'protection_analysis'],
                ['event' => 'AgentDecision', 'decisionPoint' => 'classify_query'],
            ];
            $expected = [
                'ordered' => [
                    'AgentDecision:classify_query',
                    'EngineCalled:protection_analysis',
                ],
            ];

            $result = $this->builder->gradeEngineTrace($trace, $expected);

            expect($result['ordered_violations'])->not->toBeEmpty()
                ->and($result['ordered_violations'][0])->toBe('EngineCalled:protection_analysis');
        });
    });

    describe('integration — full mitchell_advice_protection_cover JSON against a happy-path run', function () {
        it('produces a structured failures map for the JSON scenario', function () {
            $expected = loadScenarioJson('01-query-types/mitchell_advice_protection_cover.json');
            $delta = $this->builder->build(makeRun(), $expected);

            // Assert the builder ran end-to-end without throwing and surfaces
            // the new fields. The run fixture matches the YAML's success_false
            // shape, so most assertions should pass.
            expect($delta)->toHaveKey('failures');
            expect($delta)->toHaveKey('detected_result_path');
            expect($delta)->toHaveKey('actual_classification');
            expect($delta)->toHaveKey('actual_response_mode');
            expect($delta['detected_result_path'])->toBe('success_false');
            expect($delta['actual_classification']['primary'])->toBe('protection_cover');
            expect($delta['actual_response_mode'])->toBe('recommendation');
        });
    });

});
