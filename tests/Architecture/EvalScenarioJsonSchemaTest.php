<?php

declare(strict_types=1);

use JsonSchema\Validator;

$root = realpath(__DIR__.'/../../');

it('every scenario JSON validates against _schema.json', function () use ($root): void {
    $schema = json_decode((string) file_get_contents($root.'/tests/Feature/Fyn/Eval/scenarios/_schema.json'));
    $validator = new Validator;

    $files = glob($root.'/tests/Feature/Fyn/Eval/scenarios/*/mitchell_*.json') ?: [];
    expect($files)->not->toBeEmpty('no mitchell scenario files found — did Task 13 run?');

    foreach ($files as $file) {
        $data = json_decode((string) file_get_contents($file));
        $validator->validate($data, $schema);
        expect($validator->isValid())->toBeTrue(
            'schema violation in '.basename($file).': '.json_encode($validator->getErrors())
        );
        $validator->reset();
    }
});
