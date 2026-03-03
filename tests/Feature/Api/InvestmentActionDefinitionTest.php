<?php

declare(strict_types=1);

use App\Models\InvestmentActionDefinition;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\InvestmentActionDefinitionSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
    $this->seed(InvestmentActionDefinitionSeeder::class);

    $adminRole = Role::findByName(Role::ROLE_ADMIN);
    $userRole = Role::findByName(Role::ROLE_USER);

    $this->adminUser = User::factory()->create([
        'role_id' => $adminRole->id,
        'is_admin' => true,
        'is_preview_user' => true,
    ]);

    $this->regularUser = User::factory()->create([
        'role_id' => $userRole->id,
        'is_preview_user' => true,
    ]);
});

describe('Admin Investment Action Definitions API', function () {
    it('lists all action definitions for admin', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/investment-actions');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'key', 'source', 'title_template', 'category', 'priority', 'is_enabled'],
                ],
            ]);

        expect($response->json('data'))->toHaveCount(21);
    });

    it('denies access to non-admin users', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/admin/investment-actions');

        $response->assertForbidden();
    });

    it('shows a single action definition', function () {
        Sanctum::actingAs($this->adminUser);
        $definition = InvestmentActionDefinition::findByKey('high_total_fees');

        $response = $this->getJson("/api/admin/investment-actions/{$definition->id}");

        $response->assertOk()
            ->assertJsonPath('data.key', 'high_total_fees')
            ->assertJsonPath('data.what_if_impact_type', 'fee_reduction');
    });

    it('creates a new action definition', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/admin/investment-actions', [
            'key' => 'custom_investment_action',
            'source' => 'agent',
            'title_template' => 'Custom Investment Action Title',
            'description_template' => 'Custom action description with {placeholder}.',
            'category' => 'Custom',
            'priority' => 'low',
            'scope' => 'portfolio',
            'what_if_impact_type' => 'default',
            'trigger_config' => ['condition' => 'custom_condition', 'threshold' => 10],
            'is_enabled' => true,
            'sort_order' => 200,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.key', 'custom_investment_action');

        $this->assertDatabaseHas('investment_action_definitions', ['key' => 'custom_investment_action']);
    });

    it('validates required fields on create', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/admin/investment-actions', [
            'key' => '',
            'source' => 'invalid',
        ]);

        $response->assertStatus(422);
    });

    it('validates what_if_impact_type values', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/admin/investment-actions', [
            'key' => 'test_action',
            'source' => 'agent',
            'title_template' => 'Test',
            'description_template' => 'Test',
            'category' => 'Test',
            'priority' => 'low',
            'scope' => 'portfolio',
            'what_if_impact_type' => 'invalid_type',
            'trigger_config' => ['condition' => 'test'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['what_if_impact_type']);
    });

    it('updates an existing action definition', function () {
        Sanctum::actingAs($this->adminUser);
        $definition = InvestmentActionDefinition::findByKey('high_total_fees');

        $response = $this->putJson("/api/admin/investment-actions/{$definition->id}", [
            'key' => 'high_total_fees',
            'source' => 'agent',
            'title_template' => 'Updated Fee Title',
            'description_template' => $definition->description_template,
            'category' => $definition->category,
            'priority' => 'critical',
            'scope' => 'account',
            'what_if_impact_type' => 'fee_reduction',
            'trigger_config' => ['condition' => 'total_fee_above', 'threshold' => 2.0],
            'sort_order' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title_template', 'Updated Fee Title')
            ->assertJsonPath('data.priority', 'critical');
    });

    it('toggles enabled state', function () {
        Sanctum::actingAs($this->adminUser);
        $definition = InvestmentActionDefinition::findByKey('high_total_fees');

        expect($definition->is_enabled)->toBeTrue();

        $response = $this->patchJson("/api/admin/investment-actions/{$definition->id}/toggle");

        $response->assertOk();
        expect($response->json('data.is_enabled'))->toBeFalse();

        // Toggle again
        $response = $this->patchJson("/api/admin/investment-actions/{$definition->id}/toggle");
        expect($response->json('data.is_enabled'))->toBeTrue();
    });

    it('deletes an action definition', function () {
        Sanctum::actingAs($this->adminUser);
        $definition = InvestmentActionDefinition::findByKey('high_total_fees');

        $response = $this->deleteJson("/api/admin/investment-actions/{$definition->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('investment_action_definitions', ['id' => $definition->id]);
    });

    it('returns 404 for non-existent definition', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/investment-actions/99999');

        $response->assertNotFound();
    });

    it('rejects duplicate keys on create', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/admin/investment-actions', [
            'key' => 'high_total_fees',
            'source' => 'agent',
            'title_template' => 'Duplicate',
            'description_template' => 'Duplicate description.',
            'category' => 'Test',
            'priority' => 'low',
            'scope' => 'portfolio',
            'what_if_impact_type' => 'default',
            'trigger_config' => ['condition' => 'test'],
        ]);

        $response->assertStatus(422);
    });

    it('denies all CRUD operations to non-admin users', function () {
        Sanctum::actingAs($this->regularUser);
        $definition = InvestmentActionDefinition::first();

        $this->getJson('/api/admin/investment-actions')->assertForbidden();
        $this->getJson("/api/admin/investment-actions/{$definition->id}")->assertForbidden();
        $this->postJson('/api/admin/investment-actions', [])->assertForbidden();
        $this->putJson("/api/admin/investment-actions/{$definition->id}", [])->assertForbidden();
        $this->deleteJson("/api/admin/investment-actions/{$definition->id}")->assertForbidden();
        $this->patchJson("/api/admin/investment-actions/{$definition->id}/toggle")->assertForbidden();
    });
});
