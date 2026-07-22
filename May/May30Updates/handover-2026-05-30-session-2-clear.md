---
type: handover
mode: context-clear
date: 2026-05-30
session: 2
branch: feat/coala-fynloop
---

# Context Clear Handover — 2026-05-30, Session 2

## Immediate state

Just committed the CoALA Phase 5 **stream-mock harness** (`aa92fca` on
`feat/coala-fynloop`). It's built, proven (2 tests), and it **immediately caught
a shipped fatal bug in item 3** (stripped import) which is now fixed. The next
substantive piece — the **`FynLoop` extraction** (route `AdviceFyn` through the
shared loop, Option B) — is unblocked but NOT started. CSJ was asked whether to
push on with the extraction now or pause to review; the clear happened here.

## The thread

This session (a continuation after the session-1 EOD handover) did four things,
in order:
1. Diagnosed + fixed the `ActuarialLifeTableAdminTest` flake — root cause was
   `factory()->count(3)` colliding on `UNIQUE(age,gender,table_year)` ~1.53%
   (birthday paradox, NOT order-dependent). Fixed with `->sequence()` of 3
   distinct ages. On `fix/coala-test-stabilisation` (`7b07b1b`).
2. Built **CoALA Phase 5 item 3** — the typed `Action` enum + dispatcher
   (`app/Services/AI/Actions/`: `ActionType`, `Action`, `ToolActionMapper`,
   `SurfaceAllowlist`, `ActionDispatcher`) + rewired the `HasAiChat:577` seam
   from the bare `GroundGate` check to a typed-action dispatch. On
   `feat/coala-action-enum` (`28d5ebe`). 26 tests; SurfaceAllowlist is
   byte-parity with GroundGate.
3. Triaged + fixed the pre-existing `ProtectionWorkflowTest` (4 stale assertions:
   readiness-gate needs user-level income/dob/marital; tax-config memo needs
   `forgetInstance`; `adequacy_score` is now an insights array; CI policy
   soft-deletes). On `fix/coala-test-stabilisation` (`f7c8081`). Integration 30/0.
4. Started **item 4 (`FynLoop`, Option B approved by CSJ)**: `SessionMode` enum
   (`2b68b3c`) + the **stream-mock harness** (`aa92fca`) — CSJ chose "build the
   harness first" and it paid off immediately (see below).

## Files touched (all committed; nothing uncommitted)

- `app/Services/AI/Actions/{ActionType,Action,ToolActionMapper,SurfaceAllowlist,ActionDispatcher}.php` (item 3, new)
- `app/Services/AI/Loop/SessionMode.php` (item 4, new)
- `app/Traits/HasAiChat.php` (seam rewire + the **import fix**)
- `tests/Support/Fyn/{FynStreamHarness,ScriptedAnthropicClient}.php` (harness, new)
- `tests/Feature/Fyn/FynStreamHarnessTest.php` (2 proof tests, new)
- `tests/Unit/Services/AI/Actions/*`, `tests/Unit/Services/AI/Loop/SessionModeTest.php` (new)
- `tests/Integration/ProtectionWorkflowTest.php` (modernised)
- `tests/Architecture/ApplicationArchitectureTest.php` (ActionType + SessionMode added to `toBeClasses()` ignore list)

## What the next Claude needs to know

- **CRITICAL bug class — Pint strips unused imports on PostToolUse.** When you
  add a `use` import and its usage in SEPARATE edits, Pint runs after the first
  edit, sees the import unused, and strips it. This shipped a fatal in item 3
  (the `ActionDispatcher` import vanished → live seam resolved to non-existent
  `App\Traits\ActionDispatcher` → would have fatalled on every advice-mode tool
  call). It was invisible to unit tests (none drove the live seam). **Always
  `grep -c "use …;"` after adding an import, or add the usage first.** Now fixed
  (amended into item 3 `28d5ebe`) and guarded by the harness gate-e2e test.
- **CoALA PRs target `coala`, not `dev`** (standing CSJ decision).
- **Option B is approved** for the Two-Fyn collapse (shared `FynLoop` + thin
  shells; AdviceFyn & OnboardingChatDirector both delegate; the `00-canonical.md`
  contract is preserved verbatim). Do NOT propose Option A.
- **Production AI provider is xAI**, not Anthropic. The harness's `bind()`
  forces anthropic so the loop consumes its scripted stream — the loop's
  downstream logic (dispatch/gate/persistence) is provider-agnostic, so this is
  faithful. An xAI variant of the harness is a future nicety, not needed yet.
- **Nothing is pushed this session** (CSJ steer). 4 local commits across 3
  branches — see branch tips below. For a context-clear on the same machine the
  loss-risk is low; push when ready with `git push -u origin <branch>` per branch.
- **`feat/coala-action-enum` was amended** (`81088c2` → `28d5ebe`) to add the
  import fix. It was never pushed, so no force-push concern. `feat/coala-fynloop`
  was rebased onto the amended action-enum (clean, no conflict).

## Branch tips (all local; pushed-state noted)

- `fix/coala-test-stabilisation` `f7c8081` — **+2 ahead of origin** (actuarial + protection fixes). Pushed base exists.
- `feat/coala-cost-telemetry` `d390f67` — pushed (item 1).
- `feat/coala-ground-gate` `d0e50cd` — pushed (item 2 + docs).
- `feat/coala-action-enum` `28d5ebe` — **NOT pushed** (item 3, amended).
- `feat/coala-fynloop` `aa92fca` — **NOT pushed** (item 4: SessionMode + harness). Stacked on action-enum.
- `coala` `0124749` — integration branch, untouched.

## Pick up from here

The harness is ready, so the FynLoop extraction is now TDD-able. Next action,
if continuing item 4:
1. On `feat/coala-fynloop`, read `app/Services/AI/AdviceFyn.php::handle` (201-365)
   + `wrapStream` (381-520) — that's the advice-mode per-turn flow to extract.
2. Create `app/Services/AI/Loop/FynLoop.php` encapsulating the shared
   orchestration (pre-planner bypasses + the `chatWithPromptOverride`/`wrapStream`
   streaming), keyed on `SessionMode`. **No planner** (that's item 5).
3. Route `AdviceFyn::handle` through `FynLoop` first (Option B shell), preserving
   behaviour. TDD against `tests/Feature/Fyn/FynStreamHarnessTest` patterns;
   full sign-off needs the Fyn eval suite + browser (35 invariants, 75 golden
   conversations, `09-canonical-behaviour`).
4. Then route `OnboardingChatDirector::handleUserMessage` (next increment).

Alternatively (if CSJ said pause): open the now-5 feature→coala PRs for review,
or fix the deferred `config/ai_pricing.php` placeholder rates.

Verify any resumed work with: `./vendor/bin/pest tests/Unit/Services/AI/Actions/
tests/Unit/Services/AI/Loop/ tests/Feature/Fyn/FynStreamHarnessTest.php
tests/Unit/Services/AI/GroundGateTest.php` (42 green at clear).
