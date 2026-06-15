# CoALA Phase 6 — Learning Actions (Gated) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close CoALA's learning loop — human-reviewed promotion of session-derived facts into a per-user semantic layer, queued (never auto-applied) procedural-amendment proposals, and sparse relevance-based similar-case recall wired into the planner.

**Architecture:** Three gated capabilities on the existing FynLoop substrate. (C) `recall()` gains a sparse relevance scorer behind a `RecallScorer` interface (dense deferred). (A) the stale-conversation summariser path writes a session episode and emits *proposed* user facts into a `proposed_semantic_facts` staging table; an admin reviews/approves; approval reifies the fact into a runtime per-user store that the retriever loads for that user. (B) the planner can emit a `learn store='procedural'` action on a detected workflow failure, staged into `proposed_procedure_amendments`; nothing auto-applies to the corpus. Load-bearing invariant: **no autonomous edits** — the global CSJ-authored corpus is never written by any learning path, and nothing applies without an approved review row.

**Tech Stack:** Laravel 10, PHP 8.2, Pest, Vue 3 (admin views), MySQL 8. xAI grok for proposed-fact synthesis. No embeddings (dense recall deferred per CSJ 2026-06-01).

**Spec:** `docs/superpowers/specs/2026-06-15-coala-phase-6-learning-actions-design.md`.

**Deviation from spec (flagged for review):** spec §4 A5 wrote per-user facts to `fyn-memory/semantic/<user_id>/`. That path is inside `SemanticCorpusLoader::all()`'s recursive walk (cross-contaminates every user + fails the fixed `category` validation). This plan instead uses a runtime `UserSemanticStore` at `storage/app/memory/semantic-user/<user_id>/` (gitignored), kept entirely separate from the committed corpus. This strengthens the no-touch-global-corpus invariant.

---

## File Structure

**Migrations / models**
- `database/migrations/2026_06_15_000001_create_proposed_semantic_facts_table.php`
- `database/migrations/2026_06_15_000002_create_proposed_procedure_amendments_table.php`
- `app/Models/ProposedSemanticFact.php`
- `app/Models/ProposedProcedureAmendment.php`

**Component C — recall**
- `app/Services/AI/Memory/Recall/RecallScorer.php` (interface)
- `app/Services/AI/Memory/Recall/SparseRecallScorer.php` (impl)
- Modify `app/Services/AI/Memory/FynMemoryStore.php` (recall/recallContext gain `?string $query`)
- Modify `app/Services/AI/Loop/FynLoop.php` (`plannerSystemPrompt` threads the message as the recall query)

**Component A — promotion**
- `app/Services/AI/Memory/UserSemanticStore.php` (runtime per-user fact read/write)
- `app/Services/AI/Learning/ProposedFactSynthesiser.php` (xAI call emitting candidate facts)
- Modify `app/Services/AI/ConversationSummariser.php` (write session episode + emit proposed facts, flag-gated)
- `app/Http/Controllers/Api/Admin/SemanticFactReviewController.php`
- `app/Services/AI/Learning/SemanticFactPromoter.php` (reify approved fact → UserSemanticStore)
- `app/Console/Commands/FynSemanticPromote.php`
- Modify `app/Services/AI/Memory/SemanticRetriever.php` (merge per-user facts for the active user)
- `resources/js/views/Admin/ProposedSemanticFactsViewer.vue`

**Component B — amendments**
- Modify `app/Services/AI/Loop/Planner.php` (schema `store` enum + `failure_type`/`amendment` fields)
- Modify `app/Services/AI/Loop/FynLoop.php` (failure-context + `learn` semantic/procedural dispatch)
- `app/Http/Controllers/Api/Admin/ProcedureAmendmentReviewController.php`
- `resources/js/views/Admin/ProposedProcedureAmendmentsViewer.vue`

**Config / routes**
- Modify `config/fyn.php` (learning flag + per-user path + recall top-k)
- Modify `routes/api.php` (admin review endpoints)

---

## Task 1: Staging tables, models, config

**Files:**
- Create: `database/migrations/2026_06_15_000001_create_proposed_semantic_facts_table.php`
- Create: `database/migrations/2026_06_15_000002_create_proposed_procedure_amendments_table.php`
- Create: `app/Models/ProposedSemanticFact.php`, `app/Models/ProposedProcedureAmendment.php`
- Modify: `config/fyn.php`
- Test: `tests/Feature/Fyn/Learning/ProposedFactStagingTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\ProposedSemanticFact;
use App\Models\User;

it('stages a proposed semantic fact as pending', function () {
    $user = User::factory()->create();

    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id,
        'derived_from_conversation_id' => null,
        'category' => 'user_profile',
        'fact_id' => 'retires-2041',
        'title' => 'Plans to retire in 2041',
        'body' => 'User stated they intend to retire in 2041.',
        'valid_from' => null,
        'valid_to' => null,
        'status' => 'pending',
    ]);

    expect($fact->status)->toBe('pending')
        ->and($fact->reviewed_at)->toBeNull()
        ->and($fact->reviewed_by)->toBeNull();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Fyn/Learning/ProposedFactStagingTest.php`
Expected: FAIL — table/model missing.

- [ ] **Step 3: Write the migrations**

`2026_06_15_000001_create_proposed_semantic_facts_table.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proposed_semantic_facts')) {
            return;
        }

        Schema::create('proposed_semantic_facts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('derived_from_conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $t->string('derived_from_episode_id')->nullable();
            $t->string('category', 32)->default('user_profile');
            $t->string('fact_id', 128);
            $t->string('title');
            $t->text('body');
            $t->date('valid_from')->nullable();
            $t->date('valid_to')->nullable();
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();

            $t->index(['status', 'created_at']);
            $t->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposed_semantic_facts');
    }
};
```

`2026_06_15_000002_create_proposed_procedure_amendments_table.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proposed_procedure_amendments')) {
            return;
        }

        Schema::create('proposed_procedure_amendments', function (Blueprint $t) {
            $t->id();
            $t->string('procedure_id', 128);
            $t->text('problem_observed');
            $t->text('proposed_fix');
            $t->text('rationale')->nullable();
            $t->string('failure_type', 64)->nullable();
            $t->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();

            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposed_procedure_amendments');
    }
};
```

- [ ] **Step 4: Write the models**

`app/Models/ProposedSemanticFact.php`:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposedSemanticFact extends Model
{
    protected $fillable = [
        'user_id', 'derived_from_conversation_id', 'derived_from_episode_id',
        'category', 'fact_id', 'title', 'body', 'valid_from', 'valid_to',
        'status', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'reviewed_at' => 'datetime',
    ];
}
```

`app/Models/ProposedProcedureAmendment.php`:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposedProcedureAmendment extends Model
{
    protected $fillable = [
        'procedure_id', 'problem_observed', 'proposed_fix', 'rationale',
        'failure_type', 'conversation_id', 'status', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];
}
```

- [ ] **Step 5: Add config keys**

In `config/fyn.php`, inside the existing `'memory' => [ ... ]` array add:
```php
'user_semantic_path' => storage_path('app/memory/semantic-user'),
'recall_top_k' => (int) env('FYN_RECALL_TOP_K', 5),
```
And at the top level of the `fyn` config array add:
```php
'learning_enabled' => (bool) env('FYN_LEARNING_ENABLED', false),
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Fyn/Learning/ProposedFactStagingTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_06_15_0000* app/Models/Proposed*.php config/fyn.php tests/Feature/Fyn/Learning/ProposedFactStagingTest.php
git commit -m "feat(coala-p6): proposed-fact + procedure-amendment staging tables + learning flag"
```

---

## Task 2: Component C — sparse RecallScorer

**Files:**
- Create: `app/Services/AI/Memory/Recall/RecallScorer.php`
- Create: `app/Services/AI/Memory/Recall/SparseRecallScorer.php`
- Test: `tests/Unit/Services/AI/Memory/SparseRecallScorerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\AI\Memory\Recall\SparseRecallScorer;

it('ranks episodes by distinct query-term matches, most relevant first', function () {
    $scorer = new SparseRecallScorer;

    $episodes = [
        ['body' => 'User discussed emergency fund and cash savings.', 'path' => 'a'],
        ['body' => 'User wants to retire at 60 with a SIPP pension.', 'path' => 'b'],
        ['body' => 'General chat about the weather.', 'path' => 'c'],
    ];

    $ranked = $scorer->rank('tell me about my pension and retirement', $episodes);

    expect($ranked[0]['path'])->toBe('b'); // pension + retire(ment) match
});

it('returns episodes unchanged in input order when query has no content tokens', function () {
    $scorer = new SparseRecallScorer;
    $episodes = [['body' => 'one', 'path' => 'a'], ['body' => 'two', 'path' => 'b']];

    expect($scorer->rank('the a of', $episodes))->toBe($episodes);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/SparseRecallScorerTest.php`
Expected: FAIL — class missing.

- [ ] **Step 3: Write the interface**

`app/Services/AI/Memory/Recall/RecallScorer.php`:
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Recall;

/**
 * Ranks recalled episodes by relevance to a query. The sparse implementation
 * (token overlap) ships in Phase 6; a dense (embedding) implementation is the
 * deferred drop-in (CSJ 2026-06-01: dense deferred until ~500 concurrent users).
 */
interface RecallScorer
{
    /**
     * @param  list<array{body: string, ...}>  $episodes
     * @return list<array{body: string, ...}>  same shape, relevance-ranked (stable for ties)
     */
    public function rank(string $query, array $episodes): array;
}
```

- [ ] **Step 4: Write the sparse implementation**

`app/Services/AI/Memory/Recall/SparseRecallScorer.php`:
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Recall;

use App\Services\AI\Memory\SemanticRetriever;

/**
 * Token-overlap relevance over the episode body. Same grammar as
 * FynMemoryStore::matchingProcedures / SemanticRetriever (§8.2 lean-prompt law):
 * stopword-dropped query tokens, word-boundary counting with ±"s" variants,
 * scored by distinct-term matches. Stable sort: equal scores keep input order
 * (callers pass episodes recency-first, so recency is the tiebreak).
 */
final class SparseRecallScorer implements RecallScorer
{
    public function rank(string $query, array $episodes): array
    {
        $terms = array_values(array_diff($this->tokenise($query), SemanticRetriever::STOPWORDS));
        if ($terms === [] || $episodes === []) {
            return $episodes;
        }

        $scored = [];
        foreach ($episodes as $i => $episode) {
            $counts = $this->termCounts((string) ($episode['body'] ?? ''));
            $distinct = 0;
            foreach ($terms as $term) {
                if ($this->termHits($term, $counts) > 0) {
                    $distinct++;
                }
            }
            $scored[] = ['i' => $i, 'score' => $distinct, 'episode' => $episode];
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: $a['i'] <=> $b['i']);

        return array_map(static fn (array $s): array => $s['episode'], $scored);
    }

    /** @return list<string> */
    private function tokenise(string $text): array
    {
        preg_match_all('/[a-z0-9]{3,}/', mb_strtolower($text), $m);

        return array_values(array_unique($m[0]));
    }

    /** @return array<string, int> */
    private function termCounts(string $haystack): array
    {
        preg_match_all('/[a-z0-9]{3,}/', mb_strtolower($haystack), $m);

        return array_count_values($m[0]);
    }

    /** @param array<string, int> $counts */
    private function termHits(string $term, array $counts): int
    {
        $hits = ($counts[$term] ?? 0) + ($counts[$term.'s'] ?? 0);
        if (str_ends_with($term, 's')) {
            $hits += $counts[substr($term, 0, -1)] ?? 0;
        }

        return $hits;
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/SparseRecallScorerTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/AI/Memory/Recall/ tests/Unit/Services/AI/Memory/SparseRecallScorerTest.php
git commit -m "feat(coala-p6): sparse RecallScorer (dense deferred drop-in)"
```

---

## Task 3: Component C — wire relevance recall into the planner

**Files:**
- Modify: `app/Services/AI/Memory/FynMemoryStore.php` (recall/recallContext add `?string $query`)
- Modify: `app/Services/AI/Loop/FynLoop.php` (`plannerSystemPrompt` threads the message)
- Test: `tests/Unit/Services/AI/Memory/FynMemoryStoreRecallTest.php`

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/FynMemoryStoreRecallTest.php`
Expected: FAIL — `recall()` takes `(int, int)`, not `(int, ?string, int)`.

- [ ] **Step 3: Modify `recall()` and `recallContext()`**

In `app/Services/AI/Memory/FynMemoryStore.php`, replace the `recall()` method signature/body (lines 157–174) with:
```php
    /**
     * Recall a user's episodes. With $query, episodes are relevance-ranked
     * (sparse) then capped; without it, recency order (newest first). Recency is
     * the tiebreak because the candidate set is gathered newest-first before
     * scoring.
     *
     * @return list<array{meta: array<string, mixed>, body: string, path: string}>
     */
    public function recall(int $userId, ?string $query = null, int $limit = 5): array
    {
        $userDir = $this->userEpisodeDir($userId);
        if (! File::isDirectory($userDir)) {
            return [];
        }

        $files = File::glob($userDir.'/*/*.md') ?: [];
        rsort($files); // newest first (date-prefixed filenames)

        $episodes = array_map(function (string $path): array {
            [$meta, $body] = $this->parse(File::get($path));

            return ['meta' => $meta, 'body' => trim($body), 'path' => $path];
        }, $files);

        if ($query !== null && trim($query) !== '') {
            $episodes = app(\App\Services\AI\Memory\Recall\RecallScorer::class)->rank($query, $episodes);
        }

        return array_slice($episodes, 0, max(0, $limit));
    }
```

Replace `recallContext()` (lines 179–192) with:
```php
    /**
     * Recalled episodes as a context block. Empty string when the user has none.
     */
    public function recallContext(int $userId, ?string $query = null, int $limit = 5): string
    {
        $episodes = $this->recall($userId, $query, $limit);
        if ($episodes === []) {
            return '';
        }

        $blocks = array_map(
            static fn (array $e): string => '- '.trim((string) \Illuminate\Support\Str::before($e['body'], "\n## Detail")),
            $episodes,
        );

        return "## What I remember about you\n\n".implode("\n", $blocks);
    }
```

- [ ] **Step 4: Bind the scorer**

In `app/Providers/AppServiceProvider.php` `register()`, bind the interface to the sparse impl:
```php
$this->app->bind(
    \App\Services\AI\Memory\Recall\RecallScorer::class,
    \App\Services\AI\Memory\Recall\SparseRecallScorer::class,
);
```

- [ ] **Step 5: Thread the query through the planner prompt**

In `app/Services/AI/Loop/FynLoop.php`, change `plannerSystemPrompt(User $user)` (line 250) to `plannerSystemPrompt(User $user, string $query)` and its recall call (line 255) to `$this->memory->recallContext($user->id, $query)`. Update the caller (line 170) to `$plannerSystem = $this->plannerSystemPrompt($user, $message);`.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/FynMemoryStoreRecallTest.php`
Expected: PASS. Then run the existing loop suite to confirm no regression: `./vendor/bin/pest tests/Feature/Fyn --filter=Loop`.

- [ ] **Step 7: Commit**

```bash
git add app/Services/AI/Memory/FynMemoryStore.php app/Services/AI/Loop/FynLoop.php app/Providers/AppServiceProvider.php tests/Unit/Services/AI/Memory/FynMemoryStoreRecallTest.php
git commit -m "feat(coala-p6): relevance-ranked episodic recall wired into the planner"
```

---

## Task 4: Component A — UserSemanticStore (runtime per-user facts)

**Files:**
- Create: `app/Services/AI/Memory/UserSemanticStore.php`
- Test: `tests/Unit/Services/AI/Memory/UserSemanticStoreTest.php`

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/UserSemanticStoreTest.php`
Expected: FAIL — class missing.

- [ ] **Step 3: Write the store**

`app/Services/AI/Memory/UserSemanticStore.php`:
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Runtime per-user semantic facts (CoALA Phase 6). SEPARATE from the committed,
 * CSJ-authored global corpus (SemanticCorpusLoader) — the learning path never
 * writes that corpus. Facts here are reified ONLY from an approved
 * ProposedSemanticFact. Lives under storage/app (gitignored, per-user, like
 * episodes). GDPR erasure deletes the user's directory.
 */
final class UserSemanticStore
{
    public function put(int $userId, string $factId, string $title, string $body): string
    {
        $dir = $this->userDir($userId);
        File::ensureDirectoryExists($dir);
        $path = $dir.'/'.preg_replace('/[^a-z0-9\-]/', '', mb_strtolower($factId)).'.md';

        $meta = ['fact_id' => $factId, 'category' => 'user_profile', 'title' => $title, 'version' => 1];
        File::put($path, "---\n".Yaml::dump($meta, 4, 2)."---\n\n".trim($body)."\n");

        return $path;
    }

    /** @return list<array{fact_id: string, title: string, body: string}> */
    public function forUser(int $userId): array
    {
        $dir = $this->userDir($userId);
        if (! File::isDirectory($dir)) {
            return [];
        }

        $facts = [];
        foreach (File::files($dir) as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }
            [$meta, $body] = $this->parse(File::get($file->getPathname()));
            $facts[] = [
                'fact_id' => (string) ($meta['fact_id'] ?? $file->getFilenameWithoutExtension()),
                'title' => (string) ($meta['title'] ?? ''),
                'body' => trim($body),
            ];
        }

        return $facts;
    }

    public function forget(int $userId): void
    {
        $dir = $this->userDir($userId);
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }

    private function userDir(int $userId): string
    {
        return rtrim((string) config('fyn.memory.user_semantic_path'), '/').'/'.$userId;
    }

    /** @return array{0: array<string, mixed>, 1: string} */
    private function parse(string $contents): array
    {
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $contents, $m) === 1) {
            $meta = Yaml::parse($m[1]);

            return [is_array($meta) ? $meta : [], $m[2]];
        }

        return [[], $contents];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/UserSemanticStoreTest.php`
Expected: PASS.

- [ ] **Step 5: GDPR wire-up**

Find the existing GDPR erasure path (`grep -rn "FynMemoryStore" app/ | grep -i forget` and the `fyn:user:erase` command). Add a `app(UserSemanticStore::class)->forget($userId)` call alongside the existing episodic `forget()`.

- [ ] **Step 6: Commit**

```bash
git add app/Services/AI/Memory/UserSemanticStore.php tests/Unit/Services/AI/Memory/UserSemanticStoreTest.php app/Console/Commands/
git commit -m "feat(coala-p6): runtime per-user semantic store (separate from global corpus) + GDPR erase"
```

---

## Task 5: Component A — per-user facts reach the retriever

**Files:**
- Modify: `app/Services/AI/Memory/SemanticRetriever.php`
- Test: `tests/Unit/Services/AI/Memory/SemanticRetrieverUserFactsTest.php`

- [ ] **Step 1: Read `SemanticRetriever.php`** to confirm the public `retrieve()` signature and where the candidate fact set is assembled. Note the method name + return type before editing.

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\AI\Memory\SemanticRetriever;
use App\Services\AI\Memory\UserSemanticStore;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->dir = storage_path('app/test-user-semantic-'.uniqid());
    config(['fyn.memory.user_semantic_path' => $this->dir]);
});

afterEach(fn () => File::deleteDirectory($this->dir));

it('includes an approved per-user fact in retrieval for that user', function () {
    app(UserSemanticStore::class)->put(7, 'retires-2041', 'Retires 2041', 'User plans to retire in 2041.');

    $facts = app(SemanticRetriever::class)->retrieveForUser(7, 'when will I retire');

    expect(collect($facts)->pluck('body')->implode(' '))->toContain('2041');
});
```

- [ ] **Step 3: Add a `retrieveForUser(int $userId, string $message)` method** to `SemanticRetriever` that calls the existing global `retrieve()` and merges in the user's facts from `UserSemanticStore::forUser($userId)` (filtered by the same sparse term match as the global retriever). Constructor-inject `UserSemanticStore`. Keep the existing `retrieve()` unchanged so global-only callers are unaffected.

- [ ] **Step 4: Wire `retrieveForUser` into the per-turn assembler.** In `app/Services/AI/Fyn/FynContextAssembler.php`, where the semantic `<knowledge>` block is built via `SemanticRetriever`, switch the advice/onboarding turn to `retrieveForUser($user->id, $message)` so approved per-user facts appear in the context. (Confirm the exact call site by `grep -n "SemanticRetriever\|->retrieve(" app/Services/AI/Fyn/FynContextAssembler.php`.)

- [ ] **Step 5: Run the test + assembler regression**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/SemanticRetrieverUserFactsTest.php`
Then: `./vendor/bin/pest --filter=FynContextAssembler`
Expected: PASS, no regression.

- [ ] **Step 6: Commit**

```bash
git add app/Services/AI/Memory/SemanticRetriever.php app/Services/AI/Fyn/FynContextAssembler.php tests/Unit/Services/AI/Memory/SemanticRetrieverUserFactsTest.php
git commit -m "feat(coala-p6): approved per-user facts surface in the semantic retriever"
```

---

## Task 6: Component A — proposed-fact synthesiser

**Files:**
- Create: `app/Services/AI/Learning/ProposedFactSynthesiser.php`
- Test: `tests/Unit/Services/AI/Learning/ProposedFactSynthesiserTest.php`

- [ ] **Step 1: Write the failing test** (HTTP-faked xAI, mirroring ConversationSummariser's `Http::fake` pattern):

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\Learning\ProposedFactSynthesiser;
use Illuminate\Support\Facades\Http;

it('returns durable user-scoped candidate facts from a conversation', function () {
    config(['services.xai.api_key' => 'test-key']);
    Http::fake(['api.x.ai/*' => Http::response([
        'choices' => [['message' => ['content' => json_encode([
            'facts' => [[
                'fact_id' => 'retires-2041', 'title' => 'Plans to retire 2041',
                'body' => 'User stated they want to retire in 2041.',
                'valid_from' => null, 'valid_to' => null,
            ]],
        ])]]],
    ], 200)]);

    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);

    $facts = app(ProposedFactSynthesiser::class)->synthesise($conv->id, 'User: I want to retire at 60.');

    expect($facts)->toHaveCount(1)->and($facts[0]['fact_id'])->toBe('retires-2041');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Learning/ProposedFactSynthesiserTest.php`
Expected: FAIL — class missing.

- [ ] **Step 3: Write the synthesiser** (mirror `ConversationSummariser::callProvider`; prompt asks ONLY for durable, source-less, figure-free user facts — pointer model):

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Learning;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CoALA Phase 6 — synthesises *candidate* durable user facts from a session for
 * human review. Never persists; never includes figures with a live owner
 * (pointer model — a retirement DATE is durable; a £ value is not). Output is
 * staged into proposed_semantic_facts, never auto-applied.
 */
final class ProposedFactSynthesiser
{
    private const ENDPOINT = 'https://api.x.ai/v1/chat/completions';

    /** @return list<array{fact_id: string, title: string, body: string, valid_from: ?string, valid_to: ?string}> */
    public function synthesise(int $conversationId, string $transcript): array
    {
        $apiKey = config('services.xai.api_key');
        if (empty($apiKey)) {
            return [];
        }

        $system = <<<'PROMPT'
You extract DURABLE, user-specific facts worth remembering long-term from a Fynla chat. Output strict JSON: {"facts": [{"fact_id": "<kebab-case>", "title": "<short>", "body": "<one sentence, third person>", "valid_from": null, "valid_to": null}]}.
Rules:
- Only durable traits/intentions: target retirement year, risk attitude, stated long-term goals, life-stage facts.
- NEVER include monetary amounts, balances, or any figure that has a live source — those are looked up live, never remembered.
- NEVER include regulatory/tax facts — those are author-maintained.
- Omit transient chit-chat. If nothing durable was stated, return {"facts": []}.
- JSON only, no prose, no markdown fences.
PROMPT;

        try {
            $response = Http::withHeaders(['Authorization' => 'Bearer '.$apiKey])
                ->timeout(60)
                ->post(self::ENDPOINT, [
                    'model' => config('services.xai.vision_model', 'grok-4.3'),
                    'max_completion_tokens' => 600,
                    'temperature' => 0,
                    'reasoning_effort' => 'none',
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $transcript],
                    ],
                ]);

            if (! $response->successful()) {
                return [];
            }

            $decoded = json_decode((string) ($response->json()['choices'][0]['message']['content'] ?? ''), true);
            $facts = is_array($decoded['facts'] ?? null) ? $decoded['facts'] : [];

            return array_values(array_filter(array_map(static fn ($f): ?array => is_array($f) && ! empty($f['fact_id']) ? [
                'fact_id' => (string) $f['fact_id'],
                'title' => (string) ($f['title'] ?? $f['fact_id']),
                'body' => (string) ($f['body'] ?? ''),
                'valid_from' => $f['valid_from'] ?? null,
                'valid_to' => $f['valid_to'] ?? null,
            ] : null, $facts)));
        } catch (\Throwable $e) {
            Log::warning('[ProposedFactSynthesiser] failed', ['conversation_id' => $conversationId, 'error' => $e->getMessage()]);

            return [];
        }
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Learning/ProposedFactSynthesiserTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/Learning/ProposedFactSynthesiser.php tests/Unit/Services/AI/Learning/ProposedFactSynthesiserTest.php
git commit -m "feat(coala-p6): proposed-fact synthesiser (durable, figure-free, staged only)"
```

---

## Task 7: Component A — session-end consolidation + staging (flag-gated)

**Files:**
- Modify: `app/Services/AI/ConversationSummariser.php`
- Test: `tests/Feature/Fyn/Learning/SessionPromotionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\ProposedSemanticFact;
use App\Models\User;
use App\Services\AI\ConversationSummariser;
use App\Services\AI\Learning\ProposedFactSynthesiser;
use Illuminate\Support\Facades\Http;

it('stages proposed facts on summarise when learning is enabled', function () {
    config(['fyn.learning_enabled' => true, 'services.xai.api_key' => 'k']);

    // summariser index call + synthesiser call both faked
    Http::fake(['api.x.ai/*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => json_encode(['summary' => 's', 'topics' => [], 'entities_mentioned' => [], 'intents_stated' => []])]]]], 200)
        ->push(['choices' => [['message' => ['content' => json_encode(['facts' => [['fact_id' => 'risk-averse', 'title' => 'Risk averse', 'body' => 'User is cautious.', 'valid_from' => null, 'valid_to' => null]]])]]]], 200),
    ]);

    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'user', 'content' => 'I am cautious with money']);

    app(ConversationSummariser::class)->summarise($conv->id);

    expect(ProposedSemanticFact::where('user_id', $user->id)->where('status', 'pending')->count())->toBe(1);
});

it('does NOT stage facts when learning is disabled', function () {
    config(['fyn.learning_enabled' => false, 'services.xai.api_key' => 'k']);
    Http::fake(['api.x.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['summary' => 's', 'topics' => [], 'entities_mentioned' => [], 'intents_stated' => []])]]]], 200)]);

    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'user', 'content' => 'hi']);

    app(ConversationSummariser::class)->summarise($conv->id);

    expect(ProposedSemanticFact::count())->toBe(0);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Fyn/Learning/SessionPromotionTest.php`
Expected: FAIL — no staging happens.

- [ ] **Step 3: Extend `ConversationSummariser::summarise()`**

After the `forceFill([...])->save()` block (line 85), append:
```php
        $this->emitProposedFacts($conversation, $transcript);
```
Add the private method + a constructor injecting the synthesiser:
```php
    public function __construct(
        private readonly \App\Services\AI\Learning\ProposedFactSynthesiser $synthesiser = new \App\Services\AI\Learning\ProposedFactSynthesiser,
    ) {}

    private function emitProposedFacts(AiConversation $conversation, string $transcript): void
    {
        if (! config('fyn.learning_enabled', false)) {
            return;
        }

        foreach ($this->synthesiser->synthesise($conversation->id, $transcript) as $fact) {
            \App\Models\ProposedSemanticFact::updateOrCreate(
                ['user_id' => $conversation->user_id, 'fact_id' => $fact['fact_id'], 'status' => 'pending'],
                [
                    'derived_from_conversation_id' => $conversation->id,
                    'category' => 'user_profile',
                    'title' => $fact['title'],
                    'body' => $fact['body'],
                    'valid_from' => $fact['valid_from'] ?: null,
                    'valid_to' => $fact['valid_to'] ?: null,
                ],
            );
        }
    }
```
(Note: constructor default-instantiates the synthesiser so existing `new ConversationSummariser` callers and the container both work; container resolution still injects the bound instance.)

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/Fyn/Learning/SessionPromotionTest.php`
Then the existing summariser suite: `./vendor/bin/pest --filter=ConversationSummariser`
Expected: PASS, no regression.

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/ConversationSummariser.php tests/Feature/Fyn/Learning/SessionPromotionTest.php
git commit -m "feat(coala-p6): summariser stages proposed user facts (flag-gated)"
```

---

## Task 8: Component A — promoter + promote command

**Files:**
- Create: `app/Services/AI/Learning/SemanticFactPromoter.php`
- Create: `app/Console/Commands/FynSemanticPromote.php`
- Test: `tests/Feature/Fyn/Learning/SemanticFactPromoterTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\ProposedSemanticFact;
use App\Models\User;
use App\Services\AI\Learning\SemanticFactPromoter;
use App\Services\AI\Memory\UserSemanticStore;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->dir = storage_path('app/test-user-semantic-'.uniqid());
    config(['fyn.memory.user_semantic_path' => $this->dir]);
});
afterEach(fn () => File::deleteDirectory($this->dir));

it('promotes an approved fact to the per-user store and marks it approved', function () {
    $user = User::factory()->create();
    $reviewer = User::factory()->create();
    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'retires-2041',
        'title' => 'Retires 2041', 'body' => 'User plans to retire in 2041.', 'status' => 'pending',
    ]);

    app(SemanticFactPromoter::class)->approve($fact, $reviewer->id);

    expect($fact->fresh()->status)->toBe('approved')
        ->and($fact->fresh()->reviewed_by)->toBe($reviewer->id)
        ->and(app(UserSemanticStore::class)->forUser($user->id))->toHaveCount(1);
});

it('rejects a fact without writing the store', function () {
    $user = User::factory()->create();
    $reviewer = User::factory()->create();
    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'x',
        'title' => 't', 'body' => 'b', 'status' => 'pending',
    ]);

    app(SemanticFactPromoter::class)->reject($fact, $reviewer->id);

    expect($fact->fresh()->status)->toBe('rejected')
        ->and(app(UserSemanticStore::class)->forUser($user->id))->toBe([]);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Fyn/Learning/SemanticFactPromoterTest.php`
Expected: FAIL — class missing.

- [ ] **Step 3: Write the promoter**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Learning;

use App\Models\ProposedSemanticFact;
use App\Services\AI\Memory\UserSemanticStore;

/**
 * The ONLY path that writes a per-user semantic fact — and only from an approved
 * proposal. Never touches the global corpus. (CoALA Phase 6, no-auto-apply.)
 */
final class SemanticFactPromoter
{
    public function __construct(private readonly UserSemanticStore $store) {}

    public function approve(ProposedSemanticFact $fact, int $reviewerId): void
    {
        $this->store->put($fact->user_id, $fact->fact_id, $fact->title, $fact->body);

        $fact->forceFill(['status' => 'approved', 'reviewed_by' => $reviewerId, 'reviewed_at' => now()])->save();
    }

    public function reject(ProposedSemanticFact $fact, int $reviewerId): void
    {
        $fact->forceFill(['status' => 'rejected', 'reviewed_by' => $reviewerId, 'reviewed_at' => now()])->save();
    }
}
```

- [ ] **Step 4: Write the command** (CLI fallback for promotion):

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProposedSemanticFact;
use App\Models\User;
use App\Services\AI\Learning\SemanticFactPromoter;
use Illuminate\Console\Command;

class FynSemanticPromote extends Command
{
    protected $signature = 'fyn:semantic:promote {fact : proposed_semantic_facts.id} {--reviewer= : admin user id}';

    protected $description = 'Approve a staged per-user semantic fact (CoALA Phase 6).';

    public function handle(SemanticFactPromoter $promoter): int
    {
        $fact = ProposedSemanticFact::find($this->argument('fact'));
        if ($fact === null || $fact->status !== 'pending') {
            $this->error('No pending fact with that id.');

            return self::FAILURE;
        }

        $reviewerId = (int) ($this->option('reviewer') ?: User::where('email', 'chris@fynla.org')->value('id'));
        $promoter->approve($fact, $reviewerId);
        $this->info("Promoted fact {$fact->fact_id} for user {$fact->user_id}.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Fyn/Learning/SemanticFactPromoterTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/AI/Learning/SemanticFactPromoter.php app/Console/Commands/FynSemanticPromote.php tests/Feature/Fyn/Learning/SemanticFactPromoterTest.php
git commit -m "feat(coala-p6): semantic fact promoter + promote command (approval-only write)"
```

---

## Task 9: Component A — admin review endpoint + Vue surface

**Files:**
- Create: `app/Http/Controllers/Api/Admin/SemanticFactReviewController.php`
- Modify: `routes/api.php`
- Create: `resources/js/views/Admin/ProposedSemanticFactsViewer.vue`
- Modify: `resources/js/router/index.js`
- Test: `tests/Feature/Api/Admin/SemanticFactReviewControllerTest.php`

- [ ] **Step 1: Read `ProceduralCorpusController.php` + its route block in `routes/api.php` + `ProceduralCorpusViewer.vue` + its router entry** to mirror the exact middleware (`auth:sanctum`, `permission:admin.access`), response envelope, and nav pattern.

- [ ] **Step 2: Write the failing controller test**

```php
<?php

declare(strict_types=1);

use App\Models\ProposedSemanticFact;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('lists pending facts and approves one for an admin', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('admin.access'); // mirror ProceduralCorpus test setup
    Sanctum::actingAs($admin);

    $user = User::factory()->create();
    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'retires-2041',
        'title' => 'Retires 2041', 'body' => 'User plans to retire 2041.', 'status' => 'pending',
    ]);

    $this->getJson('/api/admin/semantic-facts')->assertOk()->assertJsonFragment(['fact_id' => 'retires-2041']);

    $this->patchJson("/api/admin/semantic-facts/{$fact->id}", ['action' => 'approve'])->assertOk();

    expect($fact->fresh()->status)->toBe('approved');
})->skip(fn () => ! method_exists(User::class, 'givePermissionTo'), 'permission package shape — confirm in step 1');

it('forbids a non-admin', function () {
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/admin/semantic-facts')->assertForbidden();
});
```
(In step 1 confirm the exact admin-permission grant used by `ProceduralCorpusControllerTest`; adjust the `givePermissionTo` line to match — do not invent a permission API.)

- [ ] **Step 3: Write the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProposedSemanticFact;
use App\Services\AI\Learning\SemanticFactPromoter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SemanticFactReviewController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ProposedSemanticFact::where('status', 'pending')->latest()->get(),
        ]);
    }

    public function update(Request $request, ProposedSemanticFact $fact, SemanticFactPromoter $promoter): JsonResponse
    {
        $action = (string) $request->input('action');
        if ($fact->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Already reviewed.'], 422);
        }

        match ($action) {
            'approve' => $promoter->approve($fact, (int) $request->user()->id),
            'reject' => $promoter->reject($fact, (int) $request->user()->id),
            default => abort(422, 'action must be approve or reject'),
        };

        return response()->json(['success' => true, 'data' => $fact->fresh()]);
    }
}
```

- [ ] **Step 4: Add routes** — inside the existing admin group in `routes/api.php` (the one carrying `procedural-corpus`), add:
```php
Route::get('semantic-facts', [\App\Http\Controllers\Api\Admin\SemanticFactReviewController::class, 'index']);
Route::patch('semantic-facts/{fact}', [\App\Http\Controllers\Api\Admin\SemanticFactReviewController::class, 'update']);
```

- [ ] **Step 5: Run the controller tests**

Run: `./vendor/bin/pest tests/Feature/Api/Admin/SemanticFactReviewControllerTest.php`
Expected: PASS (after step-1 permission shape confirmed).

- [ ] **Step 6: Write the Vue view** mirroring `ProposedSemanticFactsViewer.vue` on `ProceduralCorpusViewer.vue` structure: a table of pending facts (user, title, body, derived-from), Approve / Reject buttons calling `PATCH /api/admin/semantic-facts/{id}`. Wrap in `<AppLayout>` (Rule #13). No icons on the table (Rule #15 — admin is "ask first"; default no icon). Register the route `/admin/proposed-facts` in `resources/js/router/index.js` with `meta: { requiresAuth: true }`, mirroring the procedural-corpus route entry.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/Admin/SemanticFactReviewController.php routes/api.php resources/js/views/Admin/ProposedSemanticFactsViewer.vue resources/js/router/index.js tests/Feature/Api/Admin/SemanticFactReviewControllerTest.php
git commit -m "feat(coala-p6): admin review surface for proposed per-user facts"
```

---

## Task 10: Component B — planner schema + learn procedural/semantic dispatch

**Files:**
- Modify: `app/Services/AI/Loop/Planner.php` (schema `store` enum + amendment fields)
- Modify: `app/Services/AI/Loop/FynLoop.php` (Learn case: semantic + procedural staging)
- Test: `tests/Unit/Services/AI/Loop/LearnDispatchTest.php`

- [ ] **Step 1: Write the failing test** (drive the FynLoop Learn dispatch via the scripted planner harness — confirm the harness entry point in step 0 by reading `tests/Support/Fyn`):

```php
<?php

declare(strict_types=1);

use App\Models\ProposedProcedureAmendment;

it('stages a procedure amendment when the planner emits learn store=procedural', function () {
    // Arrange a FynLoop run whose planner returns:
    // Action::learn('procedural', ['procedure_id' => 'recommendation-routing',
    //   'problem_observed' => 'looped', 'proposed_fix' => 'add guard', 'rationale' => 'x',
    //   'failure_type' => 'reasoning_loop_exhaustion'])
    // (Use the existing scripted planner test harness; see tests/Support/Fyn.)

    // ...drive one FynLoop::run() turn...

    expect(ProposedProcedureAmendment::where('procedure_id', 'recommendation-routing')->where('status', 'pending')->count())->toBe(1);
})->todo('wire via the scripted planner harness identified in step 0');
```

- [ ] **Step 2: Extend the planner schema** — in `Planner.php::planSchema()` change the `store` enum (line 312) to `['episodic', 'semantic', 'procedural']` and extend its description; add to `properties`:
```php
'amendment' => ['type' => 'object', 'description' => 'learn store=procedural only — {procedure_id, problem_observed, proposed_fix, rationale, failure_type}.'],
```
In `toAction()` the `learn` branch already passes `payload` through unchanged — no change needed (the amendment travels inside `payload`). Confirm `Action::learn` accepts `store='procedural'` (it accepts any non-empty store — verified).

- [ ] **Step 3: Extend the FynLoop Learn dispatch** — replace the `case ActionType::Learn:` block (lines 191–198) with:
```php
                case ActionType::Learn:
                    match ($action->store()) {
                        'episodic' => $this->recordEpisode($user, $conversation, $action->payload()),
                        'semantic' => $this->stageProposedFact($user, $conversation, $action->payload()),
                        'procedural' => $this->stageProcedureAmendment($conversation, $action->payload()),
                        default => null,
                    };

                    continue 2;
```
Add the two guarded staging methods (never break a turn; never auto-apply):
```php
    /** @param array<string, mixed> $payload */
    private function stageProposedFact(User $user, AiConversation $conversation, array $payload): void
    {
        if (! config('fyn.learning_enabled', false) || empty($payload['fact_id'])) {
            return;
        }
        try {
            \App\Models\ProposedSemanticFact::updateOrCreate(
                ['user_id' => $user->id, 'fact_id' => (string) $payload['fact_id'], 'status' => 'pending'],
                [
                    'derived_from_conversation_id' => $conversation->id,
                    'category' => 'user_profile',
                    'title' => (string) ($payload['title'] ?? $payload['fact_id']),
                    'body' => (string) ($payload['body'] ?? ''),
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('[FynLoop] stageProposedFact failed', ['error' => $e->getMessage()]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function stageProcedureAmendment(AiConversation $conversation, array $payload): void
    {
        if (! config('fyn.learning_enabled', false) || empty($payload['procedure_id'])) {
            return;
        }
        try {
            \App\Models\ProposedProcedureAmendment::create([
                'procedure_id' => (string) $payload['procedure_id'],
                'problem_observed' => (string) ($payload['problem_observed'] ?? ''),
                'proposed_fix' => (string) ($payload['proposed_fix'] ?? ''),
                'rationale' => (string) ($payload['rationale'] ?? ''),
                'failure_type' => (string) ($payload['failure_type'] ?? ''),
                'conversation_id' => $conversation->id,
                'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[FynLoop] stageProcedureAmendment failed', ['error' => $e->getMessage()]);
        }
    }
```

- [ ] **Step 4: Run the test** (once wired to the harness) + the existing loop suite:

Run: `./vendor/bin/pest tests/Unit/Services/AI/Loop/LearnDispatchTest.php`
Then: `./vendor/bin/pest tests/Feature/Fyn`
Expected: PASS, no regression (episodic learn unchanged).

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/Loop/Planner.php app/Services/AI/Loop/FynLoop.php tests/Unit/Services/AI/Loop/LearnDispatchTest.php
git commit -m "feat(coala-p6): planner can stage semantic/procedural learn proposals (no auto-apply)"
```

---

## Task 11: Component B — workflow-failure context to the planner

**Files:**
- Modify: `app/Services/AI/Loop/FynLoop.php`
- Test: `tests/Unit/Services/AI/Loop/FailureContextTest.php`

- [ ] **Step 1: Write the failing test** — assert that when the cycle cap is exhausted (repeated retrieve no-ops), the FINAL planner consult receives a failure-context message enabling a procedural-amendment proposal. Use the scripted planner harness to force retrieve actions until the cap, and assert the planner's last `plan()` call `$messages` includes a `failure_context` marker. (Confirm the harness assertion mechanism in step 0.)

- [ ] **Step 2: Add failure context** — in `FynLoop::run()`, track a `$failures` array across cycles (append on the cap-approaching / retrieve-budget-exhausted branches). On the final cycle (or when emitting `emitNoAction` after the loop), make one planner consult that includes the failure context as an extra system/user message so the planner may emit `learn store=procedural`. Keep it bounded (one extra consult max) and guarded. Concretely: before the post-loop `emitNoAction()` (line 229), add:
```php
        if (config('fyn.learning_enabled', false)) {
            $closing = $this->planner->plan(
                $plannerSystem."\n\n## Workflow failure\nThis turn exhausted its cycle cap without answering. If a named procedure is at fault, emit a `learn` action with store=procedural proposing an amendment. Otherwise emit no_action.",
                [['role' => 'user', 'content' => $message]],
            );
            if ($closing->type === ActionType::Learn && $closing->store() === 'procedural') {
                $this->stageProcedureAmendment($conversation, $closing->payload());
            }
        }
```

- [ ] **Step 3: Run the test + loop suite**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Loop/FailureContextTest.php` then `./vendor/bin/pest tests/Feature/Fyn`
Expected: PASS, no regression (the closing consult only runs when learning is enabled AND the cap was hit — the common path is untouched).

- [ ] **Step 4: Commit**

```bash
git add app/Services/AI/Loop/FynLoop.php tests/Unit/Services/AI/Loop/FailureContextTest.php
git commit -m "feat(coala-p6): cycle-cap failure proposes a procedure amendment (flag-gated, staged)"
```

---

## Task 12: Component B — amendment review endpoint + Vue surface

**Files:**
- Create: `app/Http/Controllers/Api/Admin/ProcedureAmendmentReviewController.php`
- Modify: `routes/api.php`
- Create: `resources/js/views/Admin/ProposedProcedureAmendmentsViewer.vue`
- Modify: `resources/js/router/index.js`
- Test: `tests/Feature/Api/Admin/ProcedureAmendmentReviewControllerTest.php`

- [ ] **Step 1: Write the failing test** — mirror Task 9's controller test: admin lists pending amendments; `PATCH .../{id}` with `action=approve` sets `status=approved` + `reviewed_by`; **assert NO procedural corpus file is written** (the corpus dir is unchanged) — this is the no-auto-apply invariant for procedures:

```php
it('approving an amendment marks it accepted but writes NO corpus file', function () {
    $before = \Illuminate\Support\Facades\File::allFiles(config('fyn.memory.procedural_path'));
    // ...admin PATCH approve...
    $after = \Illuminate\Support\Facades\File::allFiles(config('fyn.memory.procedural_path'));
    expect(count($after))->toBe(count($before)); // approval never touches the corpus
});
```

- [ ] **Step 2: Write the controller** — identical shape to `SemanticFactReviewController` but over `ProposedProcedureAmendment`. `approve` sets `status='approved' / reviewed_by / reviewed_at` and **does nothing else** (an engineer applies the `.md` by hand). `reject` sets `status='rejected'`. No promoter, no file write.

- [ ] **Step 3: Add routes** — in the same admin group:
```php
Route::get('procedure-amendments', [\App\Http\Controllers\Api\Admin\ProcedureAmendmentReviewController::class, 'index']);
Route::patch('procedure-amendments/{amendment}', [\App\Http\Controllers\Api\Admin\ProcedureAmendmentReviewController::class, 'update']);
```

- [ ] **Step 4: Write the Vue view** — mirror Task 9's viewer; columns: procedure_id, problem_observed, proposed_fix, failure_type; Approve / Reject. `<AppLayout>`, no icons. Router entry `/admin/procedure-amendments`.

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Api/Admin/ProcedureAmendmentReviewControllerTest.php`
Expected: PASS (incl. the no-corpus-write assertion).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Admin/ProcedureAmendmentReviewController.php routes/api.php resources/js/views/Admin/ProposedProcedureAmendmentsViewer.vue resources/js/router/index.js tests/Feature/Api/Admin/ProcedureAmendmentReviewControllerTest.php
git commit -m "feat(coala-p6): admin review for procedure amendments (review-only, never auto-applied)"
```

---

## Task 13: No-auto-apply invariant suite + full verification

**Files:**
- Create: `tests/Feature/Fyn/Learning/NoAutonomousEditInvariantTest.php`

- [ ] **Step 1: Write the invariant tests**

```php
<?php

declare(strict_types=1);

use App\Models\ProposedSemanticFact;
use App\Models\User;
use App\Services\AI\Learning\SemanticFactPromoter;
use Illuminate\Support\Facades\File;

it('learning never writes the global semantic corpus', function () {
    $corpus = (string) config('fyn.memory.semantic_path');
    $before = File::isDirectory($corpus) ? count(File::allFiles($corpus)) : 0;

    // promote a per-user fact (the only write path)
    $user = User::factory()->create();
    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'x',
        'title' => 't', 'body' => 'b', 'status' => 'pending',
    ]);
    app(SemanticFactPromoter::class)->approve($fact, $user->id);

    $after = File::isDirectory($corpus) ? count(File::allFiles($corpus)) : 0;
    expect($after)->toBe($before); // global corpus untouched
});

it('a pending fact never reaches the per-user store without approval', function () {
    $user = User::factory()->create();
    config(['fyn.memory.user_semantic_path' => storage_path('app/test-us-'.uniqid())]);
    ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'y',
        'title' => 't', 'body' => 'b', 'status' => 'pending',
    ]);

    expect(app(\App\Services\AI\Memory\UserSemanticStore::class)->forUser($user->id))->toBe([]);
});
```

- [ ] **Step 2: Run the invariant suite + the whole Fyn suite**

Run: `./vendor/bin/pest tests/Feature/Fyn/Learning/` then `./vendor/bin/pest tests/Feature/Fyn tests/Unit/Services/AI`
Expected: all PASS.

- [ ] **Step 3: Golden-master + full suite regression**

Run: `./vendor/bin/pest` (full suite). Confirm `FynSystemPrompt::text()` snapshot test still green (byte-invariant) and tool-schema golden masters green.
Expected: 0 failures.

- [ ] **Step 4: Browser E2E (Rule #14, desktop admin per Rule #19 carve-out)**

With `FYN_LEARNING_ENABLED=true` locally: log in (john@example.com, fetch MFA from DB), drive a Fyn conversation stating a durable fact ("I want to retire at 60"), trigger summarisation (`php artisan` stale-scan or call the job), then as admin (chris@fynla.org) open `/admin/proposed-facts`, **approve** the fact, and in a NEW Fyn turn confirm the approved fact surfaces in recall/context. Verify a procedure amendment can be staged and that approving it writes no corpus file.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Fyn/Learning/NoAutonomousEditInvariantTest.php
git commit -m "test(coala-p6): no-autonomous-edit invariants (global corpus untouched, approval-gated)"
```

---

## Self-Review

**Spec coverage:**
- §4 A1 session-end consolidation → Task 7 (summariser path). A2 proposed-fact emission → Tasks 6–7. A3 staging table → Task 1. A4 review surface → Task 9. A5 reify-on-approval → Task 8 (per-user store, not the spec's corpus path — deviation flagged at top). A6 per-user recall load → Task 5. ✓
- §4 B1 failure context → Task 11. B2 learn store=procedural → Task 10. B3 staging → Task 1. B4 review surface (no auto-apply) → Task 12. ✓
- §4 C1 sparse scorer → Task 2. C2 planner wiring → Task 3. ✓
- §7 no-auto-apply invariant tests → Tasks 12 + 13. Regulatory guard (global corpus never written) → Task 13. ✓
- Flag-gating (D7) → Tasks 1, 7, 10, 11. ✓

**Placeholder scan:** Tasks 10–11 carry `->todo(...)` test stubs because they depend on the scripted planner harness (`tests/Support/Fyn`) whose exact entry point must be read at execution (noted as step 0). These are explicit, not hidden TBDs — the harness is real and used by existing planner tests; the executor confirms its shape before writing the assertion.

**Type consistency:** `recall(int, ?string, int)` and `recallContext(int, ?string, int)` consistent across Tasks 3, 5. `RecallScorer::rank(string, array): array` consistent (Tasks 2, 3). `SemanticFactPromoter::approve(ProposedSemanticFact, int)` / `reject(...)` consistent (Tasks 8, 9). `UserSemanticStore::put(int,string,string,string)` / `forUser(int)` consistent (Tasks 4, 5, 8, 13). Staging model fillables match migration columns (Task 1).

---

## Execution Handoff

Two execution options:
1. **Subagent-Driven (recommended)** — fresh subagent per task, two-stage review between tasks. Matches the established CoALA phase pattern.
2. **Inline Execution** — execute here with checkpoints.

**Open item to confirm before execution:** the per-user-store path deviation (flagged at the top of this plan) — `storage/app/memory/semantic-user/<user_id>/` instead of the spec's `fyn-memory/semantic/<user_id>/`. Recommended; confirm or redirect.
