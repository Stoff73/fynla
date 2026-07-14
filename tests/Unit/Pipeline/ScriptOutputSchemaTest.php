<?php

declare(strict_types=1);

use App\Services\Pipeline\Prompts\ScriptOutputSchema;

it('parses valid JSON output', function () {
    $json = json_encode([
        'title' => 'Test title',
        'hook' => 'A hook that stops the scroll.',
        'script' => str_repeat('Word ', 120),
        'analogy_used' => 'A one-line analogy.',
        'key_concept' => 'The single takeaway.',
        'cta_text' => 'Have a look at Fynla.',
        'estimated_duration_seconds' => 60,
    ]);

    $data = ScriptOutputSchema::parse($json);

    expect($data['title'])->toBe('Test title')
        ->and($data['estimated_duration_seconds'])->toBe(60);
});

it('strips markdown code fences before parsing', function () {
    $json = json_encode([
        'title' => 'Test',
        'hook' => 'Hook',
        'script' => str_repeat('Word ', 120),
        'analogy_used' => 'Analogy',
        'key_concept' => 'Concept',
        'cta_text' => 'CTA',
        'estimated_duration_seconds' => 60,
    ]);

    $wrapped = "```json\n{$json}\n```";
    $data = ScriptOutputSchema::parse($wrapped);

    expect($data['title'])->toBe('Test');
});

it('rejects output missing a required field', function () {
    $json = json_encode([
        'title' => 'Test',
        'hook' => 'Hook',
        'script' => str_repeat('Word ', 120),
        'analogy_used' => 'Analogy',
        'key_concept' => 'Concept',
        'estimated_duration_seconds' => 60,
    ]);

    expect(fn () => ScriptOutputSchema::parse($json))
        ->toThrow(\RuntimeException::class, 'missing required field: cta_text');
});

it('rejects a script that is too short', function () {
    $json = json_encode([
        'title' => 'Test',
        'hook' => 'Hook',
        'script' => 'One short sentence, not enough words.',
        'analogy_used' => 'Analogy',
        'key_concept' => 'Concept',
        'cta_text' => 'CTA',
        'estimated_duration_seconds' => 60,
    ]);

    expect(fn () => ScriptOutputSchema::parse($json))
        ->toThrow(\RuntimeException::class, 'at least 100 words');
});

it('rejects an out-of-range duration', function () {
    $json = json_encode([
        'title' => 'Test',
        'hook' => 'Hook',
        'script' => str_repeat('Word ', 120),
        'analogy_used' => 'Analogy',
        'key_concept' => 'Concept',
        'cta_text' => 'CTA',
        'estimated_duration_seconds' => 30,
    ]);

    expect(fn () => ScriptOutputSchema::parse($json))
        ->toThrow(\RuntimeException::class, '45–75');
});
