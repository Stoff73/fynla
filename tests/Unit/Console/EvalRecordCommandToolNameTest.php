<?php

declare(strict_types=1);

use App\Console\Commands\EvalRecordCommand;

it('extracts tool names from the tool key of SSE events', function () {
    $command = new EvalRecordCommand;
    $method = new ReflectionMethod($command, 'extractToolCallsFromEvents');

    $events = [
        ['type' => 'tool_use', 'tool' => 'get_module_analysis', 'status' => 'running'],
        ['type' => 'content', 'text' => 'hello'],
        ['type' => 'tool_use', 'tool' => 'get_tax_information', 'status' => 'running'],
    ];

    $calls = $method->invoke($command, $events);

    expect(array_column($calls, 'name'))
        ->toBe(['get_module_analysis', 'get_tax_information']);
});
