<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightArticleRevision;
use App\Models\User;
use App\Services\Pipeline\Content\ArticleImporter;
use App\Services\Pipeline\Content\GoogleDocExporter;
use App\Services\Pipeline\Content\WordDocxIngestor;
use App\Services\Pipeline\Google\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->drive = Mockery::mock(GoogleDriveService::class);
    $this->ingestor = Mockery::mock(WordDocxIngestor::class);
    $this->importer = new ArticleImporter(
        $this->drive,
        Mockery::mock(GoogleDocExporter::class),
        $this->ingestor,
    );

    $this->file = [
        'id' => 'drive-abc',
        'name' => 'Tax break changes.docx',
        'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    $this->parseAs = function (string $heading, string $body, string $hash) {
        $this->drive->shouldReceive('downloadFile')->once();
        $this->ingestor->shouldReceive('ingest')->once()->andReturn([
            'blocks' => [
                ['type' => 'heading', 'level' => 2, 'text' => $heading],
                ['type' => 'paragraph', 'html' => "<p>{$body}</p>"],
            ],
            'hash' => $hash,
            'image_count' => 0,
        ]);
    };
});

afterEach(function () {
    Mockery::close();
});

it('records a version when an import overwrites an article', function () {
    $article = InsightArticle::factory()->create([
        'slug' => 'tax-break-changes',
        'source_docx_drive_file_id' => 'drive-abc',
        'source_docx_hash' => 'old-hash',
        'title' => 'The version an editor wrote',
    ]);
    InsightArticleRevision::query()->delete();

    ($this->parseAs)('Rewritten by the import', 'New body.', 'new-hash');
    ($this->importer)->import($this->file);

    $versions = $article->revisions()->get();

    expect($versions)->toHaveCount(1)
        ->and($versions[0]->source)->toBe(InsightArticleRevision::SOURCE_DRIVE_IMPORT)
        ->and($versions[0]->saved_by)->toBeNull();
});

it('records a version when an import creates an article, so the first import can be reverted to', function () {
    ($this->parseAs)('First import', 'Body.', 'hash-1');

    $result = ($this->importer)->import($this->file);

    expect($result['article']->revisions()->count())->toBe(1);
});

it('leaves an unchanged file without adding a version', function () {
    $article = InsightArticle::factory()->create([
        'source_docx_drive_file_id' => 'drive-abc',
        'source_docx_hash' => 'same-hash',
    ]);
    InsightArticleRevision::query()->delete();

    ($this->parseAs)('Unchanged', 'Body.', 'same-hash');
    ($this->importer)->import($this->file);

    expect($article->revisions()->count())->toBe(0);
});

it('attributes a version to the person when someone saves in the CMS', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = InsightArticle::factory()->create();
    InsightArticleRevision::query()->delete();

    $this->actingAs($admin);
    $article->update(['title' => 'Edited by hand']);

    $version = $article->revisions()->first();

    expect($version->source)->toBe(InsightArticleRevision::SOURCE_CMS)
        ->and($version->saved_by)->toBe($admin->id);
});

it('still skips cross-environment sync writes, which declare no source', function () {
    $article = InsightArticle::factory()->create(['author_id' => null]);
    InsightArticleRevision::query()->delete();

    // No authenticated user and no declared system source — the sync log is
    // that path's audit trail.
    $article->update(['title' => 'Arrived from another environment']);

    expect($article->revisions()->count())->toBe(0);
});

it('restores the article to an earlier version', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = InsightArticle::factory()->create(['title' => 'Original', 'summary' => 'First summary']);

    $this->actingAs($admin);
    $article->update(['title' => 'Original', 'summary' => 'First summary']);
    $target = $article->revisions()->first();

    $article->update(['title' => 'Overwritten', 'summary' => 'Replaced summary']);

    $this->postJson("/api/admin/insights/articles/{$article->id}/revisions/{$target->id}/restore")
        ->assertOk();

    expect($article->fresh()->title)->toBe('Original')
        ->and($article->fresh()->summary)->toBe('First summary');
});

it('keeps the restored-over version so a restore can itself be undone', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = InsightArticle::factory()->create();

    $this->actingAs($admin);
    $article->update(['title' => 'Version one']);
    $target = $article->revisions()->first();
    $article->update(['title' => 'Version two']);

    $before = $article->revisions()->count();

    $this->postJson("/api/admin/insights/articles/{$article->id}/revisions/{$target->id}/restore")
        ->assertOk();

    expect($article->revisions()->count())->toBe($before + 1);
});

it('offers only the five most recent versions while retaining the rest', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = InsightArticle::factory()->create();

    $this->actingAs($admin);
    foreach (range(1, 9) as $n) {
        $article->update(['title' => "Version {$n}"]);
    }

    $response = $this->getJson("/api/admin/insights/articles/{$article->id}/revisions")->assertOk();

    expect($response->json('data'))->toHaveCount(InsightArticleRevision::HISTORY_LIMIT)
        ->and($article->revisions()->count())->toBeGreaterThan(InsightArticleRevision::HISTORY_LIMIT);
});

it('returns the newest version first', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = InsightArticle::factory()->create();

    $this->actingAs($admin);
    $article->update(['title' => 'Older']);
    $article->update(['title' => 'Newer']);

    $titles = collect(
        $this->getJson("/api/admin/insights/articles/{$article->id}/revisions")->json('data')
    )->pluck('title');

    expect($titles->first())->toBe('Newer');
});

it('refuses to restore a version belonging to a different article', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $mine = InsightArticle::factory()->create();
    $theirs = InsightArticle::factory()->create();

    $this->actingAs($admin);
    $theirs->update(['title' => 'Not yours']);
    $foreign = $theirs->revisions()->first();

    $this->postJson("/api/admin/insights/articles/{$mine->id}/revisions/{$foreign->id}/restore")
        ->assertNotFound();
});

it('requires authentication to read version history', function () {
    $article = InsightArticle::factory()->create();

    $this->getJson("/api/admin/insights/articles/{$article->id}/revisions")
        ->assertUnauthorized();
});

it('keeps the version table append-only', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = InsightArticle::factory()->create();

    $this->actingAs($admin);
    $article->update(['title' => 'Something']);
    $version = $article->revisions()->first();

    expect(fn () => $version->delete())->toThrow(RuntimeException::class)
        ->and(fn () => $version->update(['title' => 'Tampered']))->toThrow(RuntimeException::class);
});

it('restores an article that an import overwrote', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = InsightArticle::factory()->create([
        'slug' => 'tax-break-changes',
        'source_docx_drive_file_id' => 'drive-abc',
        'source_docx_hash' => 'old-hash',
    ]);

    // The editor's own work, saved in the CMS.
    $this->actingAs($admin);
    $article->update(['title' => 'Carefully edited headline', 'summary' => 'Carefully edited summary']);
    $editorVersion = $article->revisions()->first();

    // The crawl then overwrites it.
    ($this->parseAs)('Machine headline', 'Machine body.', 'new-hash');
    ($this->importer)->import($this->file);
    expect($article->fresh()->title)->toBe('Machine headline');

    $this->postJson("/api/admin/insights/articles/{$article->id}/revisions/{$editorVersion->id}/restore")
        ->assertOk();

    expect($article->fresh()->title)->toBe('Carefully edited headline')
        ->and($article->fresh()->summary)->toBe('Carefully edited summary');
});
