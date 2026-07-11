# SaveTax — Recommendations · Actions · Gamification · Nudges: the integrated-unit map

*2026-07-03, mapped from the dev tip (`a6d0009`, post-#594–#602). Every claim is file:line-verified. This is the CURRENT state — the template to copy for future campaigns (pensions, protection, investment). Companion: `wp5c-milestones-spec.md` (the expansion spec this map informs).*

*Supersedes the pre-WP morning map (`gamification-recs-tasks-map.md`) — that documented the D1–D10 defects; this documents the system after WP-1–6 + WP-5b fixed them.*

---

## 1. Where recommendations come from (generation)

```
Module engines (7)                     TaxStrategyCalculator etc.
      │
      ▼
RecommendationsAggregatorService  ──── stable recommendation_id per item
(app/Services/Coordination/…:40-264)     tax:    'tax_' + type            (…:237)
      │                                  others: module + '_' + sha1-16   (…:293)
      ├──► ComposedModulePlanService (…:27-55) — per-module plan via
      │    ModuleStrategySource adapters; returns items + combined_annual_saving
      │    + locked[] (locked = missing data, surfaced as unlock prompts, :314)
      │
      │    StrategyPlanComposer (…:45-196) = the PRIORITY + SEQUENCE brain:
      │      1. sort by estimatedAnnualTaxSaved desc          (:51-61)
      │      2. bounded pass respecting do_before edges       (:63-82)
      │      3. conflict pairs resolved by max saving; loser
      │         excluded + conflict_note                      (:84-162)
      │      4. ISA allowance pre-allocation (IsaAllowanceAllocator, :127-131)
      │
      └──► ComposedTaxPlanService::forUser() — thin tax facade (…:27-29)
```

- **Gating**: `PrerequisiteGateService::enforce()` (app/Services/PrerequisiteGateService.php:47-60) decides per module whether recommendations can be generated at all; a closed gate becomes an "unlock" card instead of recommendations.
- **Nothing is persisted** — plans recompute per request. The ONLY persisted action state is `recommendation_tracking` (interaction rows), which survives recomputes because ids are stable.

## 2. Where priorities come from (ranking)

**One ranking brain since WP-2: `NextActionsService` (app/Services/Mobile/NextActionsService.php).**

| Step | What | Where |
|---|---|---|
| Merge | recommendations (completed filtered OUT) + module unlock cards (weight = `config('gamification.unlock_action_weight')`, 65) + tax strategy-level unlock cards (weight 70, max 2 — `MAX_STRATEGY_UNLOCKS`) | :66-79, :321-393 |
| Sort | value descending (value = `potential_benefit` if numeric, else `priority_score`), module-name tie-break | :74-75, :280 |
| **Campaign affinity (WP-6)** | if `users.funnel_answers` is non-empty (= SaveTax funnel user) → tax-module items sort FIRST, value order preserved within tiers; applied BEFORE any cap so a tax item can always reach the top card | :227-241; test `tests/Unit/Services/Mobile/CampaignAffinityTest.php:35-51` |

So for a SaveTax graduate the priority chain is: **campaign(tax-first) → £ value → module name**, with the £ value of tax items coming from `StrategyPlanComposer`'s saving estimates. A non-campaign user gets pure value ranking. **This is the campaign seam to copy**: a future pensions campaign = the same affinity function preferring `retirement` items for that campaign's funnel users.

## 3. How actions are surfaced (per surface)

| Surface | Source | Cap | Affinity |
|---|---|---|---|
| /m dashboard top card ("Top actions") | `NextActionsService::build()` | **4** (`MAX_ITEMS`, :22, :43) | yes |
| /m dashboard carousel (focus areas) | `focusAreas()` — top card ≤4 + per-module cards ≤4 each | **4 per card** (:105, :147) | yes (top card) |
| Desktop `/actions` | `GET /api/recommendations/actions` → `buildAll()` — **uncapped** (RecommendationsController:300) + completed list | none | yes |
| /tax-strategy (web + /m) | `TaxStrategyController::show()` → `ComposedTaxPlanService` directly (full plan: items + locked + combined saving), completion stamped via `attachCompletionState()` with the SAME stable ids (:65-96) | none | n/a (it IS the tax plan) |
| /m Achievements "Done" | `MobileAchievementsController::completedActions()` — `recommendation_tracking` completed, newest first | **50** (:89) | no (history) |

**Post-WP-2 verdict: the "one actions model" is real for OPEN actions** — dashboard, desktop, and the unified endpoint all rank through `NextActionsService`; the tax page consumes the same composed plan the aggregator does and shares completion ids. Deliberate remaining differences: the tax page shows the *whole* plan rather than a top-N slice, and history surfaces don't apply affinity (they're backward-looking).

## 4. Action lifecycle (the loop)

1. **Surface** — ranked open actions (above).
2. **Act** — `POST /api/recommendations/{id}/mark-done` (also `in-progress`, `dismiss`) → `recommendation_tracking` row status `completed` + `completed_at` (RecommendationsController:132-243).
3. **Replace-on-done** — completed ids are filtered out of every open list on the next read (NextActionsService:250-266); the next-best item bubbles up. Completed items remain visible: /m Achievements "Done", `GET /api/recommendations/completed`, and the unified `/actions` payload's `completed` array.
4. **Award** — the completion triggers gamification (see §5) — points, possible level-up, possible milestone.
5. **Re-rank** — plans recompute; a completed tax strategy also shows as completed on /tax-strategy because the ids match.

## 5. Gamification tie-in (the loop IS wired — engine level)

Every user act lands in the append-only `point_awards` ledger via `PointsService::award()` (idempotent on `dedup_key`); `user_gamification` carries points/level/streak; level-ups set `pending_celebration_level` and surface cross-surface.

**Award trigger inventory (all verified firing):**

| Act | Hook | Points (config/gamification.php) | Dedup key |
|---|---|---|---|
| Onboarding answer (bubble + SaveTax inline) | `OnboardingChatDirector::recordProgress()` :3615-3621 / `handleInlineCapture` | 10 | `onboarding:{stateId}` / `onboarding:inline:{hash}` |
| **Recommendation completed** | `RecommendationTrackingObserver::saved()` (app/Observers/…:14-32) on `status='completed'` | 25 | `recommendation:{rec_id}` |
| First data record per category (14 models via `AwardsDataEntryPoints`) | model `created` → `PointsService::awardDataEntry()` :83-126 | 20 | `data:{category}:first` |
| Extra records (capped 3/category) | same | 5 | `data:{category}:rec:{id}` |
| Daily login + streaks (3/7/14/30) | `PointsService::recordLogin()` :133-178 (AuthController:607,656; MFAController:220) | 5 / 15–100 | `login:{date}` / `streak:{days}:{start}` |
| Milestones (all flavours) | `MilestoneDetectionService` (see below) | 30 | `milestone:{type}:{ref}:{threshold}` |

**Milestone detection call sites** — net worth + goals + journey: `MobileDashboardController::index()` :91,101,103; journey self-heal backstop: `MobileAchievementsController` :186; **tax savings identified: `TaxStrategyController::show()` :43-46 ONLY** (best-effort try/catch, uses the already-computed `combined_annual_saving`).

**The integrated chain for one completed action:** mark-done → `recommendation_tracking` → observer awards 25 pts → possible level-up (SSE `level_up` frame after `done`, `AiChatController::levelUpFrame` :150-166, or `pending_celebration_level` fallback via `GET /api/gamification/status`) → `detectJourney()` may mint the "first action" milestone on next dashboard read → the act appears in the activity feed ("Completed: {text}") → the action leaves every open list (replace-on-done) and joins the Done lists. **This loop is real and closed post-WP-1–6.**

## 6. Nudge inventory (what proactively reaches the user)

| Nudge | Trigger | Surface(s) | Evidence |
|---|---|---|---|
| Level-up celebration (fireworks modal + "what's next" ≤2 actions from `LevelService::nextActions()`) | any award crossing a level | web + /m, in-chat via SSE, cross-surface fallback on next open | AiChatController:297-308,818-829; GamificationController:18-44 |
| Milestone toast + share prompt | new milestone on dashboard read | /m dashboard only | resources/mobile/views/Dashboard.vue:198-207,685-689; `ShareContentGenerator` |
| Hero card ("X of Y actions to your next level", percentile) | every /m dashboard read | /m only | `MobileLevelService` :38-70; Dashboard.vue:42,53 |
| Fyn "added to your actions list" notice | capture turn that logs an action (#594) | chat (both surfaces) | OnboardingChatDirector:935,978 |
| Activity/History feed (passive) | visit | /m Achievements History tab | `ActivityFeedService::feed()` (limit 100); GET /api/gamification/activity |
| Push notifications | **product alerts only** — mortgage rate, ISA allowance, savings maturity, policy renewal, daily insights | iOS | 5 Notification classes + 3 scheduled commands; **zero gamification callers** |
| Email | **none** tied to gamification/milestones/actions | — | lifecycle emails are discount-focused |

## 7. Integration gaps (the honest list)

The engine-level integration you asked for **exists now** (§5's chain). What's still missing is the **outbound nudge layer and desktop parity**:

1. **Desktop has no achievements / milestones / history UI at all** — only `/actions` and the level-up overlay. `resources/js/views/` has no equivalent of Achievements.vue. A desktop-first user never sees a milestone.
2. **Tax-savings milestone fires only on the /tax-strategy read** — a SaveTax graduate who doesn't open that page never mints it (dashboard read doesn't pass the composed plan in).
3. **"What's next" is celebration-only** — `nextActions()` data exists in `/api/gamification/status` but is rendered only inside the level-up modal; no persistent "N actions to Level X — start with this" card, and the milestone "steps" (WP-5b) are text, not deep-links into the ranked actions.
4. **Push notifications never fire from gamification** — `PushNotificationService` is product-alerts only; a newly-earned milestone reaches nobody who isn't already in the app.
5. **No email loop** — level-up dismissed = engagement thread ends.
6. **Fyn's voice is gamification-silent** — Fyn notes logged actions (#594) but never acknowledges a milestone or achievement in-chat; onboarding awards are invisible until a level boundary triggers the modal.
7. **Milestones don't reference the recommendation engine** — upcoming milestone steps are hand-written strings, not the user's actual top-ranked action for that family.

*(Observation, arguably by design: recommendations ignore user level — no level-gated "advanced strategies". Not treated as a defect.)*

**Verdict for the campaign blueprint:** the data spine (stable ids → tracking → ledger → level/milestones → feed) is copy-ready. The nudge layer (gaps 1–7) is where WP-5c §6 lands, and it is campaign-agnostic — build once, every campaign inherits.

## 8. Copy-across checklist for future campaigns

1. **Funnel marker** — campaign users are identified by `users.funnel_answers` (JSON, set at funnel registration). A new campaign needs its own marker or a campaign key inside `funnel_answers`.
2. **Affinity** — extend `applyCampaignAffinity()` (NextActionsService:227) to prefer the campaign's module (currently hardwired to `tax`).
3. **Composed plan** — implement a `ModuleStrategySource` for the campaign module (tax already has `TaxStrategySource`; the composer/sequencer/conflict layer is module-agnostic).
4. **Strategy unlocks** — the locked[] → unlock-card path (NextActionsService:363-393) reads from the composed plan, so it comes free once the source exists.
5. **Milestones** — add campaign flavours per `wp5c-milestones-spec.md` §3.
6. **Landing state (WP-6 equivalent)** — campaign graduates should land on their module's actions first; that is exactly what affinity + the module deep-link page give you.
