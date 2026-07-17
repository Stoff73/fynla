<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\ArticleSyncLog;
use App\Models\Pipeline\PublisherUser;
use App\Models\User;
use App\Services\Pipeline\Content\ArticleSyncService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Config::set('pipeline.sync.dev_url', 'https://dev.example.test');
    Config::set('pipeline.sync.dev_token', 'dev-secret');
    Config::set('pipeline.sync.prod_url', 'https://prod.example.test');
    Config::set('pipeline.sync.prod_token', 'prod-secret');
    Config::set('pipeline.sync.inbound_token', 'inbound-secret');
    $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
});

it('pushes an article to dev and stamps dev_synced_at', function () {
    Http::fake([
        '*dev.example.test/*' => Http::response(['success' => true, 'article_id' => 42], 200),
    ]);

    $article = InsightArticle::factory()->published()->create();

    app(ArticleSyncService::class)->push($article, 'dev');

    $article->refresh();
    expect($article->dev_synced_at)->not->toBeNull();

    $log = ArticleSyncLog::where('insight_article_id', $article->id)->first();
    expect($log->status)->toBe('success')
        ->and($log->target_env)->toBe('dev')
        ->and($log->response_status)->toBe(200);
});

it('records an error log when the target returns non-2xx', function () {
    Http::fake([
        '*prod.example.test/*' => Http::response('oops', 500),
    ]);

    $article = InsightArticle::factory()->published()->create();

    expect(fn () => app(ArticleSyncService::class)->push($article, 'prod'))
        ->toThrow(\RuntimeException::class);

    $article->refresh();
    expect($article->prod_synced_at)->toBeNull();

    $log = ArticleSyncLog::where('insight_article_id', $article->id)->first();
    expect($log->status)->toBe('error')
        ->and($log->response_status)->toBe(500);
});

it('inbound sync endpoint rejects requests without a valid token', function () {
    $this->postJson('/api/admin/pipeline/articles/sync-inbound', ['slug' => 'x'])
        ->assertStatus(401);

    $this->postJson('/api/admin/pipeline/articles/sync-inbound',
        ['slug' => 'x'],
        ['X-Pipeline-Sync-Token' => 'wrong'],
    )->assertStatus(401);
});

it('inbound sync endpoint upserts the article when the token matches', function () {
    $payload = [
        'slug' => 'ingested-from-remote',
        'title' => 'Ingested from remote',
        'summary' => 'Sync payload from dev.',
        'body_blocks' => [['type' => 'paragraph', 'html' => '<p>Body.</p>']],
        'status' => 'published',
        'published_at' => now()->toIso8601String(),
    ];

    $this->postJson('/api/admin/pipeline/articles/sync-inbound', $payload, [
        'X-Pipeline-Sync-Token' => 'inbound-secret',
    ])->assertOk();

    $article = InsightArticle::where('slug', 'ingested-from-remote')->first();
    expect($article)->not->toBeNull()
        ->and($article->title)->toBe('Ingested from remote')
        ->and($article->status)->toBe('published');
});

it('push-to-live endpoint returns 422 until dev_synced_at is set', function () {
    $admin = User::factory()->create();
    $admin->role_id = \App\Models\Role::where('name', 'admin')->first()->id;
    $admin->save();

    $article = InsightArticle::factory()->published()->create();

    $this->actingAs($admin)
        ->postJson("/api/admin/pipeline/articles/{$article->id}/push-to-live")
        ->assertStatus(422);
});

it('push-to-live endpoint returns 403 without publisher permission', function () {
    $admin = User::factory()->create();
    $admin->role_id = \App\Models\Role::where('name', 'admin')->first()->id;
    $admin->save();

    $article = InsightArticle::factory()->published()->create([
        'dev_synced_at' => now()->subHours(2),
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/pipeline/articles/{$article->id}/push-to-live")
        ->assertStatus(403);
});

it('push-to-live succeeds for a publisher after the dev-to-prod gate', function () {
    Http::fake([
        '*prod.example.test/*' => Http::response(['success' => true, 'article_id' => 1], 200),
    ]);

    $admin = User::factory()->create();
    $admin->role_id = \App\Models\Role::where('name', 'admin')->first()->id;
    $admin->save();

    PublisherUser::create([
        'user_id' => $admin->id,
        'granted_by' => $admin->id,
        'granted_at' => now(),
    ]);

    $article = InsightArticle::factory()->published()->create([
        'dev_synced_at' => now()->subHours(2),
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/pipeline/articles/{$article->id}/push-to-live")
        ->assertOk();

    $article->refresh();
    expect($article->prod_synced_at)->not->toBeNull()
        ->and($article->prod_pushed_by)->toBe($admin->id);
});
