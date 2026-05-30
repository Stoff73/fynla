<?php

declare(strict_types=1);

use App\Services\AI\Memory\FynMemoryStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * CoALA Phase 5 — the FynMemoryStore adapter (read procedural, recall + write
 * episodes). Pure markdown file I/O against temp dirs.
 */
beforeEach(function () {
    $this->base = sys_get_temp_dir().'/fyn-memory-test-'.uniqid();
    config([
        'fyn.memory.procedural_path' => $this->base.'/procedural',
        'fyn.memory.episodic_path' => $this->base.'/episodic/episodes',
        'fyn.memory.episodic_rubric' => $this->base.'/episodic/RUBRIC.md',
    ]);
    File::ensureDirectoryExists($this->base.'/procedural');
    File::ensureDirectoryExists($this->base.'/episodic');
    $this->store = app(FynMemoryStore::class);
});

afterEach(function () {
    File::deleteDirectory($this->base);
    Carbon::setTestNow();
});

it('writes an episode and recalls it', function () {
    $path = $this->store->writeEpisode(11, 49, [
        'summary' => 'User wants to retire at 60; risk-averse.',
        'detail' => 'Mentioned a workplace pension.',
        'salience' => 4,
        'signals' => ['goal'],
        'procedural_version' => 'unified',
    ]);

    expect(File::exists($path))->toBeTrue()
        ->and($path)->toContain('/11/'.now()->format('Y').'/');

    $recalled = $this->store->recall(11);

    expect($recalled)->toHaveCount(1)
        ->and($recalled[0]['meta']['user_id'])->toBe(11)
        ->and($recalled[0]['meta']['conversation_id'])->toBe(49)
        ->and($recalled[0]['meta']['salience'])->toBe(4)
        ->and($recalled[0]['body'])->toContain('retire at 60');
});

it('recalls newest first within the limit', function () {
    Carbon::setTestNow('2026-05-01 10:00:00');
    $this->store->writeEpisode(11, 1, ['summary' => 'oldest one']);
    Carbon::setTestNow('2026-05-02 10:00:00');
    $this->store->writeEpisode(11, 1, ['summary' => 'middle one']);
    Carbon::setTestNow('2026-05-03 10:00:00');
    $this->store->writeEpisode(11, 1, ['summary' => 'newest one']);
    Carbon::setTestNow();

    $recalled = $this->store->recall(11, 2);

    expect($recalled)->toHaveCount(2)
        ->and($recalled[0]['body'])->toContain('newest one')
        ->and($recalled[1]['body'])->toContain('middle one');
});

it('builds a recall context block scoped to the user', function () {
    $this->store->writeEpisode(11, 1, ['summary' => 'Risk-averse on the pension.']);
    $this->store->writeEpisode(22, 1, ['summary' => 'Different user.']);

    $context = $this->store->recallContext(11);

    expect($context)->toContain('What I remember')
        ->and($context)->toContain('Risk-averse on the pension')
        ->and($context)->not->toContain('Different user');
});

it('forgets a user episode tree (GDPR)', function () {
    $this->store->writeEpisode(11, 1, ['summary' => 'x']);
    expect($this->store->recall(11))->toHaveCount(1);

    $this->store->forget(11);

    expect($this->store->recall(11))->toBeEmpty();
});

it('loads authored procedures and skips the scaffolding', function () {
    $dir = config('fyn.memory.procedural_path');
    File::put($dir.'/README.md', "# readme\n");
    File::put($dir.'/_TEMPLATE.md', "---\nid: t\n---\nbody\n");
    File::put($dir.'/emergency-fund.md', "---\nid: emergency-fund\ntitle: Emergency fund\napplies_when: low savings\nversion: 2\n---\nBuild three months of expenses.\n");

    $procedures = $this->store->procedures();

    expect($procedures)->toHaveCount(1)
        ->and($procedures[0]['id'])->toBe('emergency-fund')
        ->and($procedures[0]['applies_when'])->toBe('low savings')
        ->and($procedures[0]['version'])->toBe(2);

    expect($this->store->proceduralContext())->toContain('Emergency fund')
        ->and($this->store->proceduralContext())->toContain('Build three months');
});

it('returns empty for an unknown user and an empty procedural dir', function () {
    expect($this->store->recall(999))->toBeEmpty()
        ->and($this->store->recallContext(999))->toBe('')
        ->and($this->store->procedures())->toBeEmpty()
        ->and($this->store->proceduralContext())->toBe('');
});
