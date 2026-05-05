<?php

declare(strict_types=1);

use App\Models\DocumentArticle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->user = User::factory()->create(['is_admin' => false]);
});

it('forbids non-admins from listing', function () {
    Sanctum::actingAs($this->user);
    $this->getJson('/api/admin/documents')->assertForbidden();
});

it('lists articles for admins', function () {
    DocumentArticle::factory()->count(3)->create(['imported_by' => $this->admin->id]);
    Sanctum::actingAs($this->admin);
    $this->getJson('/api/admin/documents')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('imports a docx and returns the new row', function () {
    Sanctum::actingAs($this->admin);

    $docx = new UploadedFile(
        base_path('tests/fixtures/documents/sample-minimal.docx'),
        'sample-minimal.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true
    );

    $response = $this->post('/api/admin/documents', [
        'docx' => $docx,
        'html' => '<p>Hello</p><img data-pending-image="0" alt="">',
        'images' => [
            0 => UploadedFile::fake()->image('img-0.png', 100, 80),
        ],
        'metadata' => [
            'title' => 'Client title',
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Minimal Sample Title')
        ->assertJsonPath('data.status', 'draft');
});

it('updates an article', function () {
    $article = DocumentArticle::factory()->create(['imported_by' => $this->admin->id]);
    Sanctum::actingAs($this->admin);

    $this->putJson("/api/admin/documents/{$article->id}", [
        'title' => 'New title',
        'slug' => 'new-title',
        'html_body' => '<p>New body</p>',
        'subtitle' => null,
        'description' => null,
        'keywords' => null,
        'author_byline' => 'New Author',
        'cover_image_path' => null,
    ])->assertOk()->assertJsonPath('data.title', 'New title');

    expect($article->fresh()->slug)->toBe('new-title');
});

it('publishes an article', function () {
    $article = DocumentArticle::factory()->create([
        'imported_by' => $this->admin->id,
        'status' => 'draft',
        'published_at' => null,
    ]);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/admin/documents/{$article->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    expect($article->fresh()->published_at)->not->toBeNull();
});

it('rejects publish when title is empty', function () {
    $article = DocumentArticle::factory()->create([
        'imported_by' => $this->admin->id,
        'title' => '',
    ]);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/admin/documents/{$article->id}/publish")
        ->assertStatus(422);
});

it('unpublishes an article', function () {
    $article = DocumentArticle::factory()->published()->create(['imported_by' => $this->admin->id]);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/admin/documents/{$article->id}/unpublish")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');

    expect($article->fresh()->published_at)->toBeNull();
});

it('deletes an article', function () {
    $article = DocumentArticle::factory()->create(['imported_by' => $this->admin->id]);
    Sanctum::actingAs($this->admin);

    $this->deleteJson("/api/admin/documents/{$article->id}")->assertNoContent();
    expect(DocumentArticle::find($article->id))->toBeNull();
});
