# Design — `/m` Gamification + Recommendations Unification

**Date:** 2026-06-10
**Branch (work off):** `dev`
**Status:** Approved design — pending implementation plan
**Owner decisions captured:** CSJ, this session

---

## 1. Problem

On the `/m` mobile dashboard (`resources/mobile/views/Dashboard.vue`), the recommendations and gamification do not reflect real per-user data:

1. **Gamification is structurally blank for the surface CSJ tests.** `PointsService::award()` hard no-ops for preview users (`PointsService.php:29-31`) and the percentile query excludes preview users (`MobileLevelService.php:104`). Every preview persona therefore has **no `user_gamification` row, no `point_awards`, no `recommendation_tracking`** → the wheel shows **Level 1 / 0% / "0 of 0 actions"** and the percentile clamps to a constant for everyone.
2. **The "You're ahead of X% of people" box** ranks by gamification-level distribution, which (per #1) is near-empty → identical, meaningless value for all users. It also measures *engagement*, not the user's financial/planning situation.
3. **Recommendations are real-but-ungated.** They do vary slightly per user (ISA amounts, emergency-fund state), but the **estate module emits generic "Establish discretionary trust within NRB" / "Start using annual gifting exemption" for everyone** — including a 25-year-old student — because they come unconditionally from `implementation_timeline` (`RecommendationsAggregatorService.php:106-118`). These dominate the short list, so it reads as "same for everyone". The aggregator is **shared with desktop** (`/api/recommendations`), so the bug is everywhere.
4. **Gamification is barely surfaced** — only the level wheel + the level-up celebration. The main box shows "0 of 0 actions", links nowhere, and there is no achievements/milestones view.
5. **The milestone green banner sits too high** and clips on the sides (`dashboard.css:1694-1721`).

## 2. Goals

- Recommendations on `/m` are genuinely per-user and **KYC-gated across all six modules**, tied to the existing recommendation engine.
- Gamification, recommendations, actions and milestones become **one interlinked system**.
- The "ahead of X%" box reflects the user's **planning progress**, not raw engagement.
- Gamification is **surfaced** via a new Achievements panel (achievements + next actions + milestones), reachable from the wheel.
- Fyn proactively offers to fill KYC gaps via a lightweight, dismissible speech bubble.
- Preview personas show **real, differing** gamification/recommendations so CSJ can test it.

Delivered as **one combined piece** (single spec → single plan; implementation may phase internally).

## 3. Owner decisions (this session)

| # | Decision | Choice |
|---|----------|--------|
| A | "Ahead of X%" metric | **Planning-progress composite** (modules-with-data + recs actioned + milestones + KYC completeness). CSJ-approved Rule #12 carve-out. |
| B | Box ↔ list relationship | **Box = top 4, ONE unified list.** Box items and the recs list below are the same ≤4 items, no duplication. |
| C | KYC-gated module UX | **Both:** real recs for satisfied modules **and** unlock-prompts for gated ones, visually distinct. Tied to Fyn. |
| D | Fyn KYC bubble | Dismissible speech bubble; tap **opens the Fyn chat pre-seeded** with the capture prompt (chat opens at the user's choice); dismiss → user proceeds manually. |
| E | Achievements panel | **Two tabs:** "Achievements" (earned badges/levels + a "Next" section of the ≤4 actions) and "Milestones" (financial milestones). |
| F | Work split | **One combined piece.** |
| G | Gating scope | **Fix it everywhere** — gate inside the shared aggregator (corrects desktop too). |
| H | Achievement badges (Rule #15) | **Icons/illustration approved** for the Achievements panel as an intentional gamification design (Rule #15 carve-out for this surface). |
| I | Persona seeding | **Seed personas with varied data** so the wheel/percentile/achievements differ persona-to-persona. |
| J | Dev model | This dev work to run on **Claude Fable 5** (`claude-fable-5`) — CSJ switches via `/model`; not an app/Fyn provider change. |

## 4. Architecture

```
                          ┌─────────────────────────────────────────┐
                          │  NextActionsService (new)                │
                          │  → ONE ranked list, max 4                │
   gated recs ──────────► │  items = recommendations ∪ unlock-prompts│ ──┐
   (per module, KYC-gated)│  ranked by benefit / unlock-weight       │   │
                          └─────────────────────────────────────────┘   │
                                                                         ▼
   RecommendationsAggregatorService (shared, now KYC-gated) ──► MobileDashboardController
                                                                 ├─ level wheel (ring = points→next level)
   PlanningProgressService (new) ──► percentile ("ahead of X%") ─┤   sublabel = X of Y of the ≤4 actions
                                                                 ├─ recommendations list = the SAME ≤4
   PointsService / UserGamification (existing engine) ──────────►┤   wheel/box → button → Achievements
                                                                 └─ Fyn KYC bubble (md-fyn-nudge)

   GET /api/v1/mobile/achievements (new) ──► Achievements.vue (new view, /m/app/achievements)
       ├─ Achievements tab: earned badges/levels + "Next" (the ≤4 actions)
       └─ Milestones tab: financial milestones (UserMilestones)
```

### 4.1 Unified Next-Actions — `App\Services\Mobile\NextActionsService` (new)
Single source for the ≤4 ranked actions. Each item:

```php
[
  'id'      => string,          // stable id (rec id or "unlock:{module}")
  'type'    => 'recommendation' | 'unlock',
  'module'  => 'protection'|'savings'|'investment'|'retirement'|'estate'|'goals',
  'title'   => string,          // plain text, British spelling, no acronyms (Rule #9), no icons
  'meta'    => string,          // e.g. "You could save £1,240" or "2 quick questions"
  'value'   => float,           // ranking weight
  'done'    => bool,            // from recommendation_tracking
  'action'  => [                // what tapping does
     'kind'   => 'rec_chat' | 'fyn_capture' | 'deeplink',
     'payload'=> ...,           // rec title / capture intent / route
  ],
]
```

Ranking: recommendations by `potential_benefit` (fallback `priority_score`); unlock-prompts get a high fixed weight so the highest-value gated module surfaces. Cap = **4**. This exact list is returned to the dashboard for both the wheel box and the recs list, and to the Achievements "Next" section.

### 4.2 KYC-gating the recommendation engine (decision G — shared)
Gate **inside** `RecommendationsAggregatorService::aggregateRecommendations()`:
- For each module, consult the existing gate (`PrerequisiteGateService::enforce(module, user)` / the module gates `KycGateChecker` already uses).
- **Gate satisfied** → include that module's real recommendations (unchanged path).
- **Gate not satisfied** → the module contributes **no generic recs**; instead `NextActionsService` derives an `unlock` item from the gate's `missing` fields.
- Specifically removes the unconditional estate `implementation_timeline` emission for users without estate KYC.

This is a shared-service behaviour change (desktop `/api/recommendations` benefits too). Desktop UI must be re-verified for no regressions (it already tolerates variable-length lists).

**Coverage gap to close (important):** the aggregator today only contributes **protection, savings, retirement, estate** (`RecommendationsAggregatorService.php:34-122`). `PortfolioAnalyzer` (investment) is **injected but never called**, and **goals is absent entirely**. "KYC-gated recommendations for all modules" therefore requires *adding* investment and goals to the aggregator (each behind its own KYC gate, contributing real recs or an unlock-prompt) — not merely gating the existing four. This is part of this piece.

### 4.3 Planning-progress percentile — `App\Services\Mobile\PlanningProgressService` (new)
`scoreFor(User): int` → composite 0–100 from weighted sub-scores:
- modules-with-data (of 6),
- recommendations actioned (`recommendation_tracking` completed),
- milestones reached (`UserMilestones`),
- KYC completeness (universal + per-module gates passed).

`percentileFor(User): int` → rank of `scoreFor` across the **same preview-class cohort as the viewer** (preview personas ranked among preview personas; real users among real users), bounded 1–99. This keeps preview testing meaningful **and** keeps prod percentiles free of persona pollution. Cached (short TTL); cache key includes the preview class. Replaces `MobileLevelService::percentile()`.

### 4.4 Level wheel + "X of Y actions" (`MobileLevelService`, adjusted)
- Ring progress = `LevelService::progress(points)` — source unchanged.
- "X of Y actions" = of the ≤4 surfaced next-actions, count `done`. Never "0 of 0" when actions exist; a genuinely-empty user shows a "Get started" affordance instead of "0 of 0".
- The box becomes a button routing to `/m/app/achievements`.

### 4.5 Achievements panel (decision E, H)
- **View:** `resources/mobile/views/Achievements.vue`, wrapped in `MobileLayout` (Rule #13), route under `/m/app/`.
- **API:** `GET /api/v1/mobile/achievements` → `MobileAchievementsController` (new).
  - **Achievements tab:** earned badges derived from the `point_awards` ledger + level: first-data-per-module, recommendations-completed count, login streaks, level reached. Plus a **"Next"** section = the ≤4 `NextActionsService` items.
  - **Milestones tab:** financial milestones from `UserMilestones` (net-worth thresholds, goal completions), achieved + upcoming.
- Badges **may use icons/illustration** (Rule #15 carve-out approved for this surface). Note: this carve-out is for the **Achievements panel only** — the Fyn bubble and dashboard cards stay icon-free.

### 4.6 Fyn KYC bubble (decision C, D)
- Reuse `md-fyn-nudge`. When the top next-actions include an `unlock` item, show a **dismissible** Fyn speech bubble with a plain-text offer ("I can add your pension details — want to?").
- **Tap** → open the Fyn chat overlay **pre-seeded** with the capture prompt for that module (routes through the existing onboarding `delegate_to_capture` / inline-capture entry; the user chose to open it).
- **Dismiss** → hide; bubble does not nag every load (debounce/once-per-session).
- **Text only** — the Fyn chat surface is icon-banned (Rule #15). No emoji/glyphs in the bubble.

### 4.7 Milestone banner CSS (`dashboard.css:1694-1721`)
Lower it (increase top offset to clear the hero), add `max-width` and adequate left/right insets so all four sides are visible on narrow viewports. Pure CSS; no behaviour change.

### 4.8 Seeding (decision I)
Extend the preview-persona seeder(s) to give each persona realistic, **differing** `user_gamification` (points/level), `point_awards` (varied sources), and `recommendation_tracking` (mix of completed/pending) consistent with that persona's life stage — so the wheel, percentile and achievements visibly differ between e.g. a student and a retired couple. Real (non-preview) users continue to earn live via existing hooks; no backfill of real users in this piece.

## 5. Data flow (mobile dashboard request)

```
GET /api/v1/mobile/dashboard
  ├─ MobileDashboardAggregator (modules, net worth, alerts, insight)   [unchanged]
  ├─ MobileLevelService::levelFor()      → ring + "X of Y of the ≤4 actions"
  ├─ PlanningProgressService::percentileFor() → "ahead of X%"           [replaces old percentile]
  ├─ NextActionsService::build()         → ≤4 unified items (recs ∪ unlock)
  │     └─ RecommendationsAggregatorService (KYC-gated)                 [shared fix]
  └─ MilestoneDetectionService           → new_milestones banner        [unchanged detection]
```

The dashboard response carries the **same ≤4 list** consumed by the wheel box and the recs list; a flag on each `unlock` item drives the Fyn bubble.

## 6. Components & files

**New**
- `app/Services/Mobile/NextActionsService.php`
- `app/Services/Mobile/PlanningProgressService.php`
- `app/Http/Controllers/Api/V1/Mobile/MobileAchievementsController.php`
- `resources/mobile/views/Achievements.vue` (+ scoped CSS)
- Seeder additions for preview-persona gamification/tracking
- Tests (see §8)

**Modified**
- `app/Services/Coordination/RecommendationsAggregatorService.php` — KYC gating per module **and add investment (`PortfolioAnalyzer`) + goals (`GoalsAgent`) so all six modules contribute**
- `app/Services/Mobile/MobileLevelService.php` — percentile delegated to `PlanningProgressService`; "X of Y" tied to the ≤4 actions
- `app/Http/Controllers/Api/V1/Mobile/MobileDashboardController.php` — use `NextActionsService`; carry unlock flags
- `resources/mobile/views/Dashboard.vue` — wheel→button, unified list, Fyn unlock bubble
- `resources/mobile/views/dashboard.css` — milestone banner position
- `resources/mobile/store.js` + mobile router — achievements route/state

## 7. Constraints honoured

- **Rule #2** — any tax/allowance figures via `TaxConfigService` (ISA, gates), never hardcoded.
- **Rule #9** — no acronyms in user-facing text (spell out "Individual Savings Account" etc., ISA excepted).
- **Rule #12** — gamification carve-out; planning-progress percentile is CSJ-approved; **no financial-quality scores** anywhere.
- **Rule #13** — `Achievements.vue` wraps in `MobileLayout`.
- **Rule #15** — icons approved **only** on the Achievements panel; Fyn bubble + dashboard cards stay icon/emoji-free.
- **Preview isolation (Rule #1)** — seeding only touches `is_preview_user = true` rows; percentile cohort is preview-class-scoped.

## 8. Testing

- **Unit:** `NextActionsService` (ranking, cap=4, recs∪unlock); `PlanningProgressService` (composite + percentile bounds, cohort scoping); aggregator KYC-gating (student gets NO estate trust rec; data-complete user does get real recs).
- **Feature:** `GET /api/v1/mobile/dashboard` returns ≤4 unified actions + dynamic percentile; `GET /api/v1/mobile/achievements` returns both tabs.
- **Seeder:** personas have differing gamification/tracking after `db:seed`.
- **Browser (Rule #14, mobile `/m`):** load `/m` as ≥2 personas (student vs retired) → verify different wheel/percentile/actions; tap wheel → Achievements panel with both tabs; trigger a Fyn unlock bubble → tap opens pre-seeded chat, dismiss hides it; milestone banner fully visible. Interact, don't just snapshot.

## 9. Acceptance criteria

1. Two different personas show **different** wheel state, percentile, and next-actions on `/m`.
2. A KYC-incomplete module shows an **unlock prompt**, not a generic rec; a complete module shows a **real** rec.
3. A student no longer receives "Establish discretionary trust within NRB" (verified on mobile **and** desktop).
4. The wheel box is a button → Achievements panel (Achievements + Milestones tabs render with real data).
5. "Ahead of X%" varies by planning progress, not a constant.
6. Fyn unlock bubble is dismissible and opens a pre-seeded chat on tap; no icons.
7. Milestone banner is fully visible (all sides) and sits below the hero.
8. The ≤4 actions in the box are identical to the recs list below.
9. All six modules can contribute — a user with investment and goals KYC satisfied can receive investment/goals recommendations (previously impossible: investment was never called, goals absent).

## 10. Out of scope / flagged

- `BelongsTo` null-array-offset deprecation in the estate path — pre-existing; flag only.
- Backfilling real users' historic data-entry points — not in this piece.
- Repointing Fynla's Fyn provider — unrelated; app stays on xAI/grok.
