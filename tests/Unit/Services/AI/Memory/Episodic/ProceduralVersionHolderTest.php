<?php

declare(strict_types=1);

use App\Services\AI\Memory\Episodic\ProceduralVersionHolder;

it('accumulates procedure_id@version on add and returns them in insertion order', function (): void {
    $h = new ProceduralVersionHolder;
    $h->add('retirement.tool.create_dc_pension', 2);
    $h->add('general.overlay.house', 1);

    expect($h->all())->toBe([
        'retirement.tool.create_dc_pension@2',
        'general.overlay.house@1',
    ]);
});

it('de-duplicates an identical procedure_id@version', function (): void {
    $h = new ProceduralVersionHolder;
    $h->add('savings.tool.create_savings_account', 3);
    $h->add('savings.tool.create_savings_account', 3);

    expect($h->all())->toBe(['savings.tool.create_savings_account@3']);
});

it('keeps distinct versions of the same procedure id separate', function (): void {
    $h = new ProceduralVersionHolder;
    $h->add('estate.tool.create_will', 1);
    $h->add('estate.tool.create_will', 2);

    expect($h->all())->toBe(['estate.tool.create_will@1', 'estate.tool.create_will@2']);
});

it('returns an empty list before anything is added', function (): void {
    expect((new ProceduralVersionHolder)->all())->toBe([]);
});

it('clears the list on reset', function (): void {
    $h = new ProceduralVersionHolder;
    $h->add('a.b.c', 1);
    $h->reset();

    expect($h->all())->toBe([]);
});
