# Fyn Typed Memory and Dense Recall Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task, **inline in the main session**. CSJ 2026-09-24: "the implementation plan is done inline, not sub-agent driven." Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give Fyn real per-user memory and per-user learning. Every user has their own memory, Fyn learns from each of that user's conversations, and what it learns applies to that user's next turn automatically. This brings the code into line with the agreed CoALA design. That means typed SQL relationship memory, which never copies live data, which the user can see, correct and delete on web and `/m`, and which is recalled with the canonical SQL episodes. Then add dense (embedding) recall behind the existing `RecallScorer` seam, which is Option 5 of the 2026-09-24 research.

**Architecture:**
- Per-user memory moves from Markdown files (`UserSemanticStore`, `fyn-memory/episodic/episodes`) to one SQL table, `user_memory_facts`, behind a single `UserMemoryRepository`. A deterministic `MemoryFactGuard` stops any live value (money, percentages, dates of birth, fields Fynla already stores) from becoming memory.
- The conversation summary rows already written by `ConversationSummariser` become the canonical episodes, ranked by `RecallScorer`.
- Embeddings (OpenAI `text-embedding-3-small`) are stored in MySQL and ranked by brute-force cosine in PHP, blended with the existing sparse scorer. Every embedding failure falls back to sparse.
- Personal-data embeddings stay behind a separate flag until the data protection impact assessment and the OpenAI data-residency approval exist.

**Tech Stack:** PHP 8.3, Laravel 10, MySQL 8, Pest, Vue 3 (web `resources/js/`, `/m` `resources/mobile/`), `openai-php/client` ^0.19 (already installed), Playwright for browser verification.

**Spec:**
- `docs/superpowers/specs/2026-07-10-fyn-evidence-first-advice-design.md`, sections 7, 8 and 10: the memory trust model, user memory control, and learning gates.
- `codex/plans/programme/fynla-coala-implementation-plan.md` v0.5: memory holds pointers, not copies.
- `September/September24Updates/neo4j-graph-vector-architecture-research.md`: the evidence, Option 5, and CSJ's 2026-09-24 decisions.
- This plan re-plans Tasks 22F, 22G and 22H of `docs/superpowers/plans/2026-07-10-fyn-evidence-first-advice.md` against the code as it is today. Those tasks depended on `FynEvidenceAssembler` / `AdviceCase` from 22A–22D, which were never built. Here they hook into the current `FynContextAssembler` instead. When 22A–22D land later, they consume these interfaces unchanged.

## Decisions this plan relies on

| # | Decision | Status |
|---|---|---|
| D1 | **CSJ ruling, 2026-09-24:** learned facts apply automatically, and memory and learning are per user.
- After each of a user's conversations, what Fyn learned about that user (preferences, priorities, concerns, choices, intentions, circumstances, and how they like to be helped) is written to *that user's* memory. It is active on their next turn, with no approval step.
- The user can see, correct and delete it in settings, or by telling Fyn.
- The only thing that blocks a fact is `MemoryFactGuard` (no live values: the pointer rule).
- The admin fact-review queue is retired.
- Review remains only for **global** content that changes Fyn for everyone: the procedural and regulatory corpora (CoALA plan lines 68 and 810).
- This corrects the "never auto-apply" wording in spec sections 7 and 10, which over-extended that rule to per-user facts. | Decided |
| D2 | Personal text (memory facts, conversation summaries) is embedded only when `FYN_EMBEDDINGS_PERSONAL=true`. That is off until the data protection impact assessment is signed and OpenAI data residency is approved (OpenAI processes embeddings in the US or EEA, not the UK). The global corpus (house views, procedures, future FCA narrative) is not personal data and is embedded from Task 7. | Follows from the research (sections 4.3 and 4.4). |
| D3 | `FYN_LEARNING_ENABLED` stays `false` in production until Tasks 1–6 are released, because today's learning path copies figures. It was switched off on 2026-09-24 16:41. CSJ switches it on after the Task 9 walk. | CSJ 2026-09-24 |
| D4 | No household or user-data projection into any vector or graph store. User data is always read live through the existing tools and pointers. | CSJ, 2026-09-24. |
| D5 | Embedding provider: OpenAI `text-embedding-3-small` requested at 512 dimensions. | CSJ, "probably OpenAI". Confirm before Task 7. |
| D6 | The legacy Markdown fact store holds **0 files** on production, csjones and local (checked 2026-09-24). No migration command is built. The erase path still deletes any legacy directory. | Evidence, research section 15. |

## Global Constraints

- Advice Fyn stays read-only. Every memory mutation from chat goes through `delegate_to_capture` and stays GroundGate-protected (`AdviceFyn::WRITE_TOOLS`).
- Memory and learning are per user: every fact, episode and embedding row carries `user_id`, and learning for user A only ever writes user A's memory.
- Canonical financial state is always fetched live. No balance, income, contribution, allowance, date of birth, ownership share or plan figure is ever written to memory. `MemoryFactGuard` enforces this in code, not only in the prompt.
- Approximate language stays approximate: "my child is eight" never becomes a date of birth.
- Rule 20: every recall path has **one** home. When a mechanism is replaced, the old one is deleted in the same task, not left running beside it.
- Rule 19: every user-facing change ships on web **and** `/m`. Rule 13: routed views wrap in `AppLayout` (web) or `MobileChrome` (`/m`).
- Rules 8, 11, 12, 15: palette tokens only, no amber/orange, no scores, no new icons, no emoji anywhere (code, copy, commits).
- Rule 9: no cold acronyms in user-facing copy.
- British English in user-facing text; American spelling in code.
- Migrations are additive and reversible. Never `migrate:fresh`, `migrate:refresh`, `db:wipe`, `--env=testing`, `route:cache` or `artisan optimize`. Run `php artisan db:seed` after local migrations.
- Preview users (`is_preview_user = true`) can never persist memory or embeddings.
- `declare(strict_types=1);` in every PHP file. Pest `it()` / `describe()`. `Mockery::close()` in `afterEach` where Mockery is used.
- Tests that touch memory paths rely on the `tests/Pest.php` hook from PR #937. Never write into `fyn-memory/` or `storage/app/memory/` from a test.
- Every embedding call fails open to the sparse scorer, within a 2-second timeout. A Fyn turn must never fail or stall because embeddings failed.

## Review Focus

1. **Learning actually lands for that user.** A preference stated in conversation 1 must appear in that user's `<knowledge>` block on conversation 2, with no approval step, and never in another user's context (Task 3 and Task 9 walk).
2. **Figures in disguise.** "thirty thousand a year", "£30k", "30,000", "a 5 percent match" and "born in 1978" must all be rejected by `MemoryFactGuard` (Task 1 tests pin each one).
3. **Cross-user leakage.** Recall for user A must never return user B's facts, episodes or embedding rows, including a spouse who is not sharing (Tasks 2, 5 and 8 isolation tests).
4. **A superseded or deleted fact reaching the prompt.** After a correction or a delete, the old text must be absent from the assembled context on the next turn (Task 4 and Task 6 tests).
5. **Embeddings API down or slow.** The OpenAI call times out or returns 500. The turn must answer with sparse ranking and must not wait longer than the timeout (Task 8 test with a faked failing client).
6. **Erasure completeness.** `fyn:user:erase --force` must remove `user_memory_facts` rows, personal `fyn_embeddings` rows and any legacy Markdown directory (Task 4 and Task 8 erase assertions).

## File structure

| File | Responsibility |
|---|---|
| `app/Services/AI/Memory/MemoryFactGuard.php` | Deterministic rule: can this text be memory? (no live values) |
| `database/migrations/2026_09_25_100000_create_user_memory_facts_table.php` | Typed relationship-memory table |
| `app/Services/AI/Memory/MemoryTrust.php` | Enum: `learned`, `user_confirmed`. This records provenance only; it never gates use |
| `app/Services/AI/Memory/MemoryCategory.php` | Enum: `preference`, `priority`, `concern`, `choice`, `intention`, `circumstance` |
| `app/Models/UserMemoryFact.php` | Eloquent model |
| `app/Services/AI/Memory/UserMemoryRepository.php` | The one read/write home for user memory: record, relevantFor, confirm, correct, forget, eraseForUser |
| `app/Services/AI/Learning/ProposedFactSynthesiser.php` (modify) | Extracts user-stated relationship facts with category |
| `app/Services/AI/ConversationSummariser.php` (modify) | Guard, then repository, instead of staging proposals |
| `app/Services/AI/Memory/SemanticRetriever.php` (modify) | `retrieveForUser` reads the repository |
| `app/Services/AI/Memory/Episodic/EpisodeRecallService.php` | Canonical episode recall over conversation summaries |
| `app/Services/AI/Memory/FynMemoryStore.php` (modify) | Loses episode write/recall; keeps procedures and rubric |
| `app/Services/AI/Loop/FynLoop.php` (modify) | Episodic `learn` no longer writes files; the planner reads `EpisodeRecallService` |
| `app/Services/AI/Fyn/FynContextAssembler.php` (modify) | `<remembered>` from `EpisodeRecallService`; user facts labelled by trust |
| `app/Services/AI/MemoryRetrieverService.php` (modify) | Drops `prior_topics` / `prior_intents` (consolidated into `<remembered>`) |
| `app/Http/Controllers/Api/Settings/FynMemoryController.php` + two Form Requests | User-scoped memory API |
| `resources/js/views/Settings/FynMemorySettings.vue`, `resources/js/services/fynMemoryService.js` | Web surface |
| `resources/mobile/views/FynMemorySettings.vue` | `/m` surface |
| `app/Services/AI/Memory/Embeddings/EmbeddingClient.php` | OpenAI embeddings call, timeout, fail-open |
| `database/migrations/2026_09_25_110000_create_fyn_embeddings_table.php`, `app/Models/FynEmbedding.php` | Vector storage |
| `app/Services/AI/Memory/Embeddings/EmbeddingIndex.php` | Upsert by content hash; cosine top-k; erase |
| `app/Console/Commands/FynEmbeddingsReindex.php` | Deploy-time embedding of the global corpus |
| `app/Services/AI/Memory/Recall/HybridRecallScorer.php` | Sparse + dense blend; implements `RecallScorer` |
| `config/fyn.php` (modify) | `embeddings.*` keys |

---

### Task 1: `MemoryFactGuard`, the no-live-values rule in code

**Files:**
- Create: `app/Services/AI/Memory/MemoryFactGuard.php`
- Test: `tests/Unit/Services/AI/Memory/MemoryFactGuardTest.php`

**Interfaces:**
- Produces: `MemoryFactGuard::reason(string $key, string $text): ?string`. It returns `null` when the text may be stored, otherwise a short rejection reason (`money`, `percentage`, `date_of_birth`, `canonical_field`, `number_word_money`).

- [ ] **Step 1: Write the failing test.** The cases come from the real csjones facts of 2026-09-24.

```php
<?php

declare(strict_types=1);

use App\Services\AI\Memory\MemoryFactGuard;

describe('MemoryFactGuard', function () {
    it('rejects live values the user already stores in Fynla', function (string $key, string $text, string $reason) {
        expect((new MemoryFactGuard)->reason($key, $text))->toBe($reason);
    })->with([
        ['gross-annual-employment-income', "Eleanor's gross annual employment income is £82,000.", 'money'],
        ['nationwide-savings', 'Mia owns a Nationwide savings account with a £2,000 balance earning 4.1% interest.', 'money'],
        ['wedding-goal', 'User wants to save 30000 for a wedding by December 2029.', 'money'],
        ['retirement-income', 'Wants roughly 30k a year in retirement.', 'money'],
        ['retirement-income-words', 'Wants about thirty thousand pounds a year.', 'number_word_money'],
        ['workplace-pension', 'Contributes 5% of salary, matched by the employer.', 'percentage'],
        ['pension-match', 'Gets a 5 percent employer match.', 'percentage'],
        ['date-of-birth', 'Savetaxm was born on 21 June 1978.', 'date_of_birth'],
        ['birth-year', 'Was born in 1978.', 'date_of_birth'],
        ['has-workplace-pension', 'July has no workplace pension.', 'canonical_field'],
        ['spouse-income', "Sav's spouse earns a salary.", 'canonical_field'],
    ]);

    it('allows durable relationship facts', function (string $key, string $text) {
        expect((new MemoryFactGuard)->reason($key, $text))->toBeNull();
    })->with([
        ['retirement-age-intention', 'Wants to retire at 60.'],
        ['risk-attitude', 'Describes themselves as cautious with investments.'],
        ['priority-children', 'Their priority is helping the children through university.'],
        ['concern-inheritance-tax', 'Worried about inheritance tax on the family home.'],
        ['communication-preference', 'Prefers short answers without jargon.'],
        ['house-move-intention', 'Plans to move house within about two years.'],
        ['locking-away-concern', 'Sees a disadvantage in locking money away for years.'],
        ['childhood-home', 'Grew up in the north and would like to move back there one day.'],
    ]);
});
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/MemoryFactGuardTest.php`
Expected: FAIL with `Class "App\Services\AI\Memory\MemoryFactGuard" not found`.

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory;

/**
 * The CoALA v0.5 rule in code: memory holds nothing that has a live owner.
 * Money, percentages, dates of birth and anything Fynla already stores as a
 * field are looked up live, never remembered. The synthesiser prompt says the
 * same, but the model does not reliably obey it (2026-09-24: 101 of 206 staged
 * facts carried figures), so this check is authoritative.
 */
final class MemoryFactGuard
{
    /** Topics Fynla already stores as fields. Remembering them would copy live data. */
    private const CANONICAL_TOPICS = [
        'income', 'salary', 'earns', 'wage', 'balance', 'pension', 'contribute', 'contributes', 'contribution', 'isa',
        'savings account', 'mortgage', 'loan', 'debt', 'property value', 'house worth',
        'spouse', 'partner', 'married', 'children', 'child', 'dependant', 'occupation',
        'employer', 'expenditure', 'outgoings', 'spending', 'national insurance',
    ];

    /** Topic words that are allowed when they appear as an intention, priority or concern. */
    private const INTENT_MARKERS = [
        'wants', 'want to', 'plans', 'plan to', 'intends', 'hopes', 'priority', 'prioritise',
        'worried', 'concerned', 'prefers', 'would like', 'aims',
    ];

    private const NUMBER_WORDS = '(one|two|three|four|five|six|seven|eight|nine|ten|twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety|hundred)';

    public function reason(string $key, string $text): ?string
    {
        $t = mb_strtolower($text.' '.str_replace('-', ' ', $key));

        // Money: a currency sign, thousands separators, 5+ digit numbers, or "30k".
        // Bare years (2029) stay allowed: "retire by 2035" is a valid intention.
        if (preg_match('/[£$€]\s?\d|\b\d{1,3}(,\d{3})+\b|\b\d{5,}\b|\b\d+(\.\d+)?\s?k\b/iu', $text) === 1) {
            return 'money';
        }
        if (preg_match('/'.self::NUMBER_WORDS.'\s+(thousand|million|hundred)\b|\b(pounds|quid)\b/u', $t) === 1) {
            return 'number_word_money';
        }
        if (preg_match('/\d\s?%|\bper\s?cent\b|\bpercent\b/u', $t) === 1) {
            return 'percentage';
        }
        if (preg_match('/\bborn\b|date of birth|\bbirthday\b/u', $t) === 1) {
            return 'date_of_birth';
        }

        $isIntent = false;
        foreach (self::INTENT_MARKERS as $marker) {
            if (str_contains($t, $marker)) {
                $isIntent = true;
                break;
            }
        }
        if (! $isIntent) {
            foreach (self::CANONICAL_TOPICS as $topic) {
                // Word boundaries: "isa" must not match "disadvantage", "child" not "childhood".
                if (preg_match('/\b'.preg_quote($topic, '/').'s?\b/u', $t) === 1) {
                    return 'canonical_field';
                }
            }
        }

        return null;
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/MemoryFactGuardTest.php`
Expected: PASS, 19 cases.

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/Memory/MemoryFactGuard.php tests/Unit/Services/AI/Memory/MemoryFactGuardTest.php
git commit -m "feat(fyn-memory): MemoryFactGuard keeps live values out of memory"
```

---

### Task 2: Typed relationship-memory store

**Files:**
- Create: `database/migrations/2026_09_25_100000_create_user_memory_facts_table.php`
- Create: `app/Services/AI/Memory/MemoryTrust.php`, `app/Services/AI/Memory/MemoryCategory.php`
- Create: `app/Models/UserMemoryFact.php`
- Create: `app/Services/AI/Memory/UserMemoryRepository.php`
- Test: `tests/Feature/Fyn/Memory/UserMemoryRepositoryTest.php`

**Interfaces:**
- Consumes: `MemoryFactGuard::reason()` (Task 1).
- Produces:
  - `UserMemoryRepository::record(User $user, string $factKey, MemoryCategory $category, string $displayText, MemoryTrust $trust, ?int $sourceConversationId = null, ?int $sourceMessageId = null): ?UserMemoryFact`. Returns `null` when the guard rejects or the user is a preview user. Re-recording an active key with new text supersedes the old row.
  - `UserMemoryRepository::activeFor(User $user): Collection<UserMemoryFact>`
  - `UserMemoryRepository::confirm(User $user, int $factId): UserMemoryFact`
  - `UserMemoryRepository::correct(User $user, int $factId, string $newText): UserMemoryFact`. Returns the new active row; the old row becomes `superseded`.
  - `UserMemoryRepository::forget(User $user, int $factId): void`. Status becomes `deleted` and `display_text` is blanked to `''`.
  - `UserMemoryRepository::markUsed(Collection $facts): void`
  - `UserMemoryRepository::eraseForUser(int $userId): int`. A hard delete for GDPR.
  - All `int $factId` lookups are scoped to `$user->id` and throw `ModelNotFoundException` otherwise.

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserMemoryFact;
use App\Services\AI\Memory\MemoryCategory;
use App\Services\AI\Memory\MemoryTrust;
use App\Services\AI\Memory\UserMemoryRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    $this->repo = app(UserMemoryRepository::class);
    $this->user = User::factory()->create();
});

it('records a learned fact as active immediately', function () {
    $fact = $this->repo->record($this->user, 'retirement-age-intention', MemoryCategory::Intention, 'Wants to retire at 60.', MemoryTrust::Learned);

    expect($fact->status)->toBe('active')
        ->and($fact->trust_state)->toBe(MemoryTrust::Learned)
        ->and($this->repo->activeFor($this->user))->toHaveCount(1);
});

it('refuses a fact carrying a live value', function () {
    expect($this->repo->record($this->user, 'income', MemoryCategory::Circumstance, 'Earns £82,000.', MemoryTrust::Learned))->toBeNull()
        ->and(UserMemoryFact::count())->toBe(0);
});

it('refuses preview users', function () {
    $preview = User::factory()->create(['is_preview_user' => true]);

    expect($this->repo->record($preview, 'risk', MemoryCategory::Preference, 'Cautious.', MemoryTrust::Learned))->toBeNull();
});

it('supersedes the active row when the same key is re-recorded with new text', function () {
    $old = $this->repo->record($this->user, 'retirement-age-intention', MemoryCategory::Intention, 'Wants to retire at 60.', MemoryTrust::Learned);
    $new = $this->repo->record($this->user, 'retirement-age-intention', MemoryCategory::Intention, 'Wants to retire at 62.', MemoryTrust::Learned);

    expect($old->fresh()->status)->toBe('superseded')
        ->and($old->fresh()->superseded_by_id)->toBe($new->id)
        ->and($this->repo->activeFor($this->user)->pluck('display_text')->all())->toBe(['Wants to retire at 62.']);
});

it('is a no-op when the same key is re-recorded with identical text', function () {
    $a = $this->repo->record($this->user, 'risk', MemoryCategory::Preference, 'Cautious.', MemoryTrust::Learned);
    $b = $this->repo->record($this->user, 'risk', MemoryCategory::Preference, 'Cautious.', MemoryTrust::Learned);

    expect($b->id)->toBe($a->id)->and(UserMemoryFact::count())->toBe(1);
});

it('confirms, corrects and forgets only the owner\'s facts', function () {
    $fact = $this->repo->record($this->user, 'risk', MemoryCategory::Preference, 'Cautious.', MemoryTrust::Learned);
    $other = User::factory()->create();

    expect(fn () => $this->repo->confirm($other, $fact->id))->toThrow(ModelNotFoundException::class);

    $confirmed = $this->repo->confirm($this->user, $fact->id);
    expect($confirmed->trust_state)->toBe(MemoryTrust::UserConfirmed)->and($confirmed->confirmed_at)->not->toBeNull();

    $corrected = $this->repo->correct($this->user, $fact->id, 'Balanced, not cautious.');
    expect($corrected->trust_state)->toBe(MemoryTrust::UserConfirmed)
        ->and($fact->fresh()->status)->toBe('superseded');

    $this->repo->forget($this->user, $corrected->id);
    expect($this->repo->activeFor($this->user))->toHaveCount(0)
        ->and($corrected->fresh()->display_text)->toBe('');
});

it('erases every row for a user and nobody else', function () {
    $this->repo->record($this->user, 'risk', MemoryCategory::Preference, 'Cautious.', MemoryTrust::Learned);
    $other = User::factory()->create();
    $this->repo->record($other, 'risk', MemoryCategory::Preference, 'Adventurous.', MemoryTrust::Learned);

    expect($this->repo->eraseForUser($this->user->id))->toBe(1)
        ->and(UserMemoryFact::where('user_id', $other->id)->count())->toBe(1);
});
```

- [ ] **Step 2: Run and confirm failure**

Run: `./vendor/bin/pest tests/Feature/Fyn/Memory/UserMemoryRepositoryTest.php`
Expected: FAIL, missing classes and table.

- [ ] **Step 3: Migration**

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
        Schema::create('user_memory_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fact_key', 160);
            $table->string('category', 32);
            $table->text('display_text');
            $table->string('trust_state', 32);
            $table->string('source_type', 32); // conversation | settings | chat_correction
            $table->foreignId('source_conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->unsignedBigInteger('source_message_id')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->foreignId('superseded_by_id')->nullable()->constrained('user_memory_facts')->nullOnDelete();
            $table->string('status', 16)->default('active'); // active | superseded | deleted
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'fact_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_memory_facts');
    }
};
```

- [ ] **Step 4: Enums, model and repository**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory;

enum MemoryTrust: string
{
    /** Learned by Fyn from this user's conversations. Active immediately (CSJ 2026-09-24). */
    case Learned = 'learned';
    /** Confirmed or written by the user in settings. */
    case UserConfirmed = 'user_confirmed';

    public function label(): string
    {
        return match ($this) {
            self::Learned => 'Learned from your conversations',
            self::UserConfirmed => 'Confirmed by you',
        };
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory;

enum MemoryCategory: string
{
    case Preference = 'preference';
    case Priority = 'priority';
    case Concern = 'concern';
    case Choice = 'choice';
    case Intention = 'intention';
    case Circumstance = 'circumstance';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AI\Memory\MemoryCategory;
use App\Services\AI\Memory\MemoryTrust;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMemoryFact extends Model
{
    protected $fillable = [
        'user_id', 'fact_key', 'category', 'display_text', 'trust_state', 'source_type',
        'source_conversation_id', 'source_message_id', 'confirmed_at', 'superseded_by_id',
        'status', 'last_used_at',
    ];

    protected $casts = [
        'category' => MemoryCategory::class,
        'trust_state' => MemoryTrust::class,
        'confirmed_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory;

use App\Models\User;
use App\Models\UserMemoryFact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one home for per-user relationship memory (evidence-first spec §7).
 * Every write passes MemoryFactGuard; every lookup is scoped to the owner.
 */
final class UserMemoryRepository
{
    public function __construct(private readonly MemoryFactGuard $guard) {}

    public function record(User $user, string $factKey, MemoryCategory $category, string $displayText, MemoryTrust $trust, ?int $sourceConversationId = null, ?int $sourceMessageId = null, string $sourceType = 'conversation'): ?UserMemoryFact
    {
        $displayText = trim($displayText);
        if ($user->is_preview_user || $displayText === '' || $this->guard->reason($factKey, $displayText) !== null) {
            return null;
        }

        return DB::transaction(function () use ($user, $factKey, $category, $displayText, $trust, $sourceConversationId, $sourceMessageId, $sourceType): UserMemoryFact {
            $current = UserMemoryFact::where('user_id', $user->id)->where('fact_key', $factKey)
                ->where('status', 'active')->lockForUpdate()->first();

            if ($current !== null && $current->display_text === $displayText) {
                return $current;
            }

            $new = UserMemoryFact::create([
                'user_id' => $user->id,
                'fact_key' => $factKey,
                'category' => $category,
                'display_text' => $displayText,
                'trust_state' => $trust,
                'source_type' => $sourceType,
                'source_conversation_id' => $sourceConversationId,
                'source_message_id' => $sourceMessageId,
                'confirmed_at' => $trust === MemoryTrust::UserConfirmed ? now() : null,
            ]);

            $current?->forceFill(['status' => 'superseded', 'superseded_by_id' => $new->id])->save();

            return $new;
        });
    }

    /** @return Collection<int, UserMemoryFact> */
    public function activeFor(User $user): Collection
    {
        return UserMemoryFact::where('user_id', $user->id)->where('status', 'active')->orderByDesc('id')->get();
    }

    public function confirm(User $user, int $factId): UserMemoryFact
    {
        $fact = $this->owned($user, $factId);
        $fact->forceFill(['trust_state' => MemoryTrust::UserConfirmed, 'confirmed_at' => now()])->save();

        return $fact;
    }

    public function correct(User $user, int $factId, string $newText): UserMemoryFact
    {
        $fact = $this->owned($user, $factId);
        $new = $this->record($user, $fact->fact_key, $fact->category, $newText, MemoryTrust::UserConfirmed, null, null, 'settings');

        if ($new === null) {
            throw new \InvalidArgumentException('That wording contains an amount or detail Fynla keeps in your records instead.');
        }

        return $new;
    }

    public function forget(User $user, int $factId): void
    {
        $this->owned($user, $factId)->forceFill(['status' => 'deleted', 'display_text' => ''])->save();
    }

    /** @param Collection<int, UserMemoryFact> $facts */
    public function markUsed(Collection $facts): void
    {
        if ($facts->isNotEmpty()) {
            UserMemoryFact::whereIn('id', $facts->pluck('id'))->update(['last_used_at' => now()]);
        }
    }

    public function eraseForUser(int $userId): int
    {
        return UserMemoryFact::where('user_id', $userId)->delete();
    }

    private function owned(User $user, int $factId): UserMemoryFact
    {
        return UserMemoryFact::where('user_id', $user->id)->where('status', 'active')->findOrFail($factId);
    }
}
```

- [ ] **Step 5: Migrate locally, seed, run the tests**

```bash
php artisan migrate
php artisan db:seed
./vendor/bin/pest tests/Feature/Fyn/Memory/UserMemoryRepositoryTest.php
```
Expected: PASS, 7 tests.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_25_100000_create_user_memory_facts_table.php app/Models/UserMemoryFact.php app/Services/AI/Memory tests/Feature/Fyn/Memory
git commit -m "feat(fyn-memory): typed user relationship memory store"
```

---

### Task 3: Per-user learning writes that user's memory, active immediately

**Files:**
- Modify: `app/Services/AI/Learning/ProposedFactSynthesiser.php` (prompt and output shape)
- Modify: `app/Services/AI/ConversationSummariser.php:96-121` (`emitProposedFacts`)
- Modify: `app/Services/AI/Loop/FynLoop.php:355-383` (`stageProposedFact`)
- Test: `tests/Feature/Fyn/Learning/TypedFactLearningTest.php`. Update `ProposedFactStagingTest.php` and `SessionPromotionTest.php` to the new behaviour.

**Interfaces:**
- Consumes: `UserMemoryRepository::record()` (Task 2).
- Produces: `ProposedFactSynthesiser::synthesise(int $conversationId, string $transcript): list<array{fact_id: string, category: string, body: string}>`. `category` is one of the `MemoryCategory` values.

- [ ] **Step 1: Write the failing test.** Fake the xAI HTTP response with `Http::fake()`, returning one allowed and one figure-bearing fact.

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserMemoryFact;
use App\Services\AI\ConversationSummariser;
use Illuminate\Support\Facades\Http;

it('stores what Fyn learned about this user as active memory and drops live values', function () {
    config(['fyn.learning_enabled' => true, 'services.xai.api_key' => 'test']);
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    Http::fake(['api.x.ai/*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => json_encode(['summary' => 's', 'topics' => ['retirement'], 'entities_mentioned' => [], 'intents_stated' => []])]]]])
        ->push(['choices' => [['message' => ['content' => json_encode(['facts' => [
            ['fact_id' => 'retirement-age-intention', 'category' => 'intention', 'body' => 'Wants to retire at 60.'],
            ['fact_id' => 'income', 'category' => 'circumstance', 'body' => 'Earns £82,000.'],
        ]])]]]]),
    ]);

    app(ConversationSummariser::class)->summarise($conversation->id);

    $facts = UserMemoryFact::where('user_id', $user->id)->get();
    expect($facts)->toHaveCount(1)
        ->and($facts->first()->display_text)->toBe('Wants to retire at 60.')
        ->and($facts->first()->source_conversation_id)->toBe($conversation->id)
        ->and(\App\Models\ProposedSemanticFact::count())->toBe(0);
});

it('stores nothing when learning is disabled', function () {
    config(['fyn.learning_enabled' => false]);
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    Http::fake(['api.x.ai/*' => Http::response(['choices' => [['message' => ['content' => '{"summary":"s","topics":[],"entities_mentioned":[],"intents_stated":[]}']]]])]);

    app(ConversationSummariser::class)->summarise($conversation->id);

    expect(UserMemoryFact::count())->toBe(0);
});
```

Before writing the fake sequence, check `ConversationSummariser::summarise()`'s real signature and its exact xAI response parsing. Match the fake to the real payload keys ([[feedback_check_fixture_keys_against_real_payloads]]).

- [ ] **Step 2: Run and confirm failure**

Run: `./vendor/bin/pest tests/Feature/Fyn/Learning/TypedFactLearningTest.php`
Expected: FAIL. A `ProposedSemanticFact` row is created and no `UserMemoryFact` exists.

- [ ] **Step 3: Narrow the synthesiser prompt.** Replace the rules block in `ProposedFactSynthesiser.php` with:

```text
You extract what Fyn should remember about THIS user from their conversation, so Fyn helps them better next time. Output strict JSON: {"facts": [{"fact_id": "<kebab-case stable key>", "category": "preference|priority|concern|choice|intention|circumstance", "body": "<one sentence, third person, in the user's own terms>"}]}.
Rules:
- What the user said about themselves, plus clear patterns in how they want to be helped (for example they asked twice for shorter answers). Never record the assistant's own suggestions as the user's view.
- Only durable relationship facts: preferences, priorities, worries, choices, intentions, life circumstances.
- NEVER include money amounts, balances, income, contributions, percentages, dates of birth, or anything Fynla stores as a record (accounts, pensions, property, spouse or children details). Those are looked up live.
- Keep approximate language approximate.
- If nothing qualifies, return {"facts": []}. JSON only.
```

Update the output mapping to return `fact_id`, `category` (dropped when it is not a valid `MemoryCategory`) and `body`.

- [ ] **Step 4: Rewire `emitProposedFacts`** so it records typed memory:

```php
private function emitProposedFacts(AiConversation $conversation, string $transcript): void
{
    if (! config('fyn.learning_enabled', false)) {
        return;
    }

    try {
        $user = $conversation->user;
        foreach ($this->synthesiser->synthesise($conversation->id, $transcript) as $fact) {
            $category = MemoryCategory::tryFrom($fact['category']);
            if ($category === null) {
                continue;
            }
            $this->memory->record($user, $fact['fact_id'], $category, $fact['body'], MemoryTrust::Learned, $conversation->id);
        }
    } catch (\Throwable $e) {
        Log::warning('[ConversationSummariser] typed memory recording failed', ['conversation_id' => $conversation->id, 'error' => $e->getMessage()]);
    }
}
```

Inject `UserMemoryRepository $memory` into the constructor. Do the same in `FynLoop::stageProposedFact` (planner `learn store=semantic`): same guard path, source conversation id, `MemoryTrust::Learned`.

- [ ] **Step 5: Retire the admin fact-review path for user facts.** Remove the `/admin/proposed-facts` routes, `SemanticFactReviewController`, `SemanticFactPromoter`, `FynSemanticPromote`, and the admin nav entry. Remove their tests (`SemanticFactReviewControllerTest.php`, `SemanticFactPromoterTest.php`). Keep the `proposed_semantic_facts` table (migrations are additive only) and the procedure-amendment review. Grep for every consumer first: `grep -rn "SemanticFactPromoter\|SemanticFactReviewController\|proposed-facts\|FynSemanticPromote" app routes resources tests`.

- [ ] **Step 6: Run the learning suites**

Run: `./vendor/bin/pest tests/Feature/Fyn/Learning tests/Feature/Api/Admin`
Expected: PASS, and `NoAutonomousEditInvariantTest` is still green (the global corpora are never written).

- [ ] **Step 7: Commit**

```bash
git add app/Services/AI app/Http app/Console routes resources tests
git commit -m "feat(fyn-memory): learning records user-stated facts as typed memory, no admin queue"
```

---

### Task 4: Recall reads typed memory; retire the Markdown fact store; erasure

**Files:**
- Modify: `app/Services/AI/Memory/SemanticRetriever.php:111-152` (`retrieveForUser`)
- Modify: `app/Services/AI/Fyn/FynContextAssembler.php:119-146` (render trust label)
- Modify: `app/Console/Commands/FynUserErase.php` (repository erase + legacy directory)
- Delete: `app/Services/AI/Memory/UserSemanticStore.php` after its last consumer is gone (erase keeps a direct `File::deleteDirectory` on the legacy path, because D6 shows no files exist)
- Test: `tests/Unit/Services/AI/Memory/SemanticRetrieverUserFactsTest.php` (rewrite), `tests/Feature/Console/FynUserEraseTest.php` (extend), `tests/Unit/Services/AI/Memory/UserSemanticStoreTest.php` (delete)

**Interfaces:**
- Consumes: `UserMemoryRepository::activeFor()` and `markUsed()`.
- Produces: `SemanticRetriever::retrieveForUser(int $userId, string $message): list<SemanticFact>`. The signature is unchanged. User facts come back with `category: 'user_memory'` and `source: 'confirmed'` or `'learned'`, and the assembler renders the source in the heading.

- [ ] **Step 1: Write the failing tests.** Seed two facts through the repository (one confirmed, one learned) plus a superseded and a deleted one. Assert that `retrieveForUser` returns only the two active facts matching the query, each with the right `source`. Assert that the assembled context for a turn contains `(source: learned)` for the learned fact, and contains neither the superseded nor the deleted text. Assert that `fyn:user:erase {id} --force` leaves `UserMemoryFact::where('user_id', $id)->count() === 0`.

- [ ] **Step 2: Run and confirm failure**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/SemanticRetrieverUserFactsTest.php tests/Feature/Console/FynUserEraseTest.php`
Expected: FAIL, because retrieval still reads `UserSemanticStore`.

- [ ] **Step 3: Implement.** Replace the `UserSemanticStore` constructor dependency with `UserMemoryRepository`. In `retrieveForUser`, swap the `$this->userStore->forUser($userId)` loop for `$this->memory->activeFor(User::findOrFail($userId))`. Use `$fact->display_text` as the haystack and body, `$fact->category->label()` as the title, and `$fact->trust_state === MemoryTrust::UserConfirmed ? 'confirmed' : 'learned'` as the source. Call `markUsed()` on the matched facts.

In `FynUserErase::handle`, replace `$semanticStore->forget($userId)` with:

```php
$semanticFactCount = app(UserMemoryRepository::class)->eraseForUser($userId);
File::deleteDirectory(rtrim((string) config('fyn.memory.user_semantic_path'), '/').'/'.$userId); // legacy store, empty since 2026-09-24
```

- [ ] **Step 4: Delete `UserSemanticStore` and its test.** Run `grep -rn "UserSemanticStore" app tests`, which must return nothing afterwards.

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory tests/Feature/Console/FynUserEraseTest.php tests/Unit/Services/AI/Fyn/FynContextAssemblerTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app tests
git commit -m "refactor(fyn-memory): recall and erase use typed memory; retire Markdown fact store"
```

---

### Task 5: Conversation summaries become the canonical episodes (Rule 20 consolidation)

The agreed CoALA flow says "the session is the episode". `ai_conversations.summary`, `topics` and `intents_stated` are already written by `ConversationSummariser` for every settled conversation (77 of 307 on production, the latest 2026-09-23). Today three mechanisms read or imitate episodes: Markdown files (`FynMemoryStore::recall`), `MemoryRetrieverService::fromConversationIndex` (`prior_topics`/`prior_intents` in `<known_facts>`), and the `search_conversation_index` tool. This task makes one recall service and deletes the Markdown one. The tool stays; it is an explicit user-driven search, not recall.

**Files:**
- Create: `app/Services/AI/Memory/Episodic/EpisodeRecallService.php`
- Modify: `app/Services/AI/Memory/FynMemoryStore.php` (delete `writeEpisode`, `recall`, `recallContext`, `userEpisodeDir`)
- Modify: `app/Services/AI/Loop/FynLoop.php:233-238, 319-351` (episodic `learn` becomes a no-op; the planner prompt uses the new service)
- Modify: `app/Services/AI/Fyn/FynContextAssembler.php:114-117`
- Modify: `app/Services/AI/MemoryRetrieverService.php:75-89, 264-309` (remove Layer 4 from `retrieve()`; delete `fromConversationIndex`)
- Modify: `.gitignore` handling. Delete `fyn-memory/episodic/episodes/` from the repo (keep `RUBRIC.md` until the planner's `learn` action is re-specified) and remove the rsync exclude note from `deploy/DEPLOY.md` step 6.
- Test: `tests/Feature/Fyn/Memory/EpisodeRecallServiceTest.php`. Update `FynMemoryStoreTest.php`, `FynMemoryStoreRecallTest.php`, `FynLoopPlannerTest.php`, `FailureContextTest.php` and the `MemoryRetrieverService` tests.

**Interfaces:**
- Consumes: `RecallScorer::rank(string $query, array $episodes): array`. This is the existing seam, and in Task 8 it becomes hybrid.
- Produces:
  - `EpisodeRecallService::recall(User $user, string $query, int $limit = 3, ?int $excludeConversationId = null): list<array{conversation_id: int, date: string, body: string}>`
  - `EpisodeRecallService::render(array $episodes): string`, which returns `''` when empty.

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\Memory\Episodic\EpisodeRecallService;

it('recalls the most relevant summarised conversations for the owner only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    AiConversation::factory()->create(['user_id' => $user->id, 'summary' => 'Talked about paying into a pension before retiring.', 'topics' => ['retirement'], 'intents_stated' => ['retire early'], 'summarised_at' => now()->subDays(3)]);
    AiConversation::factory()->create(['user_id' => $user->id, 'summary' => 'Asked how an emergency fund works.', 'topics' => ['savings'], 'intents_stated' => [], 'summarised_at' => now()->subDay()]);
    AiConversation::factory()->create(['user_id' => $other->id, 'summary' => 'Pension question from someone else.', 'topics' => ['retirement'], 'intents_stated' => [], 'summarised_at' => now()]);

    $episodes = app(EpisodeRecallService::class)->recall($user, 'what did we say about my pension', 3);

    expect($episodes)->not->toBeEmpty()
        ->and($episodes[0]['body'])->toContain('pension before retiring')
        ->and(collect($episodes)->pluck('body')->implode(' '))->not->toContain('someone else');
});

it('excludes the current conversation and unsummarised ones', function () {
    $user = User::factory()->create();
    $current = AiConversation::factory()->create(['user_id' => $user->id, 'summary' => 'Pension today.', 'summarised_at' => now()]);
    AiConversation::factory()->create(['user_id' => $user->id, 'summary' => null, 'summarised_at' => null]);

    expect(app(EpisodeRecallService::class)->recall($user, 'pension', 3, $current->id))->toBe([]);
});

it('never writes episode files', function () {
    expect(method_exists(\App\Services\AI\Memory\FynMemoryStore::class, 'writeEpisode'))->toBeFalse();
});
```

Check `AiConversation`'s factory and casts for `topics` / `intents_stated` (JSON arrays) before running.

- [ ] **Step 2: Run and confirm failure**

Run: `./vendor/bin/pest tests/Feature/Fyn/Memory/EpisodeRecallServiceTest.php`
Expected: FAIL, class not found.

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\Memory\Recall\RecallScorer;

/**
 * Canonical episodic recall (evidence-first spec §7): a summarised
 * conversation is the episode. One home for "what have we talked about
 * before" (Rule 20). Candidates are the owner's 30 most recent summaries,
 * ranked by the RecallScorer seam, so dense scoring plugs in without a
 * change here.
 */
final class EpisodeRecallService
{
    private const CANDIDATES = 30;

    public function __construct(private readonly RecallScorer $scorer) {}

    /** @return list<array{conversation_id: int, date: string, body: string}> */
    public function recall(User $user, string $query, int $limit = 3, ?int $excludeConversationId = null): array
    {
        $rows = AiConversation::query()
            ->where('user_id', $user->id)
            ->whereNotNull('summarised_at')
            ->whereNotNull('summary')
            ->when($excludeConversationId !== null, fn ($q) => $q->where('id', '!=', $excludeConversationId))
            ->orderByDesc('summarised_at')
            ->limit(self::CANDIDATES)
            ->get(['id', 'summary', 'intents_stated', 'summarised_at']);

        $episodes = $rows->map(fn (AiConversation $c): array => [
            'conversation_id' => $c->id,
            'date' => $c->summarised_at->toDateString(),
            'body' => trim($c->summary.' '.implode('. ', array_filter((array) $c->intents_stated, 'is_string'))),
        ])->all();

        if (trim($query) === '') {
            return array_slice($episodes, 0, $limit);
        }

        $ranked = $this->scorer->rank($query, $episodes);

        return array_slice(array_values(array_filter($ranked, fn (array $e): bool => ($e['score'] ?? 1) > 0)), 0, $limit);
    }

    /** @param list<array{conversation_id: int, date: string, body: string}> $episodes */
    public function render(array $episodes): string
    {
        if ($episodes === []) {
            return '';
        }

        $lines = array_map(fn (array $e): string => "- {$e['date']}: {$e['body']}", $episodes);

        return "Earlier conversations (what was discussed then, not current figures):\n".implode("\n", $lines);
    }
}
```

`SparseRecallScorer` keeps zero-score episodes today. Add a `score` key to each returned episode in `SparseRecallScorer::rank` (distinct-term count) so the filter above can drop the irrelevant ones. Update `FynMemoryStoreRecallTest` accordingly.

- [ ] **Step 4: Rewire the consumers**
  - `FynContextAssembler.php:114-117`: `$remembered = $this->episodes->render($this->episodes->recall($ctx->user, $ctx->message, 3, $ctx->conversation?->id));`
  - `FynLoop::plannerSystemPrompt`: the same call with `$query`.
  - `FynLoop` `learn` with `store=episodic`: remove the `recordEpisode` branch (`'episodic' => null`) and delete `recordEpisode()`.
  - `MemoryRetrieverService::retrieve`: delete the Layer 4 line and the `fromConversationIndex` method. The `<remembered>` block now carries prior intents.
  - Delete the Markdown episode methods from `FynMemoryStore`.

- [ ] **Step 5: Remove the episodes folder and the deploy note.** `git rm -r fyn-memory/episodic/episodes` (only `.gitignore` and `.gitkeep` are tracked). Delete the `config('fyn.memory.episodic_path')` key and the Pest hook line that redirects it. Keep the `user_semantic_path` redirect until Task 4 has deleted that key too. Revert the `--exclude` sentence in `deploy/DEPLOY.md` step 6 and in the release skill to a one-line note that the folder no longer exists. On production and csjones, `rmdir` the now-empty folders at release time; they are already empty (research 5.6).

- [ ] **Step 6: Run the memory, loop and assembler suites**

Run: `./vendor/bin/pest tests/Feature/Fyn tests/Unit/Services/AI tests/Feature/AI`
Expected: PASS. Then run `grep -rn "writeEpisode\|recallContext\|fromConversationIndex\|episodic_path" app tests config`, which must return nothing.

- [ ] **Step 7: Commit**

```bash
git add -A app config tests fyn-memory deploy .claude/skills/release
git commit -m "refactor(fyn-memory): conversation summaries are the one episodic recall path"
```

---

### Task 6: Users see, confirm, correct and delete what Fyn remembers (web and `/m`, chat corrections)

**Files:**
- Create: `app/Http/Controllers/Api/Settings/FynMemoryController.php`
- Create: `app/Http/Requests/Settings/CorrectFynMemoryRequest.php`
- Modify: `routes/api.php` (inside the existing `Route::middleware('auth:sanctum')->prefix('settings')` group at `:1193`)
- Create: `resources/js/services/fynMemoryService.js`, `resources/js/views/Settings/FynMemorySettings.vue`
- Modify: `resources/js/views/Settings.vue` (add the panel next to `PrivacySettings`, under the same privacy section)
- Create: `resources/mobile/views/FynMemorySettings.vue`
- Modify: `resources/mobile/router.js` (route `/settings/fyn-memory`, `meta: { auth: true }`), `resources/mobile/api.js`, `resources/mobile/views/Settings.vue` (link row)
- Modify, for chat corrections: `app/Services/AI/WriteIntentClassifier.php`, `app/Services/AI/AdviceFyn.php` (`WRITE_TOOLS`), the tool schemas `fyn-memory/procedural/tool_schema/capture/forget_memory_fact.md` + `.xai.md` and `correct_memory_fact.md` + `.xai.md`, and the `CoordinatingAgent` dispatch `match` plus handlers.
- Test: `tests/Feature/Api/Settings/FynMemoryControllerTest.php`, `tests/Feature/Fyn/Memory/ChatMemoryCorrectionTest.php`, `resources/js/views/Settings/__tests__/FynMemorySettings.test.js`

**Interfaces:**
- Consumes: the `UserMemoryRepository` methods from Task 2.
- Produces:
  - `GET /api/settings/fyn-memory` returns `{ data: Item[] }` (newest first), where `Item = { id, category, text, trust_label, remembered_on, last_used_on }`
  - `PATCH /api/settings/fyn-memory/{id}/confirm` returns `Item`
  - `PUT /api/settings/fyn-memory/{id}` with body `{ text }` returns `Item`. Returns 422 with the guard message when the new text holds a live value.
  - `DELETE /api/settings/fyn-memory/{id}` returns 204
  - Another user's id returns 404. Preview users are blocked by `PreviewWriteInterceptor`, as for any write.

- [ ] **Step 1: Write the API tests.** Cover: list returns every active fact; confirm; correct (old row superseded, new row confirmed); correct with "£40,000" returns 422; delete; another user's id returns 404 on all three writes; the response exposes no `source_conversation_id` or `source_message_id`.

```php
it('returns 404 for another user\'s memory on every write', function () {
    $owner = User::factory()->create();
    $fact = app(UserMemoryRepository::class)->record($owner, 'risk', MemoryCategory::Preference, 'Cautious.', MemoryTrust::Learned);
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson("/api/settings/fyn-memory/{$fact->id}/confirm")->assertNotFound();
    $this->putJson("/api/settings/fyn-memory/{$fact->id}", ['text' => 'Adventurous.'])->assertNotFound();
    $this->deleteJson("/api/settings/fyn-memory/{$fact->id}")->assertNotFound();
});
```

- [ ] **Step 2: Run and confirm failure** (404 on every route): `./vendor/bin/pest tests/Feature/Api/Settings/FynMemoryControllerTest.php`

- [ ] **Step 3: Implement the controller.** Its methods call the repository with `$request->user()`. Map `ModelNotFoundException` to 404 (Laravel default) and `InvalidArgumentException` to 422 `{ message }`. `CorrectFynMemoryRequest` rules: `['text' => ['required', 'string', 'max:300']]`.

- [ ] **Step 4: Build the web panel.** Load the `data-integrity-traps` skill for the Resource/view mapping, and read `fynlaDesignGuide.md`. The panel sits inside `Settings.vue`, which already wraps `AppLayout`. Two plain sections:
  - **"What Fyn remembers about you"**: one list of every active fact. Each row has the text, the category, "Learned from your conversations" or "Confirmed by you", "Remembered on {date}", and Correct and Delete buttons. Learned rows also have a Confirm button. Confirming is optional; learned facts are already in use.
  - Explanatory copy: "Fyn remembers things you have told it about your plans and preferences. Your figures, accounts and family details are always read from your records, so they are not listed here."
  - Empty state: "Fyn has not remembered anything yet."
  - Correct opens an inline text field. The component emits `save`, and the panel makes the API call (Rule 3).
  - No icons, no scores, palette tokens only, currency not shown. States: loading spinner (global class), error text in `raspberry-*`.

- [ ] **Step 5: Build the `/m` screen** with the same sections and copy, wrapped in `MobileChrome`, using `resources/mobile/api.js`. Add a "What Fyn remembers" row to `/m` Settings.

- [ ] **Step 6: Chat corrections through capture.**
  - Add `forget_memory_fact` (`{ fact_text: string }`) and `correct_memory_fact` (`{ fact_text: string, new_text: string }`) as write tools, in both provider schemas. Add both to `AdviceFyn::WRITE_TOOLS`.
  - `WriteIntentClassifier` treats "forget that", "that's no longer true", "you've got that wrong about me", "stop remembering" and "change what you remember" as write intents, so advice mode routes through `delegate_to_capture`.
  - The `CoordinatingAgent` handlers resolve `fact_text` against `activeFor($user)` by highest sparse overlap. If no single match, the tool returns an error and nothing is changed. Then call `forget`/`correct`.
  - Test: an advice-mode turn "please forget that I want to retire at 60", with scripted clients, results in exactly one handoff and the fact's status becoming `deleted`. A direct `forget_memory_fact` call in advice mode is rejected by `GroundGate` (`status='stripped'`).

- [ ] **Step 7: Run all tests**

```bash
./vendor/bin/pest tests/Feature/Api/Settings/FynMemoryControllerTest.php tests/Feature/Fyn/Memory tests/Feature/Fyn
npx vitest run resources/js/views/Settings/__tests__/FynMemorySettings.test.js
```
Expected: PASS.

- [ ] **Step 8: Browser verification, web and `/m`.** Follow the CLAUDE.md testing rules. Use the `verify-m` skill for `/m`. Log in as `john@example.com` locally. Seed two facts through tinker with `UserMemoryRepository::record`. On web Settings: confirm one, correct one (first try "£40,000" and see the refusal, then a valid correction), delete one. Check the list after each step. Repeat on `/m`. In Fyn chat, say "forget that I prefer short answers" and check the row is `deleted` in the database. Screenshot each state.

- [ ] **Step 9: Commit**

```bash
git add app routes resources fyn-memory/procedural/tool_schema tests
git commit -m "feat(fyn-memory): users can see, confirm, correct and delete what Fyn remembers"
```

---

### Task 7: Embedding client, storage and deploy-time indexing of the global corpus

**Files:**
- Modify: `config/fyn.php` (add the `embeddings` block), `config/services.php` (add `openai.api_key`)
- Create: `database/migrations/2026_09_25_110000_create_fyn_embeddings_table.php`, `app/Models/FynEmbedding.php`
- Create: `app/Services/AI/Memory/Embeddings/EmbeddingClient.php`, `app/Services/AI/Memory/Embeddings/EmbeddingIndex.php`
- Create: `app/Console/Commands/FynEmbeddingsReindex.php`
- Modify: `deploy/DEPLOY.md` (add `php artisan fyn:embeddings:reindex` to the Fyn corpus gate after `fyn:semantic:reindex`)
- Modify: `tests/Pest.php` (the scripted-client hook binds a fake `EmbeddingClient` so no test calls OpenAI)
- Test: `tests/Unit/Services/AI/Memory/Embeddings/EmbeddingIndexTest.php`, `tests/Feature/Console/FynEmbeddingsReindexTest.php`

**Interfaces:**
- Produces:
  - `EmbeddingClient::embed(list<string> $texts): ?list<list<float>>`. Returns `null` on any failure, on a timeout, or when `fyn.embeddings.enabled` is false. Never throws.
  - `EmbeddingIndex::upsert(string $ownerType, string $ownerKey, ?int $userId, string $text): void`. It skips when the `sha256(model|dims|text)` is unchanged. Rows with a non-null `$userId` are refused unless `fyn.embeddings.personal` is true (D2).
  - `EmbeddingIndex::vectorsFor(string $ownerType, ?int $userId, list<string> $ownerKeys): array<string, list<float>>`
  - `EmbeddingIndex::cosine(list<float> $a, list<float> $b): float`
  - `EmbeddingIndex::eraseForUser(int $userId): int`
  - Config: `fyn.embeddings.enabled` (env `FYN_EMBEDDINGS_ENABLED`, default false), `fyn.embeddings.personal` (env `FYN_EMBEDDINGS_PERSONAL`, default false), `fyn.embeddings.model` (`text-embedding-3-small`), `fyn.embeddings.dimensions` (512), `fyn.embeddings.timeout_seconds` (2).

- [ ] **Step 1: Write the failing tests.**
  - `cosine` of identical vectors is 1.0 and of orthogonal vectors is 0.0.
  - `upsert` twice with the same text calls the client once.
  - `upsert` with a `userId` while `personal=false` writes nothing.
  - `vectorsFor` round-trips a 512-float vector exactly (packed `float32`).
  - `eraseForUser` removes only that user's rows.
  - The reindex command, with a fake client returning fixed vectors, writes one row per `house_view` fact and one per root procedure, and a second run makes zero client calls.

- [ ] **Step 2: Run and confirm failure**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/Embeddings tests/Feature/Console/FynEmbeddingsReindexTest.php`

- [ ] **Step 3: Migration and model**

```php
Schema::create('fyn_embeddings', function (Blueprint $table) {
    $table->id();
    $table->string('owner_type', 32);          // semantic | procedure | user_memory | episode
    $table->string('owner_key', 191);          // fact_id, procedure id, user_memory_facts.id, ai_conversations.id
    $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
    $table->string('model', 64);
    $table->unsignedSmallInteger('dimensions');
    $table->char('content_sha256', 64);
    $table->binary('vector');                  // packed little-endian float32
    $table->timestamps();
    $table->unique(['owner_type', 'owner_key', 'model']);
    $table->index(['owner_type', 'user_id']);
});
```

MySQL `binary()` maps to `BLOB`; 512 x 4 bytes = 2 KB per row. Pack with `pack('g*', ...$vector)` and unpack with `array_values(unpack('g*', $blob))`.

- [ ] **Step 4: `EmbeddingClient`**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Embeddings;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI embeddings (xAI has none, research §4.3). Fail-open: any error,
 * timeout or disabled flag returns null and callers fall back to sparse.
 */
class EmbeddingClient
{
    /** @param list<string> $texts @return list<list<float>>|null */
    public function embed(array $texts): ?array
    {
        $key = (string) config('services.openai.api_key');
        if (! config('fyn.embeddings.enabled') || $key === '' || $texts === []) {
            return null;
        }

        try {
            $response = Http::withToken($key)
                ->timeout((int) config('fyn.embeddings.timeout_seconds', 2))
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => config('fyn.embeddings.model'),
                    'dimensions' => (int) config('fyn.embeddings.dimensions'),
                    'input' => array_values($texts),
                ]);

            if (! $response->successful()) {
                Log::warning('[EmbeddingClient] non-2xx', ['status' => $response->status()]);

                return null;
            }

            return array_map(fn (array $row): array => array_map('floatval', $row['embedding']), $response->json('data', []));
        } catch (\Throwable $e) {
            Log::warning('[EmbeddingClient] failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
```

The class is not `final`, so tests can bind a subclass fake. Add `'openai' => ['api_key' => env('OPENAI_API_KEY', '')]` to `config/services.php`. The key is set on each server's `.env` by CSJ; never by an agent.

- [ ] **Step 5: `EmbeddingIndex` and the reindex command.** The command walks `SemanticCorpusLoader::all()` (owner_type `semantic`, key `factId`, text `title + body`) and the root procedures from `FynMemoryStore::procedures()` (owner_type `procedure`). It batches 64 texts per call and prints `embedded N, unchanged M, failed K`. It exits non-zero only when `enabled=true` and every batch failed.

- [ ] **Step 6: Run the tests.** Migrate locally, then `php artisan db:seed`, then:

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/Embeddings tests/Feature/Console/FynEmbeddingsReindexTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add config database/migrations/2026_09_25_110000_create_fyn_embeddings_table.php app/Models/FynEmbedding.php app/Services/AI/Memory/Embeddings app/Console/Commands/FynEmbeddingsReindex.php deploy/DEPLOY.md tests
git commit -m "feat(fyn-recall): OpenAI embeddings stored in MySQL with deploy-time corpus indexing"
```

---

### Task 8: Hybrid recall behind `RecallScorer` and in `SemanticRetriever`

**Files:**
- Create: `app/Services/AI/Memory/Recall/HybridRecallScorer.php`
- Modify: `app/Providers/AppServiceProvider.php:169-174` (bind `RecallScorer` to `HybridRecallScorer` with `$this->app->scoped(...)`, one instance per request, so the query is embedded once per turn and not once per consumer; the comment records the reopened 2026-06-01 deferral)
- Modify: `app/Services/AI/Memory/SemanticRetriever.php` (`retrieve()` blends dense similarity for `semantic` facts; `retrieveForUser()` does so for `user_memory` when personal is on)
- Modify: `app/Services/AI/Memory/UserMemoryRepository.php` (`record`/`correct` upsert an embedding; `forget`/`eraseForUser` delete it)
- Modify: `app/Services/AI/ConversationSummariser.php` (upsert the `episode` embedding after the summary saves)
- Modify: `app/Console/Commands/FynUserErase.php` (`EmbeddingIndex::eraseForUser`)
- Test: `tests/Unit/Services/AI/Memory/Recall/HybridRecallScorerTest.php`, `tests/Feature/Fyn/Memory/DenseRecallTest.php`

**Interfaces:**
- Consumes: `EmbeddingClient::embed`, `EmbeddingIndex::vectorsFor` and `cosine` (Task 7); `SparseRecallScorer` (existing, with `score` from Task 5).
- Produces: `HybridRecallScorer implements RecallScorer`. Each episode array may carry `embedding_key` (the conversation id). Score = `0.4 * sparse_normalised + 0.6 * cosine`; weights come from `fyn.embeddings.weights`. With no query vector or no stored vectors, it returns exactly `SparseRecallScorer::rank()`.

- [ ] **Step 1: Write the failing tests.**
  - With a fake client where "retirement income" and "pension drawdown" vectors are close and "emergency fund" is far, a paraphrased query ("how will I live once I stop working") ranks the drawdown episode first, although it shares no keywords.
  - A failing client (returns `null`) gives results identical to sparse.
  - A client that sleeps past the timeout still returns within about 2 seconds with sparse results. Use a fake that records the call and returns `null`; the timeout itself is covered by `EmbeddingClient`.
  - User A's query never loads user B's vectors (`vectorsFor` is called with A's id).
  - After `forget`, the fact's embedding row is gone.

- [ ] **Step 2: Run and confirm failure**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/Recall/HybridRecallScorerTest.php tests/Feature/Fyn/Memory/DenseRecallTest.php`

- [ ] **Step 3: Implement `HybridRecallScorer`.** Embed the query once per request and memoise it on the instance, because `SemanticRetriever` and `EpisodeRecallService` both ask. Normalise sparse scores by dividing by the maximum in the set. Missing vectors count as cosine 0. Sort is stable, as today.

- [ ] **Step 4: Wire the writes.** Repository `record` and `correct` call `EmbeddingIndex::upsert('user_memory', (string) $fact->id, $user->id, $fact->display_text)`, which is a no-op while personal is off. `forget` deletes that row. The summariser upserts `('episode', (string) $conversation->id, $conversation->user_id, $summary)`. Wrap every embedding write in `try/catch`; memory writes must not fail because of embeddings.

- [ ] **Step 5: Run the full Fyn suites**

Run: `./vendor/bin/pest tests/Feature/Fyn tests/Unit/Services/AI tests/Feature/AI tests/Feature/Console`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app tests
git commit -m "feat(fyn-recall): hybrid sparse and dense recall behind RecallScorer"
```

---

### Task 9: Recall quality evidence and the switch-on gates

**Files:**
- Create: `tests/Eval/Fyn/RecallRelevanceTest.php` (Eval testsuite, not part of the default run)
- Create: `September/September25Updates/fyn-memory-recall-gate.md` (the evidence record CSJ signs off). Use the dated folder of the day this runs.

- [ ] **Step 1: Write the relevance set.** 20 paraphrased questions, each with the `house_view` fact that should come back. Write them against the real corpus in `fyn-memory/semantic/house_view/*.md`, for example "can I move money to my wife to save tax" expecting `savings-to-spouse` or `gia-to-spouse`. Run once with `RecallScorer` bound to `SparseRecallScorer` and once with `HybridRecallScorer` and real OpenAI embeddings. Record top-1 and top-3 hit rates for each.

- [ ] **Step 2: Run on csjones with `FYN_EMBEDDINGS_ENABLED=true`** (global corpus only; CSJ sets the key). Run `php artisan fyn:embeddings:reindex`, then the eval. **Acceptance:** hybrid top-3 is at least sparse top-3 plus 20 points, and median added turn latency is 300 ms or less, measured from `ai_cost_attribution` or turn timing logs across 20 live turns.

- [ ] **Step 3: Browser walk on csjones, web and `/m`.** Ask a paraphrased strategy question and check that the `<knowledge>` block in the stored `assembled_context` holds the expected house view. Ask "what did we talk about last time" and check that `<remembered>` holds the prior conversation. Correct and delete a memory fact, then check that the next turn's context no longer holds the old text.

- [ ] **Step 4: Write the gate record.** It holds the eval numbers, latency, screenshots and the spec section 10 checklist:
  - typed memory: Tasks 2–4
  - user controls on web and `/m`: Task 6
  - erasure and export: Tasks 4 and 8 tests, plus a live `fyn:user:erase` on a csjones test account
  - relevance and contradiction scenarios: Task 9
  - per-user learning: a csjones test user states a preference in one conversation and Fyn uses it unprompted in the next (D1)
  - **CSJ launch decision:** a blank line for CSJ to sign

  Separately, record the personal-embeddings gate: DPIA reference, OpenAI data-residency approval, then `FYN_EMBEDDINGS_PERSONAL=true`.

- [ ] **Step 5: Commit**

```bash
git add tests/Eval/Fyn/RecallRelevanceTest.php September/
git commit -m "test(fyn-recall): relevance eval and switch-on gate record"
```

Nothing is switched on in production by this plan. `FYN_LEARNING_ENABLED`, `FYN_EMBEDDINGS_ENABLED` and `FYN_EMBEDDINGS_PERSONAL` are set by CSJ after reading the Task 9 record.

---

## Release notes for this plan

- **Migrations:** `2026_09_25_100000_create_user_memory_facts_table`, `2026_09_25_110000_create_fyn_embeddings_table`.
- **New deploy step:** `php artisan fyn:embeddings:reindex` after `fyn:semantic:reindex`. It is harmless while embeddings are disabled.
- **New env keys (set by CSJ only):** `OPENAI_API_KEY`, `FYN_EMBEDDINGS_ENABLED`, `FYN_EMBEDDINGS_PERSONAL`.
- **At release:** remove the empty `fyn-memory/episodic/episodes/` and `storage/app/memory/semantic-user/` folders on production and csjones.
