<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\PensionInputHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('writes pension_input_history rows for each entry', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [
            ['tax_year' => '2024/25', 'pension_input_amount' => 25000],
            ['tax_year' => '2023/24', 'pension_input_amount' => 18000],
            ['tax_year' => '2022/23', 'pension_input_amount' => 0],
        ],
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    expect($result['field_group'] ?? null)->toBe('campaign_pension_history');
    expect($result['details'])->toHaveKey('2024/25')
        ->and($result['details']['2024/25'])->toBe(25000.0);
    expect(PensionInputHistory::where('user_id', $user->id)->count())->toBe(3);
});

it('updates existing rows when called twice for same tax year', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [['tax_year' => '2024/25', 'pension_input_amount' => 5000]],
    ], $user);

    app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [['tax_year' => '2024/25', 'pension_input_amount' => 9000]],
    ], $user);

    $rows = PensionInputHistory::where('user_id', $user->id)->get();
    expect($rows)->toHaveCount(1);
    expect((float) $rows->first()->pension_input_amount)->toBe(9000.0);
});

it('rejects empty history array', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [],
    ], $user);

    expect($result['error'] ?? null)->toBeTrue();
    expect($result['error_type'] ?? null)->toBe('validation_failed');
    expect(PensionInputHistory::count())->toBe(0);
});

it('skips negative amounts', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [
            ['tax_year' => '2024/25', 'pension_input_amount' => -100],
            ['tax_year' => '2023/24', 'pension_input_amount' => 4000],
        ],
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    expect($result['details'])->toHaveKey('2023/24')
        ->and($result['details'])->not->toHaveKey('2024/25');
    expect(PensionInputHistory::where('user_id', $user->id)->count())->toBe(1);
});

it('blocks preview users', function () {
    $user = User::factory()->create(['is_preview_user' => true]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [['tax_year' => '2024/25', 'pension_input_amount' => 5000]],
    ], $user);

    expect($result['blocked'] ?? false)->toBeTrue();
    expect(PensionInputHistory::count())->toBe(0);
});
