<?php

declare(strict_types=1);

use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchHandlerRegistry;
use App\Services\AI\Pointers\FetchResult;
use App\Services\AI\Pointers\PointerRegistry;
use Illuminate\Support\Facades\File;

function handlerStub(string $id): FetchHandler
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
            return FetchResult::make('v', 's', '2026/27');
        }
    };
}

function writePointer(string $dir, string $name, string $frontmatter, string $body = 'When to use this pointer.'): void
{
    @mkdir($dir, 0777, true);
    file_put_contents("$dir/$name.md", "---\n$frontmatter\n---\n\n$body\n");
}

function registryWith(string $dir, array $handlerIds): PointerRegistry
{
    config(['fyn.memory.pointers_path' => $dir]);

    return new PointerRegistry(new FetchHandlerRegistry(array_map('handlerStub', $handlerIds)));
}

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/ptr-'.uniqid();
});

afterEach(fn () => File::deleteDirectory($this->dir));

it('loads a valid pointer indexed by pointer_id', function (): void {
    writePointer($this->dir, 'isa', "pointer_id: isa-allowance\ntopic: ISA allowance\ntriggers: [isa, allowance]\nmode: both\nhandler: tax_allowance\nsource_label: TaxConfigService\nversion: 1");
    $reg = registryWith($this->dir, ['tax_allowance']);
    expect($reg->all())->toHaveCount(1)
        ->and($reg->all()['isa-allowance']->handler)->toBe('tax_allowance');
});

it('fails closed when a pointer references an unregistered handler', function (): void {
    writePointer($this->dir, 'x', "pointer_id: x\ntopic: X\ntriggers: [x]\nmode: prefetch\nhandler: ghost\nsource_label: S\nversion: 1");
    $reg = registryWith($this->dir, ['tax_allowance']);
    expect(fn () => $reg->all())->toThrow(RuntimeException::class, 'ghost');
});

it('fails closed on a duplicate pointer_id', function (): void {
    writePointer($this->dir, 'a', "pointer_id: dup\ntopic: A\ntriggers: [a]\nmode: tool\nhandler: tax_allowance\nsource_label: S\nversion: 1");
    writePointer($this->dir, 'b', "pointer_id: dup\ntopic: B\ntriggers: [b]\nmode: tool\nhandler: tax_allowance\nsource_label: S\nversion: 1");
    $reg = registryWith($this->dir, ['tax_allowance']);
    expect(fn () => $reg->all())->toThrow(RuntimeException::class, 'duplicate');
});

it('fails closed on an unknown mode', function (): void {
    writePointer($this->dir, 'a', "pointer_id: a\ntopic: A\ntriggers: [a]\nmode: nonsense\nhandler: tax_allowance\nsource_label: S\nversion: 1");
    expect(fn () => registryWith($this->dir, ['tax_allowance'])->all())->toThrow(RuntimeException::class, 'mode');
});

it('fails closed when a prefetch pointer has no triggers', function (): void {
    writePointer($this->dir, 'a', "pointer_id: a\ntopic: A\nmode: prefetch\nhandler: tax_allowance\nsource_label: S\nversion: 1");
    expect(fn () => registryWith($this->dir, ['tax_allowance'])->all())->toThrow(RuntimeException::class, 'triggers');
});

it('matches prefetch pointers whose triggers appear in the query', function (): void {
    writePointer($this->dir, 'isa', "pointer_id: isa\ntopic: ISA\ntriggers: [isa, allowance]\nmode: prefetch\nhandler: tax_allowance\nsource_label: S\nversion: 1");
    writePointer($this->dir, 'tool-only', "pointer_id: rec\ntopic: Rec\ntriggers: [recommend]\nmode: tool\nhandler: tax_allowance\nsource_label: S\nversion: 1");
    $reg = registryWith($this->dir, ['tax_allowance']);

    $matched = $reg->matchPrefetch('what is my isa allowance');
    expect($matched)->toHaveCount(1)->and($matched[0]->pointerId)->toBe('isa');
    expect($reg->matchPrefetch('hello there'))->toBe([]);
});

it('returns tool-mode pointers for catalogue registration', function (): void {
    writePointer($this->dir, 'rec', "pointer_id: rec\ntopic: Rec\ntriggers: [recommend]\nmode: both\nhandler: tax_allowance\nsource_label: S\nversion: 1");
    expect(registryWith($this->dir, ['tax_allowance'])->toolPointers())->toHaveCount(1);
});
