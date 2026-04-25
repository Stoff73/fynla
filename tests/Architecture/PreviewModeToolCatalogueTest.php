<?php

declare(strict_types=1);

use App\Services\AI\AiToolDefinitions;
use App\Services\AI\XaiToolDefinitions;

uses(Tests\TestCase::class);

/**
 * INV-2.7.4 — preview-mode tool filtering must be consistent across
 * providers. Preview personas can read / navigate but never write,
 * so AiToolDefinitions::getTools(true) and XaiToolDefinitions::getTools(true)
 * must:
 *
 *   1. Return identical tool-name sets (no provider-specific drift).
 *   2. Contain zero DB-mutating tools — preview users see only
 *      read / navigation / billing-read / analysis tools.
 *
 * The complementary parity test in tests/Architecture/ToolCatalogueParityTest.php
 * pins property #1 in passing; this file owns the explicit intersection +
 * write-tool-absence contract so a future regression is caught here.
 */
const PREVIEW_BANNED_WRITE_TOOLS = [
    // create_*
    'create_savings_account',
    'create_investment_account',
    'create_holding',
    'create_pension',
    'create_property',
    'create_mortgage',
    'create_protection_policy',
    'create_asset',
    'create_liability',
    'create_estate_gift',
    'create_chattel',
    'create_business_interest',
    'create_trust',
    'create_family_member',
    'create_will',
    'create_power_of_attorney',
    'create_goal',
    'create_life_event',
    'create_what_if_scenario',
    // update_*
    'update_record',
    'update_profile',
    'update_will',
    'update_power_of_attorney',
    // delete_*
    'delete_record',
    // capture_* / set_*
    'capture_personal_details',
    'capture_spouse_details',
    'capture_dependants',
    'capture_work_details',
    'set_expenditure',
];

it('Anthropic preview-mode catalogue equals xAI preview-mode catalogue', function (): void {
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

it('Anthropic preview-mode catalogue contains zero DB-mutating tools', function (): void {
    $anthropicNames = collect(app(AiToolDefinitions::class)->getTools(true))
        ->pluck('name')
        ->all();

    foreach (PREVIEW_BANNED_WRITE_TOOLS as $writeTool) {
        expect($anthropicNames)->not->toContain($writeTool);
    }
});

it('xAI preview-mode catalogue contains zero DB-mutating tools', function (): void {
    $xaiNames = collect(app(XaiToolDefinitions::class)->getTools(true))
        ->map(fn ($t) => $t['function']['name'])
        ->all();

    foreach (PREVIEW_BANNED_WRITE_TOOLS as $writeTool) {
        expect($xaiNames)->not->toContain($writeTool);
    }
});

it('preview-mode catalogue is a strict subset of the non-preview catalogue on both providers', function (): void {
    foreach ([AiToolDefinitions::class, XaiToolDefinitions::class] as $cls) {
        $previewNames = collect(app($cls)->getTools(true))
            ->map(fn ($t) => $t['name'] ?? ($t['function']['name'] ?? null))
            ->filter()
            ->values()
            ->all();

        $fullNames = collect(app($cls)->getTools(false))
            ->map(fn ($t) => $t['name'] ?? ($t['function']['name'] ?? null))
            ->filter()
            ->values()
            ->all();

        $missing = array_values(array_diff($previewNames, $fullNames));
        expect($missing)->toBe([]);

        // Subset must be strict — non-preview must include at least one
        // write tool (sanity check that the filter actually removes
        // something rather than silently being a no-op).
        expect(count($previewNames))->toBeLessThan(count($fullNames));
    }
});

it('preview-mode catalogue retains the canonical read / navigation / billing tools', function (): void {
    $expectedReadTools = [
        'navigate_to_page',
        'list_records',
        'list_goals',
        'list_life_events',
        'get_recommendations',
        'get_module_analysis',
        'get_subscription_status',
        'list_invoices',
        'get_current_plan',
        'get_tax_information',
    ];

    foreach ([AiToolDefinitions::class, XaiToolDefinitions::class] as $cls) {
        $names = collect(app($cls)->getTools(true))
            ->map(fn ($t) => $t['name'] ?? ($t['function']['name'] ?? null))
            ->filter()
            ->values()
            ->all();

        foreach ($expectedReadTools as $tool) {
            expect($names)->toContain($tool);
        }
    }
});
