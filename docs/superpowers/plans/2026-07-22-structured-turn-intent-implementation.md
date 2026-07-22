# Structured Turn Intent + Gate Confirmed-Facts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the director/gate text-sniffing seam with structured state — a `turn_intent` enum persisted on every assistant message and a `confirmedFacts` channel into `CaptureAccuracyGate` — and close the CSJ-approved ledger minors (secondary-call 401s, /m hex tokens, gate phrasing gaps).

**Architecture:** Additive metadata first (no behaviour change), then read-side consumers switch one at a time with legacy fallback intact, then the gate gains a default-empty structured-facts parameter populated only from deterministic sources. Spec: `docs/superpowers/specs/2026-07-22-structured-turn-intent-and-gate-facts.md` (CSJ-approved 2026-07-22).

**Tech Stack:** Laravel 10 / PHP 8.2, Pest, existing metadata-column patterns; /m Vue + Vitest for Task 7.

## Global Constraints

- Legacy rows have no `turn_intent`; every consumer keeps its current heuristic as the fallback branch and prefers the enum when present. No backfill.
- Existing boolean flags (`is_resume_greeting`, `is_retry`, `capture_write_failed`, `is_interruption_answer`) keep being written alongside the enum (backward compatibility); no new booleans ever.
- `CaptureAccuracyGate::inspect` signature change is additive (`array $confirmedFacts = []`); text-evidence path unchanged for callers passing nothing.
- Confirmed facts come ONLY from deterministic sources (extractor parses, scripted-step answers, ISA law) — never from LLM output.
- All CLAUDE.md rules bind (ISA law, no hardcoded tax values, Pint, strict types, British copy). csjones deploy + live browser verification per Rule 14/19 close the plan.
- Suite hygiene law from 2026-07-22: every task that touches `CoordinatingAgent`/director signatures greps for test doubles overriding the touched method and runs the FULL suite before commit, not only targeted files.

---

### Task 1: `turn_intent` writer — the enum lands on every assistant persist

**Files:**
- Create: `app/Enums/FynTurnIntent.php` (string-backed enum: `StepPrompt`, `CaptureClarification`, `InterruptionAnswer`, `InterruptionOffer`, `DeferredPromise`, `DeferredRaise`, `ResumeGreeting`, `VerifyPrompt`, `VerifyAck`, `Celebration`, `TerminalNote`, `AdviceAnswer`, `CaptureAck`)
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` — every `saveMessage(...)` call site gains `'turn_intent' => FynTurnIntent::X->value` in its metadata array. Inventory the sites with `grep -n "saveMessage(" app/Services/Onboarding/OnboardingChatDirector.php` (~30 sites; map each to its enum per the spec table; the emitters named in the spec are authoritative). `HasAiChat`'s assistant persist path gains `AdviceAnswer`/`CaptureAck` stamping keyed off the persona + capture-turn state it already tracks.
- Test: `tests/Feature/Onboarding/TurnIntentStampTest.php` (create)

**Interfaces:**
- Produces: `FynTurnIntent` enum; metadata key `turn_intent` (string) on new assistant rows. Tasks 2-4 consume.

- [ ] **Step 1: Failing test** — drive one representative flow per intent family (resume → `resume_greeting`; step emission → `step_prompt`; interruption offer → `interruption_offer`; defer → `deferred_promise`; done-turn raise → `deferred_raise`; celebration → `celebration`) using the existing OnboardingInterruptionTest/OnboardingResumeTest drive idioms, asserting `metadata['turn_intent']` on the persisted rows.
- [ ] **Step 2: RED run** — `./vendor/bin/pest tests/Feature/Onboarding/TurnIntentStampTest.php` fails (key absent).
- [ ] **Step 3: Implement** — enum + stamp every site. No read-side changes.
- [ ] **Step 4: GREEN + full onboarding family** — new file + `tests/Feature/Onboarding/` + `tests/Feature/Fyn/` green.
- [ ] **Step 5: FULL suite** (global constraint) then commit `feat(fyn): persist turn_intent on every assistant message`.

### Task 2: Followup/merge arming reads the enum

**Files:**
- Modify: `OnboardingChatDirector.php` — the clarification-followup detection sites (the scans touched by c038230/d12ac27: the delegated merge, `resolvePendingInterruptionCapture`'s post-capture check, grouped equivalents). Rule: when the scanned assistant row HAS `turn_intent`, arm ONLY on `capture_clarification`; heuristics run solely in the legacy `turn_intent`-absent branch.
- Test: extend `tests/Feature/Onboarding/OnboardingInterruptionTest.php`

**Interfaces:** Consumes Task 1's key. Produces no new surface.

- [ ] Steps: failing test (a stamped `interruption_answer` row followed by an on-script reply → normal state handling, no merge — the conv-168 shape pinned via the enum rather than the tag) → RED → implement → GREEN → full suite → commit `refactor(onboarding): followup arming prefers turn_intent`.

### Task 3: Resume prune + evidence boundary read the enum

**Files:**
- Modify: `OnboardingChatDirector.php` `handleResumeAction` prune — query `metadata->turn_intent = resume_greeting` OR the legacy flag (both, one `orWhere`); `app/Agents/CoordinatingAgent.php::recentUserMessageEvidence` boundary predicate — a row with `turn_intent` is a boundary iff `step_prompt`/`verify_prompt`; legacy branch keeps today's predicate.
- Test: extend `OnboardingResumeTest.php` + a boundary test in `tests/Unit/Services/Onboarding/CaptureAccuracyGateTest.php`'s evidence-window coverage (or the CoordinatingAgent evidence test file if one exists — locate first).

- [ ] Steps: RED (stamped offer/interruption rows must NOT be boundaries; stamped step_prompt must be) → implement → GREEN → full suite → commit `refactor(fyn): evidence boundary and resume prune prefer turn_intent`.

### Task 4: Gate `confirmedFacts`

**Files:**
- Modify: `app/Services/Onboarding/CaptureAccuracyGate.php::inspect(string $tool, array $arguments, string $latestUserText, array $confirmedFacts = [])` — a key present in `$confirmedFacts` satisfies that fact before text evidence (ownership_type, isa_subtype, ownership_percentage, joint_owner_id). ISA writes implicitly carry `ownership_type => individual` (formalising a2e926d — keep the existing implementation, route it through the same array).
- Modify: `CoordinatingAgent.php::executeTool` — additive `?array $confirmedFacts = null` forwarded to the gate (grep test doubles per the hygiene law: `RepetitionCountingAgent` + any in GetRecommendationsCompletenessTest/FynHolisticPlanTierGateTest — update signatures in the same commit).
- Modify: `OnboardingChatDirector.php` — the awaiting-detail resolution passes extractor-parsed ownership/subtype from the detail reply as confirmed facts; scripted parse-state answers (campaign ISA subtype question etc.) pass their parsed fact.
- Test: extend `CaptureAccuracyGateTest.php` (facts satisfy without evidence; facts never override an explicit CONTRADICTING argument — joint+ISA still blocked) + an interruption feature test: the two-step store flow with "Just me" passes via facts with NO evidence-window dependency (the Santander-class shape becomes deterministic).

- [ ] Steps: RED → implement → GREEN → full suite → commit `feat(capture): gate accepts structured confirmed facts`.

### Task 5: Gate phrasing gaps (approved minor)

**Files:** `CaptureAccuracyGate.php::ownershipFromText` — add conservative natural phrasings for non-ISA assets: `owned by me`, `my own`, `in my name`, `on my own` (individual); `in both our names`, `with my wife/husband` (joint — wife/husband already partially covered; verify). Tests: unit rows per phrase incl. negation ("not owned by me" must not match — the existing negation machinery covers it; pin it).

- [ ] Steps: RED → implement → GREEN → full suite → commit `fix(capture): natural ownership phrasings recognised`.

### Task 6: /m secondary-call 401 gaps (approved minor)

**Files:** `resources/mobile/views/modules/Estate.vue` (second apiGet), `NetWorth.vue` + `Goals.vue` (Promise.all secondary responses) — route 401 through the shared `authExpiry` helper. Vitest: extend the existing view specs (Node 20 invocation per fix-m-401-wave-report.md). eslint clean. NO vite build.

- [ ] Steps: RED (mock 401 on the secondary call → redirect) → implement → GREEN → commit `fix(m): secondary data calls re-authenticate on 401`.

### Task 7: /m hex-to-token sweep (approved minor)

**Files:** per the audit catalogue in the 2026-07-22 sweep (ledger): `resources/mobile/style.css`, `views/Login.vue`, `components/GamificationCelebration.vue` (+ `Dashboard.vue:133` SVG stroke → `currentColor` with a class). Exact-token rows use the mapped `var(--…)` tokens from the catalogue table; no-exact-token rows use the NEAREST listed family token — do NOT invent new palette entries; where the catalogue marked "need a decision", pick the nearest and list each substitution in the commit body for CSJ's eye. Confetti palettes in `<script>` switch to importing the token hexes from one shared constants module reading the CSS custom properties at runtime (`getComputedStyle`) or a single JS mirror constant — smallest faithful approach.
- Verification: vitest suite green; eslint clean; visual spot-check on csjones after the plan's deploy (login screen + a level-up celebration + hero) — colours must be visually identical (the tokens ARE these hexes).

- [ ] Steps: implement (mechanical) → tests/lint → commit `style(m): palette tokens replace hardcoded hex (CSJ-approved sweep)`.

### Task 8: Deploy + live verification + close

- [ ] Full suite green (expect only the documented NativeSessionApiTest order-flake).
- [ ] PR → dev (admin-merge per pattern), csjones `git pull origin dev`, config:cache; bundle build + rsync (Tasks 6-7 touch mobile source).
- [ ] Live on csjones (Rule 14): one fresh-user interruption pass (question at a capture step; two-step store with "Just me" — must succeed with zero clarification churn via facts); resume greeting; login-screen + celebration visual check (Task 7).
- [ ] Ledger + report to CSJ.

## Self-Review

- Spec coverage: enum writer (T1), the three read-side consumers the spec names (T2 followup, T3 prune+boundary), gate facts (T4) — matches the spec's rollout order. Approved minors T5-T7. Out-of-scope items (prompt changes, delegated defer ruling, backfill) stay out.
- No placeholders: each task names exact files/methods; T1's site inventory is enumerated by the stated grep with the spec's table as the authority — the implementer maps sites, not invents intents.
- Type consistency: `FynTurnIntent` string-backed; metadata key `turn_intent` everywhere; `inspect(..., array $confirmedFacts = [])` and `executeTool(..., ?array $confirmedFacts = null)` align T4's producer/consumer.
