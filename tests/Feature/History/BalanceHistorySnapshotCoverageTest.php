<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Estate\Liability;
use App\Models\InvestmentAccountValueSnapshot;
use App\Models\LiabilityValueSnapshot;
use App\Models\User;
use App\Services\Onboarding\OnboardingService;
use App\Services\Stores\IngestSource;
use App\Services\Stores\InvestmentAccountStore;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
});

it('snapshots investment values on canonical store create and material update', function () {
    $user = User::factory()->create(['tier' => 'premium']);
    $store = app(InvestmentAccountStore::class);

    $account = $store->create([
        'account_name' => 'Core portfolio',
        'account_type' => 'gia',
        'current_value' => 10000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ], $user, IngestSource::FORM);

    $store->update($account->id, ['current_value' => 12000], $user, IngestSource::FORM);

    expect(InvestmentAccountValueSnapshot::query()
        ->where('investment_account_id', $account->id)
        ->orderBy('id')
        ->get(['column_name', 'value_gbp', 'trigger_reason', 'ingest_source'])
        ->map(fn ($row) => [
            $row->column_name,
            (float) $row->value_gbp,
            $row->trigger_reason,
            $row->ingest_source,
        ])->all())->toBe([
            ['current_value_gbp', 10000.0, 'create', 'form'],
            ['current_value_gbp', 12000.0, 'update', 'form'],
        ]);
});

it('snapshots investment updates through web CRUD and Fyn', function () {
    $user = User::factory()->create(['tier' => 'premium', 'is_preview_user' => false]);
    $account = app(InvestmentAccountStore::class)->create([
        'account_name' => 'Web portfolio',
        'account_type' => 'gia',
        'current_value' => 10000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ], $user, IngestSource::FORM);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/investment/accounts/'.$account->id, ['current_value' => 12000])
        ->assertOk();

    $fynResult = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'investment_account',
        'entity_id' => $account->id,
        'fields' => ['current_value' => 14000],
    ], $user);

    expect($fynResult['success'] ?? false)->toBeTrue()
        ->and(InvestmentAccountValueSnapshot::query()
            ->where('investment_account_id', $account->id)
            ->where('ingest_source', 'fyn_ai')
            ->where('value_gbp', 14000)
            ->exists())->toBeTrue();
});

it('snapshots liability values through web CRUD', function () {
    $user = User::factory()->create(['tier' => 'free']);

    $created = $this->actingAs($user, 'sanctum')->postJson('/api/estate/liabilities', [
        'liability_type' => 'personal_loan',
        'liability_name' => 'Bank loan',
        'current_balance' => 10000,
    ])->assertCreated();

    $liabilityId = $created->json('data.id');

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/estate/liabilities/'.$liabilityId, ['current_balance' => 8000])
        ->assertOk();

    expect(LiabilityValueSnapshot::query()
        ->where('liability_id', $liabilityId)
        ->orderBy('id')
        ->get(['value_gbp', 'trigger_reason', 'ingest_source'])
        ->map(fn ($row) => [(float) $row->value_gbp, $row->trigger_reason, $row->ingest_source])
        ->all())->toBe([
            [10000.0, 'create', 'form'],
            [8000.0, 'update', 'form'],
        ]);
});

it('snapshots liability creation through onboarding', function () {
    $user = User::factory()->create([
        'tier' => 'free',
        'life_stage' => 'estate',
    ]);

    app(OnboardingService::class)->saveStepProgress($user->id, 'liabilities', [
        'liabilities' => [[
            'type' => 'personal_loan',
            'lender' => 'Onboarding Bank',
            'outstanding_balance' => 7500,
            'monthly_payment' => 250,
        ]],
    ]);

    $liability = Liability::query()->where('user_id', $user->id)->sole();

    expect(LiabilityValueSnapshot::query()
        ->where('liability_id', $liability->id)
        ->where('value_gbp', 7500)
        ->where('ingest_source', 'form')
        ->exists())->toBeTrue();
});

it('snapshots liability creation and updates through Fyn', function () {
    $user = User::factory()->create(['tier' => 'free', 'is_preview_user' => false]);

    $created = $this->executeCaptureToolWithEvidence('create_liability', [
        'liability_name' => 'Fyn loan',
        'liability_type' => 'personal_loan',
        'current_balance' => 6000,
    ], $user);

    expect($created['success'] ?? false)->toBeTrue();

    $updated = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'estate_liability',
        'entity_id' => $created['entity_id'],
        'fields' => ['current_balance' => 4000],
    ], $user);

    expect($updated['success'] ?? false)->toBeTrue()
        ->and(LiabilityValueSnapshot::query()
            ->where('liability_id', $created['entity_id'])
            ->where('ingest_source', 'fyn_ai')
            ->pluck('value_gbp')
            ->map(fn ($value) => (float) $value)
            ->all())->toBe([6000.0, 4000.0]);
});
