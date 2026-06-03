<?php

declare(strict_types=1);

use App\Services\AI\Memory\Procedural\Procedure;
use Illuminate\Support\Carbon;

it('exposes its frontmatter fields', function (): void {
    $p = new Procedure(
        procedureId: 'retirement.tool.create_dc_pension',
        kind: 'tool_schema',
        module: 'retirement',
        version: 2,
        active: true,
        effectiveFrom: Carbon::parse('2026-06-02'),
        effectiveTo: null,
        body: '```json\n{}\n```',
    );

    expect($p->procedureId)->toBe('retirement.tool.create_dc_pension')
        ->and($p->kind)->toBe('tool_schema')
        ->and($p->module)->toBe('retirement')
        ->and($p->version)->toBe(2)
        ->and($p->active)->toBeTrue()
        ->and($p->effectiveTo)->toBeNull();
});

it('is in force on or after effective_from with no end', function (): void {
    $p = new Procedure('id', 'workflow', 'global', 1, true, Carbon::parse('2026-06-01'), null, 'body');

    expect($p->effectiveOn(Carbon::parse('2026-05-31')))->toBeFalse()
        ->and($p->effectiveOn(Carbon::parse('2026-06-01')))->toBeTrue()
        ->and($p->effectiveOn(Carbon::parse('2030-01-01')))->toBeTrue();
});

it('respects effective_to when set', function (): void {
    $p = new Procedure('id', 'workflow', 'global', 1, true, Carbon::parse('2026-06-01'), Carbon::parse('2026-12-31'), 'body');

    expect($p->effectiveOn(Carbon::parse('2026-12-31')))->toBeTrue()
        ->and($p->effectiveOn(Carbon::parse('2027-01-01')))->toBeFalse();
});

it('defaults provider to anthropic when not supplied', function (): void {
    $proc = new Procedure(
        procedureId: 'x.tool.y',
        kind: 'tool_schema',
        module: 'x',
        version: 1,
        active: true,
        effectiveFrom: Carbon::parse('2026-06-02'),
        effectiveTo: null,
        body: 'body',
    );
    expect($proc->provider)->toBe('anthropic');
});

it('accepts an explicit provider', function (): void {
    $proc = new Procedure(
        procedureId: 'x.tool.y',
        kind: 'tool_schema',
        module: 'x',
        version: 1,
        active: true,
        effectiveFrom: Carbon::parse('2026-06-02'),
        effectiveTo: null,
        body: 'body',
        provider: 'xai',
    );
    expect($proc->provider)->toBe('xai');
});
