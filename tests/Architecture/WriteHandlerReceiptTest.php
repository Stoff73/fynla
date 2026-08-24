<?php

declare(strict_types=1);
use App\Constants\GateRoutes;
use App\Services\AI\Fyn\RecaptureGuard;

/**
 * SPEC-crud-handler-contract C1 + §6 acceptance 1 — every write handler returns a
 * receipt that identifies the record: `entity_type` and `entity_id` on success.
 *
 * A client cannot link to a record it cannot name, and until 2026-08-17 six
 * handlers returned neither — create_will, update_will, both power of attorney
 * handlers and create_what_if_scenario returned an `id` under a key nothing
 * downstream reads, so `HasAiChat` emitted no `entity_created` for them at all.
 */
function writeHandlerBodies(): array
{
    // Not app_path() — the Architecture suite runs without the framework booted.
    $lines = file(__DIR__.'/../../app/Agents/CoordinatingAgent.php', FILE_IGNORE_NEW_LINES);
    $starts = [];
    foreach ($lines as $i => $line) {
        if (preg_match('/function (handle(?:Create|Update|Delete)\w+)\(/', $line, $m) === 1) {
            $starts[] = [$i, $m[1]];
        }
    }
    $starts[] = [count($lines), 'EOF'];

    $bodies = [];
    for ($n = 0; $n < count($starts) - 1; $n++) {
        [$from, $name] = $starts[$n];
        $bodies[$name] = implode("\n", array_slice($lines, $from, $starts[$n + 1][0] - $from));
    }

    // handleUpdateProfile edits the user's own profile, not a record with a page
    // to link to, so it has no entity to name.
    unset($bodies['handleUpdateProfile']);

    return $bodies;
}

it('returns an identifying receipt from every write handler', function (): void {
    $missing = array_keys(array_filter(
        writeHandlerBodies(),
        fn (string $body): bool => ! str_contains($body, "'entity_type' =>")
            || ! str_contains($body, "'entity_id' =>"),
    ));

    expect($missing)->toBe(
        [],
        'These handlers return no entity_type/entity_id, so no client can link to '
        .'what they wrote and HasAiChat cannot emit an entity event for them.',
    );
});

/**
 * SPEC-crud-handler-contract §5.4 + §7.3 (CSJ) — the link points at the module
 * page listing the record. An entity Fyn can write with no page has a
 * confirmation the user cannot act on, so the two maps must stay in step.
 */
it('gives every entity Fyn writes a page to link to', function (): void {
    $entities = (new ReflectionClass(RecaptureGuard::class))->getConstant('ENTITIES');

    // A what-if scenario is not a record on a module page — its handler already
    // navigates to the scenario it just built (/planning/what-if/{id}).
    $expected = array_diff(array_keys($entities), ['what_if_scenario']);

    $unrouted = array_values(array_filter(
        $expected,
        fn (string $entityType): bool => GateRoutes::forEntityType($entityType) === null,
    ));

    expect($unrouted)->toBe([], 'Add these to GateRoutes::ENTITY_DESTINATIONS, or the confirmation has no link.');
});
