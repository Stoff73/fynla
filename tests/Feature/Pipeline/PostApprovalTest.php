<?php

declare(strict_types=1);

use App\Models\DocumentArticle;
use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\PipelineArticle;
use App\Models\Pipeline\PipelinePost;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('pipeline.enabled', true);
    $this->seed(RolesPermissionsSeeder::class);
});

function pipelineAdmin(): User
{
    $admin = User::factory()->create();
    $admin->role_id = Role::where('name', 'admin')->first()->id;
    $admin->save();

    return $admin;
}

function awaitingPost(PipelineArticle $article, string $destinationType = 'article'): PipelinePost
{
    return PipelinePost::create([
        'pipeline_article_id' => $article->id,
        'platform' => 'instagram',
        'clip_index' => 1,
        'variant' => 'A',
        'caption' => 'caption',
        'hashtags' => ['#a'],
        'destination_type' => $destinationType,
        'destination_url_default' => 'https://fynla.org/insights/x',
        'status' => 'awaiting_approval',
    ]);
}

// Regression: approve() passed the InsightArticle MODEL to UtmLinkBuilder::forArticle(),
// which takes a string slug. Under strict_types that is a TypeError, so every
// post approval 500'd and nothing was ever scheduled.
it('approves an article-destination post and stamps a UTM final URL', function () {
    $insight = InsightArticle::factory()->published()->create(['slug' => 'isa-allowance']);
    $article = PipelineArticle::create([
        'insight_article_id' => $insight->id,
        'status' => 'rendered',
        'clip_paths' => ['storage/app/social/video/isa-allowance/clip-1.mp4'],
    ]);
    $post = awaitingPost($article);

    $this->actingAs(pipelineAdmin())
        ->postJson("/api/admin/pipeline/posts/{$post->id}/approve")
        ->assertOk()
        ->assertJsonPath('success', true);

    $fresh = $post->fresh();
    expect($fresh->status)->toBe('approved')
        ->and($fresh->destination_url_final)->toContain('isa-allowance')
        ->and($fresh->destination_url_final)->toContain('utm_');
});

// Same call path, but a document-sourced article has no InsightArticle at all —
// the old code passed null into a non-nullable string param.
it('approves a post whose article came from a document, not the native CMS', function () {
    $document = DocumentArticle::factory()->create([
        'slug' => 'doc-sourced',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    $article = PipelineArticle::create([
        'document_article_id' => $document->id,
        'status' => 'rendered',
        'clip_paths' => ['storage/app/social/video/doc-sourced/clip-1.mp4'],
    ]);
    $post = awaitingPost($article);

    $this->actingAs(pipelineAdmin())
        ->postJson("/api/admin/pipeline/posts/{$post->id}/approve")
        ->assertOk();

    expect($post->fresh()->destination_url_final)->toContain('doc-sourced');
});
