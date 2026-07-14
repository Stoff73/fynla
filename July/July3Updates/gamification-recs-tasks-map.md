# Gamification · Recommendations · Tasks — full system map & fit-for-purpose assessment

*2026-07-03. Mapped from the dev tip (`3b0828e`) + a live SaveTax walk user on csjones (id 168, `julycsj3@example.com`). Every claim below is either a file:line or something observed on that user.*

---

## 1. The five subsystems (and how they connect)

```
[Engines: agents + strategy catalogues]
        │  recompute on every request
        ▼
(A) RecommendationsAggregatorService ──────────► stable recommendation_id per item
        │                                        (e.g. tax_<strategy>, module+hash)
        ├──► (B) ComposedModulePlanService — tax (+ other modules when flagged);
        │        sequencing/conflicts; NOT persisted; read-only on /tax-strategy
        ▼
(C) NextActionsService (mobile) — merges: real recommendations + KYC "unlock"
    cards + strategy unlocks; ranks; CAPS AT 4; recomputed per request
        │
        ▼  user taps done (dashboard toggleRec → POST mark-done)
(D) recommendation_tracking — row created ONLY on interaction
    (completed / in_progress / dismissed); THE only persisted task state
        │  observer on status='completed'
        ▼
(E) Gamification — point_awards (append-only ledger, dedup-keyed)
    + user_gamification (points, level, streak) + user_milestones
    → level wheel, celebration, achievements badges
```

**Tables:** `recommendation_tracking`, `point_awards`, `user_gamification`, `user_milestones`. **There is NO achievements table** — badges are derived per request. Open actions are never persisted anywhere.

## 2. What each surface shows (they disagree)

| Surface | Content | Completion? |
|---|---|---|
| /m dashboard "Top actions" | NextActionsService top-4 (recs + unlock cards) | tap → mark-done → replaced (replace-on-done) |
| /m dashboard "X of Y actions" | **NOT a task count** — level-progress quarters: `4 − ceil((100−progress%)/25)` (`MobileLevelService`) | n/a |
| /m Achievements "Next" | the SAME top-4 again | tap → opens Fyn with a capture opener |
| /m Achievements "Earned" | 9 derived badges (level, 6× "Added X details" from `data:<cat>:first` awards, actioned-count, streak) | n/a |
| /m Achievements "Milestones" | `user_milestones` rows (net-worth thresholds 10k–5M, goal 25/50/75/100%) | n/a |
| /tax-strategy (web + /m) | composed_plan.items — **read-only, no mark-done** | none |
| Desktop /actions | a THIRD prioritisation (protection-first for user 168) | mark-done |
| Desktop anywhere | no achievements, no milestones, no history | — |

Observed on user 168 straight after finishing the SaveTax onboarding: dashboard said **"0 of 4 actions"** (despite 16 point-awards and Level 4), top actions were four **generic** items (No Will, No LPA, investment preferences, critical illness) — none SaveTax-related because their composed tax plan was empty ("allowances well-utilised").

## 3. Defect register (all reproduced/evidenced on user 168)

| # | Defect | Evidence | Severity |
|---|---|---|---|
| D1 | **Phantom records corrupt achievements.** The capture opener "Help me add my pension details" (Dashboard.vue:759 / Achievements.vue:155, one tap on a retirement unlock card) → Advice Fyn deflection reply ("I can only help with financial planning questions") → yet `delegate_to_capture` ran and `create_pension` persisted an EMPTY "Personal Pension" (`{scheme_name:'Personal Pension', scheme_type:'personal_pension', category:'dc'}` — no value, no provider) → `data:pension:first` +20 → "Added retirement details — Earned" → retirement module silently "unlocked". | ai_audit_events 09:42:12; dc_pensions id 91; award ledger | **P0** — writes invented records, corrupts every downstream surface |
| D2 | **Real captures fail silently.** The user's actual workplace pension ("Beta Ltd Workplace Pension", 5%/5%, salary £80k) was dispatched to `create_pension` during onboarding and **FAILED** — no retry, no user-visible error, no record. The user's true action earned nothing; the phantom earned the badge. | ai_audit_events 09:23:35 dispatched→failed | **P0** — data loss in the core capture path |
| D3 | **"X of Y actions" is not an action count.** It's level-quarters. A user completing 10 real actions at a level boundary still sees "0 of 4". (The framing was approved as "actions to next level" in #588 — but as the only progress figure it misreads as a task tally.) | MobileLevelService:38-70; user 168 "0 of 4" post-onboarding | P1 (product) |
| D4 | **No history anywhere.** `point_awards` is a perfect append-only activity ledger and is never displayed. `GET /api/recommendations/completed` + the holistic equivalent exist and are **called by no UI**. Completed actions just vanish (replace-on-done). | agent map §5; grep | P1 |
| D5 | **Milestones only fire on /m dashboard load** (`MobileDashboardController::detectMilestones`) — a user who never opens the /m dashboard earns none; a user below £10k with no ≥25% goals sees an empty page (CSJ's case). Only two flavours exist (net-worth, goal %) — nothing for SaveTax progress. | MilestoneDetectionService:28-114 | P1 |
| D6 | **Achievements page "Next" section is recommendations**, duplicating the dashboard top-4 — not achievements. On an "achievements you've earned" page, four warning cards read as noise (CSJ: "dubious"). | Achievements.vue:44-66 | P1 (product) |
| D7 | **Badge label bugs**: "Actioned 0 recommendations" as a title; "1-day check-in streak — Not yet earned" (earn threshold is 3 days but the label leads with the current streak); "Reached Organiser" has `earned_at: null` (no date shown). | MobileAchievementsController:59-102 | P2 |
| D8 | **Tax-strategy items are read-only on the tax page** but the SAME items are markable on the dashboard (via the aggregator) — completing one on the dashboard doesn't visibly change the tax page and vice versa there is no affordance. | TaxStrategyController; StrategyRecommendationList.vue | P1 |
| D9 | **Three surfaces, three action lists** (dashboard top-4 vs achievements Next vs desktop /actions priority list) with different ranking/filtering — no single "my actions" model. | §2 observations | P1 (product) |
| D10 | **SaveTax user lands in generic-land.** After a tax-focused funnel+onboarding, if the composed plan is empty the user's actions are Will/LPA/critical-illness warnings; nothing acknowledges the onboarding just completed; onboarding effort is invisible outside the level number. | user 168 walk | P1 (product) |

## 4. What IS sound (keep as the substrate)

- `point_awards` ledger: append-only, dedup-keyed, per-event `created_at`, human-translatable keys (`onboarding:campaign_dob`, `data:savings_account:first`, `milestone:net_worth:0:10000`) — a ready-made activity history.
- `user_milestones`: proper persisted table with `achieved_at`, idempotent detection.
- `recommendation_tracking` keyed on **stable recommendation ids** — completion survives recomputes.
- The award triggers (14 models, onboarding steps, recommendations, milestones, login/streak) are all wired and firing (verified: 18 awards on the walk user).
- Level engine + celebration cross-surface delivery works.

## 5. Fit-for-purpose verdict for SaveTax

**Not yet.** The engine layer (points/levels/awards) is solid, but the user-facing loop breaks down at exactly the points CSJ hit: achievements derive from pollutable data (D1/D2), progress framing doesn't reflect work done (D3/D10), completions have no home (D4), milestones are thin and passively triggered (D5), and "actions" is three different lists (D6/D8/D9).

## 6. Proposed work packages (for CSJ to prioritise — nothing built yet)

1. **WP-1 Capture integrity (P0):** inline capture must never create a record from an intent-only message (require ≥1 substantive field before any `create_*`; otherwise Fyn asks for details); fix the `create_pension` validation failure for workplace-pension inputs (zeros rejected?) and make failed captures surface to the user + retry. Kills D1/D2 and protects every future campaign.
2. **WP-2 One actions model:** a single "My actions" source (aggregator) consumed by dashboard/achievements/desktop/tax-strategy with per-surface filters; mark-done available wherever an action is shown; completed items move to a visible "Done" list (wire the existing `/completed` endpoints). Kills D8/D9, half of D4.
3. **WP-3 History feed:** an activity page rendering `point_awards` (label map per dedup-key prefix) + completed recommendations + milestones, newest-first. Kills D4; gives onboarding effort visibility (D10).
4. **WP-4 Achievements tidy:** drop the "Next" section from the achievements page (or retitle the page); persist grant timestamps (small `user_achievements` grant table or derive from the granting award's `created_at`); fix streak/actioned-count/level labels. Kills D6/D7.
5. **WP-5 Milestones expansion:** move detection to an event/observer path (fires on data change, not dashboard load); add campaign milestones ("tax profile complete", "first £X of tax savings identified", "first action completed"); backfill note for existing users. Kills D5, feeds D10.
6. **WP-6 SaveTax landing state:** post-onboarding, seed the actions surface from the campaign (composed plan items when present; otherwise campaign-relevant next steps), and show the onboarding as completed work (via WP-3). Kills D10.

**Extension note for future campaigns:** WP-1/2/3 are campaign-agnostic substrate — build once, every campaign inherits. The achievement badge categories (6 module badges) and milestone flavours are the only hardcoded lists a new campaign would extend (one array each).

---
*Companion doc: `campaign-blueprint.md` (same folder) maps the onboarding/campaign seams.*
