---
type: handover
mode: context-clear
date: 2026-06-29
session: 2
branch: savetax-income-only (worktree fynla-m-funnel, off dev)
---

# Context Clear Handover — 2026-06-29, Session 2

## Immediate state
Mid-way through running the **`/m` onboarding asset-gating matrix** on csjones. Five
fresh verified test users were created (one per funnel-asset combination) with
Sanctum tokens, to walk each combination on `/m` via token injection and confirm
**Fyn only asks about the assets the user ticked**. CSJ stopped the walk to test the
matrix themselves. Branch `savetax-income-only` is clean, all pushed, on **PR #581**;
prod UNTOUCHED.

## The thread
This whole session was the SaveTax campaign onboarding overhaul on `savetax-income-only`
(19 commits, PR #581). It began as the "income/funnel cross-check" feature (full
brainstorm → spec → plan in `docs/superpowers/`), then became a cascade of CSJ-directed
fixes as CSJ walked the `/m` flow and found issues. Everything below is on PR #581,
deployed to csjones, NOT merged to dev, NOT on prod.

## What shipped (all on PR #581)
1. **Welcome recap** — greet + point-form bullets that **echo the actual funnel answers**
   (e.g. "Earning £50,271 to £100,000", NOT "A higher-rate taxpayer"); income-only work
   step (employer/role dropped; `handleCaptureWorkDetails` requires only income).
2. **Income/funnel cross-check** — `FunnelIncomeBand` helper + `detectIncomeFunnelMismatch`
   /`maybeChallengeIncome` in `OnboardingChatDirector`; challenges when chat income
   contradicts the funnel band (user **and** spouse), Continue/Change bubbles.
3. **Web SPA two-bubble split** — `aiChat.js` flushes on `onboarding_advance`; **tightened
   web bullet lists** (`AiMessageContent`).
4. **Announce + Okay gate** before the verify-navigate (`campaign_verify_announce`) so Fyn
   states the transition and waits for Okay before navigating.
5. **On-page Continue/Edit pills** on the verify screens (`MobileChrome.vue`) — replace the
   nudge banner, hide the top-left "Edit details"; **session maintained** (verifyAnswer
   resumes the conversation before sending — was starting a new convo).
6. **Section-aware income heading** — "Your income" / "Your spouse's income" via a
   navigation `section` field → `?section=` query → `Income.vue`.
7. **Asset gating (the big one)** — Fyn only asks about ticked funnel assets:
   `STATE_CAMPAIGN_ISA_HOLDINGS` `skip_if=skipIfNoIsa`; `STATE_CAMPAIGN_BANK_ACCOUNTS`
   `skip_if=skipIfNoBankOrSavings`; pension questions skipped via `nextFromCampaignDob`
   (DOB still captured) when "pension" not ticked. Removed "outside an ISA" and the
   invented "premium bonds" from prompts. `skip_if` is corpus-exempt; the DOB branch +
   prompt copy are mirrored in `fyn-memory/.../fyn-onboarding.v1.md`.

## What the next Claude needs to know
- **Gating logic is unit-tested across combinations** (CampaignSectionFlowTest: ISA-only→ISA
  Q, bank-only→bank Q, no-pension→pension skipped; golden master green). The **`/m`
  browser matrix across all combinations is NOT yet verified** — CSJ is doing that now.
- **Recurring lesson (important):** re-walking the SAME test conversation pollutes its
  transcript with pre-fix cruft, so CSJ kept seeing stale "ISA"/"premium bonds" lines and
  doubting fixes. **Use a FRESH user/conversation per verification** (that's why the matrix
  uses 5 fresh users).
- **Lean test cadence (CSJ direction this session):** test the change + browser-verify only;
  run the FULL suite ONLY at PR-merge. Do not run the full suite per change.
- Deployed-prompt checks via `OnboardingStateMachine::getState('...')['prompt_text']` over
  SSH tinker are the fastest definitive confirmation a prompt change is live.

## Files touched (committed + pushed; nothing uncommitted)
`OnboardingStateMachine.php`, `OnboardingChatDirector.php`, `CoordinatingAgent.php`,
`FunnelIncomeBand.php` (new), `fyn-onboarding.v1.md` (corpus), `aiChat.js`,
`AiMessageContent.vue`, `MobileChrome.vue`, `Income.vue`, `dashboard.css`,
`fynText.js` + onboarding tests. Specs/plan under `docs/superpowers/`.

## Pick up from here
1. **Await CSJ's `/m` matrix findings.** If any combination over-asks (asks about an
   unticked asset) or under-asks (skips a ticked one), fix the gate: `skip_if` predicates
   (`skipIfNoIsa`/`skipIfNoBankOrSavings`) and `nextFromCampaignDob` in
   `OnboardingStateMachine.php`. Re-run `CampaignSectionFlowTest` + golden master.
2. The 5 matrix users exist on csjones with tokens (see below) for continued `/m` walks via
   localStorage `m_scaffold_token` injection + `/m/app/dashboard`.

## Known gaps / flags (not bugs in scope, but raised)
- **Property** is a funnel choice with **no capture section** — selecting property → Fyn
  never asks about it (under-ask). Flagged to CSJ; not built (needs a new section). CSJ to
  decide.
- **Spouse questions still mention ISA** ("does your spouse have ISAs…") — left intentionally
  (open-ended probe of the *spouse's* holdings, independent of the user's funnel choices).
  CSJ may want these stripped too — ask.

## Cleanup / housekeeping
- **csjones is checked out on `savetax-income-only`** (per the deploy gate). After PR #581
  merges, return it: `git checkout dev && git pull origin dev` + rebuild/upload dev assets.
- **Test data on csjones:** users `incometest0629a`, `noisatest0629`, and
  `matrix-isa/banksav/inv/pension/all@example.com` (+ Sanctum tokens). Harmless; delete when
  done.
- **Scattered screenshots** in the repo root (`m-*.png`, `fyn-*.png`, `dash-overlay.png`,
  `noah-*.png`) and `.playwright-mcp/` — verify gitignored; tidy.

## Context hints
- Branch type: feature (`savetax-income-only` off dev); main dir on `main`, work in worktree
  `fynla-m-funnel`. `fynla-coala` worktree separate — keep.
- PR #581 OPEN → dev, 19 commits, not merged. Prod untouched.
- Uncommitted: none, working tree clean.
- Last commit: `921e88d` fix(onboarding): drop invented 'premium bonds' from the savings question.
- csjones SSH: `~/.ssh/fynlaDev`, `u163-ptanegf9edny@ssh.csjones.co:18765`, root
  `~/www/csjones.co/fynla-app`. NOT the ssh-fynla MCP (prod).
