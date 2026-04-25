<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\AdviceFyn;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

$writeTools = [
    'create_savings_account', 'create_investment_account', 'create_holding',
    'create_pension', 'create_property', 'create_mortgage',
    'create_protection_policy', 'create_asset', 'create_liability',
    'create_estate_gift', 'create_chattel', 'create_business_interest',
    'create_trust', 'create_family_member', 'create_will', 'update_will',
    'create_power_of_attorney', 'update_power_of_attorney',
    'update_record', 'delete_record', 'update_profile', 'set_expenditure',
    'capture_personal_details', 'capture_spouse_details',
    'capture_dependants', 'capture_work_details',
    // S0.5.r — every persistent record-creation tool flows through the
    // delegate_to_capture handoff. Onboarding Fyn is the only writer.
    'create_goal', 'create_life_event', 'create_what_if_scenario',
];

it('AdviceFyn tool list excludes every DB-mutating tool on Anthropic', function () use ($writeTools): void {
    cache()->forever('ai_provider', 'anthropic');
    $user = User::factory()->create();
    $tools = app(AdviceFyn::class)->buildToolList($user);
    expect(array_intersect($tools, $writeTools))->toBeEmpty();
});

it('AdviceFyn tool list excludes every DB-mutating tool on xAI', function () use ($writeTools): void {
    cache()->forever('ai_provider', 'xai');
    $user = User::factory()->create();
    $tools = app(AdviceFyn::class)->buildToolList($user);
    expect(array_intersect($tools, $writeTools))->toBeEmpty();
});

it('AdviceFyn tool list exposes delegate_to_capture so the LLM can hand off writes', function (): void {
    cache()->forever('ai_provider', 'anthropic');
    $user = User::factory()->create();
    $tools = app(AdviceFyn::class)->buildToolList($user);
    expect($tools)->toContain('delegate_to_capture');
});

it('AdviceFyn tool list exposes delegate_to_capture on xAI as well', function (): void {
    cache()->forever('ai_provider', 'xai');
    $user = User::factory()->create();
    $tools = app(AdviceFyn::class)->buildToolList($user);
    expect($tools)->toContain('delegate_to_capture');
});
