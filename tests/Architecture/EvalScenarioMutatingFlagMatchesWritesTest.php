<?php

declare(strict_types=1);

$root = realpath(__DIR__.'/../../');

it('non-mutating scenarios assert expected_db_writes.expected_count: 0', function () use ($root): void {
    $files = glob($root.'/tests/Feature/Fyn/Eval/scenarios/*/*.json') ?: [];

    foreach ($files as $file) {
        if (basename($file) === '_schema.json') {
            continue;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data)) {
            continue;
        }
        if (($data['is_mutating'] ?? false) === false) {
            expect($data['expected_db_writes']['expected_count'] ?? null)
                ->toBe(0, 'non-mutating scenario '.basename($file).' should expect 0 writes');
        }
    }
});
