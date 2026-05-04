<?php

declare(strict_types=1);

use App\Models\DocumentArticle;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('returns 200 for a published article', function () {
    $article = DocumentArticle::factory()->published()->create([
        'slug' => 'hello-world',
        'title' => 'Hello World',
        'description' => 'A test article',
        'imported_by' => $this->admin->id,
        'html_body' => '<p>Body content here</p>',
    ]);

    $response = $this->get('/articles/hello-world');

    $response->assertOk()
        ->assertSee('Hello World')
        ->assertSee('Body content here', false)
        ->assertSee('<meta name="description" content="A test article"', false)
        ->assertSee('"@type": "Article"', false);
});

it('404s for a draft article without a preview token', function () {
    DocumentArticle::factory()->create([
        'slug' => 'draft-one',
        'imported_by' => $this->admin->id,
    ]);

    $this->get('/articles/draft-one')->assertNotFound();
});

it('renders a draft when a valid preview token is supplied', function () {
    $article = DocumentArticle::factory()->create([
        'slug' => 'draft-two',
        'title' => 'Draft Title',
        'imported_by' => $this->admin->id,
    ]);

    $url = $article->previewUrl();
    $this->get($url)->assertOk()->assertSee('Draft Title');
});

it('404s on a tampered preview token', function () {
    $article = DocumentArticle::factory()->create([
        'slug' => 'draft-three',
        'imported_by' => $this->admin->id,
    ]);

    $url = $article->previewUrl();
    $tampered = $url.'X';
    $this->get($tampered)->assertNotFound();
});
