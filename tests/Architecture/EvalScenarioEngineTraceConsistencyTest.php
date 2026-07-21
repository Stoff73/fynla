<?php

declare(strict_types=1);

$root = realpath(__DIR__.'/../../');

it('every expected_engine_trace entry references a valid engine, gate, or decisionPoint', function () use ($root): void {
    $validEngines = [
        'orchestrate_analysis',
        'protection_analysis',
        'savings_analysis',
        'investment_analysis',
        'retirement_analysis',
        'estate_analysis',
        'goals_analysis',
        'protection_recommendation',
        'savings_recommendation',
        'investment_recommendation',
        'retirement_recommendation',
        'estate_recommendation',
        'goals_recommendation',
    ];
    $validGates = ['kyc', 'data_readiness', 'profile_gate', 'recommendation_eligibility'];
    $validDecisionPoints = [
        'classify_query',
        'response_mode',
        'engine_call_level',
        'tool_dispatch',
        'profile_gate',
        'analyze_complete',
    ];

    $files = glob($root.'/tests/Feature/Fyn/Eval/scenarios/*/*.json') ?: [];

    foreach ($files as $file) {
        if (basename($file) === '_schema.json') {
            continue;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data)) {
            continue;
        }
        $trace = $data['expected_engine_trace'] ?? [];
        if (! is_array($trace)) {
            continue;
        }

        $entries = array_merge(
            is_array($trace['must_contain'] ?? null) ? $trace['must_contain'] : [],
            is_array($trace['must_not_contain'] ?? null) ? $trace['must_not_contain'] : [],
        );

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if (isset($entry['engine'])) {
                expect($entry['engine'])->toBeIn(
                    $validEngines,
                    'unknown engine in '.basename($file).': '.$entry['engine']
                );
            }
            if (isset($entry['gate'])) {
                expect($entry['gate'])->toBeIn(
                    $validGates,
                    'unknown gate in '.basename($file).': '.$entry['gate']
                );
            }
            if (isset($entry['decisionPoint'])) {
                expect($entry['decisionPoint'])->toBeIn(
                    $validDecisionPoints,
                    'unknown decisionPoint in '.basename($file).': '.$entry['decisionPoint']
                );
            }
        }
    }
});
