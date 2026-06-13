<?php

declare(strict_types=1);

use App\Services\AI\Memory\Episodic\FetchProvenanceCollector;

it('accumulates, returns, and resets provenance entries', function (): void {
    $c = new FetchProvenanceCollector;
    expect($c->all())->toBe([]);

    $c->record(['pointer_id' => 'isa', 'handler' => 'tax_allowance', 'source_label' => 'TaxConfigService', 'source_version' => '2026/27', 'digest' => 'd1']);
    $c->record(['pointer_id' => 'rec', 'handler' => 'recommendations', 'source_label' => 'recommendation engine', 'source_version' => '2026-06-01', 'digest' => 'd2']);

    expect($c->all())->toHaveCount(2)
        ->and($c->all()[0]['handler'])->toBe('tax_allowance');

    $c->reset();
    expect($c->all())->toBe([]);
});

it('is the same instance within a request (scoped singleton)', function (): void {
    $a = app(FetchProvenanceCollector::class);
    $b = app(FetchProvenanceCollector::class);
    $a->record(['pointer_id' => 'x', 'handler' => 'h', 'source_label' => 's', 'source_version' => 'v', 'digest' => 'd']);
    expect($b->all())->toHaveCount(1);
});
