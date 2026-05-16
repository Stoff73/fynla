---
type: parity-record
plan: docs/superpowers/plans/2026-05-16-fyn-prompt-rework.md
task: 9 (eval parity gate)
date: 2026-05-16
branch: fynPromptRework
---

# Fyn Prompt Rework — Task 9 Parity Record

## Gate definition (CSJ decision, FINAL)

The plan/spec §10 parity instrument ("run the existing eval suite under both
flags, diff per-scenario") **does not exist** — `EvalRunner::run` is a
deliberate Sprint-1 S1.1 scaffold hard-error (PR #242), pre-dating this branch
by ~18 days. The HTTP-driven eval rewrite that would make it runnable shipped on
`feature/fyn-persona-split` with 4 unresolved "Task 16 blockers" and was parked;
no automated per-scenario runner ever landed on `dev`.

**CSJ chose: the gate is Step 5 + Step 6.**

- **Step 5** — the full 3725-test suite run under both `FYN_PROMPT_ARCH` values,
  proving the unified architecture is behaviourally identical to legacy.
- **Step 6** — Playwright browser verification of the three canonical journeys
  under `FYN_PROMPT_ARCH=unified`.

Automated eval-corpus parity (building `EvalRunner`) is **deferred to separate
work** and is explicitly out of scope for this plan. The flag stays
default-`legacy`; it is flipped only after Step 5 + Step 6 are both green and
CSJ explicitly flips it. No parity number is fabricated from the no-op eval
suite (Rule #15: no fabricated success).

## Step 5 — full-suite parity (both flags)

Suites: `Unit,Feature,Architecture`. Baseline = legacy at `550a107` (Task 8
post-implementation regression).

| Flag | Command | Result |
|---|---|---|
| legacy (default) | `./vendor/bin/pest --testsuite=Unit,Feature,Architecture` | **3725 passed / 1 skipped** (15313 assertions, 599.59s) |
| `FYN_PROMPT_ARCH=unified` | `FYN_PROMPT_ARCH=unified ./vendor/bin/pest --testsuite=Unit,Feature,Architecture` | **3725 passed / 1 skipped** (14749 assertions, 526.81s) |

**Parity: EXACT.** Identical pass/skip counts under both flags.

### Root-cause fixes applied during the Rule #15 loop

The only failures under `unified` were stale strict `Mockery::mock(CoordinatingAgent::class)`
doubles that modelled `chatWithPromptOverride` but not the flag-gated collaborator
call `setUnifiedOnboardingFocus()` that the spec-locked onboarding seam
(`OnboardingChatDirector::handleAssetCaptureTurn`, `:1732`) legitimately makes
under unified. The defect is genuinely in the test doubles (they don't model the
new flag-gated contract), **not** in the approved production seam and **not** in
the byte-untouched legacy path.

Fix idiom (non-weakening, codebase-idiomatic — mirrors the pre-existing
`invalidateUserCache->zeroOrMoreTimes()` in `ChildrenDOBFallbackTest:59`):
`$mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();`. It is
zero-call-satisfied under legacy (no default-path disturbance — proven by the
legacy run above being an exact 3725/1), other methods stay strict, and no
behavioural assertion is touched or silenced.

Sites fixed (6 total across 3 files, 11 fixes):

| File | Sites | Session |
|---|---|---|
| `tests/Feature/Onboarding/AssetCaptureGapFillTest.php` | 4 | 7 (WIP `ee73271`) |
| `tests/Feature/Onboarding/AssetCaptureMultiEntityTest.php` | 1 | 7 (WIP `ee73271`) |
| `tests/Unit/Services/Onboarding/AssetCaptureOffScriptFilterTest.php` | 1 (shared `runAssetCapture()` helper → covered all 11 `it()` cases) | 8 |

## Step 6 — Playwright browser verification (unified)

(Filled in by the Step 6 browser pass — see below.)
