<?php

declare(strict_types=1);

use App\Events\ReferenceData\ReferenceDataUpdated;
use App\Models\Role;
use App\Models\TaxConfiguration;
use App\Models\TaxConfigurationAudit;
use App\Models\User;
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
