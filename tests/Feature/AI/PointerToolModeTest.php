<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;
use App\Services\AI\AiToolDefinitions;
use App\Services\AI\Pointers\PointerRegistry;
use App\Services\AI\XaiToolDefinitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('exposes tool-mode pointers as fetch_{id} tools in the catalogue', function (): void {
    $dir = sys_get_temp_dir().'/ptr-'.uniqid();
    config(['fyn.memory.pointers_path' => $dir]);
    @mkdir($dir, 0777, true);
    file_put_contents("$dir/uf.md", "---\npointer_id: position\ntopic: position\ntriggers: [x]\nmode: tool\nhandler: user_financial\nsource_label: user records\nversion: 1\n---\n\nFetch the user's position.\n");
    app()->forgetInstance(PointerRegistry::class);

    $tools = app(AiToolDefinitions::class)->getTools();
    $names = array_map(fn ($t) => $t['name'] ?? ($t['function']['name'] ?? null), $tools);

    expect($names)->toContain('fetch_position');

    File::deleteDirectory($dir);
});

it('maps a dashed pointer_id to an underscored fetch_ tool name', function (): void {
    $dir = sys_get_temp_dir().'/ptr-'.uniqid();
    config(['fyn.memory.pointers_path' => $dir]);
    @mkdir($dir, 0777, true);
    file_put_contents("$dir/isa.md", "---\npointer_id: isa-annual-allowance\ntopic: isa\ntriggers: [isa]\nmode: tool\nhandler: tax_allowance\nsource_label: TaxConfigService\nversion: 1\n---\n\nUse when the user asks about ISA allowance.\n");
    app()->forgetInstance(PointerRegistry::class);

    $tools = app(AiToolDefinitions::class)->getTools();
    $names = array_map(fn ($t) => $t['name'] ?? ($t['function']['name'] ?? null), $tools);

    expect($names)->toContain('fetch_isa_annual_allowance');

    File::deleteDirectory($dir);
});

it('uses the pointer body as the tool description', function (): void {
    $dir = sys_get_temp_dir().'/ptr-'.uniqid();
    config(['fyn.memory.pointers_path' => $dir]);
    @mkdir($dir, 0777, true);
    file_put_contents("$dir/uf.md", "---\npointer_id: position\ntopic: position\ntriggers: [x]\nmode: tool\nhandler: user_financial\nsource_label: user records\nversion: 1\n---\n\nFetch the user's financial position.\n");
    app()->forgetInstance(PointerRegistry::class);

    $tools = app(AiToolDefinitions::class)->getTools();
    $tool = collect($tools)->first(fn ($t) => ($t['name'] ?? null) === 'fetch_position');

    expect($tool)->not->toBeNull();
    expect($tool['description'])->toBe("Fetch the user's financial position.");

    File::deleteDirectory($dir);
});

it('exposes the same pointer tools on Anthropic and xAI catalogues', function (): void {
    $dir = sys_get_temp_dir().'/ptr-'.uniqid();
    config(['fyn.memory.pointers_path' => $dir]);
    @mkdir($dir, 0777, true);
    file_put_contents("$dir/uf.md", "---\npointer_id: position\ntopic: position\ntriggers: [x]\nmode: tool\nhandler: user_financial\nsource_label: user records\nversion: 1\n---\n\nFetch the user's position.\n");
    app()->forgetInstance(PointerRegistry::class);

    $anthropic = array_map(
        fn ($t) => $t['name'] ?? ($t['function']['name'] ?? null),
        app(AiToolDefinitions::class)->getTools()
    );
    $xai = array_map(
        fn ($t) => $t['function']['name'] ?? ($t['name'] ?? null),
        app(XaiToolDefinitions::class)->getTools()
    );

    expect($anthropic)->toContain('fetch_position');
    expect($xai)->toContain('fetch_position');

    File::deleteDirectory($dir);
});

it('executes a tool-mode pointer through executeTool to its bound user_financial handler', function (): void {
    $dir = sys_get_temp_dir().'/ptr-'.uniqid();
    config(['fyn.memory.pointers_path' => $dir]);
    @mkdir($dir, 0777, true);
    file_put_contents("$dir/uf.md", "---\npointer_id: position\ntopic: position\ntriggers: [x]\nmode: tool\nhandler: user_financial\nsource_label: user records\nversion: 1\n---\n\nFetch the user's position.\n");
    app()->forgetInstance(PointerRegistry::class);

    $user = User::factory()->create();

    $result = app(CoordinatingAgent::class)->executeTool('fetch_position', [], $user);

    expect($result['success'])->toBeTrue();
    expect($result)->toHaveKey('value');
    expect($result['value'])->toBeString();

    File::deleteDirectory($dir);
});

it('executes a dashed pointer_id called by its underscored fetch_ tool name', function (): void {
    $dir = sys_get_temp_dir().'/ptr-'.uniqid();
    config(['fyn.memory.pointers_path' => $dir]);
    @mkdir($dir, 0777, true);
    file_put_contents("$dir/mp.md", "---\npointer_id: my-position\ntopic: my position\ntriggers: [x]\nmode: tool\nhandler: user_financial\nsource_label: user records\nversion: 1\n---\n\nFetch the user's position.\n");
    app()->forgetInstance(PointerRegistry::class);

    $user = User::factory()->create();

    $result = app(CoordinatingAgent::class)->executeTool('fetch_my_position', [], $user);

    expect($result['success'])->toBeTrue();
    expect($result)->toHaveKey('value');
    expect($result['value'])->toBeString();

    File::deleteDirectory($dir);
});

it('falls through safely to the unknown_tool default for an unmatched fetch_ name', function (): void {
    $dir = sys_get_temp_dir().'/ptr-'.uniqid();
    config(['fyn.memory.pointers_path' => $dir]);
    @mkdir($dir, 0777, true);
    file_put_contents("$dir/uf.md", "---\npointer_id: position\ntopic: position\ntriggers: [x]\nmode: tool\nhandler: user_financial\nsource_label: user records\nversion: 1\n---\n\nFetch the user's position.\n");
    app()->forgetInstance(PointerRegistry::class);

    $user = User::factory()->create();

    $result = app(CoordinatingAgent::class)->executeTool('fetch_does_not_exist', [], $user);

    expect($result['error'])->toBeTrue();
    expect($result['error_type'])->toBe('unknown_tool');
    expect($result['message'])->toBe('Unknown tool: fetch_does_not_exist');

    File::deleteDirectory($dir);
});
