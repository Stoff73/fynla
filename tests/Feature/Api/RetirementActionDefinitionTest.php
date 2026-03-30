<?php

declare(strict_types=1);

use App\Models\RetirementActionDefinition;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
    $this->seed(RetirementActionDefinitionSeeder::class);

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

describe('Admin Retirement Action Definitions API', function () {
    it('lists all action definitions for admin', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/retirement-actions');

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

        $response = $this->getJson('/api/admin/retirement-actions');

        $response->assertForbidden();
    });

    it('shows a single action definition', function () {
        Sanctum::actingAs($this->adminUser);
        $definition = RetirementActionDefinition::findByKey('employer_match');

        $response = $this->getJson("/api/admin/retirement-actions/{$definition->id}");

        $response->assertOk()
            ->assertJsonPath('data.key', 'employer_match')
            ->assertJsonPath('data.what_if_impact_type', 'contribution');
    });

    it('creates a new action definition', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/admin/retirement-actions', [
            'key' => 'custom_action',
            'source' => 'agent',
            'title_template' => 'Custom Action Title',
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
            ->assertJsonPath('data.key', 'custom_action');

        $this->assertDatabaseHas('retirement_action_definitions', ['key' => 'custom_action']);
    });

    it('validates required fields on create', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/admin/retirement-actions', [
            'key' => '',
            'source' => 'invalid',
        ]);

        $response->assertStatus(422);
    });

    it('updates an existing action definition', function () {
        Sanctum::actingAs($this->adminUser);
        $definition = RetirementActionDefinition::findByKey('employer_match');

        $response = $this->putJson("/api/admin/retirement-actions/{$definition->id}", [
            'key' => 'employer_match',
            'source' => 'agent',
            'title_template' => 'Updated Title',
            'description_template' => $definition->description_template,
            'category' => $definition->category,
            'priority' => 'critical',
            'scope' => 'account',
            'what_if_impact_type' => 'contribution',
            'trigger_config' => ['condition' => 'employee_contribution_percent_below', 'threshold' => 8.0],
            'sort_order' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title_template', 'Updated Title')
            ->assertJsonPath('data.priority', 'critical');
    });

    it('toggles enabled state', function () {
        Sanctum::actingAs($this->adminUser);
        $definition = RetirementActionDefinition::findByKey('employer_match');

        expect($definition->is_enabled)->toBeTrue();

        $response = $this->patchJson("/api/admin/retirement-actions/{$definition->id}/toggle");

        $response->assertOk();
        expect($response->json('data.is_enabled'))->toBeFalse();

        // Toggle again
        $response = $this->patchJson("/api/admin/retirement-actions/{$definition->id}/toggle");
        expect($response->json('data.is_enabled'))->toBeTrue();
    });

    it('deletes an action definition', function () {
        Sanctum::actingAs($this->adminUser);
        $definition = RetirementActionDefinition::findByKey('employer_match');

        $response = $this->deleteJson("/api/admin/retirement-actions/{$definition->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('retirement_action_definitions', ['id' => $definition->id]);
    });

    it('returns 404 for non-existent definition', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/retirement-actions/99999');

        $response->assertNotFound();
    });

    it('rejects duplicate keys on create', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/admin/retirement-actions', [
            'key' => 'employer_match',
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
});
