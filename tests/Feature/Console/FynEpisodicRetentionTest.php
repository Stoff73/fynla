<?php

declare(strict_types=1);

use App\Models\AiMessage;
use App\Services\AI\Memory\Episodic\EpisodeBlobLocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

it('cold-archives blobs older than 12 months and the locator still resolves them', function (): void {
    $msg = AiMessage::factory()->create(['role' => 'assistant', 'created_at' => now()->subMonths(13), 'blob_md_path' => 'episodic/2025/05/01/1/1.md', 'blob_md_sha256' => str_repeat('a', 64)]);
    Storage::disk('local')->put('episodic/2025/05/01/1/1.md', 'BLOB');

    $this->artisan('fyn:episodic:cold-archive')->assertExitCode(0);

    expect(Storage::disk('local')->exists('episodic/2025/05/01/1/1.md'))->toBeFalse()
        ->and(Storage::disk('local')->exists('episodic-cold/2025/05/01/1/1.md'))->toBeTrue()
        ->and(app(EpisodeBlobLocator::class)->resolve('episodic/2025/05/01/1/1.md'))->not->toBeNull();
});

it('does not archive recent blobs', function (): void {
    $msg = AiMessage::factory()->create(['role' => 'assistant', 'created_at' => now()->subMonths(2), 'blob_md_path' => 'episodic/2026/04/01/1/2.md']);
    Storage::disk('local')->put('episodic/2026/04/01/1/2.md', 'BLOB');
    $this->artisan('fyn:episodic:cold-archive')->assertExitCode(0);
    expect(Storage::disk('local')->exists('episodic/2026/04/01/1/2.md'))->toBeTrue();
});

it('purge dry-run deletes nothing; --force deletes a >6yr row + its blob', function (): void {
    $msg = AiMessage::factory()->create(['role' => 'assistant', 'created_at' => now()->subYears(7), 'blob_md_path' => 'episodic-cold/2019/01/01/1/3.md', 'blob_md_sha256' => str_repeat('b', 64)]);
    Storage::disk('local')->put('episodic-cold/2019/01/01/1/3.md', 'OLD');

    // dry-run: nothing deleted
    $this->artisan('fyn:episodic:purge')->assertExitCode(0);
    expect(AiMessage::find($msg->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists('episodic-cold/2019/01/01/1/3.md'))->toBeTrue();

    // --force: row + blob gone
    $this->artisan('fyn:episodic:purge', ['--force' => true])->assertExitCode(0);
    expect(AiMessage::find($msg->id))->toBeNull()
        ->and(Storage::disk('local')->exists('episodic-cold/2019/01/01/1/3.md'))->toBeFalse();
});

it('reconcile flags an orphan blob (no matching ai_messages row)', function (): void {
    Storage::disk('local')->put('episodic/2026/06/01/1/999999.md', 'ORPHAN');
    $this->artisan('fyn:episodic:reconcile')
        ->expectsOutputToContain('999999')
        ->assertExitCode(0);
});
