---
type: handover
mode: end-of-day
date: 2026-06-08
session: 1
branch: dev
previous_session: 2026-06-07 session-1 (end-of-day)
---

# Handover — 2026-06-08, Session 1

## Where we left off
Short, sharp session on `dev`. Closed the last open optional item from the gamification work (an inline-capture onboarding-award unit test, PR #487), then diagnosed + fixed a real bug CSJ hit on mobile: the **"Save tax" CTA was missing from the public landing page**. Root cause was instructive — the CTA had been added to the Vue SPA `LandingPage.vue`, but the page logged-out visitors (and the `/m` iframe) actually see is the **server-rendered `public/pages/index.php`**. Fixed, PR #488, **deployed to csjones + verified live**. Nothing on production.

## What shipped today
- **#487** — `test(gamification)`: unit test for the inline-capture onboarding award (PR #484's seam in `OnboardingChatDirector::handleInlineCapture`). 6 cases / 14 assertions, mocking only the `CoordinatingAgent` generator seam (no LLM HTTP mock). Merged `c32a9a6`.
- **#488** — `fix(public)`: added the "Save tax" CTA to the real server-rendered homepage `public/pages/index.php` (+ `.btn-cta-secondary` in `index.css`), mirroring `LandingPage.vue`'s two-CTA hero (`Get started`→`/register`, `Save tax`→`/savetax`). Merged `e4c283c`, **deployed to csjones**, verified live (`<a href="/fynla/savetax" class="btn-cta-secondary">Save tax</a>` rendering).
- Memory: new `reference_public_homepage_is_server_rendered_not_spa.md`; corrected the now-misleading line in `reference_mobile_phone_entry_responsive.md`; MEMORY.md index updated.
- vault-sync: CLAUDE.md metrics (Vue 672→668, PHP Services 349→350); Git History Jun07 + June Index session 7; frontmatter added to 6 June update notes.

## What's in flight (NOT done)
- **Production deploy (fynla.org)** — still CSJ's call. `dev` is **+120 / −7** vs `main` (now includes #487 + #488 on top of the gamification engine). Runbook for the gamification release: `June/June7Updates/deploy-2026-06-07.md`. The two PRs today add: a test file (no runtime impact) and `public/pages/index.php` + `index.css` (server-rendered, no build step — just upload the two files / they're already in the dev→main diff).
- **chris@fynla.org existing-user pass** — still blocked: safety guard won't let me reset his csjones password. CSJ to reset, then I log in (web + /m) and confirm backfilled L3 + a level-up celebration.
- Optional cleanup: staging users `gamifyweb@example.com`, `gamifysavetax@example.com` (id 76) on csjones.

## Deploy status
- **csjones (dev): DEPLOYED + verified.** Today's #488 pulled (`0e82c0db → e4c283c8`, `git pull origin dev`) — PHP/CSS only, no build. Live homepage now shows both hero CTAs.
- **Production (fynla.org): NOT deployed.** Same runbook as 2026-06-07.

## Tech debt found this session
- None new. Today's changes were 4 CTA/CSS lines + one isolated test file, all self-reviewed via PR. No duplication, palette tokens only, no hardcoded values/acronyms.
- Pre-existing (carried): two parallel landing implementations exist — server-rendered `public/pages/*.php` (the real public pages) and the near-dead Vue `LandingPage.vue` (only renders for authed users, who get bounced to /dashboard). Easy to edit the wrong one. Captured in the new memory.

## Known issues / blockers
- None broken. #488 verified live on csjones.
- chris@fynla.org csjones password ≠ `Password1!`; safety guard blocks me resetting it — needs CSJ.

## Rules reinforced this session
- **Investigate the codebase before re-deciding settled work.** CSJ flagged (sharply) that the Save tax CTA was "already done, just not done correctly" — the fix was to find where the agreed design lived (`LandingPage.vue`) and port it to the page actually served (`index.php`), NOT to re-open the design as a 4-way choice. New memory: `reference_public_homepage_is_server_rendered_not_spa.md`.

## Next session should
1. **Decide the production release.** If yes: `June/June7Updates/deploy-2026-06-07.md` (gamification: 2 migrations, both bundles, `gamification:backfill`). The two CTA/test files today ride along in the same dev→main diff (index.php/index.css are no-build uploads; the test file is inert in prod).
2. **chris existing-user pass** — once CSJ resets chris's csjones password, log in (web + /m), confirm L3 + celebration.
3. Optional: purge staging test users on csjones.

## Context hints
- Active branch type: mainline (`dev`)
- Behind origin/main by: 7 ; ahead by: 120
- Uncommitted: none — working tree clean (untracked `docs/mobile/designer-brief.pdf` is NOT mine, leave it)
- Last commit: `e4c283c` Merge PR #488 (Save tax CTA) — plus this session-end docs commit on top
- csjones: deployed + verified today; fynla.org: NOT deployed
