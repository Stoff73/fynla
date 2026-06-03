<?php

declare(strict_types=1);

use App\Services\AI\Memory\Procedural\ProceduralCorpus;
use App\Services\AI\Memory\Procedural\Procedure;
use Illuminate\Support\Carbon;

function proc(string $id, int $version, bool $active, string $from, ?string $to = null, string $kind = 'tool_schema', string $module = 'retirement'): Procedure
{
    return new Procedure(
        procedureId: $id,
        kind: $kind,
        module: $module,
        version: $version,
        active: $active,
        effectiveFrom: Carbon::parse($from),
        effectiveTo: $to !== null ? Carbon::parse($to) : null,
        body: 'body',
    );
}

it('returns all procedures', function (): void {
    $corpus = new ProceduralCorpus([proc('a', 1, true, '2026-01-01'), proc('b', 1, true, '2026-01-01')]);
    expect($corpus->all())->toHaveCount(2);
});

it('filters by kind', function (): void {
    $corpus = new ProceduralCorpus([
        proc('a', 1, true, '2026-01-01', null, 'tool_schema'),
        proc('b', 1, true, '2026-01-01', null, 'workflow'),
    ]);
    expect($corpus->ofKind('workflow'))->toHaveCount(1)
        ->and($corpus->ofKind('workflow')[0]->procedureId)->toBe('b');
});

it('lists all versions of a procedure ascending', function (): void {
    $corpus = new ProceduralCorpus([proc('a', 2, false, '2026-01-01'), proc('a', 1, false, '2025-01-01')]);
    $versions = $corpus->versions('a');
    expect($versions)->toHaveCount(2)
        ->and($versions[0]->version)->toBe(1)
        ->and($versions[1]->version)->toBe(2);
});

it('resolves the active version effective on a date', function (): void {
    $corpus = new ProceduralCorpus([
        proc('a', 1, false, '2025-01-01', '2025-12-31'),
        proc('a', 2, true, '2026-01-01'),
    ]);
    expect($corpus->active('a', Carbon::parse('2026-06-02'))?->version)->toBe(2);
});

it('returns null when no active version is effective on the date', function (): void {
    $corpus = new ProceduralCorpus([proc('a', 2, true, '2027-01-01')]);
    expect($corpus->active('a', Carbon::parse('2026-06-02')))->toBeNull();
});

it('returns the highest-version active when several qualify', function (): void {
    $corpus = new ProceduralCorpus([
        proc('a', 1, true, '2025-01-01'),
        proc('a', 3, true, '2026-01-01'),
    ]);
    expect($corpus->active('a', Carbon::parse('2026-06-02'))?->version)->toBe(3);
});
