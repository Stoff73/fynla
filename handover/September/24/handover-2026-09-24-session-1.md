---
type: handover
mode: session-end
date: 2026-09-24
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-09-24, Session 1

## Where things stand

- **Research.** CSJ asked for research on Neo4j and vector databases as Fyn's knowledge and memory layer. It is written and verified at `September/September24Updates/neo4j-graph-vector-architecture-research.md`: every codebase claim is checked against the code, production and csjones, and every external claim against its source.
- **What the check found.**
  - Fyn's per-user memory, as built in June (CoALA Phase 6), does **not** match the agreed design: markdown fact files, figures copied into memory, and an admin approval queue.
  - The July replacement plan (Tasks 22F–22H) was never built.
  - A test leaked fake "cycle N learn" episodes to production. That is fixed (PR #937, merged into `dev`) and the files are deleted.
- **Learning is OFF on production** (switched on at 08:35 on CSJ's instruction, off again at 16:41 once the spec's gate was found; nothing staged).
- **The implementation plan for typed per-user memory plus dense recall is written and not started.** It is at `docs/superpowers/plans/2026-09-24-fyn-typed-memory-and-dense-recall.md`, 9 tasks.

## Priorities for the next session

CSJ, session end: **"make sure we start with implementation of the plans tomorrow."**

1. **Start Task 1 immediately, INLINE.** CSJ ruling, 19:37: "the implementation plan is done inline, not sub-agent driven." Execute it yourself in the session with the `superpowers:executing-plans` skill; do not dispatch subagents to implement tasks. No question needs asking before Task 1.
   - D5 (OpenAI `text-embedding-3-small` at 512 dimensions) needs CSJ's confirmation before Task 7 only.
   - Load `fyn-architecture`, `data-integrity-traps` and `test-failure-forensics` before coding, and `vault-context` for the Fyn/AI module if dispatching agents (CLAUDE.md).
2. **Tasks 1–6: memory.** `MemoryFactGuard`, then `user_memory_facts` and the repository, then per-user learning writing memory (active immediately), then recall and erase on typed memory, then conversation summaries as the one episodic recall path, then the settings memory screen on web and `/m` plus "forget that" in chat.
   - Branch off `dev`, PR to `dev`, admin-merge, and put csjones back on `dev` afterwards.
   - Task 6 needs browser verification on web and `/m` (the `verify-m` skill).
3. **Tasks 7–9: dense recall.** Embeddings stored in MySQL, then hybrid `RecallScorer`, then the relevance eval and gate record. Task 9 needs `OPENAI_API_KEY` on csjones, set by CSJ only.
4. **After release of Tasks 1–6:** CSJ switches `FYN_LEARNING_ENABLED=true` on production.
5. Parked by CSJ until Option 5 proves itself: Neo4j/Aura and which tier. Also the three optional 2026-09-22 decisions in `CSJTODO.md`.

## Context to load

- `docs/superpowers/plans/2026-09-24-fyn-typed-memory-and-dense-recall.md`: **the plan to execute.** Decisions D1–D6 at the top are settled; do not re-ask them.
- `docs/superpowers/specs/2026-07-10-fyn-evidence-first-advice-design.md` sections 7, 8 and 10: the spec. CSJ's 2026-09-24 ruling is at the top of sections 7 and 10 and **supersedes** the "never auto-apply" text.
- `codex/plans/programme/fynla-coala-implementation-plan.md:35-44`: the v0.5 pointer rule (memory holds pointers, never copies), which is the reason `MemoryFactGuard` exists.
- `September/September24Updates/neo4j-graph-vector-architecture-research.md` sections 5 and 12: the verified current state of memory and CSJ's answers.
- `docs/tech-debt-report.md`: today's audit. Three fixes were already folded into the plan text.

## Completed this session

- Research report (about 10k words) with verification logs: section 15 (code and servers) and section 16 (external sources).
- **PR #937** (`64374d914`, merged as `67ca3793f`): the `tests/Pest.php` global hook points `fyn.memory.episodic_path` and `user_semantic_path` at a per-test temp dir. `PestHooksLivenessTest` pins it. `deploy/DEPLOY.md` step 6 and the release skill now rsync with `--exclude 'fyn-memory/episodic/episodes/'`.
- Deleted test-output episodes: production 432 files, local 414 (csjones had none).
- Production `.env`: `FYN_LEARNING_ENABLED=false`, set explicitly (backup `.env.bak-2026-09-24-learning`), `config:cache` rerun, DB user checked (not `forge`).
- Plan written, then corrected twice by CSJ: per-user auto-apply, and no household copy.
- July spec annotated with CSJ's ruling. `CSJTODO.md` and memory updated.

## Verification state

- PR #937: without the hook, `FailureContextTest` wrote 4 files into the real folder; with it, 0. The suites touching memory paths: 759 passed, 3 skipped. The full suite was **not** run (tests and docs change only).
- Production: `config('fyn.learning_enabled') === false`; `proposed_semantic_facts` count 0; episodes folder empty.
- Not verified: nothing in the plan is built. Neo4j's `SEARCH ... WHERE` pre-filter or post-filter behaviour is unconfirmed in the docs. Voyage and SiteGround Postgres/pgvector details are unconfirmed (both moot for Option 5).

## Decisions and dead ends

- **CSJ rulings (do not re-litigate):**
  - Memory and learning are **per user**, and learned facts **apply automatically**, with no approval queue. The only block is no live values in memory (the pointer rule).
  - Review is only for global procedural and regulatory content.
  - **No household or user-data projection** into any vector or graph store; always look up live.
  - **Option 5 first** (dense recall in MySQL), Neo4j parked.
  - Production is SiteGround **shared** hosting.
  - Embeddings: probably OpenAI.
- **Where the "never auto-apply" rule came from:** the June Phase 6 and July evidence-first specs (AI-written) stretched the CoALA plan's narrow "never autonomous for *regulatory* content" (plan lines 68 and 810) to per-user facts. CSJ never agreed that. Cite `feedback_fyn_memory_per_user_auto_applies` if any doc says otherwise.
- **Option 2 ("vectorise everything") rejected:** similarity search can't answer "all" or "exactly"; filtering gaps leak data across tenants; it creates two sources of truth; it adds invertible personal copies.
- **Neo4j cannot run on SiteGround.** Bolt 7687 is blocked, so it could only be reached as Aura over the HTTPS Query API. The Query API returns 202 even on errors. `laudis/neo4j-php-client` dropped HTTP in 3.3.0. MySQL Community has no `DISTANCE()`. xAI has no embeddings.
- **No legacy-memory migration command.** The per-user markdown fact store holds 0 files on production, csjones and local.
- **Mistake to avoid repeating:** the first report pasted a subagent's code map unchecked, and learning was switched on without reading spec section 10. Verify every codebase claim yourself against code and servers (memory `feedback_subagent_accountability`).

## Things that will bite you

- `ProposedFactSynthesiser` ignores its own "no figures" prompt: 101 of the 206 csjones facts carry amounts. That is why Task 1's code guard comes first.
- csjones still has `FYN_LEARNING_ENABLED=true` and 206 pending rows in `proposed_semantic_facts`; Task 3 retires that path.
- Test output in `fyn-memory/episodic/episodes` came from `FailureContextTest`. PR #937's hook covers the Feature and Unit/Services directories only. New memory tests must live in bound directories (`tests/CLAUDE.md`).
- Task 5 deletes `fyn-memory/episodic/episodes` and the `episodic_path` config. At that point remove the Pest hook line and the rsync exclude note (both covered in Task 5, Step 5).
- The auto-mode classifier denied one harmless local `grep` after production writes. If that happens, the Read or Grep tools work.

## Tech debt deferred

- `tests/Pest.php:111`: `fyn-test-memory-*` temp dirs are never cleaned up. They disappear when Tasks 4 and 5 remove the path keys.
- The `DEPLOY.md` step 6 rsync exclude becomes obsolete in Task 5.

## Branch and deploy state

- Branch: `dev` at `67ca3793f` (up to date with origin). Unpushed commits: none before this handover commit.
- Production: main `df14df1e2` (#937 is tests and docs only, no release needed). `.env` learning false.
- csjones: `dev` `67ca3793f`.
- Uncommitted files **not from this session**, left untouched: the `docs/diagrams/*.excalidraw` pair, `workforce/ops/log/*`, `brettTest/`, `chrisMapping/`, `September/September22Updates/`, the workforce briefs, `handover/September/15/…`.
