<?php

declare(strict_types=1);

use App\Models\AiMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('persists and casts the new episode columns', function (): void {
    $msg = AiMessage::factory()->create([
        'procedural_version' => ['fyn.advice.overlay@1.0.0'],
        'semantic_snapshot_id' => str_repeat('a', 64),
        'fetch_provenance' => [['pointer_id' => 'isa', 'handler' => 'tax_allowance', 'source_label' => 'TaxConfigService', 'source_version' => '2026/27', 'digest' => 'abcd']],
        'blob_md_path' => 'episodic/2026/06/01/1/1.md',
        'blob_md_sha256' => str_repeat('b', 64),
    ]);

    $msg->refresh();

    expect($msg->procedural_version)->toBe(['fyn.advice.overlay@1.0.0'])
        ->and($msg->fetch_provenance[0]['handler'])->toBe('tax_allowance')
        ->and($msg->semantic_snapshot_id)->toHaveLength(64)
        ->and($msg->blob_md_path)->toBe('episodic/2026/06/01/1/1.md');
});
