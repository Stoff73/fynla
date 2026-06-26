---
type: plan
date: 2026-06-25
scope: /m pathway — small bugs, issues, changes
status: DECIDED — all CSJ decisions captured; ready to build (see roadmap at bottom)
---

# /m fixes & changes — working plan

Mapped from 6 parallel Explore agents. Each item: **where** (file:line), **how**, and **status** (CLEAR = do it / NEEDS YOU = product decision / ALREADY DONE / CONTRADICTS).

Layers in play:
- **A. Server-rendered funnel** — `public/pages/savetax.php`, `savetax-plan.php`, `public/pages/js/*` (vanilla JS)
- **B. PHP onboarding state machine** — `app/Services/Onboarding/OnboardingStateMachine.php` (source of truth: `CAMPAIGN_SECTION_ORDER:138`) + `OnboardingChatDirector.php` (SSE emission)
- **C. Mobile SPA** — `resources/mobile/views/*`, `resources/mobile/mixins/onboardingChat.js`

---

## 1. SaveTax landing page (Layer A)

| # | Item | Where | How | Status |
|---|------|-------|-----|--------|
| 1.1 | Collapsible "What does this mean?" | `savetax-plan.php:157-164` (`#meaning-body`) | Wrap in native `<details>`/JS toggle, hidden by default | CLEAR |
| 1.2 | "Find out how / Register for free" button under allowances | `savetax-plan.php:173-177` — a `.sp4-combined__cta` with "Find out how" text + "Register for free" link **already exists** | Confirm what's missing vs what's there | NEEDS YOU |
| 1.3 | Error message text → white on registration | `public/pages/js/savetax-plan-v4.js:274` (`color:#A93257`) | Change to `#FFFFFF` | CLEAR |
| 1.4 | Registration not stored when closing verification | `AuthController::register:92-105` already writes `PendingRegistration` (24h) **before** email | Pending reg IS persisted — clarify real symptom (resume-on-return? funnel data lost?) | CONTRADICTS / NEEDS YOU |
| 1.5 | Reduce text under SaveTax calc | `savetax-plan.php:111-113` (`#hero-subtext`) | Shorten copy (draft for approval) | CLEAR (approve copy) |
| 1.6 | Questionnaire — move partner | `savetax.php:207-232` (Q3, after income) + sequence `savetax.js:24` `['employment','income','spouse',...]` | Reorder — **target position?** | NEEDS YOU |

## 2. Fyn onboarding chat (Layer B, PHP state machine)

| # | Item | Where | How | Status |
|---|------|-------|-----|--------|
| 2.1 | First Q = income only, two bubbles, bold Q, incl bonuses/commission | `OnboardingStateMachine.php:354-375` (`STATE_BASE_EMPLOYMENT`→`STATE_BASE_WORK` grouped_extract of employer+role+income) | Re-scope first state to income only; bold via markdown | CLEAR (confirm scope) |
| 2.2 | "About 5 mins" greeting | No time-estimate exists in onboarding | Add to campaign intro (`STATE_CAMPAIGN_INTRO:433`) | CLEAR |
| 2.3 | Check SaveTax form → Fyn banding (band→salary) + ISA Q | Funnel `funnel_answers` (income BAND Q2, assets/ISA Q5) → `users.funnel_answers` (`AuthController:542`) → dispatch `AiChatController:676` | Trace whether band/ISA pre-fill the income + ISA states; fix if broken | CLEAR (investigate) |
| 2.4 | Tell user "taking you to income page, check info, close Fyn" | `OnboardingStateMachine::verifyPromptNavigate:1051-1059` ("I've added that — taking you to the screen now…") | Reword | CLEAR (approve copy) |
| 2.5 | Income page: logic for you AND partner | `STATE_BASE_WORK` + spouse states; see 3.x income screen | Ensure both captured/shown | CLEAR (confirm) |
| 2.6 | Remove Gift Aid question | `STATE_CAMPAIGN_CHARITABLE_GIVING:519-525` + remove `'giving'` from `CAMPAIGN_SECTION_ORDER:143`, `campaignSections():162`, verify config `:185`, section label `:1066` | Delete state + ordering entries | CLEAR |
| 2.7 | Split ALL bubbles → "what we've heard" + question; bold Q if it has supporting info | `OnboardingChatDirector::emitTurnForState:632-674` (single SSE event today); frontend `onboardingChat.js` already renders 2 events as 2 messages | Emit `content` (ack) then `quick_replies`/`content` (bold Q) | CLEAR |
| 2.8 | Spouse allowance response → exact target wording | Currently voiced from strategy catalogue via `buildSectionAdvice:804-843` | Hardcode target string for spouse section | NEEDS YOU (confirm hardcode) |
| 2.9 | After expenses: "we've created your personal tax strategy" + "Take me to my tax strategy" button | `STATE_CAMPAIGN_TERMINAL:572-577` + `emitTerminalNavigationTurn:2637-2679` (navigation event only) | Reword prompt + emit a button bubble | CLEAR |

## 3. Tax Strategy page (Layer C)

| # | Item | Where | How | Status |
|---|------|-------|-----|--------|
| 3.1 | "Headroom" → "Available" (green) | `TaxStrategy.vue:66, 68, 167` ("headroom"); per-allowance remain already `spring-600:206-210` | Rename labels; add spring token to hero label | CLEAR |
| 3.2 | Personalised tax strategy message after onboarding | Not implemented; payload `TaxStrategyService::getDashboardPayload` lacks `onboarding_completed` | Surface flag → gate a message block | CLEAR (approve copy) |
| 3.3 | Actions must match dashboard actions | TaxStrategy uses `TaxStrategyCalculator` (17 strategies); dashboard uses `NextActionsService` (cross-module, KYC-gated, max 4) — **divergent today** | Pick single source | NEEDS YOU |
| 3.4 | Reduce text & formatting | `TaxStrategy.vue:68, 148-149, 37-39` verbose blocks | Shorten (draft for approval) | CLEAR (approve copy) |
| 3.5 | Connect back: "see all actions to get more for your money" | No back-CTA today; `goBack():174` exists | Add footer CTA → dashboard | CLEAR (approve copy) |

## 4. Dashboard (Layer C)

| # | Item | Where | How | Status |
|---|------|-------|-----|--------|
| 4.1 | "0 of 4" not updating on check-off | `Dashboard.vue:42` reads `level.actions_completed`; mark-done (`:779`) only refetches `/api/gamification/status`, not the 5-min-cached `/api/v1/mobile/dashboard` | After mark-done: clear mobile dashboard cache + refetch, OR derive count client-side | CLEAR |
| 4.2 | Tax Strategy unlock logic (pension + ISA) | Gate `PrerequisiteGateService::canAnalyseTax:127-143` = income + employment (NOT pension/ISA); availability map `HouseholdFinancialContext:39-58` has `hasDcPension`, `hasIsaAccount` | **Confirm intended unlock rule** | NEEDS YOU |
| 4.3 | Remove "1 of 2 actions" small text | `Dashboard.vue:101` (`.md-recs__count` "X of Y done") | Delete the `<p>` | CLEAR |
| 4.4 | Check if 4 actions or skip — refresh one or all | `NextActionsService:MAX_ITEMS=4`; skip-per-item exists (`Dashboard.vue:132`); no refresh button | **Define behaviour** | NEEDS YOU |
| 4.5 | Update carousel design | Native scroll-snap, `Dashboard.vue:60-97` + `dashboard.css:393-441` (no library) | **Target look?** | NEEDS YOU |

## 5. Freemium upgrade (Layers B + C)

Free-tier caps (`TierConfigurationSeeder:45`): savings 3, investment 2, pension 5, properties 3, mortgages 10. Enforcement already live (`SavingsStore:102-113` → `TierLimitExceededException` → caught `CoordinatingAgent:2406`). No `/m/pricing` route — upgrade breaks out to `/settings?tab=subscription`.

| # | Item | Where | How | Status |
|---|------|-------|-----|--------|
| 5.1 | "1 of 2 accounts used" + upgrade link on appropriate pages | `Savings.vue:117-120`, `Investment.vue:83-86` (+ Protection/Retirement) account-count labels | Add "X of CAP" + upgrade link; **which pages?** | NEEDS YOU (pages + CTA) |
| 5.2 | Fyn tells accounts-left, and at threshold "no more — upgrade" + link | `CoordinatingAgent` create handlers already throw `TierLimitExceededException`; message generic, no link | Add remaining-count + at-limit message with upgrade redirect | CLEAR (confirm CTA) |
| 5.3 | Holistic Plan & Goals: "Try again" → upgrade button | `HolisticPlan.vue:8-10`, `Goals.vue:7-10` — **generic** error fallback today, not tier-specific | Only swap when error IS a tier lock — confirm gating | NEEDS YOU |

## 6. Bugs + Other

| # | Item | Where | How | Status |
|---|------|-------|-----|--------|
| 6.1 | Reg fail → Fyn doesn't start at SaveTax point | `AuthController:601` deletes `PendingRegistration` (holds `funnel_answers` = campaign entry); later fresh reg has no funnel_answers → dispatch `AiChatController:676` can't match SaveTax → default path | Preserve funnel_answers / re-tag SaveTax source on re-registration | CLEAR (root cause found) |
| 6.2 | Bank account saved as savings | `OnboardingService:730-765` creates cash account; `SavingsAccountNormaliser:60` defaults unknown type → `easy_access` | Map "bank"/"current" → `current_account` | CLEAR |
| 6.3 | Edit details → open Fyn asking what to change | `MobileChrome.vue:27` **already** `openFynWith('What would you like to update?')` | Where did you see it broken/missing? | ALREADY DONE / NEEDS YOU |
| 6.4 | Goals: add "Add goal" + "Edit" buttons | `resources/mobile/views/modules/Goals.vue` — read-only, no buttons | Add buttons → open Fyn / edit flow | CLEAR |
| 6.5 | Recent cash ISA / investment changes | Surfaces: `Savings.vue` (is_isa tag), `Investment.vue` (isa/gia) | **Too vague — what's the ask?** | NEEDS YOU |
| 6.6 | Google Analytics on /m + behind login | GA exists on desktop (`analyticsService.js`, `cookieConsent.js:75-90`); **none** on `/m` SPA or public funnel pages | Verify, then add if wanted | NEEDS YOU (verify vs add) |

---

## Decisions — FINAL (CSJ, 2026-06-25)

- **1 Actions single source:** YES — dashboard `NextActionsService` canonical; Tax Strategy = filtered view.
- **1.2 Find-out-how:** add a SECOND CTA at the TOP of the allowances block (keep the bottom one).
- **1.4 Reg-not-stored:** pending reg already persists; real fix is **6.1** (SaveTax continuity). No separate work.
- **1.6:** NOT "move" — it's part of **C** (remove "partner" → "spouse" everywhere).
- **2 Tax Strategy "unlock" (dashboard cards):** it's a LABEL fix. Make unlock cards **per-item**: "Unlock pension info — enter pension details", "Unlock ISA info — enter ISA details", etc. NOT "unlock your tax strategy".
- **2.8 / 10 Spouse response:** simplify to "You can definitely save money with your spouse's allowances. We've added this to your actions list which we'll take you to shortly." + **a number = the tax saving** (fallback: the unused allowance, ~£40k).
- **4.4 Done actions:** on mark-done → replace slot with next-best action; **persist done-tracking** so completed don't reappear (also fixes stale 4.1 "0 of 4").
- **4.5 Carousel:** DROPPED — done separately.
- **5 Freemium:**
  - (a) account screens (Savings cap 3, Investments cap 2, Pensions cap 5, Protection) show "X of Y accounts used" + Upgrade link.
  - (b) Fyn says accounts-left on add; at cap says "you're out — upgrade" + link.
  - (c) Upgrade link breaks out of the /m iframe to parent `/settings?tab=subscription` (web `LimitReachedModal` pattern).
  - (d) **Holistic Plan → premium** (free users see Upgrade instead of feature; add a tier gate — none exists today). **Goals stays free — no change.**
- **6 / C Remove "partner" → "spouse" EVERYWHERE** (funnel ~35, web SPA ~85, /m 1) incl. general marketing copy; **KEEP "civil partner"** (47, HMRC-correct). Careful regex: replace "partner" NOT preceded by "civil ". Confirm live funnel file (`savetax.php` vs `savetax-v2.php`) before editing.
- **6.3 / D Edit-details:** seed is page- AND data-specific. Each MobileChrome page supplies its editable scope + actual items; opener names that page's real holdings (savings page → "your savings accounts: [list]"), never a generic menu. Build deterministically client-side.
- **6.5 Cash ISA/investment:** DROPPED.
- **6.6 GA:** DROPPED — separate.
- **8 GA:** DROPPED.

Copy-reduction (1.5, 2.4, 3.2, 3.4, 3.5) — draft shortened copy for approval; not blocking.

---

## Execution roadmap (feature branch off `dev`)

Surface batches, each browser-verified on csjones before the next. Lean cadence (Rule 17) — consolidate PRs per surface.

- **Batch 1 — Funnel pages (Layer A, low risk):** 1.1 collapsible, 1.2 top CTA, 1.3 white error, 1.5 reduce calc text. ✅ DONE — PR #574, merged dev `54df1d6`, csjones-verified.
- **Batch 2 — Onboarding state machine (Layer B):** 2.1 income-first/2-bubbles/bold, 2.2 "5 mins", 2.3 band+ISA carry, 2.4 income handoff copy, 2.6 remove gift aid, 2.7 split bubbles, 2.8/10 spouse line+number, 2.9 terminal "tax strategy created" + button.
  - **Batch 2 PROGRESS (branch `m-onboarding` off dev):**
    - ✅ 2.6 Gift Aid removed + 73 onboarding tests green — committed `38fbd43` (NOT yet merged to dev).
    - 🔎 Findings: recap→question split (2.7) already structural via `OnboardingChatDirector::buildCaptureAck` (emits ack bubble) + `onboarding_advance` (frontend splits messages). Gaps: no ack after income/`BASE_WORK` capture; delegated LLM turns self-ack. Mobile Fyn bubble renders PLAIN text (`MobileChrome.vue:132` `{{ m.text }}`, no markdown lib) → "bold the question" needs a tiny `**x**`→`<strong>` renderer (bold = emphasis, not an icon → OK on Fyn surface).
    - 🔎 First-turn (2.1) is `buildFunnelRecapPrompt` (recap+income question in ONE string, `OnboardingStateMachine.php:1183`) → must split into two bubbles. Funnel band already LABELS the user (higher-rate etc.) but doesn't pre-fill salary; ISA section fires from funnel cash/isa assets (2.3 = verify, likely fine).
    - ⚠️ 2.4 income-handoff copy = generic `verifyPromptNavigate:1057` ("taking you to the screen now. Is this information correct?") — used by ALL sections; intertwined with the CANONICAL SaveTax verify sequence (`feedback_savetax_verify_sequence_canonical.md`). Read that memory before editing.
    - 2.8/10 spouse number = tax-saving from the engine (ComposedTaxPlanService / strategy catalogue), fallback unused-allowance (~£40k). Source TBC.
    - ✅ DONE + unit-green (committed, NOT yet merged to dev):
      - 2.6 Gift Aid removed (`38fbd43`).
      - 2.1 income-first two bubbles + bold question + "(this includes bonuses and commissions)"; 2.2 time single-number; 2.7 BUBBLE_BREAK split mechanism + income ack + mobile bold renderer (`f942621`).
      - 2.4 section-aware verify-navigate handoff copy (`1a30650`).
      - Full onboarding unit suite 321 green throughout.
    - TODO remaining on m-onboarding: 2.5 income screen "you + spouse" (frontend), 2.8/10 spouse advice line + saving number (tax engine), 2.9 terminal "We've created your personal tax strategy" + "Take me to my tax strategy" button (needs a frontend route-bubble in chooseBubble + confirm completion doesn't auto-nav), 2.3 band/ISA carry (verify in E2E). THEN: build /m bundle, deploy m-onboarding to csjones, live-LLM E2E walk of the SaveTax onboarding, PR Batch 2 → dev.
  - **Batch 2 design (CSJ 2026-06-25):**
    - 2.1: KEEP employer·role·income question (CSJ didn't drop them — /income screen needs them); ADD "(this includes bonuses and commissions)" to the income part; BOLD the question; split into recap-bubble + question-bubble.
    - 2.7: EVERY turn → bubble 1 "what we've heard" recap + bubble 2 the question (bold if it carries supporting detail). Implemented via a new `acknowledgement_text` per state emitted as a separate content event before the question in `OnboardingChatDirector::emitTurnForState`.
    - 2.2: single number = the LOW end (drop `-high`); keep the 3+assets scaling. `buildFunnelRecapPrompt:1234`.
- **Batch 2 — Onboarding** ✅ DONE — PR #575 merged dev `ca0ef78a`, deployed csjones, core live-verified on /m (2.1/2.2/2.3/2.4/2.5/2.7 + canonical verify); 2.6/2.8/2.9 unit+golden-master verified (CSJ accepted, live walk to terminal not required). 5117 suite pass.
- **Batch 3 — Actions single source (3.3 + 4.1 + 4.2 + 4.3 + 4.4):** ✅ BUILT on `m-actions` (#? pending). Commits `1de70d6` (4.1 running-tally count from tracking, 4.2 per-item unlock labels "Unlock pension info"/"Enter your pension details", 4.3 removed per-card "X of Y done", 4.4 exclude completed recs → next-best fills + silent dashboard refetch on check-off) + `142976d` (3.3 Tax Strategy page reads `composed_plan.items` = dashboard's canonical source). CSJ count decision: **running tally** (X=banked, Y=banked+open). Mobile+Gamification 149 + Tax 144 green. Pending: full suite, csjones deploy + browser-verify, PR→dev.
- **Batch 4 — Tax Strategy page (3.1, 3.2, 3.4, 3.5):** ✅ DONE — PR #577 merged dev `a4593ae`, deployed csjones, live-DOM-verified (Available+green, lean copy, back-CTA, composed actions; 3.2 intro gated on onboarding_completed). /m only — desktop web Tax Strategy components flagged for parity, not changed.
- **Batch 5 — Freemium (5.1, 5.2, 5.3+d):** account counts + upgrade links, Fyn count/at-limit, Holistic Plan gate.
- **Batch 6 — Bugs (6.1 reg continuity, 6.2 bank-as-savings, 6.4 goals buttons).**
- **Batch 7 — Cross-surface sweep:** C (partner→spouse), D (edit-details page-specific).
