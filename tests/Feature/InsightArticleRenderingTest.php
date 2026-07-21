<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns body_blocks unchanged through the public show endpoint', function () {
    InsightArticle::factory()->published()->create([
        'slug' => 'block-test',
        'body_blocks' => [
            ['type' => 'heading', 'level' => 2, 'text' => 'Intro'],
            ['type' => 'paragraph', 'html' => '<p>Hello</p>'],
            ['type' => 'callout', 'variant' => 'tip', 'html' => '<p>Tip</p>'],
        ],
    ]);

    $this->getJson('/api/insights/block-test')
        ->assertOk()
        ->assertJsonPath('data.body_blocks.0.type', 'heading')
        ->assertJsonPath('data.body_blocks.2.variant', 'tip');
});
