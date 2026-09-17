# Level-up celebration — from full-screen takeover to a confetti burst on the dashboard level circle

**Date:** 2026-09-17 · **Owner:** CSJ · **Status:** spec for implementation · **Surfaces:** web, `/m`, native iOS (all three, CSJ 2026-09-17)

## Goal

The level-up celebration stops being a full-screen takeover that can appear at any moment, including during a Fyn conversation. Level-ups are banked and spent in exactly one place: the level circle in the dashboard hero. The number in the middle of that circle climbs one level at a time, and each increment fires a confetti burst out of the circle.

Three banked levels means the number goes 4 → 5 → 6, with three bursts.

CSJ's wording, 2026-09-17 06:48:

> I no longer want a full screen animation that covers the dashboard, or pops up at any time during a users interaction with the app, such as when they are having a conversation with Fyn. Instead, any level up animations are stored and shown when a user is on the dashboard. The animation should just be a confetti spray from the actual level indicator circle in the top hero block of the dashboard. If a user has leveled up through actions, as they go onto the dashboard these are shown by their level number climbing, one at a time, so 1, 2, 3, with the number changing in place in the middle of the circle, and at each number change a burst of confetti spraying out from the circle.

## Rulings taken during the brainstorm

| # | Decision | CSJ |
|---|---|---|
| 1 | The queue lives on the server as a range (`celebrate_from` / `celebrate_to`), not a client-side bank and not a list of levels | 06:58, approach A |
| 2 | The animation plays when the user **views** the dashboard — not only on mount. On `/m` the Fyn chat takes the whole screen; when it collapses back to the dashboard the animation plays, as does returning to the dashboard. Web follows the same collapse rule | 06:58 |
| 3 | The named level ladder (Starter … Master) is **dropped from this flow entirely**. No caption, no name in the hero | 06:58 |
| 4 | The progress ring sweeps and resets per level, settling at the true value on the last one | 06:58 |
| 5 | Native iOS is in scope now, not deferred | 06:58 |
| 6 | `prefers-reduced-motion` is honoured | 07:00 |

## Read these first

1. `app/Services/Gamification/PointsService.php:50-75` — the award transaction. `$g->pending_celebration_level = $newLevel` at :61 is the single-slot field this spec replaces. Note `UserLevelCrossing::firstOrCreate` at :66-71 already records **every** crossed level; the range in this spec is consistent with it but does not read from it.
2. `app/Http/Controllers/Api/GamificationController.php:27-49` — `status()` builds `pending_celebration` from the single field; `ackCelebration()` at :46-52 nulls it. Route: `routes/api.php:1135`.
3. `app/Models/UserGamification.php:15-22` — fillable and casts.
4. `resources/js/views/Dashboard.vue:3-8, 138-148, 756-757` — the routed web dashboard: mounts `<GamificationCelebration>` as a fixed overlay and renders `<GamifiedDashboard />` at :110. `celebration` is mapped from `gamification/pendingCelebration` at :757.
5. `resources/js/views/GamifiedDashboard.vue:27-40` — the hero circle: `md-level__pie`, `md-level__pie-arc` driven by `--progress`, `md-level__pie-num` holding the number. **This is the confetti origin on web.**
6. `resources/mobile/views/Dashboard.vue:29-45` — the same markup on `/m`, plus an `is-levelup` / `pulsing` class already on the pie. `fynOpen` at :455, set at :798/:910, cleared at :916.
7. `resources/mobile/mixins/onboardingChat.js:197, 363, 693` — `store.queueCelebration(cursor.levelUp)` in three places. **This is what interrupts Fyn on `/m` today.**
8. `resources/js/store/modules/gamification.js` — the whole web store module (39 lines).
9. `resources/mobile/store.js:10-20, 53-88` — the `/m` equivalent.
10. `resources/js/store/modules/aiChat.js:935, 1174, 1444` — three `level_up` SSE cases dispatching `gamification/queueCelebration`.
11. `ios-native/Fynla/App/AppRootView.swift:438-451, 711` — the native takeover, mounted at app root so it covers Fyn too.
12. `ios-native/Fynla/Features/Gamification/LevelProgressView.swift:7-45` — `LevelWheelCard`, the native hero circle. **Confetti origin on native.**
13. `ios-native/Fynla/Features/Achievements/AchievementsModel.swift:13-15, 47, 164-173` — `pendingCelebration`, `refreshCelebration()`, `dismissCelebration()`.
14. `ios-native/Fynla/Core/Components/GamificationCelebrationView.swift` — the native takeover view, to be deleted.

## 1. Data model

New column on `user_gamification`:

```php
$table->unsignedInteger('celebrated_level')->nullable()->after('level');
```

`celebrated_level` is the highest level the user has actually **seen** celebrated. `pending_celebration_level` is dropped in the same migration — it can hold only one value, which is precisely why "1, 2, 3" is impossible today.

**The migration MUST backfill `celebrated_level = level` for every existing row before dropping the old column.** Production has users at level 6 who have never been "celebrated" to it; without the backfill their next dashboard view plays a five-step climb. This is the one way this change can visibly misfire on real users and it gets its own test (§6).

`PointsService` stops writing a pending level entirely — delete the `if ($leveledUp) { $g->pending_celebration_level = $newLevel; }` branch at :60-62. `celebrated_level` moves only on ack. The `UserLevelCrossing` writes at :66-71 are unchanged.

`GamificationBackfill` (`app/Console/Commands/GamificationBackfill.php:101`) currently nulls `pending_celebration_level` to stay quiet; it must instead set `celebrated_level = level`, same intent.

## 2. API contract

`GET /api/gamification/status` replaces `pending_celebration` with:

```json
{
  "celebrate_from": 3,
  "celebrate_to": 6
}
```

`celebrate_from` is `celebrated_level ?? 1` — the ladder starts at level 1, so a user who has never celebrated anything is owed everything above 1. `celebrate_to` is the current `level`. When they are equal, nothing is owed.

**Do not coalesce to `level`.** An earlier draft of this spec had `celebrated_level ?? level`, which is wrong: a brand-new user who reaches level 2 before ever acking would have `from = to = 2` and never be celebrated at all. The null case must coalesce to 1, and it is the migration backfill (§1) — not the coalesce — that stops existing users owing a climb. Both are required and they are not interchangeable.

The column is nullable rather than `default 1` because `UserGamification::firstOrCreate` does not hydrate database defaults on a just-created model (the same trap already commented at `PointsService.php:52-54`); every read coalesces.

`POST /api/gamification/celebration/ack` takes `{ "level": 6 }` and sets `celebrated_level = max(celebrated_level, level)`, clamped to the user's real level. Idempotent by construction: a double-ack, a refresh mid-animation, or a crash cannot double-play or lose state. An ack with no body acks everything owed.

**The Fyn SSE `level_up` frame stays on the wire** (`AiChatController::levelUpFrame`). Native's reducer (`FynEventReducer.swift:51-52`) and both web stores already parse it, and removing it would be a breaking event-contract change for an un-updated client. It becomes informational: **no client acts on it any more.** That kills the interrupt at source without touching the event contract.

## 3. The trigger

One predicate, three implementations: **the dashboard is the thing the user is looking at** — the dashboard is the active screen AND Fyn is not over it. It is a *watcher*, not a mount hook, so all three of CSJ's cases work: arriving at the dashboard, returning to it, and Fyn collapsing onto it.

| Surface | Predicate | Source |
|---|---|---|
| `/m` | dashboard route active && `fynOpen === false` | `resources/mobile/views/Dashboard.vue:455` |
| Web | dashboard mounted && `aiChat.isOpen === false` | `resources/js/store/modules/aiChat.js:85` |
| Native | dashboard tab visible && Fyn sheet dismissed | `AppRootView.swift` |

It must not fire while the tab or app is backgrounded (`document.hidden` on web and `/m`; `scenePhase` on native) — the animation would be spent unseen and acked.

## 4. The animation

For each level in `from+1 … to`, in the hero circle:

1. **The number** in `md-level__pie-num` changes in place: a brief scale-and-settle, not a slot-machine roll. The value is the level being reached.
2. **Confetti** sprays radially outward from the circle's edge and falls. Reuse the particle generation already in `GamificationCelebration.vue` — `buildConfetti()` at :41/:55-63 is the confetti builder (`particleStyle()` at :64 belongs to the fireworks and is not needed) — retargeted from full-screen to an origin at the circle. Do not write new confetti.
3. **The ring** (`md-level__pie-arc`, `--progress`) sweeps to 100%, snaps to 0, and on the **final** level settles at the true current `progress_percent`.

Pacing: ~900ms per level, so three levels complete in under three seconds. No dialog, no button, nothing to dismiss, nothing blocking the page or intercepting pointer events.

Ack fires **once**, when the whole sequence completes.

**Reduced motion** (`@media (prefers-reduced-motion: reduce)` on web and `/m`; `@Environment(\.accessibilityReduceMotion)` on native): the number is simply correct on arrival, the ring is at its true value, no confetti, no sweep. **Ack still fires** — the user must not accumulate an unpayable queue.

Palette: spring / raspberry / violet only, per Rule 8 and the existing celebration. No emoji, no icons, no Unicode glyphs (Rule 15). The hero circle is a dashboard surface, so no icon may be added to it.

## 5. What is deleted

| File | Action |
|---|---|
| `resources/js/components/Gamification/GamificationCelebration.vue` | delete (harvest `buildConfetti`/`particleStyle` first) |
| `resources/mobile/components/GamificationCelebration.vue` | delete |
| `ios-native/Fynla/Core/Components/GamificationCelebrationView.swift` | delete |
| `resources/js/views/Dashboard.vue:3-8, 139, 148, 757` | remove the mount, import, registration and mapped state |
| `resources/mobile/views/Dashboard.vue` | remove the mount |
| `resources/mobile/mixins/onboardingChat.js:197, 363, 693` | remove `store.queueCelebration(...)`; keep or drop `pulseWheel()` per §7 |
| `resources/js/store/modules/aiChat.js:935, 1174, 1444` | remove the three `queueCelebration` dispatches; leave the `level_up` case as a no-op with a comment saying why |
| `AppRootView.swift:438-451, 711` | remove the takeover and its dismiss call |

### On "one component"

Web and `/m` are separate Vite bundles that cannot import each other, and native is a third language. A single literal implementation is **not possible** across all three. What is achievable, and what this spec requires: the *sequence logic* — which levels are owed, pacing, ack, reduced-motion short-circuit — lives in one small, pure module per bundle with identical behaviour, driven by the same test vectors (§6). The rule lives in one described place even though it is expressed three times. Do not claim a single implementation that was not delivered.

## 6. Testing

**Pest**
- `celebrate_from`/`celebrate_to` maths: nothing owed when equal; a three-level range; a user who has never celebrated.
- Ack idempotency: acking twice does not move `celebrated_level` past the real level; an out-of-order ack cannot lower it.
- **The migration backfill**: a user at level 6 with no `celebrated_level` is owed **nothing** after migrating. This is the production-safety test.
- `PointsService` no longer writes a pending level; `UserLevelCrossing` rows are unaffected.
- `GamificationBackfill` leaves migrated users owing nothing.

**Vitest** (web and `/m`, same vectors)
- The sequence module: level list from a range, per-level timing, single ack at the end, reduced-motion path still acks.
- The trigger predicate: does not fire while Fyn is open; fires when Fyn closes with the dashboard active; does not fire when backgrounded.

**XCTest** (native)
- The same sequence and predicate vectors.

**Live browser verification** — all three surfaces, per the browser-testing law:
1. Bank two levels by awarding points while the user is in a Fyn conversation. **Confirm nothing appears during the conversation.**
2. Collapse Fyn. Confirm the number climbs one level at a time with a burst per increment, and the ring sweeps and settles.
3. Reload. Confirm it does **not** replay.
4. Reduce Motion on: confirm the number is correct, no animation, and it still does not replay.
5. Confirm no overlay intercepts clicks anywhere — the old one blocked Playwright on both surfaces.

## 7. Open, decide during implementation

- `pulseWheel()` on `/m` (`onboardingChat.js`) pulses the wheel when a level-up arrives mid-chat. The wheel is not visible during full-screen Fyn, so the pulse is spent unseen. Lean towards removing it; it is not part of the new design and it is a second, competing level-up cue.
- The `is-levelup` / `pulsing` class already on `/m`'s pie (`Dashboard.vue:31`) may be reusable as the number's scale-and-settle, or may be dead once `pulseWheel` goes.

## 8. Consequences accepted

- **The named level ladder is no longer surfaced at level-up on any surface.** The hero says "Level 6", never "Strategist". Names survive in `config/gamification.php` and on the achievements screen. Ruling 3, deliberate.
- **Native ships on its own cadence** (standing rule: web and `/m` first, iOS after). During that window a native user still gets the old takeover. Accepted.
- This touches the Rule 12 gamification carve-out — the approved `/m` level wheel, "X of Y actions complete" and percentile. **None of that is removed or altered**; only the celebration that fires over it changes.
