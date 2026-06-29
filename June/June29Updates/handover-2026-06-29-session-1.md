---
type: handover
mode: context-clear
date: 2026-06-29
session: 1
branch: savetax-income-only (worktree fynla-m-funnel, off dev)
previous_session: 2026-06-28 session 1
---

# Handover — 2026-06-29, Session 1

> SaveTax campaign Fyn welcome reformatted + two-bubble regression fixed +
> a NEW funnel income/spouse cross-check & challenge built (full
> brainstorm→spec→plan→implement→verify). All on branch `savetax-income-only`
> (off `dev`), deployed to csjones, browser-verified on web AND `/m`.
> Prod (fynla.org) UNTOUCHED. **PR #581 → dev is OPEN** (not merged).
> See "Session continued" at the bottom for the cross-check work.

## Immediate state
The SaveTax campaign onboarding welcome (the first Fyn screen after funnel
registration) now: **bubble 1** greets + lists the funnel answers as
point-form bullets (one per line) + the unchanged "started your profile…
~5 minutes" line; **bubble 2** (separate) asks for gross annual income only
(employer/business/role dropped). Verified live on csjones, web AND `/m`.

## What CSJ asked for
1. Bubble 1: greet, then list the user's funnel answers in point form (each on
   a new line), then the rest of bubble 1 as-is.
2. Bubble 2: keep it, but ask for **income only** — drop employer / business /
   role.
3. "Check all of these screens" — the employer/role ask appeared in 4 prompts.
4. (Mid-task) The income question had merged into the recap bubble on web —
   fix the two-bubble split. Then tighten web bullet spacing. Then document.

## What shipped (3 commits on `savetax-income-only`)

**`af8999b` — formatting + income-only + parity renderer**
- `app/Services/Onboarding/OnboardingStateMachine.php`
  - `buildFunnelRecapPrompt`: bubble 1 = greeting + markdown `- ` bullets (one
    per funnel answer: employment / income band / spouse / assets) + remainder.
    `BUBBLE_BREAK` kept → bubble 2 is the income question.
  - `buildWorkPrompt` (self-employed / part-time / employed) + the `base_work`
    `retry_text`: all now ask for **gross annual income only**.
- `app/Agents/CoordinatingAgent.php` — `handleCaptureWorkDetails` now requires
  **only `annual_income`** in `$missing`. Employer/occupation are still saved
  when volunteered but no longer block the flow. (Previously all three were
  required → dropping the ask alone would have stalled the state machine in a
  partial-retry loop.)
- `app/Services/Onboarding/OnboardingChatDirector.php` — `base_work` status
  label 'capturing your employer and role' → 'capturing your income'.
- `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` — `retry_text`
  resynced to match the code (DATA field is corpus-sourced; golden master
  asserts code == corpus). Corpus loads from disk + signature-invalidated
  cache, so `cache:clear` on deploy refreshes it — no reindex needed.
- `resources/mobile/utils/fynText.js` — `/m` renderer now renders `- ` bullets
  as `<ul><li>` + `\n`→`<br>` (was escape + `**bold**` only), mirroring web's
  `AiMessageContent`. Strips list newlines so no stray `<br>`.
- `resources/mobile/views/dashboard.css` — scoped `.md-fyn__msg ul/li` styling.
- Tests updated to new behavior: `OnboardingStateMachineTest` (income-only
  prompt assertions), `OnboardingChatDirectorFixesTest` (income-only required),
  `ResumeAfterDisconnectTest` (new status label).

**`1c96c66` — web SPA two-bubble split (the regression)**
- `resources/js/store/modules/aiChat.js` — all THREE `onboarding_advance` SSE
  handlers were no-ops, so a split multi-part prompt streamed into ONE bubble
  (income question mashed onto the recap). Now they flush `streamingText` into
  its own bubble on `onboarding_advance`, matching the `/m` dock + the resume
  render. (The backend ALWAYS split correctly — DB had 2 messages; this was
  purely a web-streaming render bug, pre-existing, not caused by the formatting
  change. The `/m` dock already split, per
  `reference_mobile_campaign_onboarding_and_fyn_streaming`.)

**`aaf7b06` — tighten web bullet lists**
- `resources/js/components/Shared/AiMessageContent.vue` — the `<ul>` wrap kept
  the list's `\n`, which the later `\n`→`<br>` turned into a stray `<br>`
  between items (double-spacing). Strip those newlines in the wrap; spacing
  comes from `space-y-1` alone. Affects ALL assistant bullet lists app-wide
  (tightens them consistently). CSJ approved this shared-renderer change.

## Tests
- `tests/Unit/Services/Onboarding` + `tests/Feature/Onboarding` +
  `tests/Unit/Agents` + `tests/Feature/Fyn` + tool-catalogue: green
  (713 + 419 across runs; 2 deferred-skips = cassette provenance).
- Golden master (`OnboardingWorkflowTableGoldenMasterTest`) green after the
  corpus `.md` resync.
- The 3 frontend JS/Vue changes have no unit coverage (frontend) — verified
  live in-browser instead.

## Verification (csjones, live)
Registered a fresh campaign user via the real SaveTax funnel
(`incometest0629a@example.com`, full-time / £50,271–100k / spouse / no spouse
income / bank+savings+pension):
- **Web** (`/fynla/dashboard` Fyn dock): 2 bubbles, tight bullets, income-only
  question. (`fyn-web-tight-bullets.png`)
- **`/m`** (`/fynla/m/app/dashboard` dock, logged in via `/m/app/login` + MFA):
  2 bubbles, tight bullets, income-only question. (`fyn-m-after-fix.png`)
- Both show the new status label "…capturing your income" in the resume prompt.

## Deploy state
- csjones (`~/www/csjones.co/fynla-app`) is checked out on **`savetax-income-only`
  @ aaf7b06** (per the deploy-gate: verify feature branch on csjones BEFORE
  merge). `public/build/` + `public/m-build/` rebuilt + rsynced; cache chain run
  (NO `optimize`/`route:cache`; ended `config:cache`).
- **csjones must be returned to `dev`** after the PRs merge:
  `git checkout dev && git pull origin dev` (+ rebuild/upload dev's assets).
- Prod (fynla.org) UNTOUCHED.

## What's NOT done — pick up from here
1. **Open PRs to `dev`** for `savetax-income-only` (CSJ to merge). Branch has 3
   commits; nothing merged yet.
2. **After merge, return csjones to `dev`** (see Deploy state).
3. **Test residue on csjones:** user `incometest0629a@example.com` (id=150) +
   its PendingRegistration — harmless dev data, delete if you want it gone
   (needs a DB delete; CSJ to OK).
4. **Optional polish (flagged, not done):** numbered lists in `AiMessageContent`
   are created AFTER the `<ul>` wrap so they render as bare `<li>` (pre-existing,
   untouched). Not in scope.
5. Prod release of this + the pending Batch 1–7 set is a future `dev → main`
   PR (CSJ's call).

## The thread / why it went the way it did
- Initial pushback from CSJ: the income question appeared in the SAME bubble as
  the recap. Root cause was NOT the formatting change — the backend split was
  intact (2 DB messages); the web SPA's `onboarding_advance` handlers were
  no-ops (pre-existing). Fixed by flushing the bubble on advance (commit
  `1c96c66`). Lesson reinforced: check DB + memory + code for evidence before
  theorizing about a "regression."
- The `reference_mobile_campaign_onboarding_and_fyn_streaming` memory documents
  the exact contract: "Frontends must split bubbles on `onboarding_advance`;
  resume renders DB rows as separate bubbles, so live streaming must match."

## Context hints
- Active worktree: `fynla-m-funnel` on `savetax-income-only` (REAL vendor, built
  assets). Main dir on `main`. `fynla-coala` worktree separate — keep.
- SSH csjones: `~/.ssh/fynlaDev` (loaded this session), `u163-ptanegf9edny@ssh.csjones.co:18765`,
  Laravel root `~/www/csjones.co/fynla-app`. NOT the ssh-fynla MCP (that's PROD).
- Last commit: `3b6fe63`.

---

## Session continued — funnel income/spouse cross-check (same branch)

After the welcome work, CSJ asked for logic so Fyn checks the chat-entered
income against the funnel answer and challenges contradictions. Built via the
full superpowers flow (brainstorm → spec → plan → executing-plans inline).

**Spec/plan:** `docs/superpowers/specs/2026-06-29-income-funnel-crosscheck-design.md`,
`docs/superpowers/plans/2026-06-29-income-funnel-crosscheck.md`.

**What it does:** when the income captured in chat falls outside the funnel
band (user `funnel_answers['income']` at `base_work`; spouse
`funnel_answers['spouseIncome']` at `base_spouse`), Fyn parks
`onboarding_fyn_context['pending_income_challenge']`, emits a challenge naming
the band + entered figure + "changes your tax-saving calculation", with
**Continue** (keep + advance) / **Change** (re-ask) bubbles. Backend-only →
web + `/m`. Built: `FunnelIncomeBand` helper, `detectIncomeFunnelMismatch` +
`buildIncomeChallenge` + `maybeChallengeIncome` in the director, extracted
`advanceFromState` (refactor) so Continue can resume, flag-first handler in
`handleUserMessage`.

**Two CSJ corrections handled this session:**
1. The recap was showing tax-status labels ("A higher-rate taxpayer") instead
   of the actual answer. Fixed `buildFunnelRecapPrompt`'s `incomeLabel` map to
   echo the band — "Earning £50,271 to £100,000". (CSJ was firm this must echo
   the answer, not interpret it.)
2. The challenge wasn't firing — root cause: `detectIncomeFunnelMismatch` read
   `$captureDetails['details']['annual_income']` but at the call site
   `$captureDetails` IS already the details array (assigned from
   `$event['details']` in `handleGroupedExtractTurn`). Fixed to
   `$captureDetails['annual_income']`. Unit fixtures had the same wrong shape
   (passed but tested nothing real) — browser testing caught it. Fixtures fixed.

**Verified live on /m (csjones):** recap echoes "Earning £50,271 to £100,000";
income £30,000 → challenge fires ("Earlier you told us your income was
£50,271–£100,000, but you've entered £30,000…") → Continue advances. Reset the
`incometest0629a` test user to `base_work` via tinker for the re-test.

**Test cadence (CSJ direction this session):** do NOT run the full suite after
every small change — test the change + browser-test only; full suite ONLY at
PR-merge time. Applied from this point on.

## PR

**#581 `savetax-income-only` → dev — OPEN, not merged.** Covers BOTH the
welcome formatting/two-bubble fixes AND the income cross-check (13 commits).
Next session: run the FULL suite, then admin-merge per the deploy gate, then
return csjones to `dev` (`git checkout dev && git pull origin dev`). Then the
prod release is a separate `dev → main` call (CSJ's).

**Loose ends:** `incometest0629a@example.com` (csjones test user, now at
`base_work`, harmless dev data). Full suite not yet run on the cross-check
commits — run at merge.
