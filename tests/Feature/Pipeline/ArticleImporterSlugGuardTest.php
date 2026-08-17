<?php

declare(strict_types=1);

use App\Models\DocumentArticle;
use App\Models\Insights\InsightArticle;
use App\Services\Pipeline\Content\ArticleImporter;
use App\Services\Pipeline\Content\GoogleDocExporter;
use App\Services\Pipeline\Content\WordDocxIngestor;
use App\Services\Pipeline\Google\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

beforeEach(function () {
    $this->drive = Mockery::mock(GoogleDriveService::class);
    $this->exporter = Mockery::mock(GoogleDocExporter::class);
    $this->ingestor = Mockery::mock(WordDocxIngestor::class);

    $this->importer = new ArticleImporter($this->drive, $this->exporter, $this->ingestor);

    $this->driveFile = fn (string $name, string $id = 'drive-abc') => [
        'id' => $id,
        'name' => $name,
        'mimeType' => DOCX_MIME,
    ];

    $this->parsed = [
        'blocks' => [
            ['type' => 'heading', 'level' => 2, 'text' => 'Tax break changes'],
            ['type' => 'paragraph', 'html' => '<p>What the new allowance means for you.</p>'],
        ],
        'hash' => 'hash-one',
        'image_count' => 0,
    ];
});

afterEach(function () {
    Mockery::close();
});

it('skips a file whose slug is already owned by an insight article', function () {
    InsightArticle::factory()->create(['slug' => 'tax-break-changes']);

    // The guard must fire before any Drive traffic — a skip should cost nothing.
    $this->drive->shouldNotReceive('downloadFile');
    $this->ingestor->shouldNotReceive('ingest');

    $result = ($this->importer)->import(($this->driveFile)('Tax break changes.docx'));

    expect($result['action'])->toBe('skipped')
        ->and($result['article'])->toBeNull()
        ->and($result['reason'])->toContain('already belongs to insight article')
        ->and(InsightArticle::count())->toBe(1);
});

it('skips a file whose slug is already owned by a CMS document article', function () {
    DocumentArticle::factory()->create(['slug' => 'tax-break-changes']);

    $this->drive->shouldNotReceive('downloadFile');

    $result = ($this->importer)->import(($this->driveFile)('Tax break changes.docx'));

    expect($result['action'])->toBe('skipped')
        ->and($result['reason'])->toContain('already belongs to CMS article')
        ->and(InsightArticle::count())->toBe(0);
});

it('never mints a suffixed duplicate slug', function () {
    InsightArticle::factory()->create(['slug' => 'tax-break-changes']);

    ($this->importer)->import(($this->driveFile)('Tax break changes.docx'));

    // The old behaviour created `tax-break-changes-k3n8xq` alongside the original.
    expect(InsightArticle::where('slug', 'like', 'tax-break-changes-%')->count())->toBe(0);
});

it('imports normally when the slug is free', function () {
    $this->drive->shouldReceive('downloadFile')->once();
    $this->ingestor->shouldReceive('ingest')->once()->andReturn($this->parsed);

    $result = ($this->importer)->import(($this->driveFile)('Tax break changes.docx'));

    expect($result['action'])->toBe('created')
        ->and($result['article']->slug)->toBe('tax-break-changes')
        ->and($result['article']->status)->toBe('draft');
});

it('still updates the article this Drive file already owns', function () {
    $article = InsightArticle::factory()->create([
        'slug' => 'tax-break-changes',
        'source_docx_drive_file_id' => 'drive-abc',
        'source_docx_hash' => 'stale-hash',
    ]);

    $this->drive->shouldReceive('downloadFile')->once();
    $this->ingestor->shouldReceive('ingest')->once()->andReturn($this->parsed);

    // The slug guard must not fire against the file's own article, or a re-import
    // of an edited doc would silently become a no-op.
    $result = ($this->importer)->import(($this->driveFile)('Tax break changes.docx'));

    expect($result['action'])->toBe('updated')
        ->and($result['article']->id)->toBe($article->id)
        ->and(InsightArticle::count())->toBe(1);
});

it('reports an unchanged file without re-importing it', function () {
    InsightArticle::factory()->create([
        'slug' => 'tax-break-changes',
        'source_docx_drive_file_id' => 'drive-abc',
        'source_docx_hash' => 'hash-one',
    ]);

    $this->drive->shouldReceive('downloadFile')->once();
    $this->ingestor->shouldReceive('ingest')->once()->andReturn($this->parsed);

    $result = ($this->importer)->import(($this->driveFile)('Tax break changes.docx'));

    expect($result['action'])->toBe('unchanged')
        ->and(InsightArticle::count())->toBe(1);
});
