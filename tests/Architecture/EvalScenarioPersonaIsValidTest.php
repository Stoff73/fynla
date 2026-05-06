<?php

declare(strict_types=1);

$root = realpath(__DIR__.'/../../');

it('every scenario binds to a known preview persona', function () use ($root): void {
    $valid = [
        'young_family',
        'peak_earners',
        'entrepreneur',
        'young_saver',
        'retired_couple',
        'student',
    ];

    $files = glob($root.'/tests/Feature/Fyn/Eval/scenarios/*/*.json') ?: [];
    expect($files)->not->toBeEmpty('no scenario JSON files found');

    foreach ($files as $file) {
        if (basename($file) === '_schema.json') {
            continue;
        }
        $data = json_decode((string) file_get_contents($file), true);
        expect($data['persona'] ?? null)->toBeIn($valid, 'invalid persona in '.basename($file));
    }
});
