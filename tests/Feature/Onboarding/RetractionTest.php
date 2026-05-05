<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FR-M17 — conversational retraction.
 *
 * When the user contradicts a prior answer ("actually my DOB is 12 March
 * 1985, not 1986"), the asset-capture prompt instructs the LLM to emit
 * update_profile for personal facts. This test pins the handler side:
 * update_profile with section=personal and a field dict must correctly
 * update the user's row.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('updates personal fields via update_profile tool', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'date_of_birth' => '1986-01-01',
        'marital_status' => 'single',
    ]);

    $agent = app(CoordinatingAgent::class);

    $result = $agent->executeTool('update_profile', [
        'section' => 'personal',
        'fields' => [
            'date_of_birth' => '1985-03-12',
            'marital_status' => 'married',
        ],
    ], $user);

    expect($result['updated'] ?? null)->toBeTrue();
    expect($result['section'])->toBe('personal');

    $user->refresh();
    expect($user->date_of_birth->format('Y-m-d'))->toBe('1985-03-12');
    expect($user->marital_status)->toBe('married');
});

it('silently drops fields outside the allow-list (PII protection)', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'Chris',
    ]);

    $agent = app(CoordinatingAgent::class);

    $result = $agent->executeTool('update_profile', [
        'section' => 'personal',
        'fields' => [
            'first_name' => 'UpdatedName',
            // NI number MUST NOT be writable via AI per handler comments.
            'national_insurance_number' => 'QQ123456C',
        ],
    ], $user);

    expect($result['updated'] ?? null)->toBeTrue();

    $user->refresh();
    expect($user->first_name)->toBe('UpdatedName');
    // NI number unchanged — the handler's allow-list dropped it.
    expect($user->national_insurance_number)->not->toBe('QQ123456C');
});

it('rejects updates to unknown sections', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('update_profile', [
        'section' => 'not_a_real_section',
        'fields' => ['date_of_birth' => '2000-01-01'],
    ], $user);

    expect($result['error'] ?? null)->toBeTrue();
    expect($result['message'])->toContain('Unknown profile section');
});

it('rejects empty field dict', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('update_profile', [
        'section' => 'personal',
        'fields' => [],
    ], $user);

    expect($result['error'] ?? null)->toBeTrue();
    expect($result['message'])->toContain('No fields provided');
});
