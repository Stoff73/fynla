<?php

declare(strict_types=1);

namespace Tests\Browser\Helpers;

use PHPUnit\Framework\AssertionFailedError;

/**
 * SSE event assertions — used by every BS-NN scenario that needs to
 * verify what came down the chat-stream wire (`persona_state_change`,
 * `quick_replies`, `tool_use`, `entity_created`, `advice_response`,
 * `done`, etc.).
 *
 * Claude captures the request log inside the scenario via
 * `browser_network_requests`, hands the array of request objects to
 * `fromNetworkRequests()`, then calls the assertion methods to pin
 * the SSE shape per the BS-NN spec.
 *
 * The harness deliberately keeps these helpers pure PHP so they can
 * also be unit-tested against fixture inputs in `tests/Unit/Browser/`
 * if regressions appear.
 */
final class AssertSseEvents
{
    /**
     * Filter the captured network requests down to the chat-stream
     * endpoint and parse the `text/event-stream` body into a flat
     * list of decoded event objects.
     *
     * Expected request shape (matches the Playwright MCP output):
     *   [
     *     ['url' => '...', 'method' => 'POST', 'response' => ['body' => "data: {...}\n\ndata: {...}\n\n"]],
     *     ...
     *   ]
     *
     * Filters by URL substring `/api/ai-chat/conversations/`. Returns
     * an empty list if no chat-stream request was captured.
     *
     * @param  list<array<string, mixed>>  $requests
     * @return list<array<string, mixed>>
     */
    public static function fromNetworkRequests(array $requests): array
    {
        $events = [];

        foreach ($requests as $request) {
            $url = (string) ($request['url'] ?? '');
            if (! str_contains($url, '/api/ai-chat/conversations/')) {
                continue;
            }

            $body = (string) ($request['response']['body'] ?? '');
            if ($body === '') {
                continue;
            }

            // SSE frames are `data: <json>\n\n`. Split on blank lines
            // and decode each JSON payload. Comment lines starting with
            // `:` (heartbeats) are skipped silently.
            foreach (preg_split('/\r?\n\r?\n/', trim($body)) ?: [] as $frame) {
                $frame = trim($frame);
                if ($frame === '' || str_starts_with($frame, ':')) {
                    continue;
                }

                if (! str_starts_with($frame, 'data:')) {
                    continue;
                }

                $json = trim(substr($frame, 5));
                if ($json === '' || $json === '[DONE]') {
                    continue;
                }

                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $events[] = $decoded;
                }
            }
        }

        return $events;
    }

    /**
     * Hard-fail if any event in the list has the given `type`. Used by
     * BS-11 (no `persona_state_change`) and the inline-capture sub-turn
     * windows (no `quick_replies` between first content and done).
     *
     * @param  list<array<string, mixed>>  $events
     */
    public static function assertNoEventType(array $events, string $type): void
    {
        foreach ($events as $event) {
            if (($event['type'] ?? null) === $type) {
                throw new AssertionFailedError("Unexpected SSE event '{$type}' emitted");
            }
        }
    }

    /**
     * Hard-fail unless the count of events with the given `type`
     * matches `$expected`. Used by BS-09 (exactly one `advice_response`)
     * and BS-14 (one `entity_created`).
     *
     * @param  list<array<string, mixed>>  $events
     */
    public static function assertEventTypeCount(array $events, string $type, int $expected): void
    {
        $count = count(array_filter(
            $events,
            fn (array $e): bool => ($e['type'] ?? null) === $type
        ));

        if ($count !== $expected) {
            throw new AssertionFailedError(
                "Expected {$expected} '{$type}' SSE event(s), got {$count}"
            );
        }
    }

    /**
     * Hard-fail unless at least one event has the given type. Cheaper
     * variant for "this event must have happened" checks where the
     * caller doesn't care about the count.
     *
     * @param  list<array<string, mixed>>  $events
     */
    public static function assertEventTypeEmitted(array $events, string $type): void
    {
        foreach ($events as $event) {
            if (($event['type'] ?? null) === $type) {
                return;
            }
        }

        throw new AssertionFailedError("Expected at least one '{$type}' SSE event, got none");
    }

    /**
     * Slice events down to the window between the FIRST event of
     * `$startType` and the FIRST subsequent event of `$endType`. Used by
     * BS-11 to isolate the inline-capture sub-turn ("between first
     * content event and final done").
     *
     * Returns an empty list if either bookend isn't present.
     *
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    public static function windowBetween(array $events, string $startType, string $endType): array
    {
        $window = [];
        $started = false;

        foreach ($events as $event) {
            $type = $event['type'] ?? null;

            if (! $started) {
                if ($type === $startType) {
                    $started = true;
                }

                continue;
            }

            if ($type === $endType) {
                return $window;
            }

            $window[] = $event;
        }

        return $started ? $window : [];
    }
}
