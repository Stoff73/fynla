---
type: handover
mode: context-clear
date: 2026-07-23
session: 1
branch: codex/savetax-allowance-ctas
trigger: context-handover skill (CSJ asked for a sensible break to /clear)
---

# Context Clear Handover — 2026-07-23, Session 1

## Immediate state

Everything is committed, merged to dev (PRs #645–#653), and deployed to csjones
(backend at the #653 merge `27f36c1`, BOTH bundles — `public/build/` AND
`public/m-build/` — freshly rsynced, `public/hot` removed). The ONE remaining
step is the full live E2E verification pass that CSJ demanded; it has NOT run.

## The thread

1. **Structured turn-intent plan: all 8 tasks shipped + live-verified** (PRs
   #645, #646) — enum on every assistant persist, enum-preferring readers,
   gate confirmedFacts channel (facts travel as FynLoop::stream param + gap-fill
   arg after the container-transient split bit live), phrasings, /m 401
   secondaries, hex→token sweep. July23 register written (`deferred-by-design-
   register-2026-07-23.md`, repo+vault) with screenshots.
2. **CSJ corrected me hard**: the mid-onboarding question policy was DECIDED
   long ago (simple → answer inline definitively; complex → acknowledge +
   defer to completion raise). I'd mis-filed it as an open ruling. Memory
   `feedback_fyn_question_policy_decided.md` now pins it.
3. **The walk fixes shipped across #647–#652** in response to CSJ's live
   corrections, each one a real defect: dropped questions on the partial-retry
   and model-clarification branches now dispatch (answer or defer); CSJ's
   completion-raise shape (Thanks {name} — your {Save Tax} onboarding is now
   complete… Yes/No) with topics stored at defer time; interruption answers
   are ONE short factual paragraph (definitional framing via
   buildInterruptionAnswerPrompt threaded through FynLoop::run — NOT stream(),
   which lacks handoff interception and broke INV-2.4.1 in tests); the
   re-asked question is its own bubble (mid-stream done = per-message
   finaliser); bold questions restored on base_work variants; "Brilliant."/
   "Lovely." praise transitions removed; a deterministic guard
   (definitiveAnswerText: AckSentenceDeduper + cut at first question/bold
   echo, persisted row rewritten to match) because the model rambled the
   answer twice with a parroted re-ask live — prompt-hoping alone failed
   twice. Status events (thinking) must NOT end the guard's filtering; only
   capture-family events do.
4. **Dashboard actions defect (CSJ spotted live)**: NextActionsService showed
   four "Unlock X advice" prompts to a mid-walk user (never had onboarding
   awareness). Fixed in #653: ONE midWalk predicate at the unified-list
   assembly points; per-module tab cards keep their true gate state. Web
   GamifiedDashboard now renders the SAME engine level block /m renders
   ("X of 4 actions to your next level", engine progress_percent, silent
   refetch after check-off) instead of inventing "0 of 9 actions" from the
   recommendation total.
5. **CSJ is at maximum frustration** — repeatedly interrupted to correct
   visible breakage I'd walked past, and issued the directive: everything
   reported done over the last week that is still broken (walk, onboarding,
   Fyn chat, answers, interruptions, actions, surfaces) gets fixed and
   verified properly, quickly, with no more half-jobs, no more incremental
   deploy-and-show cycles, and no more full Pest suites per small change
   (memory `feedback_no_full_suite_per_small_change.md`). CSJ is watching
   every line and every screen.
6. Rejected approaches this session (do not re-litigate): stream() for the
   dispatcher answer (breaks handoff interception); per-generator suppression
   guards (duplication); prompt-only fixes for answer shape (model ignored
   them twice — the deterministic guard is the mechanism).

## Files touched this session (all committed + pushed)

- Fyn/onboarding: `OnboardingChatDirector.php` (turn_intent writer + readers,
  dispatch guards, raise shape, definitiveAnswerText, buffered relay,
  buildInterruptionAnswerPrompt), `OnboardingStateMachine.php` (bold work
  prompts, no praise), `CaptureAccuracyGate.php` (confirmedFacts + phrasings),
  `CoordinatingAgent.php` (executeTool facts param, evidence boundary),
  `FynLoop.php` (stream confirmedFacts + run systemPromptOverride threading),
  `HasAiChat.php` (turn_intent stamp, setConfirmedCaptureFacts),
  `AssetCaptureEntityExtractor.php` (public extractOwnershipType),
  `app/Enums/FynTurnIntent.php` (new).
- Dashboard: `app/Services/Mobile/NextActionsService.php` (midWalk),
  `resources/js/views/GamifiedDashboard.vue` (engine level block).
- /m: `Estate/NetWorth/Goals.vue` (secondary 401s), `Login.vue`,
  `GamificationCelebration.vue`, `tokens.js` (new), `style.css` (guide tokens),
  `Dashboard.vue` + `dashboard.css` (stroke), `SecondaryCall401.spec.js` (new).
- Tests: `TurnIntentStampTest.php` (new), `OnboardingInterruptionTest.php`
  (heavily extended), `CaptureAccuracyGateTest.php`, `NextActionsServiceTest.php`,
  `InlineCaptureFlowTest.php`, + mock-stub sweep (setConfirmedCaptureFacts).
- Docs: turn-intent plan all ticked; July23 register (+vault mirror).

## WIP commit

- None needed — tree clean, HEAD `15475cd`, fully pushed, all PRs merged to dev.

## Open decisions

- style.css BODY hex sweep needs CSJ's neutral-500 ruling (#6B7280 rendered
  truth vs #717171 variable) — register item B3. Default: do NOT sweep until
  ruled.
- Register item A1's fix SHIPPED this session (the dispatch guards) — the
  register entry still describes it as open; update it after the E2E proves it.

## Pick up from here (auto-continue contract)

**ONE job: the full live E2E verification pass on csjones, as a user, reporting
once at the end with screenshots. CSJ's directive: get it right, do not take
all day.** Fresh funnel user on web (`https://csjones.co/fynla/savetax` →
register; MFA code via SSH tinker `PendingRegistration`). Verify in ONE
journey:

1. Recap + income prompt (single welcome, no dupes).
2. Side question at the income step ("Does my gross income include my employer
   pension contributions?") → EXACTLY two bubbles: one short factual answer
   (1–2 sentences, no "personalised answer" waffle, no missing-data ask, no
   trailing question, no duplication), then the walk's re-ask as its OWN
   bubble: "**What's your gross annual income?** This includes bonuses and
   commissions." (bold question, no "Brilliant."). Screenshot.
3. Mid-walk dashboard check (web + /m `csjones.co/fynla/m/app/...`): level
   card reads "X of 4 actions to your next level" on BOTH surfaces; NO
   "Unlock X advice" items in Top actions while mid-walk; module tabs still
   show locked states. Screenshot both.
4. Volunteer "I have a Santander savings account with £9,000 in it" →
   offer → "Yes, save it" → ONE ownership ask → "It's only mine." → saved
   (facts channel), zero churn. DB-verify the row.
5. Holistic question ("How is my financial health?") → defer promise → walk
   resumes.
6. Complete the remaining walk (multi-job No → verify Okay flow → remaining
   campaign steps → synthesis) → the completion raise MUST be CSJ's shape:
   "Thanks {name} for the information — your Save Tax onboarding is now
   complete. You asked about '…' earlier; to answer your question properly I
   may need some additional information. Are you okay to continue?" with
   Yes/No bubbles → tap Yes → advice answers it. Then celebration + route
   bubble. Screenshot.
7. Post-walk dashboard: unlock actions may now legitimately appear (step
   nulled); counts still engine-fed.
8. If ANY step fails: diagnose with evidence, fix, redeploy, re-verify —
   loop until green. Report ONCE at the end, honestly, with screenshots.
   Then update register item A1 to reflect the shipped fix.

## What the next Claude needs to know

- **CSJ is watching every line, screen, and word. No half-jobs, no progress
  theatre, no "verified" without reading the actual bubbles as a user.**
  Acknowledge visible breakage FIRST, in plain words, before touching tools.
  Instant halt on any CSJ message.
- Lean cadence is LAW now: targeted test families per change; NO full Pest
  suite per small change (only at consolidation points or if a plan mandates).
- csjones deploys: backend `git pull origin dev` (server is ON dev branch);
  bundles = rsync BOTH `public/build/` AND `public/m-build/` (the /m SPA ships
  from m-build — missing it cost a false "Dashboard 401 bug" yesterday).
  After deploy: `config:cache` + `cache:clear` (NEVER optimize/route:cache);
  cache:clear expires web sessions — the E2E user must log back in (MFA via
  tinker `EmailVerificationCode`).
- Live throwaway users on csjones: tessa (turnintent-e2e-0723@…),
  marcus (question-e2e-0723@… / Question2026!e2e — mid-walk at base_work,
  his transcript contains the broken-era bubbles; use a FRESH user for the
  clean E2E).
- Playwright is logged into csjones as Marcus (desktop SPA session may have
  expired — cache:clear kills sessions).
- The suite baseline: 3 documented NativeSessionApiTest order-flakes; ignore.
- Pint strips just-added imports if the usage lands in a later edit — add
  import + usage in the same edit, verify with grep after.

## Branch / deploy state

- Branch: codex/savetax-allowance-ctas, HEAD `15475cd`, in sync with origin.
- dev: at #653 merge (`27f36c1`) — carries ALL of today's work.
- csjones: backend `27f36c1` + both bundles current. PROD UNTOUCHED (dev-only
  per CSJ).
