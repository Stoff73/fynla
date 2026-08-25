# SaveTax Campaign Onboarding — Spec (as shipped)

> Companion to `savetax-campaign-onboarding-plan.md`. This document records what actually shipped on 2026-04-29 (session 112), what was deferred, and the boundaries of the wire-through.

**Branch:** `feature/fyn-persona-split`
**Commits:**
- `d45f6bf` — `feat(campaigns): /savetax routes to dedicated SaveTaxCampaignPage`
- `d910833` — `feat(onboarding): campaign_map config + controller dispatch`
- `38bac32` — `feat(onboarding): campaign welcome at base_personal`
- `2a34ee6` — `feat(onboarding): wire ?from= URL param through registration to onboarding/start`

(Plus prior session-111 scaffold commit `48fcdfe` which created the unrouted `SaveTaxCampaignPage.vue`.)

**Test delta:** Onboarding+Fyn suite went from 386 passed → 396 passed (+10), 1 skipped, 0 failures. Architecture suite 95/95 green.

---

## 1. Scope shipped

### 1.1 Landing page

`/savetax` now serves a dedicated `SaveTaxCampaignPage.vue` (lazy-loaded). The page contains:

- Hero: "Save more on tax" gradient banner.
- Allowances table for tax year 2026/27 — Income column (Personal Allowance, Savings Allowance, Starting Rate for Savings, Marriage Allowance) + Investment & Cash column (ISA, CGT, Dividend, Pension Annual Allowance).
- "Could this be you?" — 4 example cards (Non-working spouse, High income tax trap, General Investment Accounts, NICs / salary sacrifice).
- 5 internal CTAs (4 "Ask Fyn how →" buttons + 1 "Start your free 7-day trial") all link to `/register?from=savetax`.

The other four campaign routes (`/biggerpension`, `/paymortgage`, `/managedebt`, plus the existing `/wealth` if any) remain on the generic `CampaignPage` — out of scope.

**Tech-debt flag (carried forward):** All allowance values are hardcoded literals (e.g. £12,570, £20,000, £60,000). When 2027/28 rates land these strings will rot. Future task: source from `TaxConfigService` API or move to a constants file. Not blocking deploy — frontend marketing strings are arguably exempt from CLAUDE.md Rule #3 which targets backend calculation services.

### 1.2 Backend dispatch

- **`config/onboarding.php`** — new `campaign_map` array. First entry: `'savetax' => 'savetax'`.
- **`AiChatController::startOnboarding`** — checks `campaign_map` BEFORE `journey_map`. A matched campaign sets `users.onboarding_fyn_path = 'campaign'`, `selection = <campaign-id>`, `step = STATE_BASE_PERSONAL`. A matched journey continues to behave as before. Unknown / missing `from` falls through to `STATE_PATH_CHOICE`.
- Adding a new campaign requires only a config edit — no controller change.

**Test coverage:** `tests/Feature/Onboarding/EntrySourceCampaignMapTest.php` — 5 cases (config exposed, savetax happy path, fallthrough on unknown, ordering precedence over journey_map, journey non-regression). All green. The mirror `EntrySourceJourneyMapTest.php` (8 cases) remains green — confirmed no regression.

### 1.3 State machine welcome

`OnboardingStateMachine::buildPersonalPrompt` adds a fourth branch at the top:

> **When** `onboarding_fyn_path === 'campaign'` AND the user has neither `date_of_birth` nor `marital_status` set, **THEN** prepend a campaign-specific welcome to the existing grouped DOB+marital question. The welcome and the question land as a single assistant bubble.

Welcome text is selected via `campaignWelcomeFor(string $campaignId): string`:

| Campaign id | Welcome sentence |
|---|---|
| `savetax` | "welcome to Fynla — I'll help you build your tax-saving strategy." |
| _any other_ | "welcome to Fynla — let's get started." |

The full bubble for a fresh `savetax` user named "Verify" reads:

> Hi Verify, welcome to Fynla — I'll help you build your tax-saving strategy. Let's start with the basics: what's your date of birth, and are you single, married, in a civil partnership, divorced, or widowed?

Resume branches (DOB-known / marital-known) take precedence so the welcome only fires on the very first base_personal turn — re-entering the flow from a partial state still gets the existing pre-confirming retry prompt.

**Test coverage:** 5 new Pest cases in `OnboardingStateMachineTest.php` (`describe('OnboardingStateMachine::buildPersonalPrompt — campaign welcome', …)`) — fresh campaign user gets the savetax welcome; journey/focus paths do NOT get the welcome (still get "grab a few basics"); resume case (DOB set) skips the welcome and shows the existing "I have you as born…" branch; unknown campaign id falls back to the generic Fynla welcome.

### 1.4 Frontend wire-through (closes BS-05 / PSP-LS gap)

Four changes that together carry `?from=savetax` from the URL into the onboarding/start request body:

- **`Register.vue`** — generalised the `if (fromParam === 'fyn')` branch. Any non-empty `fromParam` now redirects to `Dashboard` with `query: { openFyn: 'journey', newUser: '1', from: fromParam }`. The `'fyn'` literal special-case is gone.
- **`Dashboard.vue`** — captures `this.$route.query.from` BEFORE `router.replace({ query: {} })` strips the URL, then forwards `{ from: fromParam }` to the `aiChat/startOnboardingConversation` action. Capturing before strip is critical — without this the onboarding director would always see `from === undefined`.
- **`store/modules/aiChat.js`** — `startOnboardingConversation` action now accepts a `payload` object, extracts `payload.from`, and forwards it to the service.
- **`services/aiChatService.js`** — `startOnboardingStream({ signal, from })` builds the request body as `JSON.stringify({ from })` when `from` is a non-empty string, otherwise sends `'{}'` (preserves existing behaviour for callers that pass nothing).

**Side benefit:** This same wire-through unblocks all journey CTAs (`?from=protection`, `?from=retirement`, `?from=goals`, `?from=budgeting`) that were silently dead because `aiChatService.startOnboardingStream` previously hardcoded `body: '{}'`. The S0.15 / INV-2.2.5 `journey_map` is now actually reachable from a real browser flow for the first time.

### 1.5 End-to-end browser verification

Driven via Playwright MCP on local dev (`http://localhost:8000`):

1. Navigate to `/savetax` → page renders with hero, allowances, examples — 0 console errors.
2. Click "Start your free 7-day trial" → URL = `/register?from=savetax`.
3. Fill registration form (Verify / Campaign / `verify-campaign-2026-04-29@example.com` / `Password1!`) and submit.
4. MFA modal opens; fetched code from DB (`PendingRegistration::latest()->first()->verification_code`); typed the 6 digits.
5. Redirected to `/dashboard` (URL stripped after capture, as designed).
6. Fyn chat panel auto-opens with the welcome bubble: *"Hi Verify, welcome to Fynla — I'll help you build your tax-saving strategy. Let's start with the basics: what's your date of birth, and are you single, married, in a civil partnership, divorced, or widowed?"*
7. DB state confirmed via tinker: `path=campaign selection=savetax step=base_personal`.

All assertions met on first run. No loop iterations needed.

---

## 2. Out of scope (deferred — awaiting CSJ's planned conversation map)

These were called out as deferred up-front in the plan and remain so:

- **Section 4 — post-expenses state-machine branch.** Today, after `STATE_PROFILE_REVIEW_EXPENDITURE`, a campaign user falls through to `STATE_ASSET_CAPTURE` (same as journey/focus). The campaign-specific divergence ("Hello {name}, in order to generate your tax savings strategies, there are some additional details I need to gather. Does {spouse_name} work?") is **not** wired up.
- **Section 5 — `capture_spouse_work_details` tool.** No deterministic write path exists for the "no, doesn't work" branch that would update the spouse user's `employment_status` / `employer` / `occupation` / `annual_employment_income`.
- **Section 6 — terminal page / strategy outcome.** CSJ's working note: *"the actions tab is a good spot, but I need to think this through properly, as we would need to create a dashboard on the fly with the user's information"*. Open question: extend `/actions`, or build a campaign-specific dashboard?
- **BS-26 / BS-27 Playwright scenario stubs** — author once sections 4-6 land and the conversation has a defined terminal.

These need a follow-up plan once CSJ delivers the conversation map.

---

## 3. Operating notes for the next session

- The plan's commit boundaries shifted slightly: the original `Tasks 1+2 together` collapsed because the landing-page file (`SaveTaxCampaignPage.vue`) was already committed in the prior session's `48fcdfe`. The router change shipped as a standalone `d45f6bf`. All other commit groups landed exactly as planned.
- The static `Get started` link at the bottom of `StaticFynChat` (the docked chat on public pages) still points to `/register?from=fyn` — that's a separate entry point we did not touch. If the user wants the static chat's CTA to forward the campaign source from the page they're on, that's a future enhancement.
- The wire-through introduces a net-new request shape: `POST /api/ai-chat/onboarding/start` with `body: { from: '<id>' }`. All existing callers continue to work because the service still defaults to `body: '{}'` when `from` is undefined or empty.

---

## 4. References

- Plan: `April/April28Updates/savetax-campaign-onboarding-plan.md`
- Sprint 0+1 audit (parent context): `April/April28Updates/sprint-0-and-1-audit-report.md`
- Parked-memory regression fix (adjacent session 111 work): commit `32e08ab`
- Canonical contract: `April/April24Updates/spec/00-canonical.md`
- Original mockup: `April/April28Updates/Save Tax Campaign.html`
