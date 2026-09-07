<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\DCPension;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('writes salary_sacrifice flag to the named dc_pension', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $pension = DCPension::factory()->for($user)->create(['salary_sacrifice' => null]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_salary_sacrifice', [
        'pension_id' => $pension->id,
        'salary_sacrifice' => true,
    ], $user);

    expect($result['updated'] ?? false)->toBeTrue();
    expect($pension->fresh()->salary_sacrifice)->toBeTrue();
});

it('rejects requests for a pension owned by another user', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create();
    $pension = DCPension::factory()->for($other)->create();

    $result = app(CoordinatingAgent::class)->executeTool('capture_salary_sacrifice', [
        'pension_id' => $pension->id,
        'salary_sacrifice' => true,
    ], $user);

    expect($result['error'] ?? null)->not->toBeNull();
    expect($pension->fresh()->salary_sacrifice)->toBeNull();
});

/**
 * W-0518. W-0204 added `users.employment_income_basis` and asks for it on the web
 * profile whenever the user has a sacrificing pension. Fyn's capture tool wrote
 * `dc_pensions.salary_sacrifice` and never asked the follow-up — and Fyn is the
 * primary capture path on `/m` and native, where there is no Income Definitions panel
 * to visit. Those are the surfaces where the `assumed_gross` assumption was least
 * likely ever to be corrected.
 *
 * The gate is the same one the web form applies: asked only of someone declaring
 * sacrifice, and never re-asked once answered.
 */
it('writes the employment income basis the user gave with the sacrifice', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'employment_income_basis' => null]);
    $pension = DCPension::factory()->for($user)->create(['salary_sacrifice' => null]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_salary_sacrifice', [
        'pension_id' => $pension->id,
        'salary_sacrifice' => true,
        'employment_income_basis' => 'post_sacrifice',
    ], $user);

    expect($result['updated'] ?? false)->toBeTrue()
        ->and($user->fresh()->employment_income_basis)->toBe('post_sacrifice');
});

it('leaves the basis unasked when the user is not sacrificing', function () {
    // Asked only when the answer would change a figure. Someone who says they do NOT
    // sacrifice has no annual allowance taper to decide, so null stays null and
    // `IncomeDefinitionsService` keeps reporting the basis as not-recorded.
    $user = User::factory()->create(['is_preview_user' => false, 'employment_income_basis' => null]);
    $pension = DCPension::factory()->for($user)->create(['salary_sacrifice' => null]);

    app(CoordinatingAgent::class)->executeTool('capture_salary_sacrifice', [
        'pension_id' => $pension->id,
        'salary_sacrifice' => false,
        'employment_income_basis' => 'gross',
    ], $user);

    expect($user->fresh()->employment_income_basis)->toBeNull();
});

it('never re-asks: an answer already on file is not overwritten', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'employment_income_basis' => 'gross']);
    $pension = DCPension::factory()->for($user)->create(['salary_sacrifice' => null]);

    app(CoordinatingAgent::class)->executeTool('capture_salary_sacrifice', [
        'pension_id' => $pension->id,
        'salary_sacrifice' => true,
        'employment_income_basis' => 'post_sacrifice',
    ], $user);

    expect($user->fresh()->employment_income_basis)->toBe('gross');
});

it('refuses a basis the column cannot hold', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'employment_income_basis' => null]);
    $pension = DCPension::factory()->for($user)->create(['salary_sacrifice' => null]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_salary_sacrifice', [
        'pension_id' => $pension->id,
        'salary_sacrifice' => true,
        'employment_income_basis' => 'net',
    ], $user);

    expect($result['error'] ?? null)->not->toBeNull()
        ->and($user->fresh()->employment_income_basis)->toBeNull();
});

it('offers the basis in the tool schema, or the model can never send it', function () {
    // The root cause, and the half a handler fix would not reach: the live xAI schema
    // is `strict: true` with `additionalProperties: false`, so a parameter that is not
    // declared cannot be returned however well the model is prompted.
    $schema = (string) file_get_contents(
        base_path('fyn-memory/procedural/tool_schema/campaign/capture_salary_sacrifice.xai.md')
    );

    expect($schema)->toContain('employment_income_basis')
        ->and($schema)->toContain('post_sacrifice');
});
