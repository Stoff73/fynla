# Plan — `15-post-sprint-priorities-plan.md` (post-sprints 0-4 priorities)

> **Canonical contract:** [`../spec/00-canonical.md`](../spec/00-canonical.md).
> **Branch:** all implementation commits on `feature/fyn-persona-split` (or a child branch off it). This plan starts ONLY after Sprints 0-4 are GREEN per their own verification rollups.
> **Direction:** CSJ, 2026-04-26 — captured during BS-05 review when the BS-05 stub assumed entry-source CTAs that are not yet shipped on the landing page. CSJ confirmed those CTAs are the Lifestyle Landing Pages workstream, paired with the parallel Campaign Landing Pages workstream, both queued after sprints 0-4 GREEN.

---

## Purpose

Capture the two parallel landing-page workstreams that sit *outside* the Fyn v2 canonical-contract sprint stack but inherit its onboarding architecture. Both feed users into the same Fyn-driven onboarding flow defined in Sprints 0-4, but with a focused entry that pre-selects the user's path so Fyn opens with a personalised welcome to the chosen life stage or campaign rather than asking the canonical "Follow a journey vs Pick a focus" path-choice bubbles.

This file is the queue, the gate, and the architectural-extensibility brief. Per-task implementation detail lives in the workstream-specific plan files (existing campaign draft + future lifestyle draft).

---

## Gate (when this plan starts)

This plan does NOT begin until **all of the following are GREEN**:

- Sprint 0 verification rollup (`S0.17`) — full Pest green, Architecture suite green, `php artisan ai:audit:verify-chain` → `chain_valid: true`, Browser matrix 20/20, Rubric-A 13-15/40.
- Sprint 1 verification rollup (`S1.V1`) — Rubric-A ≥17/40 🟠 Limited beta.
- Sprint 2 verification rollup (`S2.V1`) — Rubric-A ≥22/40.
- Sprint 3 verification rollup (`S3.V1`) — local-first verification + dev deploy to `csjones.co/fynla` clean.
- Sprint 4 verification rollup (`S4.V1`) — production live on `https://fynla.org`, 48-hour soak clean, Rubric-A 28-30/40 🟡 Commercial-ready.

Per memory `critical_browser_testing_law.md` and `feedback_never_claim_verified.md`: no "ready to start post-sprint priorities" claim until each sprint's verification artefacts are committed and reviewed.

---

## Architectural extensibility — applies to current Sprint 0-4 work

CSJ direction 2026-04-26: whatever we are currently building must be adjustable for different entry points, different campaigns, and different outcomes. The current onboarding architecture is already shaped this way and must stay that way:

- **`config('onboarding.journey_map')`** is the canonical entry-source map. It is config-driven and additive. The Pest sibling test `EntrySourceJourneyMapTest::it honours runtime-added journey-map entries` (S0.15.2) pins this contract — adding a new key/value pair must not require any controller / director / state-machine change.
- **`AiChatController::startOnboarding`** reads `request->input('from')` and looks it up in `journey_map`. Unknown / missing `from` falls through to `STATE_PATH_CHOICE`. The handler is map-driven, not switch-driven.
- **`OnboardingChatDirector::emitFirstTurn`** accepts an optional `?string $stateId = null` (defaults to `STATE_PATH_CHOICE`) so the controller can hand it any pre-resolved starting state. New entries that need to land at a non-default state can do so via the same hook.
- **`users.onboarding_fyn_context`** is a JSON column already cast to `array` (User.php line 129). Both the campaign workstream (per `CSJ-CAMPAIGN-LANDING-PLAN.md`) and the lifestyle workstream piggyback on this column for entry-source context. No new column needed.
- **`OnboardingPromptBuilder::buildAssetCapturePrompt`** is the per-turn prompt-composition seam. The campaign workstream layers `applyCampaignBias` here. A future lifestyle layer would attach the same way.
- **Shared base-KYC trunk**: regardless of entry source (canonical / lifestyle / campaign), the user always walks the same base-KYC states (`STATE_BASE_PERSONAL` → `STATE_BASE_SPOUSE` → `STATE_BASE_DEPENDANTS`/`_DETAIL` → `STATE_BASE_EMPLOYMENT` → `STATE_BASE_WORK` → `STATE_BASE_RETIREMENT_DATE` → `STATE_BASE_EXPENDITURE`). Divergence happens **after** expenditure based on the entry source's pre-selected journey / campaign. This is invariant — any sprint task that introduces a parallel base-KYC track for an entry source breaks this contract.

**No current-sprint code change needed** for extensibility — the architecture above is already in place. This section exists to make sure no Sprint 0-4 task tightens it (e.g. hardcodes the four canonical journey-map keys, or assumes only one shape of `from` value, or gates on `users.onboarding_fyn_path === 'journey'` in a way that excludes future `'campaign'` entries).

---

## PSP-LS — Lifestyle landing pages

- **Objective:** Ship one landing page per life-stage journey id in `config('onboarding.journey_map')`. Initial set: 4 pages — `Starting Out` (`from=budgeting`), `Building Foundations` (`from=goals`), `Protecting What Matters` (`from=protection`), `Planning Your Future` (`from=retirement`). Possibly a 5th `Enjoying Your Wealth` page if/when an `enjoying` journey id is added to the map. Each page routes its primary CTA to `/register?from={journey_id}`. Registration carries `from` through to the dashboard handoff and `POST /api/ai-chat/onboarding/start`. Fyn opens with a personalised welcome to that life stage and skips `STATE_PATH_CHOICE` + `STATE_JOURNEY_SELECTION`, landing the user at `STATE_BASE_PERSONAL`.
- **Existing seeds (do not rebuild):**
  - `resources/js/views/Public/stages/StartingOutPage.vue`, `BuildingFoundationsPage.vue`, `ProtectingAndGrowingPage.vue`, `PlanningYourFuturePage.vue`, `EnjoyingYourWealthPage.vue` — already exist with SEO content, but currently route their CTAs to `/register?stage={career_stage}` (which feeds the legacy `Onboarding.vue` flow). The lifestyle workstream either (a) repoints these CTAs to `/register?from={journey_id}` and retires the `?stage=` path, OR (b) ships fresh pages alongside. Decision deferred to the workstream.
  - `app/Http/Controllers/Api/AiChatController::startOnboarding` already handles the `from` parameter end-to-end (S0.15.2).
  - `tests/Feature/Onboarding/EntrySourceJourneyMapTest.php` already pins the controller contract.
- **Frontend plumbing required:**
  - `resources/js/views/Register.vue` — extend the post-verify routing decision so `from in journey_map.keys()` routes to `Dashboard` with `?openFyn=journey&from={journey_id}&newUser=1` (currently only `from=fyn` triggers the Dashboard handoff; everything else falls through to legacy `/onboarding`).
  - `resources/js/views/Dashboard.vue` — when `openFyn=journey` arrives with a `from` query param, dispatch `aiChat/startOnboardingConversation` with the `from` value.
  - `resources/js/store/modules/aiChat.js::startOnboardingConversation` — accept and forward `from` into the service call.
  - `resources/js/services/aiChatService.js::startOnboardingStream` — accept `{from}` in the options bag and serialise it into the POST body (currently hardcoded `'{}'`).
- **Director-side personalised welcome:**
  - `app/Services/Onboarding/OnboardingChatDirector.php` — when the user lands at `STATE_BASE_PERSONAL` via the journey-map route (`onboarding_fyn_path === 'journey'` + `onboarding_fyn_selection !== null`), open the first turn with a one-line welcome that references the chosen life stage by name (e.g. "Welcome — let's get started on Protecting What Matters") rather than the generic base-personal opener. Copy lookup keyed on `onboarding_fyn_selection`, sourced from a small lookup map (config or i18n file) so adding a new lifestyle entry is data-only.
- **Browser verification:** at this point `BS-05` becomes drivable end-to-end via the canonical real-user flow. Update the BS-05 stub script with the per-page CTA labels confirmed during PSP-LS implementation, then walk the 5 sub-cases through Playwright per CLAUDE.md Rule #15.
- **Out of scope of PSP-LS:** Campaign-specific bias (lives in PSP-C). Variant A/B testing on lifestyle pages (lives with PSP-C if it ships). Awin / LinkedIn pixel integration (PSP-C territory). Migrating existing `/stage/*` SEO pages to a data-driven model (consider only if PSP-C migrates `/savetax` / `/biggerpension` / `/paymortgage` to dynamic — keep both workstreams' migration calls coordinated).

---

## PSP-C — Campaign landing pages

- **Objective:** Ship the dynamic campaign landing-page system per CSJ's existing draft plan: data-driven `/c/{slug}` route, per-campaign scripted Fyn chat (no LLM, canned bubbles), end-to-end attribution (UTM parameters → cookie → `landing_visits` table → `users.acquisition_*` columns → `users.onboarding_fyn_context['campaign']`), A/B variant infrastructure, admin CMS, LinkedIn Insight Tag integration.
- **Source plan:** `CSJ-CAMPAIGN-LANDING-PLAN.md` at the repo root (Last updated: 2026-04-25, Status: Draft for review). 617 lines covering: goals, what already exists, architecture, schema, 6-phase rollout, file-level checklist, scope estimate (~7-8 days for full system, ~3-4 days for first usable end-to-end on dev). When PSP-C starts, that draft becomes the canonical campaign plan — move it into `April/April24Updates/plan/16-campaign-landing-plan.md` (or similar number) and finalise the open questions in §13 before kickoff.
- **Existing seeds (do not rebuild):**
  - `resources/js/views/Public/CampaignPage.vue` — already serves `/savetax`, `/biggerpension`, `/paymortgage`. Slug-hardcoded, due for data-drive in Phase 5.
  - `resources/js/components/Public/StaticFynChat.vue` — public no-auth chat shell. Right starting point for the scripted Fyn variant.
  - `resources/js/components/Fyn/FynQuickReplies.vue` and `resources/js/components/Shared/AiMessageContent.vue` — reusable as-is.
  - `app/Http/Controllers/Api/AuthController::register` already accepts `registration_source` and writes to `PendingRegistration`. Extension point for the campaign attribution copy.
  - `app/Services/Onboarding/OnboardingPromptBuilder::buildAssetCapturePrompt` — campaign-bias injection point per the source plan §3.
  - `users.onboarding_fyn_context` — campaign context lands here as `['campaign' => ...]`.
- **Architectural touchpoints (must align with PSP-LS):**
  - Both lifestyle and campaign entries should resolve to a config-driven `entry_source` shape that the director can read uniformly. The simplest division: `journey_map` (lifestyle) and `campaign_map` (campaign), each keyed on the URL `from`/slug, each resolving to a journey id and an optional welcome-copy override. Implementation detail deferred to PSP-C kickoff once §13 open questions are closed.
  - Per CSJ direction: the user never feels a switch between lifestyle vs campaign onboarding — both feed into the same Fyn surface, both walk the same base-KYC trunk, both diverge after expenditure based on the entry source.
- **Out of scope:** non-LinkedIn paid channels until LinkedIn end-to-end is proven. Multi-touch attribution beyond last-touch-wins (per source plan §13.5).

---

## PSP-S — Shared plumbing + welcome-copy lookup

This sub-task captures the small amount of shared frontend + director-side plumbing both PSP-LS and PSP-C need before either can ship. Whichever workstream lands first owns this work; the other inherits it.

- `resources/js/views/Register.vue` — generalise the post-verify routing decision so any `from` query param that resolves to a known entry source (lifestyle journey id OR campaign slug) routes to `Dashboard` with the `from` preserved. Today this is hardcoded to `from === 'fyn'`.
- `resources/js/services/aiChatService.js::startOnboardingStream` — accept `{from}` in the options bag and serialise it into the POST body.
- `resources/js/store/modules/aiChat.js::startOnboardingConversation` — accept and forward `from`.
- `app/Services/Onboarding/OnboardingChatDirector.php` — first-turn personalised-welcome composition keyed on `onboarding_fyn_path` + `onboarding_fyn_selection` (lifestyle path) or `onboarding_fyn_context['campaign']` (campaign path). Copy lookup map sourced from config or a per-entry-source dictionary so adding a new entry is data-only.
- Browser test rollup: BS-05 GREEN end-to-end (lifestyle), plus a new BS-NN scenario (or extension of an existing one) that walks a single canonical campaign URL → register → first onboarding turn → verifies the campaign-biased welcome copy. The existing campaign plan §11 calls for browser-test coverage; coordinate with PSP-C kickoff.

---

## Verification — when is this plan "done"

- All four lifestyle landing pages (or 5 if `enjoying` joins the journey map) live on `fynla.org` with their CTAs routing through the journey-map flow.
- Campaign landing-page system live per Phases 1-5 of `CSJ-CAMPAIGN-LANDING-PLAN.md` (Phase 6 LinkedIn tag may queue separately).
- BS-05 GREEN end-to-end via canonical real-user flow, screenshots committed, stub docblock updated.
- New campaign-equivalent BS-NN scenario GREEN end-to-end.
- Pest sweep across `tests/Feature/Auth tests/Feature/AI tests/Feature/Fyn tests/Feature/Onboarding tests/Feature/Campaign tests/Architecture` GREEN with no regressions.
- 14-day acquisition-funnel soak clean on `csjones.co/fynla` before promoting to `fynla.org`.

---

*End of plan for post-sprint priorities. Sprints 0-4 own the canonical Fyn v2 contract delivery; this plan owns the entry-point breadth that follows.*
