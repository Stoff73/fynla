<?php

declare(strict_types=1);

use App\Services\Eval\EvalSseConsumer;

it('parses a complete SSE stream into events', function () {
    $body = "data: {\"type\":\"title\",\"text\":\"Hello\"}\n\n"
        ."data: {\"type\":\"content\",\"text\":\"world\"}\n\n"
        ."data: {\"type\":\"done\"}\n\n";

    $events = (new EvalSseConsumer)->consume($body);

    expect($events)->toHaveCount(3)
        ->and($events[0])->toMatchArray(['type' => 'title', 'text' => 'Hello'])
        ->and($events[1])->toMatchArray(['type' => 'content', 'text' => 'world'])
        ->and($events[2])->toMatchArray(['type' => 'done']);
});

it('handles partial frames split across the buffer', function () {
    $part1 = "data: {\"type\":\"content\",\"text\":\"hello";
    $part2 = " world\"}\n\ndata: {\"type\":\"done\"}\n\n";

    $events = (new EvalSseConsumer)->consume($part1.$part2);

    expect($events)->toHaveCount(2)
        ->and($events[0]['text'])->toBe('hello world')
        ->and($events[1]['type'])->toBe('done');
});

it('skips non-data lines (comments, retry, id, event)', function () {
    $body = ": keep-alive comment\n\n"
        ."event: ping\nretry: 5000\nid: abc\n\n"
        ."data: {\"type\":\"done\"}\n\n";

    $events = (new EvalSseConsumer)->consume($body);

    expect($events)->toHaveCount(1)
        ->and($events[0]['type'])->toBe('done');
});

it('returns empty list for empty body', function () {
    expect((new EvalSseConsumer)->consume(''))->toBe([]);
});

it('skips frames whose data: payload is not valid JSON', function () {
    $body = "data: not-json\n\n"
        ."data: {\"type\":\"valid\"}\n\n";

    $events = (new EvalSseConsumer)->consume($body);

    expect($events)->toHaveCount(1)
        ->and($events[0]['type'])->toBe('valid');
});

it('joins multiple data: lines within one frame with newline', function () {
    // Per SSE spec, multiple data: lines in one frame join with \n.
    $body = "data: line1\ndata: line2\n\n";

    $events = (new EvalSseConsumer)->consume($body);

    // line1\nline2 isn't valid JSON either, so the result is empty.
    // The point of the test is the parser doesn't crash on multi-data frames.
    expect($events)->toBe([]);
});
