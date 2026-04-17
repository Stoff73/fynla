<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightTemplate;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
    $adminRole = Role::findByName(Role::ROLE_ADMIN);

    $this->admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'is_admin' => true,
    ]);

    Sanctum::actingAs($this->admin);
});

it('saves a template from an article', function () {
    $article = InsightArticle::factory()->create(['author_id' => $this->admin->id]);

    $this->postJson('/api/admin/insights/templates', [
        'article_id' => $article->id,
        'name' => 'My template',
        'description' => 'A description',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'My template');
});

it('lists templates', function () {
    InsightTemplate::factory()->count(3)->create();

    $this->getJson('/api/admin/insights/templates')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('renames a template', function () {
    $template = InsightTemplate::factory()->create(['name' => 'Old']);

    $this->putJson("/api/admin/insights/templates/{$template->id}", ['name' => 'New'])
        ->assertOk();

    expect($template->fresh()->name)->toBe('New');
});

it('deletes a template and nulls article references', function () {
    $template = InsightTemplate::factory()->create();
    $article = InsightArticle::factory()->create(['template_id' => $template->id]);

    $this->deleteJson("/api/admin/insights/templates/{$template->id}")
        ->assertOk();

    expect($article->fresh()->template_id)->toBeNull();
});
