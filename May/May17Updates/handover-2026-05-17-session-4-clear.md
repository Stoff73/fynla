---
type: handover
mode: context-clear
date: 2026-05-17
session: 4
branch: fynPromptRework
trigger: context-handover skill (context tripwire @ ~192k)
---

# Context Clear Handover — 2026-05-17, Session 4

## Immediate state

**Rule #15 loop on the session-3 unified-seam fix is GREEN.** Journey (b) browser-re-verified end-to-end under unified (the decisive test session 3 left un-run), journey (a) re-walked GREEN, journey (c) regression-verified (scoped), and the full Pest suite passed under the new unified default (3771 passed / 25 skipped, exit 0). This session made **zero code changes** — pure verification on top of session-3's WIP `9c19dcc`. Tree clean, branch 0/0 with origin.

## The thread

- Session-start auto-continued from session-3 handover's "Pick up from here": resume the Rule #15 loop on journey (b).
- Baseline established: uid 11 (john@example.com) had exactly 1 SavingsAccount (#165 Nationwide Cash ISA £5k). `config('fyn.prompt_architecture')` resolves `unified` — cutover confirmed live.
- **Journey (b) — GREEN (browser + DB).** Logged in as John via Playwright (session persisted, no MFA needed), sent "Add a savings account with Barclays, £3,000, easy access, 4.2% interest" into Fyn chat. Fyn: short ack "Got it — recording that now. Recorded your Barclays savings account…" + "Saved to your records" card. **No security-clause-6 refusal.** DB confirmed: new row **260** (Barclays Savings Account, £3000, `is_isa=0`, `easy_access`, rate 4.2, ts 13:42:33), count 1→2. The session-3 `handleInlineCapture` unified-seam fix works end-to-end in the live browser.
- **Journey (a) — GREEN (browser + DB).** Asked "Do I have any pensions, and how much should I be saving for retirement?" → accurate "you do not currently have any pensions recorded", hedged (offered analysis, no fabricated figures), FCA signpost present. Zero DB writes (savings stayed 2; DB/DC/State pensions all 0).
- **Journey (c) — GREEN (scoped, honest).** uid 73 (`unified.tester@example.com`) is an HTTP/eval fixture (no known pw, unverified email) — not browser-loggable. Instead registered a FRESH user (uid 110, journeyc.unified@example.com / Password1!) through the real flow, drove the **Fyn onboarding bubble flow** via `/dashboard?openFyn=journey` (path_choice → Follow a journey → Building Foundations → STATE_BASE_PERSONAL). Multi-fact one-turn capture **DB-verified**: "I was born on 12 March 1990 and I'm single" → uid 110 got `date_of_birth=1990-03-12` AND `marital_status=single` in a single turn under unified. State machine advanced/persisted across nav (`fyn_step` path_choice→base_personal→profile_review_family). Deeper "two SavingsAccount rows in one onboarding turn" assertion delegated to the deterministic Feature suite (see below) — I did NOT browser-walk to the asset-capture bubble (settings-review sub-nav at profile_review_family routes to /settings/personal; not worth fighting given deterministic coverage exists). No overclaim.
- Dispatch grounded in code (not speculation): `AiChatController:171` — `$inOnboarding = onboarding_completed===false && onboarding_fyn_step!==null && config('onboarding.fyn_flow_enabled')`. uid 11 has `fyn_step=null` → routes to AdviceFyn (confirms (a)/(b) were genuinely advice-mode). `handleInlineCapture` is advice→capture handoff ONLY — NOT on the onboarding bubble path — so journey (c)'s only exposure is the global config cutover, now positively shown working on the onboarding path.
- **Targeted suite: Fyn + Onboarding = 179 passed / 1 skipped** under unified default — deterministically covers journey (c)'s deeper multi-entity onboarding capture assertion.
- **Full suite under NEW unified default: 3771 passed / 25 skipped, exit 0, 388s.** Zero failures. The session-3 sanctioned mock idiom (`->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes()`) held; cutover is suite-clean.

## Files touched this session

**None.** Zero code changes. Verification-only session. The only artefacts are Playwright snapshot files under `.playwright-mcp/` (gitignored) and test-data pollution (harmless, `updateOrCreate`-safe — see below). Code state is unchanged from session-3 WIP `9c19dcc`.

## WIP commit

- No new WIP commit this session — **tree was clean** (no code changes to snapshot). Phase 3 of context-handover is conditional on uncommitted changes; there were none.
- The authoritative code commit remains session-3's **`9c19dcc`** (cutover + handleInlineCapture unified-seam fix). Pushed: yes. Still needs squash + a NEW PR (PR #332 already merged the PRE-cutover state).

## Open decisions

- None blocking. CSJ already ordered the cutover (unified = default). Direction of travel: finish Task 3's remaining legacy-sanity run, then Task 4 (squash + new PR). Default for next session: proceed with both.

## Pick up from here (auto-continue contract)

1. **Legacy rollback sanity (Task 3 remainder, NOT yet run):** `FYN_PROMPT_ARCH=legacy ./vendor/bin/pest --compact`. Expect green (parity was 3726/1 pre-cutover in session 3; full unified is now 3771/25 — legacy should be comparably green). If any strict `CoordinatingAgent` mock fails, apply the SAME sanctioned idiom `->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();` (precedent: `tests/Feature/Onboarding/AssetCaptureGapFillTest.php`). Loop per Rule #15 until green.
2. **Task 4 — squash + new PR.** Squash session-3 WIP `9c19dcc` into a proper commit: `fix(fyn): wire handleInlineCapture into unified seam + cutover to unified default`. Open a NEW PR `fynPromptRework → dev` (PR #332 already merged `8d1ba2b` — do NOT reopen it; this is a follow-up PR for the cutover + handleInlineCapture fix). Do NOT self-approve.
3. After PR open: the **STILL-OVERDUE** EOD `/session-end` items (carried from sessions 1/2/3): vault-sync must run + carry the rewritten `April/April24Updates/spec/00-canonical.md` into the vault (`/April/` is gitignored — lost on next `/April/`-tree change). Plus tech-debt-session on the cumulative diff (session-3's 5 files; this session added none).

## What the next Claude needs to know

- **One prompt only.** Do not reintroduce advice/onboarding-prompt framing. Legacy = rollback. `config/fyn.php` default = `unified` and that is CSJ-ordered & intentional — do NOT revert "to be safe".
- **The decisive Rule #15 item is DONE.** Journey (b) is browser-GREEN under unified with a confirmed DB row (260). Do not re-litigate or re-run it as if unverified.
- The mock idiom is sanctioned + non-weakening — do not flag it as test-weakening.
- **Test-data pollution carried forward (harmless, `updateOrCreate`-safe, do NOT `migrate:fresh`):** uid 11 now has SavingsAccount #260 Barclays £3k (this session's journey-(b) artefact) alongside #165 Nationwide ISA. uid 73 still has #189/#190 from session 1. New uid 110 (`journeyc.unified@example.com`) exists mid-onboarding (`fyn_step=profile_review_family`) — left intentionally, it's a valid fresh-user journey-(c) fixture. None of this needs cleanup; a DB reseed (`php artisan db:seed`) is `updateOrCreate`-safe and leaves these intact.
- `:8000` artisan serve + `:5173` vite were already running at session start (LISTEN sockets confirmed). Code/config changes are live without restart (config uncached). Do NOT `pkill -f vite` (kills sibling project).
- 6-box MFA / verification: fill box-by-box (bulk fill only lands first digit). For a NEW registration the code is in `pending_registrations.verification_code` (NOT `users` / `email_verification_codes` — the user row doesn't exist until verified). Tinker: `DB::table('pending_registrations')->where('email',$e)->latest('created_at')->first()->verification_code`.
- Browser session persistence: navigating to `/login` or `/register` while authed redirects to `/dashboard`. To switch users, click sidebar **Sign Out** (works) — `/logout` URL just lands on a public page without clearing the SPA auth state reliably.

## Branch / deploy state

- Branch: `fynPromptRework`
- Ahead of origin: 0
- Behind origin: 0
- Relative to `dev`: PR #332 (`8d1ba2b`) merged the PRE-cutover state. Cutover + handleInlineCapture fix (`9c19dcc`) still NOT in dev — needs the new PR from Pick-up item 2.
- Deploy status: **Not deployed.** No csjones/prod deploy this session.
