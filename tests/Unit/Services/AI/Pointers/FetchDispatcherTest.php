<?php

declare(strict_types=1);

use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\Memory\Episodic\FetchProvenanceCollector;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchDispatcher;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchHandlerRegistry;
use App\Services\AI\Pointers\FetchResult;
use App\Services\AI\Pointers\Pointer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function okHandler(): FetchHandler
{
    return new class implements FetchHandler
    {
        public function id(): string
        {
            return 'ok';
        }

        public function fetch(FetchContext $ctx): FetchResult
        {
            return FetchResult::make('Your ISA allowance is fetched live.', 'TaxConfigService', '2026/27');
        }
    };
}

function boomHandler(): FetchHandler
{
    return new class implements FetchHandler
    {
        public function id(): string
        {
            return 'boom';
        }

        public function fetch(FetchContext $ctx): FetchResult
        {
            throw new RuntimeException('engine down');
        }
    };
}

function pointer(string $handler): Pointer
{
    return new Pointer('p1', 'topic', ['isa'], 'both', $handler, 'TaxConfigService', 1, 'body');
}

it('runs the handler and returns its result', function (): void {
    $d = new FetchDispatcher(new FetchHandlerRegistry([okHandler()]), new FetchProvenanceCollector);
    $user = User::factory()->create();
    $res = $d->run(pointer('ok'), new FetchContext($user, 'what is my isa allowance'));
    expect($res)->not->toBeNull()
        ->and($res->value)->toContain('fetched live');
});

it('returns null and does not throw when the handler fails', function (): void {
    $d = new FetchDispatcher(new FetchHandlerRegistry([boomHandler()]), new FetchProvenanceCollector);
    $user = User::factory()->create();
    expect($d->run(pointer('boom'), new FetchContext($user, 'x')))->toBeNull();
});

it('records provenance onto an AiMessage metadata when given one', function (): void {
    $d = new FetchDispatcher(new FetchHandlerRegistry([okHandler()]), new FetchProvenanceCollector);
    $user = User::factory()->create();
    $msg = AiMessage::factory()->create(['role' => 'assistant']);

    $d->run(pointer('ok'), new FetchContext($user, 'isa'), $msg);

    $msg->refresh();
    expect($msg->metadata['fetch_provenance'][0]['handler'])->toBe('ok')
        ->and($msg->metadata['fetch_provenance'][0]['source_version'])->toBe('2026/27');
});
