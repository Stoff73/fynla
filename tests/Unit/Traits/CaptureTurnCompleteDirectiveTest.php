<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;

/*
 * csjones walk 2026-10-08: the partner confirmed "It's the same one", update_record
 * wrote nothing (updated: false, "Already on file"), and the model, told only that
 * the record "is saved", replied "Updated the existing Santander … account".
 * SPEC-crud-handler-contract C5: never claim a write that did not happen.
 */
function captureDirective(array $result): ?string
{
    $agent = app(CoordinatingAgent::class);
    $persona = new ReflectionProperty($agent, 'personaOverride');
    $persona->setValue($agent, 'data_capture');
    $method = new ReflectionMethod($agent, 'captureTurnCompleteDirective');

    return $method->invoke($agent, 'update_record', $result);
}

it('tells the model nothing changed when the values already matched', function () {
    $directive = captureDirective(['success' => true, 'updated' => false, 'entity_id' => 893, 'entity_type' => 'savings_account']);

    expect($directive)->toContain('nothing was changed')
        ->and($directive)->toContain('never say it was updated');
});

it('still tells the model the record is saved after a real write', function () {
    $directive = captureDirective(['success' => true, 'updated' => true, 'entity_id' => 893, 'entity_type' => 'savings_account']);

    expect($directive)->toContain('this record is saved')
        ->and($directive)->not->toContain('nothing was changed');
});
