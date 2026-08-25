<?php

declare(strict_types=1);

use App\Services\AI\StructuredResponseValidator;

it('collapses three or more consecutive normalised-identical response blocks', function (): void {
    $validator = new StructuredResponseValidator;
    $response = implode("\n\n", [
        'I need a little more information.',
        '  i NEED a little   more information.  ',
        "I need a little\nmore information.",
        'I need a little more information.',
    ]);

    $result = $validator->sanitiseWithViolations($response);

    expect($result['response'])->toBe('I need a little more information.')
        ->and($result['violations'])->toBe([[
            'rule' => 'repetition_collapsed',
            'detail' => 'Collapsed consecutive repeated response blocks',
            'severity' => 'high',
        ]])
        ->and($validator->sanitise($response))->toBe($result['response']);
});

it('preserves two repeated blocks because the collapse threshold is three', function (): void {
    $validator = new StructuredResponseValidator;
    $response = "Keep this paragraph.\n\nKeep this paragraph.";

    expect($validator->sanitiseWithViolations($response))->toBe([
        'response' => $response,
        'violations' => [],
    ]);
});

it('preserves list blocks whose items differ after normalisation', function (): void {
    $validator = new StructuredResponseValidator;
    $response = "- Add £100 to savings\n\n- Add £200 to your pension\n\n- Review in six months";

    expect($validator->sanitiseWithViolations($response))->toBe([
        'response' => $response,
        'violations' => [],
    ]);
});

it('preserves the first retained block separator when collapsing a middle run', function (): void {
    $validator = new StructuredResponseValidator;
    $response = "Prefix.\n\nRepeat.\r\n\r\nRepeat.\n\n\nRepeat.\n\n\n\nSuffix.";

    expect($validator->sanitiseWithViolations($response)['response'])
        ->toBe("Prefix.\n\nRepeat.\r\n\r\nSuffix.");
});
