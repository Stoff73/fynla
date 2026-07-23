<?php

declare(strict_types=1);

use App\Models\DocumentArticle;
use App\Models\Insights\InsightArticle;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lists only published articles', function () {
    InsightArticle::factory()->published()->create();
    InsightArticle::factory()->create(['status' => 'draft']);
    InsightArticle::factory()->create(['status' => 'archived']);

    $this->getJson('/api/insights')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('returns featured article and two supporting articles', function () {
    InsightArticle::factory()->published()->featured()->create(['published_at' => now()->subDay()]);
    InsightArticle::factory()->published()->create(['published_at' => now()->subHour()]);
    InsightArticle::factory()->published()->create(['published_at' => now()->subMinutes(30)]);
    InsightArticle::factory()->published()->create(['published_at' => now()->subMinutes(15)]);

    $response = $this->getJson('/api/insights/featured')->assertOk();

    expect($response['data']['featured']['is_featured'])->toBeTrue()
        ->and($response['data']['supporting'])->toHaveCount(2);
});

it('falls back to the latest published article as featured when nothing flagged is_featured', function () {
    // InsightController::featured() prefers an explicitly is_featured=true
    // article but falls back to the most recently published one so the public
    // homepage always has a featured article (1dba112, supersedes the earlier
    // no-fallback contract from 5d3ac7f). The fallback never appears twice —
    // supporting excludes it.
    $latest = InsightArticle::factory()->published()->create(['published_at' => now()->subHour()]);
    $older = InsightArticle::factory()->published()->create(['published_at' => now()->subDay()]);

    $response = $this->getJson('/api/insights/featured')->assertOk();

    expect($response['data']['featured']['slug'])->toBe($latest->slug)
        ->and($response['data']['featured']['is_featured'])->toBeFalse()
        ->and($response['data']['supporting'])->toHaveCount(1)
        ->and($response['data']['supporting'][0]['slug'])->toBe($older->slug);
});

it('surfaces published document (CMS/pipeline) articles on the homepage featured strip', function () {
    InsightArticle::factory()->published()->create(['published_at' => now()->subDay()]);
    // A newer CMS/pipeline document article — must appear on the homepage strip,
    // not only the /insights listing.
    DocumentArticle::factory()->published()->create([
        'slug' => 'cms-pipeline-article',
        'published_at' => now()->subHour(),
    ]);

    $response = $this->getJson('/api/insights/featured')->assertOk();

    $slugs = collect([$response['data']['featured']])
        ->merge($response['data']['supporting'])
        ->pluck('slug');

    expect($slugs)->toContain('cms-pipeline-article');
});

it('falls back to a default cover image when an article has none', function () {
    DocumentArticle::factory()->published()->create([
        'slug' => 'no-cover-doc',
        'cover_image_path' => null,
    ]);

    $response = $this->getJson('/api/insights/featured')->assertOk();

    expect($response['data']['featured']['image_card'])
        ->toEndWith('/images/insights/insight-default.svg');
});

it('returns a published article by slug', function () {
    $article = InsightArticle::factory()->published()->create(['slug' => 'my-post']);

    $this->getJson('/api/insights/my-post')
        ->assertOk()
        ->assertJsonPath('data.slug', 'my-post');
});

it('returns 404 for drafts', function () {
    InsightArticle::factory()->create(['slug' => 'draft-post', 'status' => 'draft']);

    $this->getJson('/api/insights/draft-post')
        ->assertNotFound();
});

it('returns 404 for archived articles', function () {
    InsightArticle::factory()->create(['slug' => 'old-post', 'status' => 'archived']);

    $this->getJson('/api/insights/old-post')
        ->assertNotFound();
});

it('admins with ?preview=true can see drafts', function () {
    $this->seed(RolesPermissionsSeeder::class);
    $adminRole = Role::findByName(Role::ROLE_ADMIN);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_admin' => true]);

    InsightArticle::factory()->create(['slug' => 'preview-me', 'status' => 'draft']);

    Sanctum::actingAs($admin);

    $this->getJson('/api/insights/preview-me?preview=true')
        ->assertOk();
});

it('non-admins with ?preview=true still get 404 for drafts', function () {
    $this->seed(RolesPermissionsSeeder::class);
    $userRole = Role::findByName(Role::ROLE_USER);
    $user = User::factory()->create(['role_id' => $userRole->id, 'is_admin' => false]);

    InsightArticle::factory()->create(['slug' => 'preview-hidden', 'status' => 'draft']);

    Sanctum::actingAs($user);

    $this->getJson('/api/insights/preview-hidden?preview=true')
        ->assertNotFound();
});
