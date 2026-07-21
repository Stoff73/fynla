<?php

declare(strict_types=1);

use App\Events\ReferenceData\ReferenceDataUpdated;

it('carries entity_key, entity_id, changed_keys, actor_user_id as readonly props', function () {
    $event = new ReferenceDataUpdated(
        entityKey: 'tax_configuration',
        entityId: 7,
        changedKeys: ['income_tax', 'isa_allowance'],
        actorUserId: 42,
    );

    expect($event->entityKey)->toBe('tax_configuration');
    expect($event->entityId)->toBe(7);
    expect($event->changedKeys)->toBe(['income_tax', 'isa_allowance']);
    expect($event->actorUserId)->toBe(42);
});

it('accepts null actor for seeder writes', function () {
    $event = new ReferenceDataUpdated(
        entityKey: 'currency_rate',
        entityId: 3,
        changedKeys: ['rate'],
        actorUserId: null,
    );

    expect($event->actorUserId)->toBeNull();
});
