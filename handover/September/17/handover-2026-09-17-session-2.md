---
type: handover
mode: session-end
date: 2026-09-17
session: 2
repo: fynla
branch: dev (main checkout; clean apart from the long-standing untracked workforce/ and docs/diagrams/ files)
---

# Session Handover — 2026-09-17, Session 2

## Where things stand

**Three releases went to production today.** The first two (this morning) were the onboarding-forms release and a tech-debt PR. The third, and the substance of this session, redesigns the level-up celebration: the full-screen takeover is gone from web, `/m` and native iOS, and level-ups are now banked server-side and spent on the dashboard hero circle — the number climbs one level at a time with a confetti burst out of the circle. Production is on main `0988f6d31`, verified live on both web and `/m`.

**The one thing still outstanding is CSJ's own iOS check.** The native code is on `main` but has never been seen on screen — it compiles, its unit suite is green, and it ships via TestFlight rather than by the web deploy, so nothing reached users unverified. A repeat simulator pass wedged CoreSimulator mid-session; devices were shut down and left clean.

## Priorities for the next session

1. **Ask CSJ about the iOS check.** — The native level-climb has never run on a device. It is on `main`, untested on screen. Nothing is blocked on it (native ships via TestFlight, not the web deploy), but it is the only unverified part of today's work. **BLOCKED ON CSJ — they said "I will test the iOS" at 09:23.** If it fails, the fix is in `LevelProgressView.swift` and `LevelCelebrationSequence.swift`; the JS twins are the reference behaviour.
2. **Watch for a replayed climb on production.** The bug the csjones gate caught (see Decisions) was a climb that repeated on every dashboard view for a user whose stored `level` column had drifted from their points. It is fixed and the fix is live, but it only ever appeared on real data. If CSJ or anyone reports "the confetti keeps happening", check `celebrated_level` vs `LevelService::levelForPoints(total_points)` for that user first.
3. **Tech debt, if wanted.** `docs/tech-debt-report.md` — 0 critical, 2 warnings, 1 suggestion. None urgent; warning 1 is deliberate and must not be "fixed".
4. **Parked, raise only if asked:** mapping PRs #829–#839 and the 55 mapping bugs (`CSJTODO.md`); the iPhone registration bounce; `HolisticPlan.vue:90`'s fourth `MODULE_LABELS` map.

## Context to load

- `docs/superpowers/specs/2026-09-17-level-up-celebration-redesign-design.md` — the spec for today's main work, including the six rulings behind it. Read before touching anything gamification-related.
- `handover/September/16/sdd-account-forms/progress.md` — the rulings ledger, now 42–54. **Rulings 53 and 54 are today's.** Extend it, never re-litigate it.
- `docs/tech-debt-report.md` — today's audit, and the standing "do not re-raise" list.
- `app/Http/Controllers/Api/GamificationController.php:18-70` — `status()` and `ackCelebration()`, the whole server contract. Both ends derive the level from points; see Decisions for why that matters.
- `resources/js/utils/levelCelebration.js` — the celebration rule. Its `/m` and Swift twins must change in the same commit.
- `docs/superpowers/plans/2026-09-17-level-up-celebration-redesign.md` — the executed plan, if you need the task-by-task detail.

## Completed this session

**Released to production (three times):**
- **main `07f772d8b`** — PR #898: the unlinked-spouse action (#896) and the `/m` all-actions list (#897). No migration. Verified on fynla.org with account 711.
- **main `e2368a982`** — PR #900 / #899: the module label vocabulary moved to the server (`module_label` on every open and completed action row); both client maps deleted. Also `pounds()` shown **not** to be a duplicate, `spouseInputs()` → `singleWriteInputs()`, `focusAreas()` 86 → 25 lines, `MODULE_ROUTES` checked and kept.
- **main `0988f6d31`** — PR #902 / #901: the level-up redesign. **This one had a migration.**

**The level-up redesign, in detail:**
- Server: `pending_celebration_level` (one slot) → `celebrated_level`; `status()` returns `celebrate_from`/`celebrate_to`; the ack takes the level reached and is clamped and monotonic. The SSE `level_up` frame stays on the wire but no client acts on it.
- Migration `2026_09_17_120000` backfills `celebrated_level = level` for every row before dropping the old column.
- One pure rule module per client (JS ×2, Swift), driven by the same 13 test vectors.
- **Five trigger sites deleted, not the three the plan predicted** — web had a fourth SSE dispatch, and native had a *second* takeover mounted over the Fyn conversation itself.
- `/m`'s `pulseWheel` removed (the wheel sits behind the full-screen overlay, so the pulse was always spent unseen), with its dead `is-levelup` CSS.
- Ruling 53 (`CaptureForms.php` is never to be split) recorded in the class docblock, the audit report, the ledger and project memory.

**Housekeeping:** `c.jones@csjones.co` purged from production (account 736, plus one orphaned access token); test account 711 restored to its true level; every verification token revoked on both prod and csjones; the `feedback_clear_cjones_prod_account_after_every_fix` memory note corrected.

## Verification state

- **Production (fynla.org), main `0988f6d31`**: web `/dashboard` — three levels banked, nothing animated while Fyn had the user, then 6 → 4 → 5 → 6 with 48 confetti frames, settling clean. `/m` — nothing for 2.5s while Fyn covered the screen, then 6 → 5 → 6 on "Close Fyn chat". Migration: **all 43 production users, levels up to 7, zero owed a climb.** Smoke all 200; zero errors in the log since deploy.
- **csjones, at `2fadaad43`**: same two journeys, plus the 4-level case (7 → 4 → 5 → 6 → 7). Migration on 95 real users, zero owed a climb.
- **Pest**: 47 gamification tests green (9 new, including the drift regression and the production-safety backfill test).
- **Vitest**: 1398 across 148 files, on a quiet machine.
- **Native**: `BUILD SUCCEEDED`, `TEST BUILD SUCCEEDED`, 422 unit tests across 66 suites with only the six known-red `Local StoreKit configuration` failures (missing App Store Connect products — a real but unrelated signal). `verify-project.sh` clean.
- **Not verified:** the native level climb **on screen**. No device run happened. Also: no full Pest suite today, focused files only, per the standing instruction.

## Decisions and dead ends

- **CSJ's rulings today** (54 and 54a in the ledger): the queue is a server-side *range*, not a client bank or a list; the animation plays when the user is actually looking at the dashboard, never while Fyn has them; the named level ladder (Starter … Master) is **dropped from this flow entirely** — the hero says "Level 6", never "Strategist"; the ring sweeps and resets per level; native was in scope in the same pass; `prefers-reduced-motion` is honoured.
- **Ruling 54a, chosen deliberately over two alternatives:** on web an *expanded* Fyn dock counts as "Fyn has the user", so the climb waits for it to collapse — even though the docked panel sits beside the hero rather than over it. CSJ knew the consequence: `chatCollapsed` is persisted per user, so someone who habitually leaves the dock open banks levels and sees them only on collapse. It defaults to collapsed. **Do not change this without asking.**
- **Ruling 53: `CaptureForms.php` is never to be split.** Recorded in four places. An audit flagging it for length is declined, not actioned.
- **THE BUG THE CSJONES GATE CAUGHT, and the day's most valuable find.** User 399 had 825 points — level 7 on the ladder — while `user_gamification.level` still said 6. `status()` offered the range from the *derived* level but `ackCelebration()` clamped against the *stored column*. When those drift the ack can never catch up, so **the climb replays on every single dashboard view**, with the number visibly bouncing 7 → 6. Local fixtures never drift; only real data exposed it. Both ends now derive from points, with a regression test. **The lesson: a gamification change cannot be trusted against seeded data alone.**
- **Dead end — `aiChat.isOpen` is the wrong flag for "Fyn has the user" on web.** It is true whenever the chat panel component is live, *including while the desktop dock sits collapsed*, so the first predicate suppressed the climb permanently. Only `AppLayout` knows the answer (dock collapsed on desktop, floating panel open on narrow); it now computes `fynHasTheUser` and broadcasts a `fyn-attention` event.
- **Dead end — watching `celebrateTo` alone.** Either end of the range can move; both dashboards watch the owed *count*.
- **Dead end — assuming the banked range is fresh when Fyn closes.** The level-up happens *during* the conversation, so the range held on screen is stale by the time Fyn is dismissed. All three surfaces now refresh the status on release. Without this the `/m` close showed nothing at all.
- **`--violet-400` does not exist** in the web dashboard's token block (only `--violet-500`); a third of the confetti would have been invisible.
- **`pounds()` was never a duplicate.** `CaptureForms::pounds()` keeps pence for figures a user typed and is reading back on a form; the director's rounds for conversational recaps. The audit's suggested merge **would have changed money on screen.** The director's is now `wholePounds()`.

## Things that will bite you

- **Every web selector in `gamified-dashboard.css` is `.gamified-dash`-prefixed.** An unprefixed rule ties on specificity and depends on source order. `/m`'s equivalents are unprefixed and live in `resources/mobile/views/dashboard.css`, **not** `style.css`.
- **Both surfaces already carry a `stroke-dashoffset` transition (0.45s)** on `.md-level__pie-arc`. The ring sweep reuses it — do not add a second, and keep the snap-back at 420ms so each sweep lands before the next starts.
- **Do not run heavy jobs concurrently on this machine.** A native test run alongside Vitest starved the frontend suite into 15 spurious failures and a 2022s duration (normally ~200s), and wedged CoreSimulator. A clean re-run was 1398/1398. **Treat a red suite as suspect if anything else heavy was running.**
- **`xcrun simctl list` hanging is the wedge**, not slowness. The ladder is in the `ios-simulator` skill; step 1 (`simctl shutdown all`) cleared it today.
- **The `RetentionPurgeService` namespace is `App\Services\Account`, not `Retention`.** The wrong one throws "Target class does not exist" and *silently skips the purge* while the force-delete still succeeds — so the count check passes and it looks done. Memory note corrected; sweep `personal_access_tokens.tokenable_id` for orphans afterwards.
- Backups from today's three releases: `~/release-backups/2026-09-17{a,c,d}/`.
- csjones test user 399 is at level 7 with `celebrated_level` 7; production 711 restored to level 6.

## Tech debt deferred

From `docs/tech-debt-report.md` (0 critical, 2 warnings, 1 suggestion):
- The celebration rule in three copies — **deliberate, do not "fix" by importing across bundles**; the risk is drift, guarded by shared test vectors.
- `playBankedLevels()` ~52 lines duplicated across the two Vue dashboards — extract a mixin per bundle only if it needs changing twice more.
- `resources/mobile/views/Dashboard.vue` at 1,087 lines — lift the Fyn overlay out if it grows again.

Standing: `CaptureForms.php` **declined**; `HolisticPlan.vue:90`'s fourth label map; "Free plan" hardcoded at `OnboardingChatDirector.php:1105`; `handleFormTurn` at 142 lines.

## Branch and deploy state

- Branch: `dev` at `563570c5d` (== `origin/dev`). Working tree clean apart from the long-standing untracked `workforce/` and `docs/diagrams/` files inherited from earlier sessions.
- Unpushed commits: none.
- **Dev is one commit behind main** (`0988f6d31` carries the release merge) — normal after a release.
- Deploy status: production (fynla.org) on main `0988f6d31`, deployed and verified 10:52–11:00. csjones on `dev` `563570c5d` with matching bundles.
