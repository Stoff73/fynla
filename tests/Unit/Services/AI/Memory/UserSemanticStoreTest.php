<?php

declare(strict_types=1);

use App\Services\AI\Memory\UserSemanticStore;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->dir = storage_path('app/test-user-semantic-'.uniqid());
    config(['fyn.memory.user_semantic_path' => $this->dir]);
});

afterEach(fn () => File::deleteDirectory($this->dir));

it('writes and reads a per-user fact, isolated by user', function () {
    $store = new UserSemanticStore;
    $store->put(7, 'retires-2041', 'Plans to retire 2041', 'User intends to retire in 2041.');

    expect($store->forUser(7))->toHaveCount(1)
        ->and($store->forUser(7)[0]['body'])->toContain('2041')
        ->and($store->forUser(99))->toBe([]);
});
