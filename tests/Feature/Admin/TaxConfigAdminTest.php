<?php

declare(strict_types=1);

use App\Events\ReferenceData\ReferenceDataUpdated;
use App\Models\Role;
use App\Models\TaxConfiguration;
use App\Models\TaxConfigurationAudit;
use App\Models\User;
use App\Services\Stores\TaxConfigStore;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);

    $this->admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
        'role_id' => Role::findByName(Role::ROLE_ADMIN)?->id,
    ]);
    Sanctum::actingAs($this->admin);

    Event::fake([ReferenceDataUpdated::class]);
});

function validConfigPayload(array $overrides = []): array
{
    return array_merge([
        'tax_year' => '2030/31',
        'effective_from' => '2030-04-06',
        'effective_to' => '2031-04-05',
        'is_active' => false,
        'config_data' => [
            'income_tax' => [
                'personal_allowance' => 12570,
                'bands' => [
                    ['name' => 'Basic', 'threshold' => 12570, 'rate' => 0.20],
                    ['name' => 'Higher', 'threshold' => 50270, 'rate' => 0.40],
                    ['name' => 'Additional', 'threshold' => 125140, 'rate' => 0.45],
                ],
            ],
            'isa' => ['annual_allowance' => 20000],
        ],
    ], $overrides);
}

describe('TaxSettings — create', function () {
    it('creates a row, writes an audit row, and emits ReferenceDataUpdated', function () {
        $response = $this->postJson('/api/tax-settings/create', validConfigPayload([
            'tax_year' => '2030/31',
            'rationale' => 'Initial provisioning',
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tax_year', '2030/31');

        $config = TaxConfiguration::where('tax_year', '2030/31')->first();
        expect($config)->not->toBeNull();

        $audit = TaxConfigurationAudit::where('tax_configuration_id', $config->id)
            ->where('change_type', 'created')->first();
        expect($audit)->not->toBeNull()
            ->and($audit->rationale)->toBe('Initial provisioning');

        Event::assertDispatched(
            ReferenceDataUpdated::class,
            fn ($e) => $e->entityKey === 'tax_configuration' && $e->entityId === $config->id
        );
    });

    it('returns 422 when validation fails', function () {
        $this->postJson('/api/tax-settings/create', validConfigPayload(['tax_year' => '2030-31']))
            ->assertStatus(422);
    });

    it('rejects non-admin users with 403', function () {
        $user = User::factory()->create(['is_admin' => false, 'email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $this->postJson('/api/tax-settings/create', validConfigPayload())
            ->assertStatus(403);
    });
});

describe('TaxSettings — update', function () {
    it('updates a row, writes an updated audit row, and emits ReferenceDataUpdated', function () {
        $config = TaxConfiguration::factory()->create([
            'config_data' => ['income_tax' => ['personal_allowance' => 12570]],
        ]);

        $response = $this->putJson("/api/tax-settings/{$config->id}", [
            'config_data' => ['income_tax' => ['personal_allowance' => 13000]],
            'rationale' => 'PA uplift',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $config->refresh();
        expect($config->config_data['income_tax']['personal_allowance'])->toBe(13000);

        $audit = TaxConfigurationAudit::where('tax_configuration_id', $config->id)
            ->where('change_type', 'updated')->first();
        expect($audit)->not->toBeNull()
            ->and($audit->before_state['income_tax']['personal_allowance'])->toBe(12570);

        Event::assertDispatched(
            ReferenceDataUpdated::class,
            fn ($e) => $e->entityKey === 'tax_configuration' && $e->entityId === $config->id
        );
    });

    it('returns 404 for unknown id', function () {
        $this->putJson('/api/tax-settings/99999', ['config_data' => ['x' => 1]])
            ->assertNotFound();
    });
});

describe('TaxSettings — setActive', function () {
    it('activates the chosen row, deactivates siblings, writes both audit rows', function () {
        $old = TaxConfiguration::factory()->forTaxYear('2024/25')->active()->create();
        $new = TaxConfiguration::factory()->forTaxYear('2025/26')->create(['is_active' => false]);

        $response = $this->postJson("/api/tax-settings/{$new->id}/activate", ['rationale' => 'Promote 2025/26']);

        $response->assertOk()->assertJsonPath('success', true);

        expect(TaxConfiguration::find($old->id)->is_active)->toBeFalse();
        expect(TaxConfiguration::find($new->id)->is_active)->toBeTrue();

        expect(TaxConfigurationAudit::where('tax_configuration_id', $old->id)
            ->where('change_type', 'deactivated')->count())->toBe(1);

        $activated = TaxConfigurationAudit::where('tax_configuration_id', $new->id)
            ->where('change_type', 'activated')->first();
        expect($activated)->not->toBeNull()
            ->and($activated->rationale)->toBe('Promote 2025/26');
    });

    it('returns 404 for unknown id', function () {
        $this->postJson('/api/tax-settings/99999/activate')->assertNotFound();
    });
});

describe('TaxSettings — duplicate', function () {
    it('copies config_data into a new inactive row and audits it', function () {
        $source = TaxConfiguration::factory()->forTaxYear('2024/25')->create([
            'config_data' => ['income_tax' => ['personal_allowance' => 12570]],
        ]);

        $response = $this->postJson("/api/tax-settings/{$source->id}/duplicate", [
            'new_tax_year' => '2031/32',
            'effective_from' => '2031-04-06',
            'effective_to' => '2032-04-05',
        ]);

        $response->assertCreated();

        $new = TaxConfiguration::where('tax_year', '2031/32')->first();
        expect($new)->not->toBeNull()
            ->and($new->is_active)->toBeFalse()
            ->and($new->config_data['income_tax']['personal_allowance'])->toBe(12570);

        $audit = TaxConfigurationAudit::where('tax_configuration_id', $new->id)->first();
        expect($audit)->not->toBeNull()
            ->and($audit->change_type)->toBe('duplicated')
            ->and($audit->rationale)->toContain('2024/25');
    });

    it('rejects duplicate tax_year', function () {
        $source = TaxConfiguration::factory()->forTaxYear('2024/25')->create();
        TaxConfiguration::factory()->forTaxYear('2031/32')->create();

        $this->postJson("/api/tax-settings/{$source->id}/duplicate", [
            'new_tax_year' => '2031/32',
            'effective_from' => '2031-04-06',
            'effective_to' => '2032-04-05',
        ])->assertStatus(422);
    });
});

describe('TaxSettings — delete', function () {
    it('deletes a non-active row', function () {
        $config = TaxConfiguration::factory()->forTaxYear('2024/25')->create(['is_active' => false]);

        $this->deleteJson("/api/tax-settings/{$config->id}")->assertOk();

        expect(TaxConfiguration::find($config->id))->toBeNull();
    });

    it('refuses to delete the active row with 403', function () {
        $config = TaxConfiguration::factory()->forTaxYear('2024/25')->active()->create();

        $this->deleteJson("/api/tax-settings/{$config->id}")->assertForbidden();

        expect(TaxConfiguration::find($config->id))->not->toBeNull();
    });

    it('returns 404 for unknown id', function () {
        $this->deleteJson('/api/tax-settings/99999')->assertNotFound();
    });
});

describe('TaxSettings — B2 round-trip (R1.5)', function () {
    it('preserves every Vue-editable section through a full saveChanges payload', function () {
        // B2-A: the Vue form has v-model bindings across 14 config_data sections
        // but saveChanges() omitted 5 of them. This test exercises the FULL
        // canonical-Vue payload shape so any backend regression that drops a
        // section on round-trip is caught.
        $config = TaxConfiguration::factory()->forTaxYear('2027/28')->create([
            'config_data' => [
                'income_tax' => ['personal_allowance' => 12570],
                'national_insurance' => ['class_1' => ['employee' => ['main_rate' => 0.08]]],
                'isa' => ['annual_allowance' => 20000],
                'capital_gains_tax' => ['annual_exempt_amount' => 3000],
                'dividend_tax' => ['allowance' => 500],
                'pension' => ['annual_allowance' => 60000],
                'inheritance_tax' => ['nil_rate_band' => 325000],
                'gifting_exemptions' => ['annual' => 3000],
                'stamp_duty' => ['threshold' => 250000],
                'savings' => ['premium_bonds_prize_fund_rate' => 0.033],
                'investment' => ['default_growth_rate' => 0.05],
                'protection' => ['life_insurance_default_term_years' => 20],
                'benefits' => ['child_benefit' => ['eldest_child_weekly' => 27.05]],
                'assumptions' => ['inflation_rate' => 0.025],
            ],
        ]);

        $this->putJson("/api/tax-settings/{$config->id}", [
            'config_data' => [
                'income_tax' => ['personal_allowance' => 13000],
                'national_insurance' => ['class_1' => ['employee' => ['main_rate' => 0.09]]],
                'isa' => ['annual_allowance' => 21000],
                'capital_gains_tax' => ['annual_exempt_amount' => 3500],
                'dividend_tax' => ['allowance' => 600],
                'pension' => ['annual_allowance' => 65000],
                'inheritance_tax' => ['nil_rate_band' => 350000],
                'gifting_exemptions' => ['annual' => 3500],
                'stamp_duty' => ['threshold' => 275000],
                'savings' => ['premium_bonds_prize_fund_rate' => 0.04],
                'investment' => ['default_growth_rate' => 0.06],
                'protection' => ['life_insurance_default_term_years' => 25],
                'benefits' => ['child_benefit' => ['eldest_child_weekly' => 28.00]],
                'assumptions' => ['inflation_rate' => 0.03],
            ],
            'rationale' => 'B2-A round-trip test',
        ])->assertOk();

        $config->refresh();

        // All 14 sections must round-trip
        expect($config->config_data['income_tax']['personal_allowance'])->toBe(13000);
        expect($config->config_data['national_insurance']['class_1']['employee']['main_rate'])->toBe(0.09);
        expect($config->config_data['isa']['annual_allowance'])->toBe(21000);
        expect($config->config_data['capital_gains_tax']['annual_exempt_amount'])->toBe(3500);
        expect($config->config_data['dividend_tax']['allowance'])->toBe(600);
        expect($config->config_data['pension']['annual_allowance'])->toBe(65000);
        expect($config->config_data['inheritance_tax']['nil_rate_band'])->toBe(350000);
        expect($config->config_data['gifting_exemptions']['annual'])->toBe(3500);
        expect($config->config_data['stamp_duty']['threshold'])->toBe(275000);
        // The 5 previously-dropped sections must persist
        expect((float) $config->config_data['savings']['premium_bonds_prize_fund_rate'])->toBe(0.04);
        expect((float) $config->config_data['investment']['default_growth_rate'])->toBe(0.06);
        expect((int) $config->config_data['protection']['life_insurance_default_term_years'])->toBe(25);
        expect((float) $config->config_data['benefits']['child_benefit']['eldest_child_weekly'])->toBe(28.00);
        expect((float) $config->config_data['assumptions']['inflation_rate'])->toBe(0.03);
    });
});

describe('TaxSettings — getCalculations reads from active config (W1)', function () {
    it('reflects the active configs personal allowance, not a hardcoded literal', function () {
        // W1: getCalculations() previously hardcoded '£0 - £12,570 (0%)'.
        // After the fix it must read from TaxConfigStore::activeConfig().
        // Deactivate any pre-existing active row and drop the store memo
        // so activeConfig() resolves to the row this test creates.
        TaxConfiguration::where('is_active', true)->update(['is_active' => false]);
        app(TaxConfigStore::class)->forgetActive();

        $active = TaxConfiguration::factory()->forTaxYear('2030/31')->active()->create([
            'config_data' => [
                'income_tax' => [
                    'personal_allowance' => 15000,
                    'bands' => [
                        ['name' => 'Basic', 'lower_limit' => 15000, 'upper_limit' => 60000, 'rate' => 0.20],
                        ['name' => 'Higher', 'lower_limit' => 60000, 'upper_limit' => 150000, 'rate' => 0.40],
                        ['name' => 'Additional', 'lower_limit' => 150000, 'upper_limit' => null, 'rate' => 0.45],
                    ],
                ],
                'isa' => ['annual_allowance' => 25000],
                'inheritance_tax' => ['nil_rate_band' => 400000, 'residence_nil_rate_band' => 200000, 'standard_rate' => 0.40],
                'pension' => ['annual_allowance' => 80000],
                'capital_gains_tax' => ['annual_exempt_amount' => 5000],
            ],
        ]);

        $response = $this->getJson('/api/tax-settings/calculations');
        $response->assertOk()->assertJsonPath('success', true);

        $body = $response->json('data');

        // Income tax band string must include the new personal allowance (15,000), not the legacy 12,570
        expect($body['income_tax']['bands']['personal_allowance'] ?? '')->toContain('15,000');
        expect($body['income_tax']['bands']['personal_allowance'] ?? '')->not->toContain('12,570');

        // ISA, IHT, pension, CGT all reflect this config
        expect($body['isa_allowances']['total_allowance'] ?? '')->toContain('25,000');
        expect($body['inheritance_tax']['nil_rate_band'] ?? '')->toContain('400,000');
        expect($body['inheritance_tax']['residence_nil_rate_band'] ?? '')->toContain('200,000');
        expect($body['pension_allowances']['annual_allowance'] ?? '')->toContain('80,000');
        expect($body['capital_gains_tax']['annual_exemption'] ?? '')->toContain('5,000');
    });
});
