<?php

declare(strict_types=1);

use App\Services\AI\Memory\FynMemoryStore;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->dir = storage_path('app/test-episodes-'.uniqid());
    config(['fyn.memory.episodic_path' => $this->dir]);
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

function writeEpisodeFile(string $base, int $userId, string $stamp, string $summary): void
{
    $dir = "{$base}/{$userId}/2026";
    File::ensureDirectoryExists($dir);
    File::put("{$dir}/{$stamp}.md", "---\nuser_id: {$userId}\n---\n\n## Summary\n\n{$summary}\n");
}

it('recalls most-relevant episode first when a query is given', function () {
    writeEpisodeFile($this->dir, 7, '2026-01-01-000000-a-aaaa', 'Talked about emergency fund savings.');
    writeEpisodeFile($this->dir, 7, '2026-02-01-000000-b-bbbb', 'Wants to retire at 60 on a pension.');

    $store = new FynMemoryStore;
    $recalled = $store->recall(7, 'my pension and retirement', 5);

    expect($recalled[0]['body'])->toContain('retire');
});

it('keeps recency order when no query is given (backwards compatible)', function () {
    writeEpisodeFile($this->dir, 7, '2026-01-01-000000-a-aaaa', 'Older episode.');
    writeEpisodeFile($this->dir, 7, '2026-02-01-000000-b-bbbb', 'Newer episode.');

    $store = new FynMemoryStore;
    $recalled = $store->recall(7, null, 5);

    expect($recalled[0]['body'])->toContain('Newer');
});
