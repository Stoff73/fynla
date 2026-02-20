<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\IHTCalculator;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\Savings\ISATracker;
use App\Services\TaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration tests for Tax Configuration System
 *
 * Tests the entire tax configuration system end-to-end:
 * - Tax config activation and switching
 * - Services using active tax configuration
 * - API endpoints for CRUD operations
 * - Historical tax year retrieval
 * - Validation and business rules
 */
class TaxConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions (required for RBAC middleware)
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

        $adminRole = \App\Models\Role::findByName(\App\Models\Role::ROLE_ADMIN);
        $userRole = \App\Models\Role::findByName(\App\Models\Role::ROLE_USER);

        // Create admin user with admin role
        $this->admin = User::factory()->create([
            'email' => 'admin@fps.com',
            'password' => bcrypt('admin123'),
            'is_admin' => true,
            'role_id' => $adminRole->id,
        ]);

        // Create regular user (non-admin)
        $this->regularUser = User::factory()->create([
            'email' => 'user@fps.com',
            'is_admin' => false,
            'role_id' => $userRole->id,
        ]);
    }

    // =========================================================================
    // 1. Tax Config Activation Switching Tests
    // =========================================================================

    public function test_activating_tax_year_deactivates_others(): void
    {
        // Create two tax configurations
        $config2024 = TaxConfiguration::factory()->create([
            'tax_year' => '2024/25',
            'is_active' => true,
        ]);

        $config2025 = TaxConfiguration::factory()->create([
            'tax_year' => '2025/26',
            'is_active' => false,
        ]);

        // Activate 2025/26 using correct POST method
        $response = $this->actingAs($this->admin)
            ->postJson("/api/tax-settings/{$config2025->id}/activate");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify 2025/26 is active and 2024/25 is inactive
        $this->assertTrue($config2025->fresh()->is_active);
        $this->assertFalse($config2024->fresh()->is_active);
    }

    public function test_services_use_newly_activated_config(): void
    {
        // Create two tax configurations with different values
        TaxConfiguration::factory()->create([
            'tax_year' => '2024/25',
            'is_active' => true,
            'config_data' => [
                'income_tax' => [
                    'personal_allowance' => 12570,
                ],
            ],
        ]);

        $config2025 = TaxConfiguration::factory()->create([
            'tax_year' => '2025/26',
            'is_active' => false,
            'config_data' => [
                'income_tax' => [
                    'personal_allowance' => 13000, // Different value
                ],
            ],
        ]);

        // Activate 2025/26
        $this->actingAs($this->admin)
            ->postJson("/api/tax-settings/{$config2025->id}/activate");

        // Create fresh service instance to pick up new active config
        $taxService = new TaxConfigService;
        $incomeTax = $taxService->getIncomeTax();

        // Verify service uses new config
        $this->assertEquals(13000, $incomeTax['personal_allowance']);
    }

    // =========================================================================
    // 2. Services Retrieve Correct Values Tests
    // =========================================================================

    public function test_tax_config_service_uses_active_config(): void
    {
        TaxConfiguration::factory()->create([
            'tax_year' => '2025/26',
            'is_active' => true,
        ]);

        // TaxConfigService should retrieve active config
        $taxService = app(TaxConfigService::class);
        $incomeTax = $taxService->getIncomeTax();

        // Should use personal allowance from active config
        $this->assertArrayHasKey('personal_allowance', $incomeTax);
        $this->assertEquals(12570, $incomeTax['personal_allowance']);
    }

    public function test_iht_calculator_uses_active_tax_config(): void
    {
        TaxConfiguration::factory()->create([
            'tax_year' => '2025/26',
            'is_active' => true,
            'config_data' => [
                'inheritance_tax' => [
                    'nil_rate_band' => 325000,
                    'residence_nil_rate_band' => 175000,
                    'rnrb_taper_threshold' => 2000000,
                    'rnrb_taper_rate' => 0.5,
                    'standard_rate' => 0.40,
                    'reduced_rate_charity' => 0.36,
                ],
            ],
        ]);

        $ihtCalculator = app(IHTCalculator::class);
        $config = $ihtCalculator->calculateCharitableReduction(1000000, 5);

        // Should use standard rate from active config
        $this->assertEquals(0.40, $config);
    }

    public function test_isa_tracker_uses_active_tax_config(): void
    {
        TaxConfiguration::factory()->create([
            'tax_year' => '2025/26',
            'is_active' => true,
        ]);

        $isaTracker = app(ISATracker::class);
        $allowance = $isaTracker->getTotalAllowance('2025/26');

        // Should use ISA allowance from active config (£20,000)
        $this->assertEquals(20000.0, $allowance);
    }

    public function test_annual_allowance_checker_uses_active_tax_config(): void
    {
        TaxConfiguration::factory()->create([
            'tax_year' => '2025/26',
            'is_active' => true,
        ]);

        $checker = app(AnnualAllowanceChecker::class);
        $taperedAllowance = $checker->calculateTapering(250000, 300000);

        // Should use pension allowances from active config
        // Adjusted income exceeds threshold by £40,000
        // Reduction: £40,000 / 2 = £20,000
        // Tapered allowance: £60,000 - £20,000 = £40,000
        $this->assertEquals(40000.0, $taperedAllowance);
    }

    // =========================================================================
    // 3. Historical Tax Year Retrieval Tests
    // =========================================================================

    public function test_can_retrieve_historical_tax_config(): void
    {
        // Create historical and current configs
        $config2024 = TaxConfiguration::factory()->create([
            'tax_year' => '2024/25',
            'is_active' => false,
        ]);

        $config2025 = TaxConfiguration::factory()->create([
            'tax_year' => '2025/26',
            'is_active' => true,
        ]);

        // Retrieve all configs via API
        $response = $this->actingAs($this->admin)
            ->getJson('/api/tax-settings/all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(2, 'data');

        // Verify both configs are returned
        $data = $response->json('data');
        $taxYears = collect($data)->pluck('tax_year')->toArray();
        $this->assertContains('2024/25', $taxYears);
        $this->assertContains('2025/26', $taxYears);
    }

    // =========================================================================
    // 4. Tax Config CRUD via API Tests
    // =========================================================================

    public function test_admin_can_create_tax_config(): void
    {
        // Use correct endpoint: /api/tax-settings/create
        $response = $this->actingAs($this->admin)
            ->postJson('/api/tax-settings/create', [
                'tax_year' => '2026/27',
                'effective_from' => '2026-04-06',
                'effective_to' => '2027-04-05',
                'is_active' => false,
                'config_data' => [
                    'income_tax' => [
                        'personal_allowance' => 12570,
                        'bands' => [
                            ['name' => 'Basic Rate', 'threshold' => 0, 'rate' => 0.20],
                            ['name' => 'Higher Rate', 'threshold' => 37700, 'rate' => 0.40],
                            ['name' => 'Additional Rate', 'threshold' => 125140, 'rate' => 0.45],
                        ],
                    ],
                    'national_insurance' => [
                        'class_1_employee' => [
                            'primary_threshold' => 12570,
                            'upper_earnings_limit' => 50270,
                            'main_rate' => 0.08,
                            'additional_rate' => 0.02,
                        ],
                    ],
                    'isa' => [
                        'annual_allowance' => 20000,
                        'lifetime_isa' => [
                            'annual_allowance' => 4000,
                        ],
                        'junior_isa' => [
                            'annual_allowance' => 9000,
                        ],
                    ],
                    'pension' => [
                        'annual_allowance' => 60000,
                        'mpaa' => 10000,
                        'tapered_annual_allowance' => [
                            'threshold_income' => 200000,
                            'adjusted_income_threshold' => 260000,
                            'minimum_allowance' => 10000,
                        ],
                    ],
                    'inheritance_tax' => [
                        'nil_rate_band' => 325000,
                        'residence_nil_rate_band' => 175000,
                        'standard_rate' => 0.40,
                        'reduced_rate_charity' => 0.36,
                    ],
                    'gifting_exemptions' => [
                        'annual_exemption' => 3000,
                        'small_gifts_limit' => 250,
                    ],
                    'capital_gains_tax' => [
                        'annual_exempt_amount' => 3000,
                        'basic_rate' => 0.18,
                        'higher_rate' => 0.24,
                    ],
                    'dividend_tax' => [
                        'allowance' => 500,
                        'basic_rate' => 0.0875,
                        'higher_rate' => 0.3375,
                        'additional_rate' => 0.3935,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('tax_configurations', [
            'tax_year' => '2026/27',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_update_tax_config(): void
    {
        $config = TaxConfiguration::factory()->create([
            'tax_year' => '2025/26',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/tax-settings/{$config->id}", [
                'config_data' => [
                    'income_tax' => [
                        'personal_allowance' => 13000, // Updated value
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals(13000, $config->fresh()->config_data['income_tax']['personal_allowance']);
    }

    public function test_admin_can_delete_inactive_tax_config(): void
    {
        $config = TaxConfiguration::factory()->create([
            'tax_year' => '2023/24',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/tax-settings/{$config->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('tax_configurations', [
            'id' => $config->id,
        ]);
    }

    public function test_cannot_delete_active_tax_config(): void
    {
        $config = TaxConfiguration::factory()->create([
            'tax_year' => '2025/26',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/tax-settings/{$config->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseHas('tax_configurations', [
            'id' => $config->id,
        ]);
    }

    public function test_duplicate_tax_config_endpoint(): void
    {
        $sourceConfig = TaxConfiguration::factory()->create([
            'tax_year' => '2024/25',
            'is_active' => true,
            'config_data' => [
                'income_tax' => [
                    'personal_allowance' => 12570,
                ],
            ],
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/tax-settings/{$sourceConfig->id}/duplicate", [
                'new_tax_year' => '2025/26',
                'effective_from' => '2025-04-06',
                'effective_to' => '2026-04-05',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        // Verify duplicated config exists with same config_data
        $duplicate = TaxConfiguration::where('tax_year', '2025/26')->first();
        $this->assertNotNull($duplicate);
        $this->assertFalse($duplicate->is_active); // Starts as inactive
        $this->assertEquals(12570, $duplicate->config_data['income_tax']['personal_allowance']);
    }

    // =========================================================================
    // 5. Validation Tests
    // =========================================================================

    public function test_cannot_create_tax_config_with_invalid_data(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/tax-settings/create', [
                'tax_year' => 'INVALID', // Invalid format
                'effective_from' => '2025-04-06',
                'effective_to' => '2026-04-05',
                'config_data' => [], // Empty config_data
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tax_year', 'config_data']);
    }

    public function test_only_one_tax_year_can_be_active(): void
    {
        // Create first active config
        TaxConfiguration::factory()->create([
            'tax_year' => '2024/25',
            'is_active' => true,
        ]);

        // Try to create second active config
        $response = $this->actingAs($this->admin)
            ->postJson('/api/tax-settings/create', [
                'tax_year' => '2025/26',
                'effective_from' => '2025-04-06',
                'effective_to' => '2026-04-05',
                'is_active' => true,
                'config_data' => [
                    'income_tax' => [
                        'personal_allowance' => 12570,
                        'bands' => [
                            ['name' => 'Basic Rate', 'threshold' => 0, 'rate' => 0.20],
                            ['name' => 'Higher Rate', 'threshold' => 37700, 'rate' => 0.40],
                            ['name' => 'Additional Rate', 'threshold' => 125140, 'rate' => 0.45],
                        ],
                    ],
                    'national_insurance' => [
                        'class_1_employee' => [
                            'primary_threshold' => 12570,
                            'upper_earnings_limit' => 50270,
                            'main_rate' => 0.08,
                            'additional_rate' => 0.02,
                        ],
                    ],
                    'isa' => [
                        'annual_allowance' => 20000,
                        'lifetime_isa' => [
                            'annual_allowance' => 4000,
                        ],
                        'junior_isa' => [
                            'annual_allowance' => 9000,
                        ],
                    ],
                    'pension' => [
                        'annual_allowance' => 60000,
                        'mpaa' => 10000,
                        'tapered_annual_allowance' => [
                            'threshold_income' => 200000,
                            'adjusted_income_threshold' => 260000,
                            'minimum_allowance' => 10000,
                        ],
                    ],
                    'inheritance_tax' => [
                        'nil_rate_band' => 325000,
                        'residence_nil_rate_band' => 175000,
                        'standard_rate' => 0.40,
                        'reduced_rate_charity' => 0.36,
                    ],
                    'gifting_exemptions' => [
                        'annual_exemption' => 3000,
                        'small_gifts_limit' => 250,
                    ],
                    'capital_gains_tax' => [
                        'annual_exempt_amount' => 3000,
                        'basic_rate' => 0.18,
                        'higher_rate' => 0.24,
                    ],
                    'dividend_tax' => [
                        'allowance' => 500,
                        'basic_rate' => 0.0875,
                        'higher_rate' => 0.3375,
                        'additional_rate' => 0.3935,
                    ],
                ],
            ]);

        $response->assertStatus(201);

        // Verify only one config is active
        $this->assertEquals(1, TaxConfiguration::where('is_active', true)->count());

        // Verify the new config is active and old one is not
        $this->assertFalse(TaxConfiguration::where('tax_year', '2024/25')->first()->is_active);
        $this->assertTrue(TaxConfiguration::where('tax_year', '2025/26')->first()->is_active);
    }

    public function test_non_admin_cannot_access_tax_config_endpoints(): void
    {
        // Test create endpoint
        $response = $this->actingAs($this->regularUser)
            ->postJson('/api/tax-settings/create', [
                'tax_year' => '2026/27',
            ]);

        $response->assertStatus(403);
    }

    public function test_tax_year_format_validation(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/tax-settings/create', [
                'tax_year' => '2025-2026', // Wrong format
                'effective_from' => '2025-04-06',
                'effective_to' => '2026-04-05',
                'config_data' => [
                    'income_tax' => ['personal_allowance' => 12570, 'bands' => []],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('tax_year');
    }

    public function test_effective_to_must_be_after_effective_from(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/tax-settings/create', [
                'tax_year' => '2025/26',
                'effective_from' => '2026-04-05',
                'effective_to' => '2025-04-06', // Before effective_from
                'config_data' => [
                    'income_tax' => ['personal_allowance' => 12570, 'bands' => []],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('effective_to');
    }

    public function test_tax_year_must_be_unique(): void
    {
        TaxConfiguration::factory()->create([
            'tax_year' => '2025/26',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/tax-settings/create', [
                'tax_year' => '2025/26', // Duplicate
                'effective_from' => '2025-04-06',
                'effective_to' => '2026-04-05',
                'config_data' => [
                    'income_tax' => ['personal_allowance' => 12570, 'bands' => []],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('tax_year');
    }
}
