# Patch Notes — Gamification Engine

**Date:** 2026-06-06
**Branch flow:** feature branches → `dev` (all merged) → **NOT on production**
**PRs:** #477 (prep) · #478 · #479 · #480 · #481 · #482 · #483 · #484
**Deployed to:** csjones (dev/staging) — backend + both bundles + backfill run. **fynla.org (prod): NOT deployed.**
**Spec:** `docs/superpowers/specs/2026-06-06-gamification-engine-design.md`
**Plan:** `docs/superpowers/plans/2026-06-06-gamification-engine.md`

---

## 1. Summary

A backend **points-and-levels engine** — the single source of truth for user progression — plus its full UI on both the web app and the `/m` pathway. Users earn points for entering information, crossing financial milestones, daily app use (with streaks), and completing recommendations. Points accumulate into a **finite ladder of 10 named levels**. On level-up, a **full-screen fireworks celebration** fires — on the web dashboard, the `/m` dashboard, and as a dismissable interrupt over a Fyn conversation. The existing `/m` "level wheel" was repointed to read this engine, so there is one level number everywhere.

Headline journey delivered: a user arriving via the **savetax campaign** levels up several times "as they go" while answering Fyn's onboarding questions.

Built with the brainstorming → spec → plan → subagent-workflow → live-browser-verification pipeline. Three integration bugs were found by live browser testing and fixed in the same effort.

---

## 2. Design decisions (locked in brainstorming)

| # | Decision |
|---|----------|
| 1 | **One engine, single source of truth.** The `/m` wheel + percentile repoint to the summed-points level; no parallel level systems. |
| 2 | **Full web parity.** Web shows level + progress + "what's next" and fires the celebration (extends the Rule #12 gamification carve-out to web). |
| 3 | **Backfill data + milestones + completed recommendations** for existing users, quietly (no celebration). No historical logins/streaks. |
| 4 | **Points never decrease.** Append-only ledger; deletions never refund; no de-levelling. |
| 5 | **Daily-login award (once/calendar day) + consecutive-day streak bonuses**, capped. |
| 6 | **Recommendations = tasks = one source** (`recommendation_tracking` completion). |
| 7 | **Raw points hidden.** User sees level + named level + progress bar + action-oriented "what's next" — never a points number. |
| 8 | **10 named levels**, front-loaded curve. |
| 9 | **Full-screen fireworks celebration** (confetti + rockets + bursts + sparkle), Fynla palette, no emoji/icons. Same component web + `/m`. |
| 10 | **Fyn interrupt fires AFTER the reply finishes** streaming. |
| 11 | **One celebration for the highest level** on a multi-level jump. |
| 12 | **Pending celebration persisted; delivered on next app open** (any surface), consumed once via `ack`. |
| — | Point values, level names, thresholds are **tunable** (`config/gamification.php`). |

---

## 3. Architecture

```
Award trigger (observer / hook)
  → PointsService::award(user, source, dedupKey, points)
      → point_awards (append-only ledger; unique(user_id, dedup_key) = single-award guarantee)
      → user_gamification (total_points monotonic, level, pending_celebration_level)
      → LevelUpCollector (request-scoped) on level-up
  → LevelService maps points → level + action-oriented "what's next"
  → API (GET /api/gamification/status, POST /api/gamification/celebration/ack)
  → Fyn SSE level_up frame (after `done`)
  → Frontend: shared GamificationCelebration.vue (web + /m), LevelCard (web), engine-fed /m wheel
```

**Data model**
- `point_awards` — append-only ledger; `unique(user_id, dedup_key)` is the entire anti-double-award guarantee (awarding is `firstOrCreate`).
- `user_gamification` — one row/user: `total_points` (monotonic), `level`, `pending_celebration_level`, `last_login_award_date`, `login_streak_days`, `streak_started_on`.
- Reuses the existing `user_milestones` table.

**Levels (`config/gamification.php`, tunable):** Starter (0) → Saver (50) → Builder (120) → Organiser (220) → Planner (360) → Strategist (550) → Optimiser (800) → Guardian (1120) → Steward (1520) → **Master (2000)**.

**Point values (tunable):** data first-in-category 20, extra record 5 (cap 3/category), onboarding answer 10, milestone 30, recommendation 25, daily login 5, streak bonuses 15/30/50/100 (day 3/7/14/30).

---

## 4. Changes by phase / PR

### PR #477 — Prep
- Landed the spec + plan on `dev`.
- Removed orphaned scaffold-login fields (`challengeToken`/`maskedEmail`) from `resources/mobile/store.js` (dead since the #474 canonical-auth switch).
- Refreshed `/m` pathway docs (`savetaxFix.md`, `m-pathway-connection-delta.md`).

### PR #478 — Phase 1: engine core (11 tests)
- Migrations: `point_awards`, `user_gamification`.
- Models: `PointAward`, `UserGamification`, `User::gamification()` relation.
- `config/gamification.php` — levels ladder + point values.
- `App\Services\Gamification\`: `AwardResult` (DTO), `LevelService` (level resolution, progress, action-oriented next steps), `LevelUpCollector` (request-scoped singleton), `PointsService::award` (idempotent, monotonic, level-up detection, **preview-user safe**, **never-throw**).

### PR #479 — Phase 2: award hooks (29 tests)
- `AwardsDataEntryPoints` trait on **12 models / 7 categories** (savings, investment, property, DC + DB pension, all 5 protection policy types, goal, estate LPA) — first-in-category bonus + capped extras, via the model `created` event.
- Income + expenditure first-capture awards (`UserProfileService::updateIncomeOccupation` made `totalGrossAnnualIncome` public for parity; `ExpenditureProfile` trait).
- `RecommendationTrackingObserver` — awards on `status='completed'`.
- Milestone awards inside `MilestoneDetectionService` (net worth + goal).
- Onboarding/savetax answer awards in `OnboardingChatDirector::recordProgress` (bubble flow).
- Daily-login award + consecutive-day streak (`PointsService::recordLogin`), wired into `AuthController::verifyCode` + `MFAController::verify`.

### PR #480 — Phase 3: API + SSE + /m repoint (40 tests)
- `GamificationController`: `GET /api/gamification/status`, `POST /api/gamification/celebration/ack`.
- Fyn SSE `level_up` frame emitted **after** `done` in both `sendMessage` + `action` stream closures (`AiChatController::levelUpFrame` helper + `LevelUpCollector`).
- **`MobileLevelService` repointed to the points engine**: level + ring from `total_points`, percentile from the points-based level distribution. The Rule #12-approved **"X of Y actions complete" heading is preserved**, now fed from recommendation completion (completed vs open+completed). Superset payload kept the pre-Phase-5 `/m` bundle working.

### PR #481 — Phase 4: backfill (2 tests)
- `gamification:backfill` — quiet, idempotent one-time reconciliation: data (12 models / 7 categories, by owning `user_id`), income, completed recommendations, already-crossed milestones — all using the **same dedup keys** as the live path (re-run safe). Clears `pending_celebration_level` per user (no celebration spam). Logins/streaks not backfilled.

### PR #482 — Phase 5: frontend (web + /m)
- Web: `services/gamification.js` + Vuex `gamification` module; `LevelCard.vue` (level + progress bar + action-oriented next, **no points number**); shared `GamificationCelebration.vue` (fireworks takeover) driven by `pendingCelebration`; `fetchStatus` on dashboard mount.
- `/m`: store gamification state (`fetchStatus`/`ack` via bearer); wheel rewired to the engine values (`d.level.*` + `d.percentile`), preserving the "X of Y actions complete" heading + level-up pulse; `level_up` SSE case in the chat `onEvent` (celebrates **after** the reply); celebration mounted; missed-celebration delivery on mount.
- **Build fix (`95fcea2`):** the isolated `/m` build (`vite.mobile.config.js`, `@m` alias only) keeps its own copy of the celebration component (`resources/mobile/components/GamificationCelebration.vue`) — `@/components/...` from `resources/js` did not resolve in the mobile bundle.

### PR #483 — Fix: /m recommendation completion awards points
- **Bug found by live testing:** the `/m` dashboard's `toggleRec` was purely client-side (flip `rec.done` + animation) — it never persisted, so completing a recommendation on `/m` awarded **zero** points (web `mark-done` worked). Now it POSTs to `/api/recommendations/{id}/mark-done` → `RecommendationTracking::markAsCompleted` → observer → `PointsService`, then refreshes status to surface any level-up and sync the wheel. Never-throws.

### PR #484 — Fix: savetax / inline-capture onboarding answers award points
- **Bug found by live testing:** the per-answer onboarding award (`recordProgress`, `onboarding:{stateId}`) only fired in the **bubble** onboarding state. Savetax/advice users running through `handleInlineCapture` (the `delegate_to_capture` write handoff) never reached `recordProgress`. Created records award via the model observers, but profile-field answers (DOB/marital/income) update `users.*` and emit no `created` event → awarded nothing. Now `handleInlineCapture` awards one onboarding answer per successful capture turn, deduped on a content signature (retry-safe, progressive). Keys differ from the bubble path (`onboarding:inline:{hash}` vs `onboarding:{stateId}`) so no double-award.

---

## 5. Live verification on csjones (Playwright, real interaction)

| Scenario | Result |
|---|---|
| Engine + API live (401 unauth) + backfill (20 users; chris → L3 quiet) | ✅ |
| **Web new user** — register → verify → LevelCard "Level 1 · Starter" + action-oriented next, no score | ✅ |
| **Web existing user (john)** — login fired the **daily-login award** (20→25 live) | ✅ |
| **Web level-up** — complete recommendation (+25 → 50) → **fireworks celebration** ("You reached Saver", L2) → dismiss → `ack` clears pending | ✅ |
| **/m wheel** — engine-fed Level + "X of Y actions complete" + "ahead of X%" percentile | ✅ |
| **/m recommendation completion** — awards points after #483 (50→75→100 live) | ✅ |
| **/m level-up celebration** — "You reached Builder" (L3) via cross-surface missed-delivery → dismiss → `ack` | ✅ |
| **Savetax campaign** — funnel (4 Q) → register → verify → onboarding chat in the `/m` Fyn dock | ✅ |
| **Savetax "level up as they go"** — 5 onboarding chat answers awarded +10 each (5→55), crossing to **Level 2** | ✅ |
| **/m in-chat `level_up` interrupt** during onboarding | ✅ fired + consumed (crossing award set `pending=2`, then cleared by the celebration `ack`; component/SSE/onEvent all independently verified) |

Screenshots captured: web fireworks celebration (Saver), `/m` celebration (Builder), web LevelCard (new user).

---

## 6. Rules compliance

- **Rule #1** — preview users excluded at the `award()` boundary; never accrue points or enter the percentile distribution.
- **Rule #6** — joint records award the owning `user_id` only (no double-award across a couple).
- **Rule #8** — palette tokens only; success/achievement = `spring-*`; no amber/orange.
- **Rule #12** — level/progress/percentile + named levels are the approved gamification (CSJ-directed extension to web); raw points hidden; no financial-quality score anywhere; the `/m` "X of Y actions complete" display preserved (never stripped); `ModuleSummaryController::removeScores()` untouched.
- **Rule #13** — web LevelCard inside `AppLayout`; celebration is a fixed-position overlay, not a route.
- **Rule #15** — no emoji/icons/Unicode glyphs in any level name, "what's next" copy, celebration text or graphic; fireworks/confetti are the approved animation.
- **Never-throw** — every award path logs + swallows on failure; a gamification error never breaks a save, login, or chat turn.

---

## 7. Outstanding / follow-ups

- **chris@fynla.org existing-user pass** — blocked: the safety guard won't let me reset his csjones password (john served as the proxy ✅). Run the reset command, then this pass can be added.
- **Production release** — separate `dev → main → fynla.org`, CSJ's call. Includes the two new migrations (`point_awards`, `user_gamification`), `config/gamification.php`, the rebuilt web + `/m` bundles, and a `gamification:backfill` run after `migrate --force`.
- **Test coverage gap (minor):** the inline-capture onboarding award (PR #484) is verified live but not unit-tested (testing `handleInlineCapture` needs heavy LLM-stream mocking); the `PointsService` dedup mechanism it relies on is unit-tested.
- **Staging test data** on csjones (harmless): users `gamifyweb@example.com`, `gamifysavetax@example.com` (id 76, L2); john (L3) carries test recommendations/points.

---

## 8. Deployment notes

- **Backend phases (1–4)** deployed to csjones via `git pull origin dev` + `composer dump-autoload -o` + `migrate --force` (two new tables, batch 31) + cache clears. No Vite build needed.
- **Frontend (Phase 5 + fixes)** required rebuilding both bundles with the csjones base (`./deploy/csjones-fynla/build.sh`) and uploading `public/build/` + `public/m-build/`. The `/m` celebration import fix (`@m`) was needed for the mobile build to compile.
- **Backfill** run once on csjones (`php artisan gamification:backfill`) — 20 non-preview users processed.
- **No production deployment.**
