<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);

    $adminRole = Role::findByName(Role::ROLE_ADMIN);
    $userRole = Role::findByName(Role::ROLE_USER);

    $this->admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'is_admin' => true,
    ]);

    $this->user = User::factory()->create([
        'role_id' => $userRole->id,
        'is_admin' => false,
    ]);
});

it('forbids non-admins from listing articles', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/admin/insights/articles')
        ->assertForbidden();
});

it('lists articles for admins', function () {
    InsightArticle::factory()->count(3)->create(['author_id' => $this->admin->id]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/admin/insights/articles')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('creates an article', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/admin/insights/articles', [
        'title' => 'New Guide',
        'summary' => 'What this is about',
        'category' => 'pensions',
        'body_blocks' => [['type' => 'heading', 'level' => 2, 'text' => 'Intro']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'new-guide');
});

it('rejects invalid block types', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/admin/insights/articles', [
        'title' => 'Bad',
        'summary' => 's',
        'category' => 'pensions',
        'body_blocks' => [['type' => 'invented']],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body_blocks');
});

it('publishes an article', function () {
    $article = InsightArticle::factory()->create(['author_id' => $this->admin->id]);

    Sanctum::actingAs($this->admin);

    $this->postJson("/api/admin/insights/articles/{$article->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');
});

it('setting featured auto-unfeatures the previous', function () {
    $previous = InsightArticle::factory()->published()->featured()->create(['author_id' => $this->admin->id]);
    $next = InsightArticle::factory()->published()->create(['author_id' => $this->admin->id]);

    Sanctum::actingAs($this->admin);

    $this->postJson("/api/admin/insights/articles/{$next->id}/feature")
        ->assertOk();

    expect($previous->fresh()->is_featured)->toBeFalse()
        ->and($next->fresh()->is_featured)->toBeTrue();
});

it('archives an article', function () {
    $article = InsightArticle::factory()->published()->create(['author_id' => $this->admin->id]);

    Sanctum::actingAs($this->admin);

    $this->postJson("/api/admin/insights/articles/{$article->id}/archive")
        ->assertOk();

    expect($article->fresh()->status)->toBe('archived');
});

it('lists revisions for an article', function () {
    $article = InsightArticle::factory()->create(['author_id' => $this->admin->id]);

    Sanctum::actingAs($this->admin);

    $this->putJson("/api/admin/insights/articles/{$article->id}", ['title' => 'Changed'])
        ->assertOk();

    $this->getJson("/api/admin/insights/articles/{$article->id}/revisions")
        ->assertOk()
        ->assertJsonStructure(['data']);

    expect($article->fresh()->revisions()->count())->toBeGreaterThan(0);
});
