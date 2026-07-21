<?php

declare(strict_types=1);

use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchHandlerRegistry;
use App\Services\AI\Pointers\FetchResult;

function fakeHandler(string $id): FetchHandler
{
    return new class($id) implements FetchHandler
    {
        public function __construct(private string $id) {}

        public function id(): string
        {
            return $this->id;
        }

        public function fetch(FetchContext $ctx): FetchResult
        {
            return FetchResult::make('v', 'src', '2026/27');
        }
    };
}

it('resolves a registered handler by id', function (): void {
    $reg = new FetchHandlerRegistry([fakeHandler('tax_allowance')]);
    expect($reg->has('tax_allowance'))->toBeTrue()
        ->and($reg->get('tax_allowance')->id())->toBe('tax_allowance');
});

it('reports an unknown handler as absent', function (): void {
    $reg = new FetchHandlerRegistry([fakeHandler('tax_allowance')]);
    expect($reg->has('nope'))->toBeFalse();
});

it('throws when getting an unknown handler', function (): void {
    $reg = new FetchHandlerRegistry([]);
    expect(fn () => $reg->get('nope'))->toThrow(RuntimeException::class, 'nope');
});

it('exposes all registered ids', function (): void {
    $reg = new FetchHandlerRegistry([fakeHandler('a'), fakeHandler('b')]);
    expect($reg->ids())->toContain('a')->toContain('b');
});
