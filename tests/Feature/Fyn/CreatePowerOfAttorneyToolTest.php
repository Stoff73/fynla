<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\LpaAttorney;
use App\Models\User;
use App\Services\AI\AiToolDefinitions;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * B-7 — LPA status=registered was being dropped by the LLM.
 *
 * The symptom was that when a user said "I have a registered LPA" the model
 * called create_power_of_attorney but omitted status — handler fell back
 * to 'draft' and the DB row had is_registered_with_opg=false. This test
 * pins the handler side: when the LLM (or the deterministic gap-filler)
 * DOES pass status='registered', the DB row is_registered_with_opg flag
 * and registration_date must both be populated correctly.
 *
 * The LLM side is addressed by the tightened prompt in
 * AiToolDefinitions.php + XaiToolDefinitions.php — see Report 1 §B-7.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('persists is_registered_with_opg when status=registered is provided', function () {
    $user = User::factory()->create(['is_preview_user' => false,
        'first_name' => 'Chris',
        'surname' => 'Jones',
        'date_of_birth' => '1980-01-01',
    ]);

    $agent = app(CoordinatingAgent::class);

    $result = $agent->executeTool('create_power_of_attorney', [
        'lpa_type' => 'property_financial',
        'primary_attorney_name' => 'Tom Jones',
        'status' => 'registered',
    ], $user);

    expect($result['action'] ?? null)->toBe('record_saved');
    expect($result['id'])->toBeInt();

    $lpa = LastingPowerOfAttorney::find($result['id']);
    expect($lpa)->not->toBeNull();
    expect($lpa->status)->toBe('registered');
    expect((bool) $lpa->is_registered_with_opg)->toBeTrue();
    expect($lpa->registration_date)->not->toBeNull();

    $attorney = LpaAttorney::where('lasting_power_of_attorney_id', $lpa->id)->first();
    expect($attorney)->not->toBeNull();
    expect($attorney->full_name)->toBe('Tom Jones');
    expect($attorney->attorney_type)->toBe('primary');
});

it('defaults to draft and does not set registration_date when status is omitted', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'first_name' => 'Draft', 'surname' => 'User']);

    $agent = app(CoordinatingAgent::class);

    $result = $agent->executeTool('create_power_of_attorney', [
        'lpa_type' => 'health_welfare',
        'primary_attorney_name' => 'Alice Smith',
    ], $user);

    $lpa = LastingPowerOfAttorney::find($result['id']);
    expect($lpa->status)->toBe('draft');
    expect((bool) $lpa->is_registered_with_opg)->toBeFalse();
    expect($lpa->registration_date)->toBeNull();
});

it('accepts both lpa_type values and creates a primary attorney row per LPA', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $agent = app(CoordinatingAgent::class);

    $agent->executeTool('create_power_of_attorney', [
        'lpa_type' => 'property_financial',
        'primary_attorney_name' => 'Tom',
        'status' => 'registered',
    ], $user);

    $agent->executeTool('create_power_of_attorney', [
        'lpa_type' => 'health_welfare',
        'primary_attorney_name' => 'Tom',
        'status' => 'draft',
    ], $user);

    $lpas = LastingPowerOfAttorney::where('user_id', $user->id)->get();
    expect($lpas)->toHaveCount(2);
    expect($lpas->pluck('lpa_type')->sort()->values()->toArray())
        ->toBe(['health_welfare', 'property_financial']);
});

it('includes status-extraction signal words in the create_power_of_attorney tool description', function () {
    $toolDefs = app(AiToolDefinitions::class);

    // Locate the create_power_of_attorney tool in the full tool list.
    $method = new ReflectionMethod($toolDefs, 'estateCreationTools');
    $method->setAccessible(true);
    $tools = $method->invoke($toolDefs);

    $lpa = collect($tools)->firstWhere('name', 'create_power_of_attorney');
    expect($lpa)->not->toBeNull();

    // The signal words CSJ called out in B-7 must be explicitly listed in
    // the prompt so the LLM knows how to extract status correctly.
    expect($lpa['description'])->toContain('registered');
    expect($lpa['description'])->toContain('in force');
    expect($lpa['description'])->toContain('OPG');
    expect($lpa['description'])->toContain('draft');
    expect($lpa['description'])->toContain('NEVER drop status=registered');
});
