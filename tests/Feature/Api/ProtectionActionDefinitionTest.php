<?php

declare(strict_types=1);

use App\Models\ProtectionActionDefinition;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
    $this->seed(ProtectionActionDefinitionSeeder::class);

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

describe('Admin Protection Action Definitions API', function () {
    it('lists all action definitions for admin', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/protection-actions');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'key', 'source', 'title_template', 'category', 'priority', 'is_enabled'],
                ],
            ]);

        expect($response->json('data'))->toHaveCount(30);
    });

    it('denies access to non-admin users', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/admin/protection-actions');

        $response->assertForbidden();
    });

    it('shows a single action definition', function () {
        Sanctum::actingAs($this->adminUser);
        $definition = ProtectionActionDefinition::findByKey('life_insurance_gap');

        $response = $this->getJson("/api/admin/protection-actions/{$definition->id}");

        $response->assertOk()
            ->assertJsonPath('data.key', 'life_insurance_gap')
            ->assertJsonPath('data.what_if_impact_type', 'coverage_increase');
    });

    it('creates a new action definition', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/admin/protection-actions', [
            'key' => 'custom_protection_action',
            'source' => 'agent',
            'title_template' => 'Custom Protection Action Title',
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
            ->assertJsonPath('data.key', 'custom_protection_action');

        $this->assertDatabaseHas('protection_action_definitions', ['key' => 'custom_protection_action']);
    });

    it('validates required fields on create', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/admin/protection-actions', [
            'key' => '',
            'source' => 'invalid',
        ]);

        $response->assertStatus(422);
    });

    it('validates what_if_impact_type values', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/admin/protection-actions', [
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
        $definition = ProtectionActionDefinition::findByKey('life_insurance_gap');

        $response = $this->putJson("/api/admin/protection-actions/{$definition->id}", [
            'key' => 'life_insurance_gap',
            'source' => 'gap',
            'title_template' => 'Updated Life Insurance Gap Title',
            'description_template' => $definition->description_template,
            'category' => $definition->category,
            'priority' => 'critical',
            'scope' => 'portfolio',
            'what_if_impact_type' => 'coverage_increase',
            'trigger_config' => ['condition' => 'gap_exists', 'coverage_type' => 'life_insurance', 'threshold' => 0],
            'sort_order' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title_template', 'Updated Life Insurance Gap Title')
            ->assertJsonPath('data.priority', 'critical');
    });

    it('toggles enabled state', function () {
        Sanctum::actingAs($this->adminUser);
        $definition = ProtectionActionDefinition::findByKey('life_insurance_gap');

        expect($definition->is_enabled)->toBeTrue();

        $response = $this->patchJson("/api/admin/protection-actions/{$definition->id}/toggle");

        $response->assertOk();
        expect($response->json('data.is_enabled'))->toBeFalse();

        // Toggle again
        $response = $this->patchJson("/api/admin/protection-actions/{$definition->id}/toggle");
        expect($response->json('data.is_enabled'))->toBeTrue();
    });

    it('deletes an action definition', function () {
        Sanctum::actingAs($this->adminUser);
        $definition = ProtectionActionDefinition::findByKey('life_insurance_gap');

        $response = $this->deleteJson("/api/admin/protection-actions/{$definition->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('protection_action_definitions', ['id' => $definition->id]);
    });

    it('returns 404 for non-existent definition', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/protection-actions/99999');

        $response->assertNotFound();
    });

    it('rejects duplicate keys on create', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/admin/protection-actions', [
            'key' => 'life_insurance_gap',
            'source' => 'gap',
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
        $definition = ProtectionActionDefinition::first();

        $this->getJson('/api/admin/protection-actions')->assertForbidden();
        $this->getJson("/api/admin/protection-actions/{$definition->id}")->assertForbidden();
        $this->postJson('/api/admin/protection-actions', [])->assertForbidden();
        $this->putJson("/api/admin/protection-actions/{$definition->id}", [])->assertForbidden();
        $this->deleteJson("/api/admin/protection-actions/{$definition->id}")->assertForbidden();
        $this->patchJson("/api/admin/protection-actions/{$definition->id}/toggle")->assertForbidden();
    });
});
