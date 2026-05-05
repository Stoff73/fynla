<?php

declare(strict_types=1);

use App\Models\DocumentArticle;
use App\Models\User;
use App\Services\Documents\DocumentArticleImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('creates a draft row + writes images + rewrites placeholders', function () {
    $docx = new UploadedFile(
        base_path('tests/fixtures/documents/sample-minimal.docx'),
        'sample-minimal.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true
    );

    $html = '<p>Hello</p><img data-pending-image="0" alt="">';
    $imageBlobs = [
        0 => UploadedFile::fake()->image('img-0.png', 100, 80),
    ];

    $article = app(DocumentArticleImporter::class)->import(
        docxFile: $docx,
        html: $html,
        imageBlobs: $imageBlobs,
        clientMetadata: [
            'title' => 'Client title',
            'subtitle' => null,
            'description' => null,
            'keywords' => null,
            'author_name' => null,
        ],
        importedBy: $this->admin,
    );

    expect($article)->toBeInstanceOf(DocumentArticle::class)
        ->and($article->status)->toBe('draft')
        ->and($article->title)->toBe('Minimal Sample Title') // server-extracted overrides client
        ->and($article->author_name)->toBe('Jane Doe')
        ->and($article->html_body)->toContain('/storage/document-articles/'.$article->id.'/img-0.png')
        ->and($article->html_body)->not->toContain('data-pending-image');

    Storage::disk('public')->assertExists('document-articles/'.$article->id.'/img-0.png');
});

it('rejects import when html references an image index with no matching blob', function () {
    $docx = new UploadedFile(
        base_path('tests/fixtures/documents/sample-minimal.docx'),
        'sample-minimal.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true
    );

    $beforeCount = DocumentArticle::count();

    expect(fn () => app(DocumentArticleImporter::class)->import(
        docxFile: $docx,
        html: '<p>Hi</p><img data-pending-image="0">',
        imageBlobs: [], // missing image referenced by HTML
        clientMetadata: [
            'title' => 'X', 'subtitle' => null, 'description' => null,
            'keywords' => null, 'author_name' => null,
        ],
        importedBy: $this->admin,
    ))->toThrow(\RuntimeException::class);

    expect(DocumentArticle::count())->toBe($beforeCount);
});

it('rolls back the article row when image write fails inside the transaction', function () {
    $docx = new UploadedFile(
        base_path('tests/fixtures/documents/sample-minimal.docx'),
        'sample-minimal.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true
    );

    // Override the fake disk installed in beforeEach with a mock that returns false from putFileAs.
    Storage::shouldReceive('disk')
        ->with('public')
        ->andReturnSelf();
    Storage::shouldReceive('putFileAs')
        ->andReturn(false);
    // Allow delete() calls during cleanup.
    Storage::shouldReceive('delete')->andReturn(true);

    $beforeCount = DocumentArticle::count();

    expect(fn () => app(DocumentArticleImporter::class)->import(
        docxFile: $docx,
        html: '<p>Hi</p><img data-pending-image="0" alt="">',
        imageBlobs: [
            0 => UploadedFile::fake()->image('img-0.png', 100, 80),
        ],
        clientMetadata: [
            'title' => 'Will Roll Back',
            'subtitle' => null,
            'description' => null,
            'keywords' => null,
            'author_name' => null,
        ],
        importedBy: $this->admin,
    ))->toThrow(\RuntimeException::class, 'Failed to write image');

    // The DB::transaction must have rolled back the create.
    expect(DocumentArticle::count())->toBe($beforeCount);
});
