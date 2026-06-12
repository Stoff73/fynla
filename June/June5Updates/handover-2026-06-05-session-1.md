---
type: handover
mode: end-of-day
date: 2026-06-05
session: 1
branch: dev
previous_session: 2026-06-04 session-2 (context-clear)
---

# Handover — 2026-06-05, Session 1

## Where we left off
Spent 2026-06-04 making the mobile `/m/app` surface real: walked the mobile Fyn-dock savetax onboarding to the `/tax-strategy` terminal, fixed the bugs that surfaced, built a real mobile Tax Strategy view (incl. married/joint household mode), then built real mobile drill-down detail views for **net worth, protection, savings, retirement, and investment** and wired the dashboard + tax-strategy links to them. Everything browser-verified on localhost with real data; all shipped to **`dev`** (commits `06937fc`, `7dce0e2`). **Nothing deployed to csjones or prod.**

## What shipped today
- `fix(fyn): onboarding capture streaming + provider-aware soft-degrade` (`06937fc`) — tripled-ack fix (personaOverride:'data_capture'), double-`done` SSE swallow on the delegated path, and `HasAiGuardrails::getAiModel` soft-degrade now provider-aware (was hardcoded Anthropic `claude-haiku-4-5` → broke chat under xAI). `FynMeteringTest` asserts both provider branches; new optional `XAI_DEGRADE_CHAT_MODEL`.
- `feat(mobile): tax strategy view + module drill-down detail views` (`7dce0e2`) — `TaxStrategy.vue` (+ household married/joint) and 10 views under `resources/mobile/views/modules/` (NetWorth/Protection/Savings/Retirement/Investment, each overview + drill-down) fetching the existing web `/api/*` endpoints via Bearer; dock streaming now splits bubbles on `onboarding_advance` + routes the terminal navigation; savings path tidied `/module/savings` → `/savings`.
- (earlier in the day, before this work: `05b1e8e` CLAUDE.md lean + `4c02ac3` session-2 handover.)

## What's in flight (NOT done)
- **Tax Strategy next-step CTA buttons** (Open investment/savings/retirement) are code-verified and their target routes load, but **no seeded test user produced a recommendation with a next-step `type`**, so the CTA itself was never clicked live. Same `$router.push(route)` mechanism, routes confirmed.
- **Not deployed** — dev is 44 ahead / 7 behind main. csjones deploy needs a mobile-bundle rebuild for the `/fynla/` base (see deploy note); the local `public/m-build` is built for LOCAL base — do not upload it.
- **Mobile detail/drill-down views have no automated tests** (frontend, like the dock — live-verified only). Backend IS covered.

## Deploy status
Ready but NOT deployed — see `June/June5Updates/deploy-2026-06-05.md`. Backend: upload 3 PHP files + `config:cache` (config/services.php changed), no migrations. Frontend: mobile bundle (`public/m-build`) only — rebuild for csjones base + upload; main `public/build` unaffected.

## Tech debt found this session
- `tech-debt-report.md` (root) — 3 minor suggestions, 1 fixed in-audit (hardcoded hex in `TaxStrategy.vue` → palette tokens). Remaining: (a) 9 mobile views each define a local `formatCurrency()` — candidate for a shared `resources/mobile/format.js` (accepted isolated-bundle convention for now); (b) no mobile-view unit tests.
- **CLAUDE.md metrics drift** (pre-existing from 2026-06-03 funnel work, flagged again by vault-sync): PHP Services 340→345, Models 119→123. Left unchanged (CLAUDE.md was just deliberately leaned; not this session's change).

## Known issues / blockers
- None broken. Full mobile surface verified working live on localhost.
- Local-dev: `public/m-build` must be rebuilt for local base (`bash scripts/build-mobile.sh`, no VITE_ROUTER_BASE) or `/m/app/*` doubles to `/fynla/m/app/m/app/*`. Auth any mobile view by injecting `localStorage['m_scaffold_token']` with a Sanctum token.

## Rules reinforced this session
- Provider-blind model constants break chat under xAI — soft-degrade/fallbacks must be provider-aware. See `reference_mobile_campaign_onboarding_and_fyn_streaming.md`.
- Onboarding streams ack + advance + next-prompt in ONE SSE response (one `done` only; frontends split bubbles on `onboarding_advance`). Same memory file.
- Build the real UI, not an approximation (Rule #16) — CSJ chose "real mobile Tax Strategy view" + "real drill-downs", not iframe/scaffold.

## Next session should
1. If deploying: follow `June/June5Updates/deploy-2026-06-05.md` — `git pull origin dev` on csjones, `config:cache`, rebuild `public/m-build` for the `/fynla/` base, upload it. Then verify `/m/app/{tax-strategy,net-worth,protection,savings,retirement,investment}` + drill-downs load on csjones.
2. Optional: engineer a test user that triggers a tax-strategy next-step recommendation (e.g. high income + unused ISA + savings interest above PSA) to click the CTA live and fully close that loop.
3. Optional cleanup: local test users created this session (`mobiletax@example.com`, `mobiletax2@example.com`, `mobilemarried@example.com`, `mobilemarried2@example.com`) + their AiDailyUsage resets.
4. Consider the shared `resources/mobile/format.js` extraction if touching the mobile views again.

## Context hints
- Active branch type: mixed (dev — backend fix + mobile feature)
- Behind origin/main by: 7 ; ahead by: ~46 (after today's 2 commits + handover)
- Uncommitted: none of mine — working tree clean (untracked `docs/mobile/designer-brief.pdf` is NOT mine, leave it; `tech-debt-report.md` committed with the handover)
- Last commit: `7dce0e2` feat(mobile): tax strategy view + module drill-down detail views
- csjones: still pre-this-session (NOT redeployed)
