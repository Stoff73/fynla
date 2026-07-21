<?php

declare(strict_types=1);

use App\Services\AI\AiToolDefinitions;
use App\Services\AI\XaiToolDefinitions;
use Tests\TestCase;

uses(TestCase::class);

it('Anthropic and xAI tool catalogues match exactly (non-preview)', function (): void {
    $anthropicNames = collect(app(AiToolDefinitions::class)->getTools(false))
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    $xaiNames = collect(app(XaiToolDefinitions::class)->getTools(false))
        ->map(fn ($t) => $t['function']['name'])
        ->sort()
        ->values()
        ->all();

    expect($anthropicNames)->toEqual($xaiNames);
});

it('Anthropic and xAI tool catalogues match exactly (preview)', function (): void {
    $anthropicNames = collect(app(AiToolDefinitions::class)->getTools(true))
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    $xaiNames = collect(app(XaiToolDefinitions::class)->getTools(true))
        ->map(fn ($t) => $t['function']['name'])
        ->sort()
        ->values()
        ->all();

    expect($anthropicNames)->toEqual($xaiNames);
});

it('billing tools are exposed on both providers in non-preview mode', function (): void {
    $anthropicNames = collect(app(AiToolDefinitions::class)->getTools(false))
        ->pluck('name')
        ->all();

    $xaiNames = collect(app(XaiToolDefinitions::class)->getTools(false))
        ->map(fn ($t) => $t['function']['name'])
        ->all();

    foreach (['get_subscription_status', 'list_invoices', 'get_current_plan'] as $tool) {
        expect($anthropicNames)->toContain($tool);
        expect($xaiNames)->toContain($tool);
    }
});

it('billing tools are exposed on both providers in preview mode', function (): void {
    // Billing tools are read-only — preview personas should be able to discuss
    // their (empty) subscription state rather than seeing the tools vanish.
    $anthropicNames = collect(app(AiToolDefinitions::class)->getTools(true))
        ->pluck('name')
        ->all();

    $xaiNames = collect(app(XaiToolDefinitions::class)->getTools(true))
        ->map(fn ($t) => $t['function']['name'])
        ->all();

    foreach (['get_subscription_status', 'list_invoices', 'get_current_plan'] as $tool) {
        expect($anthropicNames)->toContain($tool);
        expect($xaiNames)->toContain($tool);
    }
});
