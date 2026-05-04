<?php

declare(strict_types=1);

use App\Models\DocumentArticle;
use App\Models\User;
use App\Services\Documents\SlugGenerator;

it('returns a slug as-is when not taken', function () {
    expect((new SlugGenerator())->unique('hello-world'))->toBe('hello-world');
});

it('appends -2 when slug is taken once', function () {
    DocumentArticle::factory()->create(['slug' => 'hello-world', 'imported_by' => User::factory()]);
    expect((new SlugGenerator())->unique('hello-world'))->toBe('hello-world-2');
});

it('keeps incrementing until free', function () {
    DocumentArticle::factory()->create(['slug' => 'hello-world', 'imported_by' => User::factory()]);
    DocumentArticle::factory()->create(['slug' => 'hello-world-2', 'imported_by' => User::factory()]);
    DocumentArticle::factory()->create(['slug' => 'hello-world-3', 'imported_by' => User::factory()]);

    expect((new SlugGenerator())->unique('hello-world'))->toBe('hello-world-4');
});

it('respects an ignored id (used when updating an existing row)', function () {
    $row = DocumentArticle::factory()->create(['slug' => 'hello-world', 'imported_by' => User::factory()]);

    expect((new SlugGenerator())->unique('hello-world', ignoreId: $row->id))->toBe('hello-world');
});
