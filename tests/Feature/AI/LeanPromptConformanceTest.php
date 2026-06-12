<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use App\Services\AI\Pointers\PointerRegistry;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('never carries the composed tax plan in the assembled context — it arrives on demand only', function (): void {
    // §6d lean-prompt law: the composed plan is a tool-mode fetch (a heavier,
    // explicit ask), never a blanket pre-fetch riding in every prompt.
    $user = User::factory()->create();

    $ctx = FynTurnContext::make(
        user: $user,
        message: 'What is the weather like for planning a holiday?',
        currentRoute: '/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'general'],
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    // Affirmative anchor first — build() always emits the <user_message> block
    // (unconditional tail), so the negative assertions below cannot pass vacuously.
    expect($out)->toContain('<user_message>')
        ->and($out)->not->toContain('composed_tax_plan')
        ->and($out)->not->toContain('combined_annual_saving');
});

it('pins the recommendations pointer to tool mode — the no-prefetch tripwire', function (): void {
    // A future flip to prefetch/both would inject the whole composed plan
    // into every prompt, breaking the lean-prompt law. This test is the tripwire.
    $pointers = app(PointerRegistry::class)->all();

    expect($pointers)->toHaveKey('recommendations')
        ->and($pointers['recommendations']->mode)->toBe('tool');
});
