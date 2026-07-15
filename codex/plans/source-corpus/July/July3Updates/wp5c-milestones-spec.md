# WP-5c — Full Milestone Catalogue + Uncapped Pages + Nudge Wiring (SPEC — awaiting CSJ approval)

*2026-07-03. Status: **DRAFT for CSJ sign-off — nothing built.** Extends WP-5/WP-5b (#600/#602). Companion: `savetax-recs-gamification-map.md` (same folder, current-state map).*

## 1. Problem

After WP-5/WP-5b the milestone catalogue has only **5 families** (net worth ×10, goal % ×4/goal, tax profile complete, first tax saving identified, first action completed). A user with no goals has a lifetime ceiling of ~13 milestones — far too thin to nudge someone through an entire tax and financial life. Separately, the dedicated pages inherit small caps that only make sense on the dashboard (main card + carousel are deliberately limited to 4; the Achievements / Milestones / History **pages** must not be).

## 2. Design principles (all inherited from WP-5)

- **Substrate unchanged**: `UserMilestone` (unique on `user_id + milestone_type + reference_id + threshold`), `MilestoneDetectionService::recordOnce()`, points via `config('gamification.points.milestone')` (tunable), append-only `point_awards` dedup keys `milestone:<type>:<ref>:<threshold>`.
- **Detection takes already-computed figures as arguments** (the `detectNetWorth(User, float)` pattern) — never recompute an aggregate inside detection; hook into the read/event where the figure already exists. No new queries on hot paths.
- **Two trigger classes**: *event-driven* (model observers, `RecommendationTrackingObserver`) where a discrete act crosses the line; *read-time self-healing* (dashboard/module/tax-strategy reads) where a computed aggregate crosses it — so nothing depends on the user visiting one specific page (the WP-5 fix).
- **Rules compliance**: no icons/emoji (Rule 15); no scores (Rule 12 — this whole layer is the CSJ-approved gamification carve-out); acronyms spelled out except ISA (Rule 9); every tax threshold from `TaxConfigService` (Rule 2); British spelling in labels; `/m` and web parity (Rule 19).
- **Milestones never un-achieve** — the ledger is append-only. A figure later dropping below a threshold does not remove the milestone.

## 3. The catalogue

Existing families (keep, unchanged): `net_worth` (10 thresholds £10k–£5M) · `goal` (25/50/75/100% per goal) · `campaign` (tax profile complete) · `tax_savings` (first £X/year identified) · `action` (first action completed).

### 3.1 Tax (SaveTax campaign core)

| Type | Thresholds | Trigger | Source | Label pattern |
|---|---|---|---|---|
| `tax_actioned` | first, then cumulative £250 / £500 / £1,000 / £2,500 / £5,000 per year | event: recommendation marked done where the item carries a quantified annual saving | `RecommendationTrackingObserver` + composed-plan item saving | "You've actioned your first tax saving." / "Actions you've completed are saving you £X a year in tax." |
| `isa_used` | 50%, 100% — per tax year (`reference_id` = tax-year start, e.g. 2026) | read-time: savings/dashboard reads | `ISATracker::getISAAllowanceStatus()` | "You've used half / all of your ISA allowance for 2026/27." |
| `pension_aa_used` | 50%, 100% — per tax year | read-time: retirement/tax reads | `AnnualAllowanceChecker::checkAnnualAllowance()` | "You've used half / all of your pension Annual Allowance for 2026/27." |
| `pa_restored` | 1 — per tax year | **DEFERRED from 5c-i** (build finding 2026-07-03): the allowance grid shows the standard Personal Allowance from config, not a per-user tapered figure — honest detection needs the tax engine to expose adjusted net income first | tax calc (taper threshold via `TaxConfigService`, never hardcoded) | "You've kept your full Personal Allowance this year." |
| `strategy_first` | 1 per strategy (pension contribution, ISA move, Gift Aid, salary sacrifice, Marriage Allowance…) | event: first completed action of each strategy type | `RecommendationTrackingObserver` + recommendation id prefix | "You've made your first Gift Aid claim count." (per-strategy copy) |

### 3.2 Savings

| Type | Thresholds | Trigger | Source | Label pattern |
|---|---|---|---|---|
| `emergency_fund` | 1, 3, 6 months of spending | read-time: savings/dashboard reads | `EmergencyFundCalculator::calculateRunway()` | "Your emergency fund now covers three months of your spending." |
| `isa_first` | 1 | event: first ISA account created (cash or Stocks & Shares) | model `created` observer | "You've opened your first ISA." |

### 3.3 Retirement

| Type | Thresholds | Trigger | Source | Label pattern |
|---|---|---|---|---|
| `pension_pot` | £10k / £25k / £50k / £100k / £250k / £500k / £1M | read-time: retirement/dashboard reads | combined pension value (already aggregated for the dashboard) | "Your pension savings have passed £100,000." |
| `retirement_on_track` | 1 | read-time: retirement projection read | `RetirementProjectionService` readiness | "You're on track for the retirement you've planned." |

*(No `pension_first` milestone — first-capture is already an achievement badge via `data:pension:first`; duplicating it as a milestone would double-celebrate one act.)*

### 3.4 Protection & Estate

| Type | Thresholds | Trigger | Source | Label pattern |
|---|---|---|---|---|
| `will_in_place` | 1 | event: `Estate\Will` created | model observer | "Your will is in place." |
| `lpa_in_place` | 1 | event: `Estate\LastingPowerOfAttorney` created | model observer | "Your Lasting Power of Attorney is in place." |
| `protection_adequate` | 1 | read-time: /m dashboard read | protection summary `critical_gaps === 0` with ≥1 policy (adjusted at build 2026-07-03: the read exposes the gap count, not a per-type life gap — so the milestone is protection-wide, a stronger condition) | "Your protection now covers what your family would need." |
| `estate_plan_started` | 1 | event: first estate action completed (or first gift recorded) | `RecommendationTrackingObserver` / `Estate\Gift` observer | "You've started planning your estate." |

### 3.5 Debt & Property

| Type | Thresholds | Trigger | Source | Label pattern |
|---|---|---|---|---|
| `mortgage_paid` | 25 / 50 / 75 / 100% of original loan, per mortgage (`reference_id` = mortgage id) | event: mortgage balance update; read-time backstop on property read | `Mortgage.original_loan_amount` vs outstanding balance | "You've paid off half the mortgage on <property>." |

### 3.6 Journey

| Type | Thresholds | Trigger | Source | Label pattern |
|---|---|---|---|---|
| `module_profile` | 1 per module (7 modules; `reference_id` from a module→id map const) | read-time: module summary reads | `ModuleDataRequirementsService` readiness | "Your protection profile is complete." |
| `anniversary` | 1, 2, 3… years | login-time (`PointsService::recordLogin` already runs there) | `users.created_at` | "A year of planning with Fynla." |
| `household` | 1 | event: mutual spouse link established | `users.spouse_id` linking flow | "You've linked your household — planning together." |

**Lifetime shape:** a typical engaged user goes from ~13 possible milestones to **45–60+, growing every tax year** (yearly ISA/Annual Allowance/Personal Allowance/anniversary families reset via `reference_id`), with every module contributing.

## 4. Upcoming milestones (`upcoming()`) — expansion

Today `upcoming()` returns ≤ ~7 items (1 net-worth + ≤3 goals + ≤3 journey). WP-5c changes it to return the **next unearned milestone from every family** that applies to the user, each with the concrete step and, where computable, the distance ("you're £4,200 away", "2 months of spending to go"). The Milestones page shows **all of them, grouped** (Tax year · Savings · Retirement · Protection & estate · Property · Journey); the dashboard keeps a small slice (see §5).

## 5. Caps — where they stay and where they go

*Principle (CSJ direction 2026-07-03): the dashboard main card + carousel stay capped at 4 — the dedicated pages are uncapped, because they exist to nudge.*

Verified caps inventory on the dev tip, with the WP-5c decision per row:

| List | Today | WP-5c |
|---|---|---|
| /m dashboard "Top actions" card | 4 (`NextActionsService::MAX_ITEMS`, :22) | **KEEP** — dashboard is a teaser |
| /m carousel per-module cards | 4 (`NextActionsService` :147) | **KEEP** |
| Strategy unlock cards | 2 (`MAX_STRATEGY_UNLOCKS`, :28) | **KEEP** |
| Achievements "Done" (completed actions) | `limit(50)` (`MobileAchievementsController` :89) | **UNCAP** — paginate (25/page, load-more) |
| Activity/History feed | `limit(100)` default (`ActivityFeedService` :57) | **UNCAP** — cursor pagination on `created_at`, infinite scroll on `/m` |
| Upcoming milestones | ~7 (1 net-worth + `take(3)` goals + ≤3 journey, `MilestoneDetectionService` :167-241) | **REPLACE** — next-per-family, all families, grouped (§4); no arbitrary slice |
| Achievements badges | uncapped already | show locked badges with "how to earn" copy |
| Earned milestones list | uncapped already | unchanged (paginate if >30 rendered) |

Hard slices are removed only on the dedicated pages; pagination (not unbounded render) keeps payloads sane.

## 6. Nudge wiring

The companion map (§6/§7) shows today's nudges are: level-up modal, /m milestone toast + share, /m hero card, Fyn's "added to your actions" notice, and a passive history tab — with **zero** push, **zero** email, tax-savings detection trapped on one page, and no persistent "what's next". WP-5c closes the gaps that are milestone-shaped:

1. **Detection reach** — move `detectTaxSavingsIdentified` input so the /m dashboard read also detects it (pass the composed plan's saving when the dashboard aggregator already has it, or accept a cached figure); every new family detects on reads the user actually makes (§3 trigger columns). *(Map gap 2.)*
2. **Milestone steps become deep-links** — each upcoming milestone's "step" carries a `route`/`action_id` so tapping it opens the ranked action or capture flow it refers to, sourced from `NextActionsService` where one exists — the milestone layer finally *references* the recommendation engine instead of hand-written strings. *(Map gaps 3 + 7.)*
3. **Persistent hero nudge** — the dashboard hero gains one line under the level wheel: the single nearest upcoming milestone with its distance ("£4,200 to a £100,000 pension pot"), tappable per (2). Not a list — one line. *(Map gap 3.)*
4. **Push notification on newly-earned milestones** — one send per mint, via the existing `PushNotificationService`, **flag-gated** (`GAMIFICATION_PUSH_ENABLED`, default off) pending CSJ's §7.5 call. *(Map gap 4.)*
5. **Fyn acknowledges mints in-chat** — when a capture turn's award mints a milestone, the turn's ack appends one plain-text sentence ("That takes your pension savings past £50,000."). No emoji, no icons, Fyn stays plain text. *(Map gap 6.)*

**Explicitly deferred out of WP-5c** (flag in backlog, CSJ to schedule): desktop achievements/milestones/history parity (map gap 1 — a Rule 19-sized piece of its own) and any email loop (map gap 5).

## 7. Decisions (resolved by CSJ 2026-07-03: "approved — yearly repeats yes, push flag-gated yes")

1. **Yearly repeats** — YES: `isa_used`/`pension_aa_used`/`anniversary` repeat per tax year via `reference_id` = tax-year start.
2. **`strategy_first` granularity** — spec proposal stands: pension, ISA, Gift Aid, salary sacrifice, Marriage Allowance (built as strategy-key families in 5c-i).
3. **Lasting Power of Attorney** — one milestone (spec default), minted on a **registered** Lasting Power of Attorney (build finding: drafts don't count).
4. **Threshold tuning** — ladders live in one const per family in `MilestoneDetectionService`; tunable any time.
5. **Push notifications for milestones** — flag-gated at launch (5c-iii).

## 8. Delivery shape (once approved)

- One PR per layer, same cadence as WP-1–6: **5c-i** catalogue + detection (service + observers + tests), **5c-ii** uncap pages + grouped upcoming (backend payload + `/m` views), **5c-iii** nudge wiring. `/m` parity in every PR (Rule 19); Pest coverage per family (earn-once, yearly-repeat, no-recompute); browser-verify on csjones per Rule 14.
