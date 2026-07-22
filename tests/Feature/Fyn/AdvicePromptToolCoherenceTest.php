<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use App\Services\AI\Fyn\FynSystemPrompt;
use App\Services\AI\Prompts\ComplianceRules;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
});

it('does not instruct the default unified advice prompt to call a stripped navigation tool', function (): void {
    expect(FynSystemPrompt::text())
        ->not->toContain('navigate_to_page')
        ->not->toContain('ALWAYS use the navigate_to_page tool')
        ->not->toContain('using navigate_to_page')
        ->not->toContain('Use navigate_to_page with');
});

it('does not instruct the legacy advice prompt to call a stripped navigation tool', function (): void {
    $user = User::factory()->create();
    $prompt = app(AdvicePromptBuilder::class)->build($user);

    expect(ComplianceRules::get())
        ->not->toContain('ALWAYS use the navigate_to_page tool');

    expect($prompt)
        ->not->toContain('navigate_to_page')
        ->not->toContain('ALWAYS use navigate_to_page')
        ->not->toContain('using navigate_to_page')
        ->not->toContain('Use navigate_to_page with');
});

it('returns label-only signposting when an advice tool is blocked', function (): void {
    $user = User::factory()->create();

    $result = app(CoordinatingAgent::class)->executeTool(
        'get_module_analysis',
        ['module' => 'retirement'],
        $user,
    );
    $encoded = json_encode($result, JSON_THROW_ON_ERROR);

    expect($result['blocked'])->toBeTrue()
        ->and($result['suggested_action'])->toHaveKey('label')
        ->and($encoded)->not->toContain('navigate_to_page')
        ->and($encoded)->not->toContain('/profile')
        ->and($encoded)->not->toContain('/retirement')
        ->and($encoded)->not->toContain('"route"');
});
