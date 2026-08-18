# AGENTS.md

This file provides guidance to Codex (Codex.ai/code) when working with code in this repository.

## Project Overview

**Fynla** is a UK financial planning application (Laravel 10 + Vue.js 3 + MySQL 8) covering seven modules: Protection, Savings, Investment, Retirement, Estate Planning, Goals & Life Events, and Coordination.

| Metric | Count |
|--------|-------|
| Vue Components | 674 |
| PHP Services | 435 |
| Controllers | 125 |
| Models | 130 |
| Vuex Stores | 35 |
| Agents | 9 |

**Production**: https://fynla.org | **Version**: v1.0

## Commands

```bash
# Development
./dev.sh                             # Start Laravel + Vite (recommended)

# Testing
./vendor/bin/pest                    # Run all tests
./vendor/bin/pest tests/Unit/...     # Single file

# Database - Reseed (PRESERVES existing data)
php artisan db:seed                  # Reseed all data

# Database - Fresh install only (runs pending migrations)
php artisan migrate && php artisan db:seed

# Code formatting
./vendor/bin/pint                    # PSR-12 format
```

**CRITICAL: NEVER use `migrate:fresh` or `migrate:refresh` when asked to reseed. These commands DROP ALL TABLES and destroy user data. Use `php artisan db:seed` instead.**

**CRITICAL: ALWAYS reseed after any operation that modifies or loses local database data.** This includes running migrations, schema dumps, database resets, table drops, or any other destructive database operation. After such operations, run `php artisan db:seed` to restore all seeded data (tax config, preview personas, market rates, etc.).

**Reseed specific data:**

| Issue | Command |
|-------|---------|
| Tax calculations failing | `php artisan db:seed --class=TaxConfigurationSeeder --force` |
| Tax Status tab empty | `php artisan db:seed --class=TaxProductReferenceSeeder --force` |
| Preview personas broken | `php artisan db:seed --class=PreviewUserSeeder --force` |
| Life expectancy errors | `php artisan db:seed --class=ActuarialLifeTablesSeeder --force` |
| Savings market rates missing | `php artisan db:seed --class=SavingsMarketRatesSeeder --force` |

**Custom artisan commands:**

| Command | Purpose |
|---------|---------|
| `php artisan preview:reset` | Reset all preview persona data |
| `php artisan audit:purge` | Purge old audit log entries |
| `php artisan trials:expire` | Expire ended trial subscriptions |
| `php artisan sessions:cleanup` | Clean up orphaned user sessions |
| `php artisan registrations:cleanup` | Remove stale pending registrations |
| `php artisan fyn:episodic:backfill-blobs` | One-shot idempotent backfill of episodic .md blobs for legacy ai_messages rows |
| `php artisan fyn:episodic:cold-archive` | Move episodic blobs older than 12 months to cold storage (scheduled weekly) |
| `php artisan fyn:episodic:reconcile` | Flag orphan episodic blobs with no matching ai_messages row (scheduled daily) |
| `php artisan fyn:episodic:purge --force` | Hard-delete episodes older than 6 years (FCA SYSC 9.1); dry-run without --force |
| `php artisan fyn:user:erase {user} --force` | GDPR erasure of a user's episodic rows + blobs (hot + cold); dry-run without --force |

## Architecture

```
Vue Component → API Service → Controller → Agent → Services → Models → DB
```

**Backend** (`app/`): See `app/Services/AGENTS.md` and `app/Http/AGENTS.md` for detailed conventions.
- `Agents/` - Module orchestrators (ProtectionAgent, SavingsAgent, InvestmentAgent, RetirementAgent, EstateAgent, GoalsAgent, CoordinatingAgent)
- `Services/{Module}/` - Domain calculations (214 services across 32 module directories)
- `Http/Controllers/Api/` - API endpoints (89 controllers)
- `Http/Requests/` - Form request validation (83 classes)
- `Http/Resources/` - API response transformation
- `Traits/` - Shared behaviours (`Auditable`, `HasJointOwnership`, `CalculatesOwnershipShare`, `FormatsCurrency`, `StructuredLogging`, `PolicyCRUDTrait`, `ResolvesExpenditure`, `ResolvesIncome`, `TracksGoalContributions`)
- `Constants/` - `TaxDefaults`, `ValidationLimits`, `EstateDefaults`
- `Observers/` - Risk recalculation observers, goal contribution trackers, Monte Carlo triggers (12 observers)
- `Exceptions/FinancialCalculationException` - Domain exception with factory methods

**Frontend** (`resources/js/`): See `resources/js/AGENTS.md` for detailed conventions.
- `components/{Module}/` - Vue components (488 across 29 module directories)
- `views/` - Page-level route components (138 views)
- `store/modules/` - Vuex state management (33 namespaced modules)
- `services/` - API wrappers (45 services)
- `mixins/` - `currencyMixin` (formatting), `previewModeMixin` (preview blocking)
- `utils/` - `currency`, `dateFormatter`, `ownership`, `poller`, `logger`
- `constants/` - `designSystem`, `eventIcons`, `eventIconSvgs`, `goalIcons`, `taxConfig`
- `directives/` - `v-preview-disabled` (blocks actions in preview mode)
- `layouts/` - `AppLayout` (authenticated), `PublicLayout` (public pages)
- `router/index.js` - Routes with lazy loading, guards, meta flags (`requiresAuth`, `public`, `previewMode`)

**Database** (`database/`): See `database/AGENTS.md` for detailed conventions.

**Tests** (`tests/`): See `tests/AGENTS.md` for detailed conventions.

**Fyn AI — one prompt, two write states, converging to one Fyn (canonical contract).** Source of truth: `April/April24Updates/spec/00-canonical.md`. Fyn has two states behind one chat surface — the user never sees or feels the switch. The system prompt is now **unified**: both states send the identical `FynSystemPrompt::text()` + per-turn `FynContextAssembler::build()` (`app/Services/AI/Fyn/`), gated by `FYN_PROMPT_ARCH` (`config('fyn.prompt_architecture')`, **default `unified`** post-cutover 2026-05-17; `legacy` is the emergency rollback path); the two write states are enforced purely at dispatch + tool-gating, not by prompt content.
- **Onboarding Fyn** (`app/Services/Onboarding/OnboardingChatDirector`) is the **only** state that enters or edits information. It runs the bubble-driven onboarding flow and the post-onboarding `handleInlineCapture` entry point. Both write to the database.
- **Advice Fyn** (`app/Services/AI/AdviceFyn`) is **read-only**. It answers user questions using the recommendation engine, risk module, and every other engine/module. It exposes **zero** `create_*` / `update_*` / `delete_*` / `set_expenditure` / `capture_*` tools — every persistent record-creation tool (including `create_what_if_scenario`, which persists a `WhatIfScenario` row) is in `AdviceFyn::WRITE_TOOLS` and stripped from the catalogue.
- **Write intents in advice mode** flow through `delegate_to_capture` (LLM tool call) → `AdviceFyn::wrapStream` → `OnboardingChatDirector::handleInlineCapture` → the same direct-write handlers in `CoordinatingAgent`. The synthetic `handoff` SSE event is consumed internally and never reaches the frontend (INV-2.4.1).
- **No `FynPersonaOrchestrator`**, no invoker, no registry, no `DataCapturePromptBuilder`. The dispatch is one guard in `AiChatController::sendMessage` on a **3-part predicate**: the onboarding write state requires `users.onboarding_completed === false` **and** `users.onboarding_fyn_step !== null` **and** `config('onboarding.fyn_flow_enabled', true)`; every other case (including a paused onboarding user whose `onboarding_fyn_step` was nulled) routes to the read-only advice state. It is **not** keyed purely on `onboarding_completed`. See `00-canonical.md:11`.
- **No frontend persona signals.** No `persona_state_change` SSE event. No "capturing" pill. Input placeholder invariant. Any UI that distinguishes the two states violates the contract.

**Where we are vs where we're heading — read before any new Fyn or mobile `/m` work.** The contract above is the **current dev state**: two write states, write-safety via catalogue-strip (`AdviceFyn::WRITE_TOOLS`) at `AiChatController::sendMessage`. The **CoALA work landing on dev soon** (currently on the `coala` branch; see the `project_coala_phase5_progress.md` memory) adds a shared Fyn loop with mechanical write-safety at the dispatch boundary (`GroundGate` rejects write tools on the read-only advice surface, audited `status='stripped'`) — the substrate for collapsing the two write states into **one Fyn**. The final single-loop step (Option A: delete the shells) is a deferred design call; the direction of travel is one Fyn. **Build new work against a single Fyn surface:** web and mobile `/m` already share the one endpoint `POST /api/ai-chat/conversations/{id}/messages` → `AiChatController::sendMessage`, so read/write dispatch is server-side and surface-agnostic. `/m` must not bake in an onboarding-vs-advice split client-side — send to the one endpoint, render the stream; write intents always route through the unseen `delegate_to_capture` handoff, regardless of surface or which Fyn model is active.

## Working Style

**Parallel tool calls.** Batch independent calls in one response (reads, `git status`+`diff`+`log`, several endpoints). Sequential only when one call's output feeds the next.

**Scope discipline.** Change only what was asked. Report adjacent issues rather than silently fixing them. Don't add comments/validation/error-handling/type-hints to code you didn't change, and don't build for hypothetical future needs. Validate only at system boundaries (user input, external APIs) — trust internal code and framework guarantees.

**Investigate before answering.** Never speculate about code you haven't opened. If the user references a file, read it first. Ground claims in the codebase as it is now — if you don't know, say so and look.

**Subagents.** Spawn for parallel/isolated/independent work; use `Explore` or `general-purpose` for research that would take 3+ queries. Do single-file edits and shared-context sequential work directly.

**Long sessions.** Context compacts automatically — don't wrap up early over token worries. For multi-window work, save progress to a file (git log, progress note, `CSJTODO.md`).

**Code review output.** Report every issue tagged with confidence + severity — coverage, not judgment. Don't pre-filter for "only important issues". Canonical review path: `/code-review` for full reviews; `pr-review-toolkit` agents for targeted passes (tests, silent failures, types, comments); `security-reviewer` + `tax-compliance-reviewer` for auth/financial-data/tax changes.

**Response length & effort.** Calibrate to the task. Default effort `xhigh`; drop to `high` for routine edits; `max` only for genuinely hard problems.

## Key Rules

### 1. Preview User Isolation
Preview users (`is_preview_user = true`) are seeded test personas, completely separate from real users. When debugging preview issues, only query `WHERE is_preview_user = true`.

### 2. No Hardcoded Tax Values
Use `TaxConfigService` for all UK tax values:
```php
$nrb = $this->taxConfig->getInheritanceTax()['nil_rate_band'];
```

### 3. Form Modal Events
Form modals emit `save` (not `submit`) to prevent double submission:
- Internal: `<form @submit.prevent="handleSubmit">` → `this.$emit('save', formData)`
- Parent: `<AccountForm @save="handleAccountSave" @close="closeModal" />`
- Parent handles API call and closes modal on success; keeps modal open on error

### 4. Canonical Enums
| Type | Values |
|------|--------|
| Ownership | `individual`, `joint`, `tenants_in_common`, `trust` |
| Property | `main_residence`, `secondary_residence`, `buy_to_let` |
| Mortgage | `repayment`, `interest_only`, `mixed` |

Never use `sole` (use `individual`).

### 5. Currency Formatting
Always use `currencyMixin` - never define local `formatCurrency()` methods.

### 6. Joint Assets Pattern
Joint assets use a SINGLE record with `joint_owner_id` and `ownership_percentage` (primary owner's share). The spouse's share is `(100 - ownership_percentage)`. Use `CalculatesOwnershipShare` trait (backend) or `ownership.js` util (frontend) to calculate shares. Never create duplicate records for joint owners. Query with `WHERE user_id = ? OR joint_owner_id = ?`.

### 7. PreviewWriteInterceptor Middleware
When adding new auth-related POST routes, add them to `EXCLUDED_ROUTES` in `app/Http/Middleware/PreviewWriteInterceptor.php`. This middleware intercepts all write operations from preview users - any route that must work regardless of preview mode state (login, register, password reset) must be excluded.

### 8. No Amber, Orange, or Non-Palette Colours
Amber (`amber-*`) and orange (`orange-*`) are banned. Warnings/caution → violet (`violet-*`); errors/danger → raspberry (`raspberry-*`); success → spring (`spring-*`). All colours from the `./fynlaDesignGuide.md` palette via Tailwind tokens (`raspberry/horizon/spring/violet/savannah/eggshell-*`) — never hardcode hex.

### 9. No Acronyms in User-Facing Text
All acronyms must be spelled out in user-facing text. Write "Annual Allowance" not "AA", "Stocks & Shares" not "S&S", "Defined Benefit" not "DB", "Defined Contribution" not "DC", "Money Purchase Annual Allowance" not "MPAA", etc. The only exception is **ISA**, which may remain abbreviated.

### 10. Design System Compliance
**Before any UI work, read `./fynlaDesignGuide.md` (v1.3.0) — it is the single source of truth for all visual decisions:** colours, typography (Segoe UI / Inter; weights 900 display, 700 h2–h5), buttons/cards/forms/modals, badges, and charts (via `designSystem.js`). Never introduce a colour, spacing value, or component pattern that isn't in the guide. **Where Rules 12 (No Scores) and 15 (Icons) conflict with the guide, those AGENTS.md rules win** — the guide predates them.

### 11. CSS Governance
Palette tokens only (`raspberry/horizon/spring/violet/savannah/eggshell/neutral/light-*`; never old `primary-*`/`secondary-*`/`gray-*`). No hardcoded hex in `<style>` — use `@apply` (e.g. `@apply text-horizon-500`); chart colours from `designSystem.js`. Before adding scoped CSS, check `app.css` for an existing global class: `.scrollbar-hide`/`.scrollbar-thin`, `.animate-fade-in*`, `.detail-inline-back`, `.expand-*`, card variants (`.card`/`.card-lg`/`.card-sm`), badge classes, and the spinner (`<div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin">` — never a custom `@keyframes spin`). Full rules in `./fynlaDesignGuide.md`.

### 12. No Scores in User-Facing UI
Numerical ratings ("75/100", adequacy / diversification / portfolio-health scores) must never appear in user-facing UI — no score badges, metric cards, score-formatted values, or score-based narrative. They oversimplify and mislead. Use descriptive text, specific metrics (currency, %, time periods), and actionable guidance instead.

**Carve-out — gamification by design (CSJ-specified, not LLM/agent-chosen).** This rule stops an LLM/agent inserting scores everywhere; it does **not** ban a gamification/progress/engagement mechanic CSJ has explicitly designed or approved (e.g. an action-driven engagement "level", a progress bar to a target, a "ahead of X% of people" percentile on the mobile dashboard). Still banned regardless: any score/rating/percentile an LLM/agent adds on its own initiative, and any numerical financial-quality rating ("X/100") anywhere. When unsure, ASK CSJ.

**Built & approved — the `/m` mobile dashboard gamification (CSJ direction 2026-06-05).** The `/m` pathway dashboard (`resources/mobile/views/Dashboard.vue`) intentionally shows a gamification layer: the **Level wheel + level number**, the **"X of Y actions complete" progress**, and the **"you're ahead of X% of people" percentile** (fed by `MobileLevelService` → `GET /api/v1/mobile/dashboard`). This is a deliberate engagement mechanic — **leave it in; never strip, "score-launder", or flag it in audits.** It does NOT count as a banned score. Note `ModuleSummaryController::removeScores()` only strips *financial-quality* scores (`adequacy_score`, `diversification_score`, etc.) from module summaries — it must never be extended to the `level`/`percentile` gamification fields.

### 13. All Pages Must Wrap in AppLayout
Every routed Vue view MUST wrap its template in `<AppLayout>` (authenticated pages) or `<PublicLayout>` (public pages) — never ship a chrome-less page. Mobile routes under `/m/*` use `<MobileLayout>`. Without the layout the user has no top nav, no sidebar, no footer, and no way to navigate back — a hard dead-end.

Pattern (see `views/Admin/AdminPanel.vue`):
```vue
<template>
  <AppLayout>
    <!-- page content -->
  </AppLayout>
</template>
<script>
import AppLayout from '@/layouts/AppLayout.vue';
export default { components: { AppLayout, ... } };
</script>
```

The only exception is when the user explicitly says "standalone" / "chrome-less" / "no layout". When refactoring an existing view onto a new route, confirm the destination view is layout-wrapped before claiming done.

### 14. LOOP UNTIL CORRECT — NON-NEGOTIABLE

**FOR ALL TESTS AND WHEN CSJ POINTS AT A SPECIFIC PLAN AND SAYS "MAKE THIS WORK", I LOOP UNTIL IT IS GREEN PER THAT PLAN. I DO NOT STOP. I DO NOT HAND BACK. I DO NOT DECLARE PARTIAL SUCCESS. I DO NOT WRITE APOLOGIES INSTEAD OF FIXES.**

**The loop is:**
1. Use the systematic-debugging skill to diagnose the failure with file:line evidence (DB, audit, network, code paths) — never speculate.
2. Fix the root cause in code.
3. Re-verify in the browser end-to-end via Playwright (click, fill, submit, observe DB + SSE + UI).
4. If still RED, return to step 1 with the new evidence. **Repeat until GREEN exactly as the plan defines GREEN.**

**Acceptance is defined by the plan, not by me.** For BS-NN scenarios in `April/April24Updates/plan/`, the docblock in `tests/Browser/scenarios/BS-NN-*.php` is the contract — every assertion must hold (DB row, SSE shape, audit chain, UI card, no fabricated success).

**The only acceptable exits from the loop are:**
- (a) The test is GREEN per the plan's full acceptance criteria, verified in the live browser.
- (b) I hit a question that genuinely requires a CSJ decision the plan does not answer. Before exiting under (b) I must have exhausted the plan, the spec, the canonical contract, and the relevant memory files. Asking "what should I try next?" is **not** an acceptable exit — that's me handing the work back.

**Forbidden inside the loop:**
- Apologies without an attached fix attempt.
- Marking a task complete on partial evidence.
- Declaring something "good enough" because the plan didn't anticipate the bug — bugs uncovered while looping route through the plan's own bug-fix sub-task pattern (see Sprint 0 plan §S0.16b: "any failures route through dedicated bug-fix sub-tasks against the relevant Sprint 0 file"). **Routing means I open and fix the sub-task in the same loop, then re-verify BS-NN. It does not mean I hand back.**
- Stopping to write reports, summaries, or session notes mid-loop. Reports come AFTER GREEN.

**Ownership:** This rule is OWNED by CSJ. The mirror copies in `MEMORY.md` (under "Top laws") and the fynlaBrain vault are read-only references — the source of truth is this section of AGENTS.md.

### 15. Icons — Functional Only, Decorative Banned

**Icons are allowed ONLY when functionally necessary; decorative icons are banned everywhere.** "Functionally necessary" = the icon is the only way to identify or operate a UI element (canonical example: the collapsed side nav, where labels are hidden and icons are the sole way to tell items apart). "Decorative" = there for visual balance, personality, brand flavour, or because a label "feels bare" — banned.

**Banned surfaces (no icons, emoji, or glyphs, ever):**
- **Fyn chat window** — message text, quick-reply bubbles, header chrome, system messages, streaming indicators, delete/collapse/new-conversation buttons. Fyn speaks in plain text.
- **Dashboard cards** — every module/summary/metric/empty-state card on `/dashboard`.
- **Detail views** — every module page (`/net-worth/*`, `/protection`, `/estate`, `/retirement`, `/goals`, `/plans/*`, `/trusts`, etc.), drill-down panel, and tabbed sub-view.

**Allowed surface:** the **side nav (`AppNavbar`)** — icons required because it collapses to icon-only mode with labels hidden. The one canonical case of functional necessity.

**Other surfaces — ASK CSJ first** (modals, top navbar, forms, alerts, tables, badges, toasts, tooltips, non-card empty states, settings, admin, onboarding wizards, mobile app). Default is NO icon; don't guess or copy nearby patterns.

**Specific bans anywhere (even the side nav):** emoji in any string/label/bubble/tooltip/AI-response/system-prompt/commit-message/comment/doc/markdown/JSON/DB-row/migration; Unicode-as-icons (★ ✓ ✗ → ← ⚠ ℹ); `::before`/`::after` glyph or icon-font injection; icon fonts as a class (font-awesome, material-icons, any webfont); mascot/character images as inline icons (the Fyn character is allowed only as a large illustrated hero on public pages, never as a button/nav/card icon).

**Enforcement (forward-only — existing violations grandfathered):** all current violations stay (e.g. `goalIcons.js` emoji 🔥🎯📈⭐🏆, `AdminDashboard.vue` ▲▼ arrows) — don't rip them out, flag them in audits, or "tidy them up" while editing nearby. Everything new complies strictly from the moment it lands, no grace period. If a plan shows icons on a banned surface, strip them BEFORE coding and flag the plan. Remove an existing violation only if CSJ specifically asks. When in doubt, ASK CSJ.

**Carve-out — icons by design (CSJ-specified, not LLM/agent-chosen).** Like Rule 12, this stops an LLM/agent sprinkling decorative icons; it does **not** ban icons that are part of a design CSJ has explicitly specified or approved (e.g. an approved mobile-dashboard or landing-page redesign) — allowed even on otherwise-banned surfaces when an intentional part of that design. Still banned: any icon an LLM/agent adds on its own initiative to fill space or add personality. When unsure, ASK CSJ. (Added by CSJ direction 2026-06-03.)

**Ownership:** OWNED by CSJ — changeable only by CSJ editing this section. No plan, PR, contributor, sub-agent, earlier `fynlaDesignGuide.md`, or historical spec overrides it.

### 16. Build to the Agreed Spec — Never Invent or Substitute

When a feature has been specced, planned, and agreed, implement exactly that. Do not invent design decisions that were never agreed (e.g. an "upgrade" CTA in the side menu, greying-out nav items). Do not substitute a cheaper approximation for the agreed approach (e.g. an iframe shell where a real UI with working drill-downs was specced). Do not change which behaviours or tiers were agreed. If you believe the spec is wrong or a deviation is warranted, STOP and ask CSJ before deviating — never ship the deviation and explain afterwards. Before claiming a spec change is done (screens removed, flows gated), verify it is actually reflected in the live UI.

### 17. Lean PR / Test Cadence

Don't run the full test suite or full process ceremony after every single PR when CSJ has signalled "lean" or is iterating on prompts, evals, or a multi-PR refactor — queue several PRs and do one consolidated test pass. This is a speed concession for low-risk iteration only. It does **not** weaken Rule #14 (Loop Until Correct): every BS-NN browser scenario, and any change CSJ has pointed at and said "make this work", still requires the full diagnose → fix → live-browser-verify loop per its plan before it is called done. When unsure whether a change is "lean-eligible" or needs full per-change verification, ASK CSJ.

### 18. Internalise Agreed Plans — Don't Make CSJ Re-Explain

When CSJ has explained an architecture or plan, or explicitly deferred an issue ("we'll do this after the refactor", "this doesn't need to come up every time"), internalise it and act on it. Do not re-raise a settled or deferred decision on every turn, and do not make CSJ re-explain the same already-agreed design repeatedly. If a detail is genuinely unclear, re-read the spec, the canonical contract, and the relevant memory files first; only ask once you've exhausted those and the question is one they have not already answered.

### 19. Every Instruction Applies to the `/m` Pathway Too (Mobile Web Iframe Build)

**Every instruction, feature, fix, and plan applies to BOTH the desktop web SPA and the `/m` mobile pathway unless CSJ specifically excludes it.** The `/m` pathway is the mobile **web** build — phones are detected and routed to `/m`, which iframes the real funnel and serves the mobile SPA (`resources/mobile/`) at `/m/app/*` with its own dashboard. It is NOT the iOS Capacitor build (that is a separate packaging of the same views, rebuilt only via `./deploy/mobile/build-ios.sh` when explicitly in scope).

What this means in practice:
- **Scope interpretation:** "add X to the dashboard", "fix the tax strategy page", "change module summaries" — all implicitly include the `/m` equivalents (`resources/mobile/views/`, `MobileDashboardAggregator`, mobile module screens). Never deliver web-only and call it done.
- **Backend is shared by architecture** (one endpoint, e.g. `POST /api/ai-chat/conversations/{id}/messages`, `GET /api/tax-strategy`) — backend changes usually reach `/m` for free. The gap risk is per-surface frontends: anything with a web component (dashboard card, module summary, new page/route, Fyn rendering behaviour) needs its `resources/mobile/` counterpart checked and, where missing, built.
- **"Done" for user-facing work = verified on web AND `/m`.** `/m` is verified on csjones (it serves the built bundle, no HMR; the `ssh-fynla` MCP tool is PROD — never use it for csjones).
- **Plans and specs:** when writing or executing a plan, include the `/m` surface explicitly. If a plan is silent on `/m`, treat `/m` parity as in-scope by default — flag, don't skip.
- The only exceptions are when CSJ says so ("web only", "desktop only", "skip /m"), or surfaces that have no mobile counterpart by design (e.g. the admin panel, which lives on desktop routes).

**Ownership:** OWNED by CSJ (issued 2026-06-11). Changeable only by CSJ editing this section.

## Vault Reference (fynlaBrain)

The project knowledge base is at `/Users/CSJ/Desktop/fynlaBrain/` (693 Obsidian docs). **Before working on any module, read the relevant vault docs.**

| Module | Architecture Doc | Current State Doc |
|--------|-----------------|-------------------|
| Investment | `v083/09-MODULES.md` | `Investment.md` |
| Estate | `v083/09-MODULES.md` | `EstatePlanning.md` |
| Protection | `v083/09-MODULES.md` | `Protection.md` |
| Retirement | `v083/09-MODULES.md` | `Retirement.md` |
| Savings | `v083/09-MODULES.md` | `Savings.md` |
| Goals | `v083/09-MODULES.md` | `GoalsLifeEvents.md` |
| Property | `v083/09-MODULES.md` | `Property.md` |
| Auth/Security | `v083/03-AUTH-SECURITY.md` | `Auth.md` |
| Database | `v083/02-DATABASE.md` | — |
| Frontend | `v083/05-FRONTEND.md` | — |
| Backend | `v083/04-BACKEND.md` | — |
| Deployment | `v083/11-CONFIG-DEPLOY.md` | `DeploymentBuild.md` |
| AI Chat | `v083/10-NEW-SYSTEMS.md` | — |
| Tax/Financial | `v083/08-FINANCIAL-CALCS.md` | `UKTaxes.md` |
| Payments | `v083/10-NEW-SYSTEMS.md` | `PaymentSubscription.md` |

### Sub-Agent Vault Context (MANDATORY)

When dispatching ANY agent to work on module code:
1. Read the relevant vault docs for the module first
2. Include in the agent prompt: architecture patterns, recent fixes, feedback rules

Never dispatch an agent with just "fix X" or "build Y". Always include:
- What module this is in and its patterns
- Recent bugs/fixes in this area (from vault deploy/fix docs)

## Deployment

### Two environments

Fynla runs on two environments, isolated database, code, and credentials:

| Env | URL | Purpose | Branch | Server path | SSH alias |
|-----|-----|---------|--------|-------------|-----------|
| **Production** | `https://fynla.org` | Live customers — real charges, real emails | `main` | `~/www/fynla.org/public_html/` | `ssh.fynla.org:18765` as `u2783-hrf1k8bpfg02` |
| **Dev / staging** | `https://csjones.co/fynla` | Pre-production testing — Revolut sandbox, throwaway DB | `dev` | `~/www/csjones.co/fynla-app/` (Laravel root; `public_html/fynla` is a symlink to its `public/`) | `ssh.csjones.co:18765` as `u163-ptanegf9edny` |

**Work always flows `feature → dev → main`, never skipping the dev gate.** See the branch workflow section below.

**⚠️ Never** deploy `dev` to fynla.org or `main` to csjones.co — the build scripts target different `VITE_BASE_PATH` / `RewriteBase` paths and the wrong combination breaks routing silently.

### Branch workflow

```
<feature-branch>   ──PR──►   dev   ──PR──►   main
```

- `main` = exactly what's running on `fynla.org`. Protected. Only `@Stoff73` can merge.
- `dev` = exactly what's running on `csjones.co/fynla`. Protected. Only `@Stoff73` can merge.
- **Feature branches** = working branches. Branch off `dev`, not `main`. Naming:
  - **CSJ's own work:** any short descriptive name is fine — camelCase or kebab-case. Examples: `onboardingFyn`, `fyn-quick-start`, `lifecycle-email-engine`, `revolutLive`. No prefix required.
  - **External contributors (mandatory prefix for traceability):**
    - `feature/icecube/<task>` — `icecube-acc`
    - `feature/phailanx/<task>` — `Phailanx`
  - PRs from contributors without the correct prefix will be closed.
- **All PRs target `dev`**, never `main` directly (except the periodic `dev → main` release PR which only `@Stoff73` opens).
- `.github/CODEOWNERS` forces `@Stoff73` as a required reviewer on every PR.

### Build & deploy procedures

Step-by-step build + deploy commands for both environments live in **`deploy/DEPLOY.md`** (build scripts, per-env Vite settings, dev/prod deploy steps, env templates). Essentials:

- **Build locally** (servers lack npm memory): `./deploy/fynla-org/build.sh` (prod) or `./deploy/csjones-fynla/build.sh` (dev). These set different `VITE_BASE_PATH` / `RewriteBase` — **never mix**: a dev build uploaded to prod = blank page / 404 loop, no nice error.
- **Dev (csjones)** is a git checkout tracking `origin/dev`: deploy = `git pull origin dev` on the server + upload `public/build/`. Never `migrate:fresh`; `db:seed --force` to reset. First-time setup: `deploy/csjones-fynla/BOOTSTRAP.md`.
- **Prod (fynla.org)** is manual upload: build, upload `public/build/` + changed PHP, run `migrate --force` + cache clears, monitor `storage/logs/laravel.log` for 10–15 min.
- Credentials live only in each server's `.env` (gitignored) — never in the repo or chat.

## Mobile App (Capacitor iOS)

Full conventions in `resources/js/AGENTS.md` (Mobile section) + the `mobile_capacitor_patterns.md` memory. Load-bearing essentials:

- **Build:** `./deploy/mobile/build-ios.sh` (web assets + `npx cap sync ios`). NEVER `npx vite build` alone — changes won't reach the iOS app. After any mobile change, `php artisan cache:clear` (mobile dashboard cached 5 min/user).
- **vite.config.js (blank-screen prevention):** never add `external` to `rollupOptions` for image/asset paths (Rollup leaves `/images/*` as JS imports → WKWebView rejects PNGs: `'image/png' is not a valid JavaScript MIME type'`); always keep `transformAssetUrls: false` in the `vue()` plugin; always keep `!disablePWA && VitePWA(...)`.
- **Biometric login:** mobile logout uses `auth/mobileLogout` (local state only) — NEVER `auth/logout` (revokes server token, breaks Face ID).
- **Data flow:** `MobileDashboardAggregator` (raw fields) → store `normaliseModule()` (normalised shape) → `ModuleSummaryCard`/`ModuleSummary`.

## Preview Mode

Test via landing page persona selector at http://localhost:8000, not direct URLs.

| Persona | Users | Focus |
|---------|-------|-------|
| young_family | James & Emily Carter | Mortgage, workplace pensions |
| peak_earners | David & Sarah Mitchell | Multiple properties, SIPP + NHS pension |
| entrepreneur | Alex Chen | SIPP, business interests |
| young_saver | John Morgan | Emergency fund, first-time savings |
| retired_couple | Patricia & Harold Bennett | Decumulation, estate planning |
| student | Janice Taylor | LISA, student loan, early-career planning |

## UK Tax Context

Orientation only — `TaxConfigService` is the source of truth for every value (Rule 2).

- Tax Year: April 6 - April 5 (active: 2026/27)
- IHT: 40% above NRB (£325k) + RNRB (£175k)
- ISA: £20,000/year
- Pension Annual Allowance: £60,000

## Authentication for Testing

**Production (fynla.org):**
1. Obtain the current production credentials from CSJ through a secure channel; never store them in the repository.
2. When the verification code screen appears, **ask the user for the code**
3. Enter the code provided and continue testing

**Local dev (localhost:8000) — get the code yourself:**
1. Enter credentials: `john@example.com` / `password` (or any seeded test user)
2. When the verification code screen appears, fetch it from the database:
```bash
php artisan tinker --execute="\$u = \App\Models\User::where('email','john@example.com')->first(); echo \App\Models\EmailVerificationCode::where('user_id', \$u->id)->latest()->first()->code ?? 'none';"
```
3. Enter the code and continue — do NOT ask the user for local dev codes

**Test user credentials (local dev):**

| Email | Password | Notes |
|-------|----------|-------|
| `john@example.com` | `password` | Test user with full data |
| `jane@example.com` | `password` | Spouse of John |
| `sarah@example.com` | `password` | Additional test user |
| Obtain from CSJ securely | Never store in repository | Admin user |

## Troubleshooting

Don't suggest browser cache clearing - user tests in incognito.

| Error | Fix |
|-------|-----|
| Blank page with 127.0.0.1:5173 | `rm public/hot` on server |
| Public homepage / `/m` landing shows the old SPA design instead of the server-rendered page | `php artisan route:clear` on the server. **NEVER `php artisan optimize` / `route:cache` on this app** — the compiled matcher lets the SPA catch-all shadow `/` (and the `/m` iframe loads `/`). Re-cache config only: `config:cache`. |
| MIME type errors (web) | Rebuild with `./deploy/fynla-org/build.sh` |
| iOS blank screen / `'image/png' is not a valid JavaScript MIME type'` | Check `vite.config.js`: remove any `external` from `rollupOptions`, ensure `transformAssetUrls: false` in vue() plugin. Run `grep -r 'import("/images' public/build/assets/` to verify no image imports in built JS. Delete app from device, clean build in Xcode. |
| 500 DirectoryMatch error | Upload `deploy/fynla-org/.htaccess` |
| 429 Too Many Requests | `php artisan cache:clear` |

Check routes: `php artisan route:list --path=endpoint`

## Coding Standards

**PHP (PSR-12)**
- `declare(strict_types=1);` in all files
- Classes: `PascalCase`, Methods: `camelCase`, Database: `snake_case`
- Type hints required

**Vue.js**
- Multi-word component names
- Always use `:key` with `v-for`
- Never `v-if` with `v-for` on same element

**Spelling**
- User-facing text: British (Optimisation, Customise)
- Code syntax: American (optimize, center)

## Testing

### CRITICAL — Browser Testing Rules (NON-NEGOTIABLE)

**These override everything. Violating them is an absolute failure.**

1. **"Browser tested" = you INTERACTED in Playwright** — clicked, filled, submitted, verified the result. Reading a diff or taking a snapshot without interacting is NOT a test.
2. **Not done until every form is FILLED and SUBMITTED.** If login fails: on prod, ask the user for the verification code; on local dev, fetch it from the DB yourself (see Authentication for Testing). Don't skip, defer, or write "requires user assistance".
3. **No completion report until ALL testing is done.** Reports are last; a report before testing is lying.
4. **Test every journey end-to-end** — register/login → select stage → fill EVERY field on EVERY step → submit → verify dashboard, sidebar, and cards show all entered data. No shortcuts.
5. **If you can't test something, say "I COULD NOT TEST THIS"** — never "verified"/"pass"/"confirmed" for untested items.
6. **Hit a blocker → STOP AND ASK.** Don't give up or skip.
7. **After ANY code change, re-test from step 1** — fixes break other things.
8. **Every `[x]` you mark must have a corresponding Playwright interaction.**

### Pest Tests

```bash
./vendor/bin/pest                                  # All tests
./vendor/bin/pest tests/Unit/Services/Estate/      # By directory
./vendor/bin/pest --testsuite=Architecture         # By suite
./vendor/bin/pest --filter="calculateIHTLiability" # By name
```

Pest (`it()`/`describe()`), `RefreshDatabase`, TaxConfiguration IS auto-created by the live Pest.php global hook (`uses()->beforeEach(...)->in(...)` — a 2019/20 safety-net row when no active config exists; liveness pinned by `tests/Feature/Fyn/PestHooksLivenessTest.php`). Tests needing real seeded years still seed explicitly (`$this->seed(TaxConfigurationSeeder::class)`); chat-path suites get empty scripted AI clients bound by the second global hook. `Sanctum::actingAs()`, Mockery with `Mockery::close()` in `afterEach`. **Full conventions in `tests/AGENTS.md`.**
