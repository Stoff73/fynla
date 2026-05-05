<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightTemplate;
use App\Models\User;
use App\Services\Insights\InsightTemplateService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->service = app(InsightTemplateService::class);
});

it('saves a template from an existing article', function () {
    $article = InsightArticle::factory()->create([
        'body_blocks' => [
            ['type' => 'heading', 'level' => 2, 'text' => 'Hello'],
            ['type' => 'paragraph', 'html' => '<p>World</p>'],
        ],
    ]);

    $template = $this->service->saveFromArticle($article, 'My template', 'Description', $this->admin);

    expect($template->name)->toBe('My template')
        ->and($template->body_blocks)->toEqual($article->body_blocks)
        ->and($template->created_by)->toBe($this->admin->id);
});

it('rejects duplicate template names', function () {
    $article = InsightArticle::factory()->create();
    InsightTemplate::factory()->create(['name' => 'Standard guide']);

    $this->service->saveFromArticle($article, 'Standard guide', null, $this->admin);
})->throws(QueryException::class);

it('deletes a template and nulls the reference on articles', function () {
    $template = InsightTemplate::factory()->create();
    $article = InsightArticle::factory()->create(['template_id' => $template->id]);

    $this->service->delete($template);

    expect($article->fresh()->template_id)->toBeNull();
});
