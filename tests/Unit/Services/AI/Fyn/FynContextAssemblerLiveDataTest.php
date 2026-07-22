<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use App\Services\AI\Pointers\PointerRegistry;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/ptr-'.uniqid();
    config(['fyn.memory.pointers_path' => $this->dir]);
    @mkdir($this->dir, 0777, true);
    file_put_contents(
        "{$this->dir}/uf.md",
        "---\npointer_id: uf\ntopic: position\ntriggers: [my accounts]\nmode: prefetch\nhandler: user_financial\nsource_label: user records\nversion: 1\n---\n\nWhen to use.\n"
    );
    $this->seed(TaxConfigurationSeeder::class);
    // PointerRegistry caches all() in-process and is a singleton; FynContextAssembler
    // is resolved with the registry injected. Forget both so the assembler reads a
    // fresh registry against this test's temp corpus dir.
    app()->forgetInstance(PointerRegistry::class);
    app()->forgetInstance(FynContextAssembler::class);
});

afterEach(fn () => File::deleteDirectory($this->dir));

it('emits a <live_data> block when a prefetch pointer matches the query', function (): void {
    $user = User::factory()->create();
    $ctx = FynTurnContext::make(
        user: $user,
        message: 'show my accounts',
        currentRoute: '/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'general'],
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<live_data>');
});

it('omits <live_data> when no pointer matches', function (): void {
    $user = User::factory()->create();
    $ctx = FynTurnContext::make(
        user: $user,
        message: 'hello there',
        currentRoute: '/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'general'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))->not->toContain('<live_data>');
});
