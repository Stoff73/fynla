<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Memory\Episodic\FetchProvenanceCollector;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchDispatcher;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchHandlerRegistry;
use App\Services\AI\Pointers\FetchResult;
use App\Services\AI\Pointers\Pointer;

it('records provenance into the collector on a successful fetch', function (): void {
    $handler = new class implements FetchHandler
    {
        public function id(): string
        {
            return 'ok';
        }

        public function fetch(FetchContext $ctx): FetchResult
        {
            return FetchResult::make('v', 'TaxConfigService', '2026/27');
        }
    };
    $collector = new FetchProvenanceCollector;
    $d = new FetchDispatcher(new FetchHandlerRegistry([$handler]), $collector);
    $user = User::factory()->create();
    $pointer = new Pointer('isa', 't', ['isa'], 'both', 'ok', 'TaxConfigService', 1, 'b');

    $d->run($pointer, new FetchContext($user, 'isa allowance'));

    expect($collector->all())->toHaveCount(1)
        ->and($collector->all()[0]['pointer_id'])->toBe('isa')
        ->and($collector->all()[0]['source_version'])->toBe('2026/27');
});

it('records nothing when the handler fails', function (): void {
    $handler = new class implements FetchHandler
    {
        public function id(): string
        {
            return 'boom';
        }

        public function fetch(FetchContext $ctx): FetchResult
        {
            throw new RuntimeException('down');
        }
    };
    $collector = new FetchProvenanceCollector;
    $d = new FetchDispatcher(new FetchHandlerRegistry([$handler]), $collector);
    $user = User::factory()->create();
    $pointer = new Pointer('p', 't', ['x'], 'both', 'boom', 'S', 1, 'b');

    expect($d->run($pointer, new FetchContext($user, 'x')))->toBeNull()
        ->and($collector->all())->toBe([]);
});
