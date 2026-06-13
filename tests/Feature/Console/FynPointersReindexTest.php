<?php

declare(strict_types=1);

use App\Services\AI\Pointers\PointerRegistry;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir().'/ptrs-'.uniqid();
    @mkdir($this->tmp, 0777, true);
    config(['fyn.memory.pointers_path' => $this->tmp]);
    app()->forgetInstance(PointerRegistry::class);
});

afterEach(function (): void {
    File::deleteDirectory($this->tmp);
    app()->forgetInstance(PointerRegistry::class);
});

it('exits 0 and prints the pointer count for a valid corpus', function (): void {
    file_put_contents("{$this->tmp}/user-financial.md", implode("\n", [
        '---',
        'pointer_id: user-financial-position',
        'topic: The user own accounts and balances',
        'triggers: [my accounts, my savings]',
        'mode: prefetch',
        'handler: user_financial',
        'source_label: user records',
        'version: 1',
        '---',
        '',
        'Use when the user asks about their own financial position.',
        '',
    ]));

    $this->artisan('fyn:pointers:reindex')
        ->assertExitCode(0)
        ->expectsOutputToContain('1 pointer');
});

it('exits 1 when a pointer references an unregistered handler', function (): void {
    file_put_contents("{$this->tmp}/ghost.md", implode("\n", [
        '---',
        'pointer_id: ghost-pointer',
        'topic: Ghost pointer',
        'triggers: [ghost]',
        'mode: prefetch',
        'handler: ghost',
        'source_label: nowhere',
        'version: 1',
        '---',
        '',
        'This references an unregistered handler.',
        '',
    ]));

    $this->artisan('fyn:pointers:reindex')
        ->assertExitCode(1);
});
