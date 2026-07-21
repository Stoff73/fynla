---
type: handover
mode: end-of-day
date: 2026-05-18
session: 1
branch: fynPromptRework
previous_session: 2026-05-17 session 4 (context-clear)
---

# Handover — 2026-05-18, Session 1

## Where we left off

End-of-day 2026-05-17. The Fyn unified-prompt cutover + `handleInlineCapture` unified-seam fix (session-3 WIP `9c19dcc`) is **fully verified GREEN** — all three browser journeys re-walked under unified, full Pest suite green under the new unified default (3771/25, exit 0). Code is committed + pushed on `fynPromptRework`, tree clean. Two things remain before this can land: the legacy rollback sanity run, and squash + a NEW PR to `dev`. EOD `/session-end` was run at extreme context pressure (~212k) so vault-sync was deliberately deferred (see Known issues).

## What shipped today (2026-05-17, across sessions 1–4)

- Sessions 1–2: Fyn prompt-rework plan completed; PR #332 opened then code-reviewed (all 5 findings fixed side-by-side legacy+unified), admin-merged to `dev` (`8d1ba2b`) — PRE-cutover state.
- Session 3: `config/fyn.php` default flipped `legacy → unified` (CSJ-ordered cutover). Root-caused journey-(b) write-intent failure → `OnboardingChatDirector::handleInlineCapture` never wired into the unified seam; fix applied (derive focus via `inferFocusesFromEntityTypes`, `setUnifiedOnboardingFocus` + try/finally). Strict CoordinatingAgent mocks fixed with sanctioned `->zeroOrMoreTimes()` idiom. WIP `9c19dcc` pushed. Decisive browser re-verification left un-run (tripwire).
- Session 4 (this one — **zero code changes**, verification only):
  - **Journey (b) GREEN** (browser+DB): Barclays £3k write via Fyn chat → SavingsAccount #260 created (is_isa=0, easy_access, 4.2%), no security-clause-6 refusal. The session-3 fix works end-to-end live.
  - **Journey (a) GREEN** (browser+DB): advice read-only — accurate "no pensions recorded", hedged, FCA signpost, zero DB writes.
  - **Journey (c) GREEN (scoped)**: fresh registration (uid 110) → Fyn onboarding bubble flow under unified; multi-fact one-turn capture DB-verified (DOB+marital in one turn). Deeper multi-savings assertion covered by deterministic suite.
  - **Targeted Fyn+Onboarding suite: 179 passed / 1 skipped** under unified.
  - **Full Pest suite under NEW unified default: 3771 passed / 25 skipped, exit 0, 388s.** Zero failures.
  - Commits: `dfb74e7` (session-4-clear handover) only — no code.

## What's in flight (NOT done)

- **Legacy rollback sanity run NOT done:** `FYN_PROMPT_ARCH=legacy ./vendor/bin/pest --compact` — must confirm the rollback path is still green post-cutover. Expect green (session-3 parity was 3726/1). Apply the sanctioned `->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();` idiom to any strict-mock failures; loop per Rule #15.
- **Squash + new PR NOT done:** squash `9c19dcc` → `fix(fyn): wire handleInlineCapture into unified seam + cutover to unified default`; open NEW PR `fynPromptRework → dev` (PR #332 already merged — do NOT reopen). No self-approve.
- **vault-sync NOT run + canonical-spec carry STILL overdue (now 4 sessions):** `April/April24Updates/spec/00-canonical.md` was rewritten on disk; `/April/` is gitignored so it is NOT in git and will be lost on the next `/April/`-tree change. This is the single highest-risk outstanding item.

## Deploy status

**Ready but NOT deployed.** No csjones/prod deploy. The cutover + handleInlineCapture fix (`9c19dcc`) is verified-green locally but not in `dev` (PR #332 merged only the pre-cutover state). No new deploy note written this session — no new code shipped in session 4; the deployable unit is the pending squashed PR.

## Tech debt found this session

None — session 4 changed zero code files, so `tech-debt-session` was correctly skipped per the skill rule. Session-3's 5-file diff still has a deferred tech-debt-session audit (carried since session 2, low risk).

## Known issues / blockers

- **vault-sync skipped under context exhaustion (~212k/200k).** Invoking the heavy vault-sync skill at >97.5% budget would likely blow the hard limit mid-run and corrupt the sync. Deliberately deferred whole, not partially run. **Next session-end (or a fresh-context session-start) must run vault-sync FIRST and carry `April/April24Updates/spec/00-canonical.md` into the vault.** This is overdue 4 sessions and is the top priority.
- No functional blockers. App is green. Test-data pollution (uid 11 #260 Barclays, uid 73 #189/#190, uid 110 mid-onboarding) is harmless + `updateOrCreate`-safe — do NOT `migrate:fresh`.

## Rules reinforced this session

- No new memory files written. Reinforced existing: Rule #15 loop-until-correct (journey-b looped to GREEN with DB evidence), browser-testing law (clicked/filled/submitted/DB-verified, no overclaim on journey-c's un-walked asset bubble), `feedback_never_claim_verified` (honest scoping of journey c).

## Next session should

1. **First: run vault-sync** (`Skill: vault-sync`) and confirm `April/April24Updates/spec/00-canonical.md` lands in the vault. Highest priority — 4 sessions overdue, data-loss risk.
2. `FYN_PROMPT_ARCH=legacy ./vendor/bin/pest --compact` — legacy rollback sanity. Fix strict-mock failures with the sanctioned `->zeroOrMoreTimes()` idiom. Loop per Rule #15 until green.
3. Squash `9c19dcc` and open NEW PR `fynPromptRework → dev` (do NOT reopen #332; do NOT self-approve).
4. Then run `tech-debt-session` on session-3's cumulative diff (5 files: `config/fyn.php`, `app/Services/Onboarding/OnboardingChatDirector.php`, 3 test files) — deferred since session 2, low risk.

## Context hints

- Active branch type: mainline feature (`fynPromptRework`)
- Behind origin/main by: N/A (feature branch; relative to `dev`, the cutover commits are NOT yet in dev)
- Behind/ahead origin/fynPromptRework: 0 / 0 — clean, pushed
- Uncommitted: none, working tree clean
- Last commit: `dfb74e7` docs(session): context-handover 2026-05-17-session-4
- Authoritative code commit: `9c19dcc` (session-3 WIP — cutover + handleInlineCapture fix; needs squash)
- Dev servers were running (:8000 php, :5173 vite). Do NOT `pkill -f vite`. Config uncached — code/config live without restart.
- New-registration verification code lives in `pending_registrations.verification_code` (user row absent until verified), not `email_verification_codes`.
