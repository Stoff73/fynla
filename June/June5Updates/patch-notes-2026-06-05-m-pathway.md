# Patch notes — 2026-06-05 — `/m` mobile pathway: full connection, hardening, savetax, canonical auth, csjones deploy

**Branch:** `dev` (tip `3570d32` after this work) · **Deployed:** csjones (`https://csjones.co/fynla`) · **Production (fynla.org):** NOT deployed — CSJ's call.

**Scope:** Wired the `/m` mobile-web pathway to everything the new dashboard needs, hardened it, made the savetax campaign reachable inside `/m`, switched `/m` to the canonical auth screens, cleaned up architecture-test debt, and deployed + verified end-to-end on csjones.

PRs this session: **#461–#474** (index at the bottom).

---

## 1. `/m` connection sequence (the build backlog from `m-pathway-connection-delta.md`)

The new dashboard mostly didn't route into the dedicated module views that had been built; several surfaces were unreachable. Worked the documented build sequence end-to-end.

### Row 1 — dashboard nav → dedicated views (#462)
- Drawer nav + "View all recommendations" routed every module to the generic `/module/{slug}` placeholder (`ModuleDetail.vue`, "Scaffold — redesign pending"), bypassing the real dedicated views.
- **Bug fixed:** the Net Worth drawer link used slug `net_worth` → `/module/net_worth` → `GET /api/v1/mobile/modules/net_worth` → **404** (`net_worth` not in `ModuleSummaryController::VALID_MODULES`).
- Drawer now routes to the dedicated views (`/net-worth`, `/protection`, `/savings`, `/investment`, `/retirement`); "View all" → the active category's dedicated view.
- **CLAUDE.md Rule #12:** added a "Built & approved" note that the `/m` dashboard gamification (Level wheel, "X of Y actions complete", "ahead of X% of people" percentile via `MobileLevelService`) is a deliberate CSJ design decision — leave it in, never strip; `removeScores()` only strips financial-quality scores. Also removed an accidental duplicate of Rules 16/17/18 that had landed in the working tree.

### Row 2 — browser-verify dedicated views
- Verified live (entrepreneur persona): Net Worth £1,389,180 full breakdown; Investment £140k/2 accounts; Retirement £99,280/yr + Monte Carlo; Savings; Protection. All consume the real web `/api/*` endpoints.

### Row 3 — Investment finance-grid panel (#468)
- The finance grid (Net Worth / Protection / Savings / Retirement) had no Investment tile. Added Investment as a **full-width 5th tile** (donut: portfolio value + account/holdings count) → `/investment`, reading `modules.investment.portfolio_value`. Full-width (`grid-column: 1/-1`) so the 2-col grid reads as 2×2 + 1 banner.

### Row 4 — dedicated Estate + Goals views (#463)
- Estate + Goals were the only drawer links still on the placeholder. Built dedicated views:
  - **Goals** — `GET /api/goals` + `/api/goals/dashboard-overview`: on-track hero, overall-progress bar, per-goal list.
  - **Estate** — `GET /api/estate`, handling both gating modes: **teaser** (free/Tier-1) shows the IHT exposure as information only (no upgrade CTA — `/m` has no checkout; CSJ decision); **full** (Tier-2+) shows the authoritative estate value from `GET /api/estate/net-worth` (the `/api/estate` `assets` collection omits property/pensions, which produced a misleading negative net estate) + gifts/trusts/will.
- After this, **no `/module` placeholder fallbacks remain** in the drawer.

### Row 5 — cache-coherence on write + score-strip parity audit (#464)
- **Bug 1:** `CacheInvalidationService` cleared module summaries under the wrong key (`module_summary_*`); the controller caches them as `mobile_module_*`, so the `forget()` matched nothing and the 7 `/m` drill-downs stayed stale up to 24h after a write. Also omitted `tax` + the level cache. Fixed the key, added `tax` + `mobile_level_actions_*`.
- **Bug 2:** the Fyn capture path (`CoordinatingAgent` — the *only* `/m` write path) only cleared `BaseAgent`'s `v1_{agent}_*` keys, never the mobile caches. `CoordinatingAgent` now overrides `invalidateUserCache` to also run `CacheInvalidationService::invalidateForUser`, so every Fyn write clears the mobile dashboard/module/level caches.
- **Parity audit (PASS):** zero banned financial-quality scores surfaced anywhere in `/m`; the dedicated views render currency/%/counts/text only (the dashboard level/percentile are the approved gamification, not banned scores).

### Row 6 — auth hardening on the existing channel (#465, #469, #474)
- `/m` already uses the canonical Sanctum bearer (funnel login → bridge). The httpOnly-cookie option was **rejected** — the app is pure-bearer (no session cookie), so a cookie path would be a bespoke `/m`-only channel, against "use the existing channels."
- **#465** — server-side token revoke on sign-out: `signOut` now `POST /api/auth/logout` (revokes the PersonalAccessToken + UserSession) before clearing local state. Was local-only.
- **#469** — short-TTL + boot rotation: `/m` rotates its bearer on boot via the existing `TokenRefreshController`; the bridged token is revoked and replaced with `/m`'s own short-lived token (`sanctum.mobile_token_ttl_minutes`, default 240 = 4h, matching the standard expiry — was a hardcoded 30 days). Best-effort + time-boxed (2s) so a slow refresh never blocks mount.
- **#474** — canonical auth screens (see §3).

### Row 7 — reverse-delta APIs (#466, #467)
- **#466 — daily insight:** the dashboard payload already carried `fyn_insight` (unused). Surfaced it as a "Today's insight" card between the level callout and the finance grid — no extra API call, no icons.
- **#467 — milestone detection + share (full infra):** `ShareContentGenerator` was 4 generic canned messages with **no milestone-trigger backend**. Built the missing infra:
  - `user_milestones` table + `UserMilestone` model (unique per user/type/reference/threshold → each milestone fires once).
  - `MilestoneDetectionService` — net-worth thresholds (£10k…£5M) + goal-progress thresholds (25/50/75/100%); detects newly-crossed, persists, returns only the new.
  - `MobileDashboardController::index` runs detection **outside** the 24h cache and **only** on the mobile-only endpoint (so a shared web read can't consume a milestone first); never throws.
  - Frontend: milestone toast (positioned below the header so it never blocks the hamburger) with Share (`navigator.share` + clipboard fallback) + Dismiss; **"Share Fynla" drawer item** (with icon — allowed in the drawer per CSJ) → referral share.

---

## 2. Savetax campaign reachable inside `/m` (#471, #472, #473)

The savetax campaign was unreachable on mobile — neither by navigation nor by deep-link — and a 302 that drops the path defeats the purpose of a campaign URL.

- **#471 — landing link:** `/m` frames the SPA homepage (`LandingPage.vue`), which had no savetax link. Added a "Save tax" CTA to the hero.
- **#473 — point it at the REAL campaign:** the CTA was first a `router-link`, so the SPA intercepted `/savetax` and rendered the Vue marketing page ("Save more on tax"). Changed to a plain `<a href="/savetax">` → full navigation to the **real server-rendered campaign funnel** (the question workflow). In `/m` it loads in-frame via the `Sec-Fetch-Dest: iframe` redirect-skip.
- **#472 — deep-link preservation (savetaxFix.md Option B, kept in `/m`):** a phone hitting `/savetax` now redirects to `/m?to=/savetax` (query string preserved for utm); the `/m` host frames the validated `to` path. New `isFramableTo()` open-redirect guard (campaign allowlist; rejects `//evil.com`, `https://…`, `/admin`, prefix-bypass like `/savetaxevil`). Non-campaign paths still go to plain `/m`.

---

## 3. `/m` uses the canonical auth screens (#474)

The isolated `/m/app` SPA shipped its own SP3 scaffold `Login.vue` + `Verify.vue` ("Sign in" / "Enter code", single field) and routed sign-out + the unauth guard to them — so signing out, or hitting `/m/app` unauthenticated, showed the bespoke scaffold instead of the canonical funnel auth (`reference_mobile_phone_entry_responsive.md` §2: `/m` auths via the canonical funnel; `/m/app` is post-auth only).

- `router.js` — removed the scaffold `/login` + `/verify` routes/imports; unauth access to any `meta.auth` route navigates to the **canonical login** (`(VITE_ROUTER_BASE||'/')+'login'`), which loads in-frame via the redirect-skip. `/` → `/dashboard`.
- `Dashboard.vue signOut` — after the server-side revoke, navigates to the canonical login.
- Deleted the orphaned scaffold `views/Login.vue` + `views/Verify.vue`.

---

## 4. Architecture-test debt (#470)

The Architecture suite had failures (surfacing more `strict_types` violators as each was fixed).
- Added `declare(strict_types=1)` to 8 files: Models `Approval`/`Article`/`PipelineAsset`/`PipelineRun`; Services `HeyGen`/`ImageRenderer`/`FFmpeg`/`ArticleScraper`. (Untested subsystems — verified via `php -l` + the reflection-based arch suite, not live exercise.)
- `Phase03ArchitectureTest` — two stale assertions aligned to the SP1 Pass 4 `PropertyStore` refactor (`NetWorthService` gained a 2nd ctor dep + reads Property via `PropertyStore`): assert the `CrossModuleAssetAggregator` dependency by type (not an exact param count), and Property via `PropertyStore`.
- Result: **117 passed, 0 failed.**

Also merged earlier in the day: **#461** — SP1 Pass 6 PR 5b (Goals/Performance reads through `InvestmentAccountStore`).

---

## 5. csjones deploy

Deployed all of the above to `https://csjones.co/fynla` (staging). Server `6d30719` → `3570d32`.
- `git pull origin dev` (all PHP + the prior session's mobile commits).
- **Migration ran:** `user_milestones`.
- Both bundles rebuilt for the **`/fynla/` base** (`deploy/csjones-fynla/build.sh`) and uploaded (`public/build` + `public/m-build`).
- `composer dump-autoload -o` + `php artisan optimize`; later a mobile-only redeploy for #474 (m-build + main rebuilt, source pulled, caches cleared).

**Smoke (live):** `/`, `/m`, `/m/app`, `/api/v1/health` all 200; phone-UA `/savetax` → `302 /fynla/m?to=%2Fsavetax`; `/m?to=/savetax` frames the real campaign; mobile bundle loads from `/fynla/m-build` (correct base).

---

## 6. End-to-end verification (live on csjones)

**Full funnel → onboarding (one continuous flow):**
- Walked the savetax questionnaire (full-time · higher-rate · no spouse · bank/savings/pension/ISA) → `/savetax/plan` → registered (`mflow0605@example.com`).
- Pending registration captured the **funnel answers exactly**; email verify (code from DB) created the user (id 74) with answers transferred (`employment_status=full_time`, `marital_status=single`, `funnel_answers` intact) → handoff to `/m/app/dashboard` as "Flow".
- **Fyn onboarding recapped every answer** ("full-time… higher-rate… bank accounts, savings, a pension and an ISA… started your profile from what you told us") and asked the **correct new question (DOB)** — did not re-ask answered ones. Answering DOB persisted (`1985-06-15`) and advanced the step.

**Canonical auth screens:** unauth `/m/app` → canonical `/fynla/login`; sign-out → canonical `/fynla/login`; canonical 6-box verify accepted.

**Resume across logout:** Flow's state persisted (`fyn_step='base_dependants'`). New session → opened Fyn → it loaded the **full prior transcript** (recap, DOB question, Flow's "15 June 1985" answer) and resumed at the exact next question (dependants Yes/No) — **same conversation** (`convos=1`), not a restart.

**Caveat (honest):** the verification was done in links, not one unbroken click-through. The in-frame canonical-login → bridge → `/m/app` step was verified in the earlier funnel walk (not re-clicked in the canonical-auth test, which was top-level and therefore landed on the desktop dashboard, since the iframe bridge only fires when framed). The resume was verified by reaching authenticated `/m/app` with a fresh Flow token (simulating the re-login result). Every link is proven; the single unbroken sequence (sign-out in the `/m` iframe → canonical login in-frame → verify → bridge → `/m/app` → resume) was not run as one continuous test.

---

## 7. Open / follow-ups

- **Production (fynla.org) NOT deployed** — separate `dev → main` release, CSJ's call.
- **Unbroken iframe chain** — optionally run sign-out → in-frame canonical login → verify → bridge → `/m/app` → resume as one continuous test to close the seam above.
- **Staging test user** `mflow0605@example.com` (id 74) is mid-onboarding on csjones — delete or leave.
- **"Save tax" CTA is on the shared homepage** (`LandingPage.vue`) so it also shows on desktop (discoverability; `savetaxFix.md` T7). Make `/m`-only if desired.
- **`savetaxFix.md` companion-doc updates** (T8/T9) and the `reference_mobile_phone_entry_responsive.md` memory note still describe pre-fix behaviour for campaign deep-links.
- Pre-existing, unrelated: orphaned `store.challengeToken`/`maskedEmail` in `resources/mobile/store.js` (now unused after the scaffold-login removal).

---

## 8. PR index

| PR | Title |
|----|-------|
| #461 | SP1 Pass 6 PR 5b — Goals/Performance reads through InvestmentAccountStore |
| #462 | `/m` dashboard nav → dedicated views + gamification carve-out (Row 1) |
| #463 | Dedicated Estate + Goals views (Row 4) |
| #464 | Cache-coherence on write (Row 5) |
| #465 | Server-side token revoke on sign-out (Row 6) |
| #466 | Daily insight card (Row 7) |
| #467 | Milestone detection + share (Row 7) |
| #468 | Investment finance-grid panel (Row 3) |
| #469 | Token rotation + short TTL (Row 6) |
| #470 | Architecture-test debt cleanup |
| #471 | "Save tax" CTA on the `/m` landing |
| #472 | Campaign deep-link preservation through `/m` |
| #473 | Save tax CTA → real savetax campaign funnel |
| #474 | `/m` canonical auth screens (retire scaffold login/verify) |
