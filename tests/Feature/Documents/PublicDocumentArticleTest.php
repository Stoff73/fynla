<?php

declare(strict_types=1);

use App\Models\DocumentArticle;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('returns a published document article through the public insights API', function () {
    DocumentArticle::factory()->published()->create([
        'slug' => 'hello-world',
        'title' => 'Hello World',
        'subtitle' => 'A subtitle',
        'description' => 'A test article',
        'author_byline' => 'Test Author',
        'html_body' => '<p>Body content here</p>',
        'imported_by' => $this->admin->id,
    ]);

    $response = $this->getJson('/api/insights/hello-world');

    $response->assertOk()
        ->assertJsonPath('data.slug', 'hello-world')
        ->assertJsonPath('data.title', 'Hello World')
        ->assertJsonPath('data.subtitle', 'A subtitle')
        ->assertJsonPath('data.summary', 'A test article')
        ->assertJsonPath('data.body_html', '<p>Body content here</p>')
        ->assertJsonPath('data.body_blocks', [])
        ->assertJsonPath('data.is_bespoke', false)
        ->assertJsonPath('data.source', 'document')
        ->assertJsonPath('data.authors', ['Test Author']);
});

it('404s when fetching a draft document article without preview', function () {
    DocumentArticle::factory()->create([
        'slug' => 'draft-one',
        'imported_by' => $this->admin->id,
    ]);

    $this->getJson('/api/insights/draft-one')->assertNotFound();
});

it('returns the draft when an admin requests preview=true', function () {
    DocumentArticle::factory()->create([
        'slug' => 'draft-two',
        'title' => 'Draft Title',
        'description' => 'Draft summary',
        'imported_by' => $this->admin->id,
    ]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/insights/draft-two?preview=true')
        ->assertOk()
        ->assertJsonPath('data.title', 'Draft Title')
        ->assertJsonPath('data.status', 'draft');
});

it('still 404s on draft preview when the requester is not an admin', function () {
    DocumentArticle::factory()->create([
        'slug' => 'draft-three',
        'imported_by' => $this->admin->id,
    ]);

    $nonAdmin = User::factory()->create(['is_admin' => false]);
    Sanctum::actingAs($nonAdmin);

    $this->getJson('/api/insights/draft-three?preview=true')->assertNotFound();
});

it('lists published document articles in the public insights index (page 1, no category)', function () {
    DocumentArticle::factory()->published()->create([
        'slug' => 'doc-list-one',
        'title' => 'Doc List One',
        'imported_by' => $this->admin->id,
    ]);

    $response = $this->getJson('/api/insights');

    $response->assertOk();
    $slugs = collect($response->json('data'))->pluck('slug')->all();
    expect($slugs)->toContain('doc-list-one');
});

it('builds previewUrl pointing at /insights/{slug}?preview=true', function () {
    config(['app.url' => 'https://example.test']);

    $article = DocumentArticle::factory()->create([
        'slug' => 'preview-slug',
        'imported_by' => $this->admin->id,
    ]);

    expect($article->previewUrl())->toBe('https://example.test/insights/preview-slug?preview=true');
});

it('serves SEO meta for a published document article slug at /insights/{slug}', function () {
    DocumentArticle::factory()->published()->create([
        'slug' => 'seo-doc',
        'title' => 'Doc SEO Title',
        'description' => 'Doc SEO description',
        'imported_by' => $this->admin->id,
    ]);

    $response = $this->get('/insights/seo-doc');

    $response->assertOk()
        ->assertSee('<title>Doc SEO Title</title>', false)
        ->assertSee('<meta name="description" content="Doc SEO description">', false);
});
