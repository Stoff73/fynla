---
type: handover
mode: context-clear
date: 2026-06-09
session: 2
branch: dev
previous_session: 2026-06-09 session-1 (end-of-day)
---

# Context Clear Handover — 2026-06-09, Session 2

## Immediate state
All three fixes this session are committed, merged to `dev`, deployed to csjones, and verified. Working tree clean (one pre-existing untracked file `docs/mobile/designer-brief.pdf`, left as-is). Nothing mid-edit. `dev` @ `faa86d6` (+165 / −7 vs `origin/main`).

## The thread
Session started as a routine bootstrap (prior work — freemium #501 + `/m` cold-boot #500 — was already shipped). It became three back-to-back bug fixes, all the **same root class** (subdirectory base-path) plus one unrelated production-class loop:
1. **#502** — the `/m` "Save tax" CTA 404'd on csjones. The `/m` iframe loads the **Vue SPA `LandingPage.vue`** (NOT `index.php` — overturned a stale memory claim), whose Save tax CTA was a hardcoded `<a href="/savetax">` missing the `/fynla` base.
2. **#503** — swept the whole SPA for the same class; found 6 more raw root-relative navigations (`window.open`/`window.location`/`<a href>`) that 404 under `/fynla/`. Fixed with a new shared `resources/js/utils/basePath.js` → `withBase()` helper.
3. **#504** — Fyn looped, persisting the SAME message 17,509× (~41/sec) for user `test@phailanx.co.uk` (id 79, conv 66). Root cause: `STATE_CAMPAIGN_ADVICE_SPOUSE` had `next => itself`; advice turns auto-advance with no user input, so a self-edge recursed forever. Fixed the self-edge + added a `MAX_ADVICE_CHAIN` guard + regression test. Cleaned up conv 66 (deleted 17,488 junk rows, kept 34 real messages).

## Files touched (all committed + merged to dev)
- #502: `resources/js/views/Public/LandingPage.vue`
- #503: `resources/js/utils/basePath.js` (new), `WillInfoStep.vue`, `ArticleEditor.vue`, `store/modules/preview.js`, `SitemapPage.vue`, `NewsHubPage.vue`, `Version.vue`
- #504: `app/Services/Onboarding/OnboardingStateMachine.php`, `app/Services/Onboarding/OnboardingChatDirector.php`, `tests/Unit/Services/Onboarding/CampaignSectionFlowTest.php`

## What the next Claude needs to know
- **`withBase()` is now the canonical helper** for any full-page navigation that bypasses Vue Router (`<a href>`, `window.open`, `window.location`) to a server-rendered or SPA route. `router.push()` / `<router-link>` already apply the base — never wrap those. This whole class only breaks on csjones (`/fynla/`), never prod (root).
- **The `/m` iframe serves the SPA `LandingPage.vue`**, not the server-rendered `public/pages/index.php`. The 2026-06-07 memory note claiming otherwise was wrong and is now corrected in `reference_mobile_phone_entry_responsive.md`.
- **Advice/auto-advance onboarding states must have a `next` that strictly progresses** — never itself, never a cycle. The `MAX_ADVICE_CHAIN = 6` guard in `OnboardingChatDirector` now enforces this mechanically (logs + force-completes on a detected cycle).
- csjones ssh-agent currently holds `fynlaDev` (loaded this session). A fresh session must `ssh-add ~/.ssh/fynlaDev` before any dev deploy. `mcp__ssh-fynla` MCP = production only, never csjones.

## Pick up from here
No open task — the session's three fixes are all done and verified. If continuing, the standing item is the **prod release** (`dev → main`), which is CSJ's call: `dev` is +165 / −7 vs `main`, and the priority reason remains **#489 auth-throttle** (prod MFA password reset broken until released). Today's #502/#503/#504 ride along in that same diff. Prod runbook: `June/June9Updates/deploy-2026-06-09.md`. Also still open: set real tier prices in admin Tier Configuration (placeholders live).
