<?php

declare(strict_types=1);

/**
 * Rule 20 — one backend event, three clients, all of them handling it.
 *
 * `entity_created` was consumed by all three surfaces and turned into a link by
 * none of them; native discarded the id and type at decode time so it could not
 * have. Edits and deletes had no event at all. This test is what notices when a
 * fourth event, or a fourth surface, lands on only some of them.
 *
 * It reads source rather than behaviour deliberately: the three clients have no
 * shared test harness, and a grep that fails loudly beats a contract nothing
 * checks.
 */
const ENTITY_EVENTS = ['entity_created', 'entity_updated', 'entity_deleted'];

/** Every surface that consumes the Fyn stream, and the file that does it. */
const ENTITY_EVENT_CONSUMERS = [
    'web' => 'resources/js/store/modules/aiChat.js',
    'mobile web (/m)' => 'resources/mobile/mixins/onboardingChat.js',
    'native' => 'ios-native/Fynla/Features/Fyn/FynEvent.swift',
];

it('handles every entity write event on every surface', function (): void {
    $root = dirname(__DIR__, 2);
    $missing = [];

    foreach (ENTITY_EVENT_CONSUMERS as $surface => $relativePath) {
        $source = file_get_contents($root.'/'.$relativePath);
        expect($source)->not->toBeFalse("{$relativePath} is missing — the parity list has drifted.");

        foreach (ENTITY_EVENTS as $event) {
            if (! str_contains((string) $source, $event)) {
                $missing[] = "{$surface} does not handle {$event} ({$relativePath})";
            }
        }
    }

    expect($missing)->toBe([], implode("\n  ", $missing));
});

it('emits those events from one place in the backend', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Traits/HasAiChat.php');

    // One loop over one map, not a block per event — the shape that let
    // `entity_created` exist alone for as long as it did.
    expect($source)->toContain('self::ENTITY_EVENTS');

    foreach (ENTITY_EVENTS as $event) {
        expect($source)->toContain($event);
    }
});
