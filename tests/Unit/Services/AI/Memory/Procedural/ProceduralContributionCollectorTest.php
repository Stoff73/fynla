<?php

declare(strict_types=1);

use App\Services\AI\Memory\Procedural\ProceduralContributionCollector;

it('starts empty', function (): void {
    expect((new ProceduralContributionCollector)->all())->toBe([]);
});

it('records contributions in order', function (): void {
    $c = new ProceduralContributionCollector;
    $c->record(['procedure_id' => 'retirement.overlay.tone', 'kind' => 'system_prompt_overlay', 'module' => 'retirement', 'version' => 1]);
    $c->record(['procedure_id' => 'general.fca.dbtransfer', 'kind' => 'fca_block', 'module' => 'general', 'version' => 2]);

    expect($c->all())->toBe([
        ['procedure_id' => 'retirement.overlay.tone', 'kind' => 'system_prompt_overlay', 'module' => 'retirement', 'version' => 1],
        ['procedure_id' => 'general.fca.dbtransfer', 'kind' => 'fca_block', 'module' => 'general', 'version' => 2],
    ]);
});

it('reset clears all recorded contributions', function (): void {
    $c = new ProceduralContributionCollector;
    $c->record(['procedure_id' => 'x', 'kind' => 'fca_block', 'module' => 'general', 'version' => 1]);
    $c->reset();

    expect($c->all())->toBe([]);
});

it('is bound scoped in the container (one instance per resolution scope)', function (): void {
    expect(app(ProceduralContributionCollector::class))
        ->toBe(app(ProceduralContributionCollector::class));
});
