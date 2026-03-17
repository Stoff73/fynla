<?php

declare(strict_types=1);

use App\Models\ProtectionActionDefinition;
use App\Models\Role;
use App\Models\TaxActionDefinition;
use App\Models\User;
use Database\Seeders\EstateActionDefinitionSeeder;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
    $this->seed(ProtectionActionDefinitionSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
    $this->seed(EstateActionDefinitionSeeder::class);

    $adminRole = Role::findByName(Role::ROLE_ADMIN);
    $userRole = Role::findByName(Role::ROLE_USER);

    $this->admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'is_admin' => true,
        'is_preview_user' => true,
    ]);

    $this->regularUser = User::factory()->create([
        'role_id' => $userRole->id,
        'is_preview_user' => true,
    ]);
});

describe('Generic ActionDefinitionController', function () {
    it('lists action definitions for a valid module', function () {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/action-definitions/protection');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    });

    it('returns 422 for invalid module parameter', function () {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/action-definitions/invalid');

        $response->assertStatus(422);
    });

    it('creates an action definition via generic endpoint', function () {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/admin/action-definitions/estate', [
            'key' => 'test_estate_action',
            'source' => 'agent',
            'title_template' => 'Test Title',
            'description_template' => 'Test description',
            'category' => 'Test',
            'priority' => 'medium',
            'scope' => 'portfolio',
            'what_if_impact_type' => 'estate_protection',
            'trigger_config' => ['condition' => 'test'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('estate_action_definitions', ['key' => 'test_estate_action']);
    });

    it('updates an action definition via generic endpoint', function () {
        Sanctum::actingAs($this->admin);

        $def = ProtectionActionDefinition::first();

        $response = $this->patchJson("/api/admin/action-definitions/protection/{$def->id}", [
            'key' => $def->key,
            'source' => $def->source,
            'title_template' => 'Updated Title',
            'description_template' => $def->description_template,
            'category' => $def->category,
            'priority' => $def->priority,
            'scope' => $def->scope,
            'what_if_impact_type' => $def->what_if_impact_type,
            'trigger_config' => $def->trigger_config,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title_template', 'Updated Title');
    });

    it('toggles enabled state via generic endpoint', function () {
        Sanctum::actingAs($this->admin);

        $def = ProtectionActionDefinition::first();
        $original = $def->is_enabled;

        $response = $this->patchJson("/api/admin/action-definitions/protection/{$def->id}/toggle");

        $response->assertOk();
        expect($def->fresh()->is_enabled)->toBe(! $original);
    });

    it('deletes an action definition via generic endpoint', function () {
        Sanctum::actingAs($this->admin);

        $def = TaxActionDefinition::create([
            'key' => 'test_delete_target',
            'source' => 'agent',
            'title_template' => 'Delete Me',
            'description_template' => 'Test',
            'category' => 'Test',
            'priority' => 'low',
            'scope' => 'portfolio',
            'what_if_impact_type' => 'tax_optimisation',
            'trigger_config' => ['condition' => 'test'],
            'is_enabled' => true,
            'sort_order' => 999,
        ]);

        $response = $this->deleteJson("/api/admin/action-definitions/tax/{$def->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('tax_action_definitions', ['id' => $def->id]);
    });

    it('returns decision matrix data for a module', function () {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/decision-matrix/protection');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'module',
                    'stats' => ['total', 'enabled', 'disabled', 'critical_high', 'medium'],
                    'categories' => [
                        '*' => [
                            'name',
                            'definitions' => [
                                '*' => [
                                    'id', 'key', 'title_template', 'description_template',
                                    'category', 'priority', 'is_enabled', 'trigger_config',
                                    'tree_nodes',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    });

    it('requires admin permission for action definition endpoints', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/admin/action-definitions/protection');

        $response->assertStatus(403);
    });
});
