# Gamification Engine — Design Spec

**Date:** 2026-06-06
**Status:** Approved (brainstorm complete; ready for implementation plan)
**Author:** CSJ + Claude (brainstorming session)
**Branch (proposed):** `feat/gamification-engine` off `dev`

---

## 1. Summary

A backend **points-and-levels engine** that is the single source of truth for user
progression across the whole product. Users earn points for entering information,
crossing financial milestones, daily app use (with streaks), and completing
recommendations/tasks. Points accumulate into a **finite ladder of 10 named levels**.
On level-up, a **full-screen fireworks celebration** fires — on the web dashboard, on
the `/m` dashboard, and (critically) as a dismissable interrupt over a Fyn
conversation. The engine exposes an API consumed identically by the web app and the
`/m` pathway.

The existing `/m` "level wheel" (today a *derived* count of configured modules in
`MobileLevelService`) is **repointed** to read this engine — one level number
everywhere, no competing systems.

The headline journey: a user arriving via the **savetax campaign onboarding** levels
up several times "as they go" while answering questions and entering data with Fyn.

---

## 2. Decisions locked in brainstorming

| # | Decision | Choice |
|---|----------|--------|
| 1 | Source of truth | **One engine**; `/m` wheel + percentile repoint to summed-points level. `MobileLevelService` becomes a thin reader. No parallel level systems. |
| 2 | Web visibility | **Full parity** — web shows level + progress bar + "what's next" and fires the celebration. Extends the Rule #12 gamification carve-out to web (CSJ direction). |
| 3 | Backfill existing users | **Backfill data + milestones + completed recommendations**, quietly (no celebration). Do **not** backfill logins/streaks. |
| 4 | Points permanence | **Never decrease.** Append-only ledger; deletions never refund; no de-levelling. |
| 5 | Logins/usage | **Daily login award (once/calendar day) + consecutive-day streak bonuses**, capped so engagement can't out-earn real financial actions. |
| 6 | Recommendations vs tasks | **One source** — completing a recommendation/action (`recommendation_tracking.status='completed'`) is the "task". No separate task store exists. |
| 7 | Raw points visible? | **No.** User sees level + progress bar + **action-oriented** "what's next" (never a points figure). Points are an internal currency. |
| 8 | Level structure | **Finite, 10 named levels**, escalating thresholds, front-loaded curve. |
| 9 | Celebration treatment | **Full-screen takeover with fireworks** (confetti + rising rockets + radial bursts + sparkle shimmer), Fynla palette, **no emoji/icons**. Same component on dashboard and Fyn interrupt. |
| 10 | Fyn interrupt timing | **After Fyn's reply finishes streaming** (not mid-stream). |
| 11 | Multi-level jump | **One celebration for the highest level reached.** |
| 12 | Missed delivery | **Persist a pending celebration; deliver on next app open** (any surface). Consumed once via `ack`. |

Point values, level names, and thresholds are CSJ's call and **tunable post-launch**
(CSJ: "we can change this later if needed").

---

## 3. Data model

### 3.1 `point_awards` — append-only ledger (audit trail)

```
id
user_id            FK users, cascadeOnDelete
source_type        string(32)  // data | onboarding | milestone | recommendation | login | streak
points             unsignedInteger
dedup_key          string(191)
meta               json nullable
created_at, updated_at

unique(user_id, dedup_key)      // THE anti-double-award guarantee
index(user_id, source_type)
```

Awarding is `firstOrCreate(['user_id'=>…, 'dedup_key'=>…], ['source_type'=>…, 'points'=>…, 'meta'=>…])`.
If the row already exists, no points are added. The unique constraint — not application
logic — enforces single-award.

### 3.2 `user_gamification` — fast-read aggregate (one row per user)

```
id
user_id                  FK users, cascadeOnDelete, unique
total_points             unsignedInteger default 0   // monotonic, only increases
level                    unsignedTinyInteger default 1
pending_celebration_level unsignedTinyInteger nullable // set on level-up, cleared on ack
last_login_award_date    date nullable
login_streak_days        unsignedInteger default 0
streak_started_on        date nullable
timestamps
```

Row is created lazily on first award (`firstOrCreate`).

### 3.3 Reused: `user_milestones`

Existing table (`2026_06_05_120000_create_user_milestones_table`) is reused unchanged.
`MilestoneDetectionService` already records each milestone once; the engine awards points
keyed off the same milestone identity.

---

## 4. Services

### 4.1 `App\Services\Gamification\PointsService`

```php
award(User $user, string $sourceType, string $dedupKey, int $points, array $meta = []): AwardResult
```

- No-op + early return for preview users (`is_preview_user === true`) — Rule #1.
- `firstOrCreate` on `point_awards(user_id, dedup_key)`. If `wasRecentlyCreated`:
  - increment `user_gamification.total_points`,
  - recompute `level` via `LevelService`,
  - if level rose: set `pending_celebration_level` to the new (highest) level **and** push
    a `LevelUp` onto the request-scoped `LevelUpCollector`.
- Returns `AwardResult { awarded: bool, points: int, leveledUp: bool, newLevel: int, newLevelName: string }`.
- Wrapped so a gamification failure never breaks the triggering write (log + swallow,
  same never-throw discipline as CoALA provenance).

### 4.2 `App\Services\Gamification\LevelService`

- `levelForPoints(int $points): int` and `levelName(int $level): string` from a config
  ladder (§6).
- `progress(int $points): array{level, level_name, progress_percent, next_level_name}` —
  `progress_percent` is points-within-current-band ÷ band-size (internal points math;
  the figure shown to the user is the **percent**, never the points).
- `nextActions(User $user): array<string>` — produces the **action-oriented** "what's next"
  by inspecting the user's highest-value unfilled actions (unconfigured data categories,
  open high-priority recommendations), returning plain-text imperatives
  (e.g. `["Add a protection policy", "Complete 2 recommendations"]`). Never mentions points.

### 4.3 `App\Services\Gamification\LevelUpCollector`

Request-scoped singleton (mirrors the existing CoALA provenance collector). Accumulates
`LevelUp` records during a request so the controller / SSE layer can surface an immediate
celebration for the current turn. Highest level wins if several are pushed.

### 4.4 `MobileLevelService` (refactor)

Slimmed to a reader: `levelFor()` / `percentile()` delegate to `user_gamification` +
`LevelService`. The module-count derivation in `completedActionCount()` is removed (its
own docblock flagged this as the swap-in seam). Percentile distribution still computed
across the base, now from `user_gamification.level`.

---

## 5. Award triggers

All awards route through `PointsService::award` with a deterministic dedup key.

| Source | Hook | `source_type` | `dedup_key` | Points (proposed) |
|--------|------|---------------|-------------|-------------------|
| Data — first of a category | model `created` observer | `data` | `data:{category}:first` | 20 (once ever) |
| Data — extra records | same observer, capped 3/category | `data` | `data:{model}:{id}` | 5 (max 3 extra/category) |
| Onboarding / savetax answer | `OnboardingChatDirector` capture handlers | `onboarding` | `onboarding:{step_key}` | 10 each |
| Milestone crossed | `MilestoneDetectionService` | `milestone` | `milestone:{type}:{ref}:{threshold}` | 30 |
| Recommendation/task completed | `RecommendationTracking::markAsCompleted()` | `recommendation` | `recommendation:{id}` | 25 |
| Daily login | login event, once/day | `login` | `login:{Y-m-d}` | 5 |
| Streak bonus | day 3/7/14/30 of a run | `streak` | `streak:{n}:{run_start}` | 15 / 30 / 50 / 100 |

**Data categories:** savings account, investment account, property, DC/DB pension,
protection policy, will/LPA, goal — each hooked via a model `created` observer. **Income
and expenditure** are profile-style writes (no per-record `created` event in the same
sense — `ExpenditureProfile` / the income resolution chain), so their first-capture award
is hooked at the relevant write path (profile save), not a created observer; the plan
resolves the exact seam. Dedup key stays `data:income:first` / `data:expenditure:first`.

**Streak logic:** on each daily-login award, if the prior award was yesterday,
`login_streak_days++`; if older, reset to 1 and set `streak_started_on = today`. When the
running streak reaches a bonus length (3/7/14/30), award the bonus keyed
`streak:{n}:{streak_started_on}` so a fresh run after a break can earn it again, but the
same run cannot double-award.

**Joint assets:** a single joint record (Rule #6) awards the **owning** user only — no
double-award across a couple.

---

## 6. Levels (config — tunable)

`config/gamification.php` holds the ladder. Proposed:

| Level | Name | Cumulative points to reach |
|-------|------|----------------------------|
| 1 | Starter | 0 |
| 2 | Saver | 50 |
| 3 | Builder | 120 |
| 4 | Organiser | 220 |
| 5 | Planner | 360 |
| 6 | Strategist | 550 |
| 7 | Optimiser | 800 |
| 8 | Guardian | 1120 |
| 9 | Steward | 1520 |
| 10 | Master | 2000 |

Names are British, no acronyms, no emoji (Rules #9, #15). Curve is front-loaded so the
savetax onboarding journey produces several early level-ups.

---

## 7. API surface

All routes Sanctum-authed; the `/m` SPA consumes them with its bearer token.

### `GET /api/gamification/status`
```json
{
  "level": 5,
  "level_name": "Planner",
  "level_label": "Level 5 · Planner",
  "progress_percent": 62,
  "next_level_name": "Strategist",
  "next_actions": ["Add a protection policy", "Complete 2 recommendations"],
  "pending_celebration": {
    "level": 5,
    "level_name": "Planner",
    "next_actions": ["Add a protection policy", "Complete 2 recommendations"]
  }
}
```
`pending_celebration` is `null` unless `pending_celebration_level` is set.

### `POST /api/gamification/celebration/ack`
Clears `pending_celebration_level`. Idempotent. Returns `{ "acknowledged": true }`.

### `/m` dashboard
`GET /api/v1/mobile/dashboard` keeps returning `level` / `percentile` / progress, now
**fed by the engine** via the slimmed `MobileLevelService`. No new `/m` wheel endpoint.
The `/m` celebration uses the shared `/api/gamification/*` routes.
`ModuleSummaryController::removeScores()` is **untouched** (it strips financial-quality
scores only; never gamification fields — Rule #12).

### Fyn chat SSE
After a turn that caused a level-up, the stream emits a terminal **`level_up`** event
*after* the reply's `done`:
```
event: level_up
data: { "level": 5, "level_name": "Planner", "next_actions": [...] }
```
The chat client consumes it and shows the interrupt once Fyn has finished
(decision #10). This mirrors how onboarding SSE events are already handled.

---

## 8. Frontend

### Shared celebration component
A single component renders the approved **fireworks takeover** (deep horizon→violet
gradient, animated level ring with the new level number, "Congratulations / You reached
{name}", action-oriented next-level line, rising rockets + radial bursts + confetti +
sparkle shimmer, `spring-*` success accents). Used on web and `/m`.

- **Dismiss:** tap anywhere or the button → calls `ack`.
- **No emoji/icons** in any text or graphic. Confetti/fireworks are the approved
  gamification animation (carve-out).
- On `/m`, it overlays the Fyn chat; on dismiss the conversation is exactly where it was.

### State
- Web: `store/modules/gamification.js` Vuex module.
- `/m`: equivalent in the mobile store.
- Both hold `{ level, levelName, progressPercent, nextActions, pendingCelebration }` and an
  `ack` action.

### Two delivery paths into the celebration
1. **Live:** the `status` response or the Fyn `level_up` SSE event carries
   `pending_celebration` → show immediately → `ack`.
2. **Missed:** `pending_celebration_level` persists; next `GET /api/gamification/status`
   (on dashboard/chat mount) returns it → show → `ack`. Surface-agnostic, never missed.

### Persistent UI placement
- **Web:** a level card (ring + progress bar + "what's next") near the top of
  `/dashboard`, inside `AppLayout` (Rule #13).
- **`/m`:** the existing dashboard wheel, now engine-fed.

---

## 9. Backfill

Idempotent artisan command `gamification:backfill`:
- Walks non-preview users; awards `data:{category}:first` (+ capped extras),
  `milestone:*` (from `user_milestones`), and `recommendation:{id}` (from
  `recommendation_tracking` where `status='completed'`) using the **same dedup keys** as
  live awards → safe to re-run, cannot double-award.
- Does **not** backfill logins/streaks.
- Recomputes `total_points`/`level` but **leaves `pending_celebration_level` null** —
  existing users land quietly at their earned level; only post-launch level-ups celebrate.

---

## 10. Integrity, edge cases, rules compliance

- **Preview isolation (Rule #1):** preview users excluded at the `award()` boundary; never
  accrue points or appear in the percentile distribution.
- **Permanence:** `total_points` monotonic; deletions never refund; no de-levelling.
- **Performance:** each award is one `firstOrCreate`; observer hooks add negligible latency
  to saves. Reads are O(1) off `user_gamification`.
- **Never-throw:** a gamification failure logs and is swallowed — it must never break a
  user's save, login, or chat turn.
- **Rule #12 (Scores):** level/progress/percentile + named levels are the approved
  gamification (CSJ direction extends the carve-out to web). Raw points hidden. No
  financial-quality score anywhere. `removeScores()` never extended to gamification fields.
- **Rule #15 (Icons):** no emoji/icons/Unicode-glyphs in level names, "what's next" copy,
  celebration text, or graphic. Fireworks/confetti are animation (carve-out).
- **Rule #8 (Colour):** palette only; success/achievement = `spring-*`; accents
  `raspberry/violet/savannah`; no amber/orange.
- **Rule #13 (Layout):** web level card lives inside `AppLayout`; celebration is an overlay,
  not a chrome-less route.
- **Rule #6 (Joint):** joint records award the owning user only.

---

## 11. Testing

**Pest unit**
- `PointsService` idempotency: same `dedup_key` twice → one ledger row, points added once.
- `total_points` monotonic; level resolution correct at each threshold boundary.
- Preview-user exclusion.
- Streak: consecutive-day increment, break-and-reset, bonus award once per run.
- Backfill re-run safety (second run awards nothing).

**Pest feature**
- `GET /api/gamification/status` shape; `pending_celebration` present then cleared by `ack`.
- Observers/hooks each award once: data-create, milestone, `markAsCompleted`.
- SSE `level_up` emitted after `done` on a level-up turn; absent otherwise.

**Browser (Rule #14 — headline path)**
- Drive the **savetax `/m` onboarding** end-to-end: answer questions → watch points accrue
  → trigger a level-up → confirm the fireworks interrupt appears **after** Fyn's reply →
  dismiss → conversation resumes → `pending` flag cleared.
- Web dashboard level-up walk: complete a recommendation that crosses a threshold → fireworks
  takeover on `/dashboard` → dismiss → level card shows new level + progress.

---

## 12. Build sequence (feeds the implementation plan)

1. **Schema + core:** migrations (`point_awards`, `user_gamification`), `config/gamification.php`,
   `PointsService`, `LevelService`, `LevelUpCollector`, `UserGamification` model.
2. **Award hooks:** model observers for the 9 data categories; onboarding capture hook;
   `RecommendationTracking::markAsCompleted` hook; milestone hook; login + streak.
3. **API + SSE:** `GamificationController` (`status`, `ack`), routes; Fyn SSE `level_up`
   event after `done`; repoint `MobileLevelService`.
4. **Backfill:** `gamification:backfill` command.
5. **Frontend:** shared fireworks celebration component; web Vuex module + dashboard level
   card; `/m` store + wheel repoint + chat interrupt wiring.
6. **Verify:** run backfill on dev DB; full Pest pass; browser walk of both headline paths
   (savetax `/m` onboarding interrupt + web dashboard level-up).

---

## 13. Out of scope (this spec)

- Production deploy (separate `dev → main → fynla.org` release, CSJ's call).
- Badges / achievements beyond the milestone share that already exists.
- Leaderboards or social comparison beyond the existing percentile.
- Rewards/unlocks tied to levels (levels are recognition-only for now).
- Push notifications for level-ups (in-app delivery only).
