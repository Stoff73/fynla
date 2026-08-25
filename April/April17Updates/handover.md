# Handover — Admin Insights CMS

**Date:** 17 April 2026
**Feature:** Admin CMS for managing insight articles on fynla.org
**Status:** Spec + plan + PRD complete, codebase-audited, ready to implement
**Branch:** `main` (clean — no feature branch started yet)

You are picking up a fully scoped, validated feature. Your job is to execute the plan. **Do not re-design.** All the hard decisions have been made through a rolling interview with the user against a codebase audit. Eight 🔴 conflicts, 7 🟡 ambiguities, and 4 🟢 gaps were surfaced and resolved before you arrived.

---

## The three documents — read in this order

1. **PRD** (this folder): `PRD-admin-insights-cms.md` — 5-minute read. The "why" and "what". Section 5 lists all functional requirements, prioritised.
2. **Spec**: `/Users/CSJ/Desktop/fynla/docs/superpowers/specs/2026-04-17-admin-insights-cms-design.md` — the canonical design doc. 374 lines. Data model, block schema, architecture, risks.
3. **Plan**: `/Users/CSJ/Desktop/fynla/docs/superpowers/plans/2026-04-17-admin-insights-cms.md` — the task list. 6,058 lines, 44+ tasks across 6 phases. Every task has file paths, code snippets, test code, and a commit command. **This is your execution document.** Follow it task-by-task.

All three agree — amendments were applied synchronously across spec + plan after the audit.

---

## Feature at a glance

- 3 new tables: `insight_articles`, `insight_templates`, `insight_article_revisions`
- 11 block types powering a structured content editor
- New public route `/insights/:slug` rendering DB-driven articles (declared AFTER the 8 named bespoke routes)
- Admin UI under `/admin/insights` with list + split-layout editor + template management
- Landing page's "Latest insights" section changes from 3 equal cards to 2/3 featured + 1/3 stacked bento
- 8 existing bespoke articles get DB rows with `is_bespoke=true` but keep rendering from their Vue files — the CMS only edits their metadata
- Feature flag `VITE_INSIGHTS_CMS_ENABLED` lets Phases 1-3 ship to production before the frontend activates
- New dependencies: `intervention/image` (composer), `dompurify` (npm) — both are **not** currently installed

---

## Execution approach

**The user has not picked subagent vs. inline yet.** Ask them before starting. Memory rule: *"Prefer inline execution for sequential work. If using subagents, MUST check their work rigorously."* For a 44-task plan, inline is the practical choice — phase-by-phase with checkpoints at boundaries.

Tasks follow TDD: write failing test → run to confirm failure → implement → run to confirm pass → commit. Every task ends with a `git commit` command. **Do not batch commits.** One task = one commit.

---

## Critical audit findings you must not lose

These are the things the codebase audit caught that would have broken the feature if missed. They're baked into the plan, but flagging them here so you don't "clean them up" without understanding why:

1. **`/insights/:slug` route precedence** — must come AFTER the 8 named insight routes in `router/index.js` AND BEFORE `/{any}` catch-all in `routes/web.php`. An architecture test in Task 42 enforces both orderings. If you touch either file, confirm ordering.

2. **`SanitizeInput` middleware would silently strip paragraph HTML** — Fynla's global middleware runs `strip_tags` on every string field in every request, including nested JSON. Task 16b updates it to preserve `body_blocks`. **Do not skip Task 16b** or the CMS will store plain text where HTML was expected, with no error.

3. **Admin middleware is `permission:admin.access`, not `admin`** — every existing admin route uses the RBAC permission string. The plan uses this throughout. Don't "simplify" to the `admin` alias.

4. **Revisions are written by the observer, not the service** — `InsightArticleService::update()` does NOT call `InsightArticleRevision::create(...)`. The `InsightArticleObserver` owns that write. If you see a second revision write appearing anywhere, that's the double-write bug the audit caught; remove it.

5. **`intervention/image` and `dompurify` are NOT installed** — Tasks 10 Step 0 and 26 Step 2 install them. They have their own commits. Run these before writing code that depends on them.

6. **`app.blade.php` has no SEO injection point by default** — Task 22 Step 5 adds `@stack('head')`. The middleware pushes to this stack via a view composer (Step 6). Don't try the earlier inline-Blade approach — the spec originally proposed it but `app.blade.php` has a fully static `<head>`.

7. **No `SitemapController` exists in Fynla** — Task 23 is intentionally skipped. Don't try to "find the existing sitemap controller" — there isn't one. Insights discoverability comes from internal links until someone builds sitemap infrastructure as a follow-up.

8. **Design guide is v1.4.0 not v1.3.0** — refer to `fynlaBrain/Design/fynlaDesignGuide.md` for the palette and patterns.

9. **8 bespoke articles, not 9** — the `InsightsHubPage.vue` hardcoded array has 9 entries but one entry maps to no real Vue file. The seeder in Task 8 lists 8.

10. **Preview query-param auth check** — `?preview=true` returning drafts must verify `is_admin` first. Task 21's test suite covers this.

---

## Fynla rules to respect throughout (non-negotiable)

- **All tax values via `TaxConfigService` / `@/constants/taxConfig`.** The `TaxYearStatBlock` depends on this; so does anything you might add. Zero hardcoded tax years, allowances, thresholds. Stop hook enforces this.
- **Design system v1.4.0 palette only**: `raspberry-*` (CTAs), `horizon-*` (text/nav), `spring-*` (success), `violet-*` (warnings), `savannah-*` (hover), `eggshell-*` (bg), `neutral-*` (muted). No amber, orange, `primary-*`, `secondary-*`, or `gray-*`.
- **British spelling in user-facing text** (Optimisation, Customise). American in code (optimize, customize).
- **Acronyms spelled out** except ISA.
- **No scores** (0-100, X/100) in UI — use currency, percentages, time periods.
- **Form modals emit `save`** — parent owns the API call. Full-page editors are exempt.
- **Global CSS classes** in `resources/css/app.css`: reuse `.card`, `.card-lg`, `.modal-overlay`, `.modal`, `.badge-*`, `.btn-primary`, `.btn-secondary`. Never redefine.
- **`declare(strict_types=1);`** on every PHP file.
- **`php artisan db:seed` is mandatory** — before browser tests, after backend changes, after frontend changes, always. Memory rule: violated repeatedly, user is furious.

---

## Pre-flight checklist (run these before touching code)

```bash
# Sanity check — on main, clean tree
git status
git rev-parse --abbrev-ref HEAD

# Pull latest
git fetch origin && git pull origin main

# Seed database
php artisan db:seed

# Dev server (if not running)
./dev.sh

# Confirm the 8 bespoke insight Vue files exist
ls resources/js/views/Public/insights/*Page.vue | grep -v InsightsHub

# Confirm router has the 8 named insight routes
grep "name: 'Insight" resources/js/router/index.js | head -10

# Confirm permission:admin.access is a real middleware (not a typo)
grep -r "permission:admin" routes/api.php | head -3

# Confirm Intervention Image is NOT yet installed (plan's Step 0 will add)
composer show intervention/image 2>&1 | head -2

# Confirm DOMPurify is NOT yet installed
grep dompurify package.json || echo "not installed — plan's Task 26 Step 2 installs it"
```

---

## Starting point — Task 1

Open the plan. Scroll to:

```
## Phase 1 — Data Layer

### Task 1: Migration — `insight_articles` table
```

That's your starting point. Follow steps 1 and 2 exactly. Commit per the commit command shown. Move to Task 2.

Phase 1 is 8 tasks of pure data-layer work — migrations, models, factories, seeder. Low risk, clean commits. A good shakedown that you've absorbed the Fynla conventions before you hit the more interesting backend services in Phase 2.

---

## Recommended phase cadence

- **Phase 1** (Tasks 1-8, ~2 hours): Data layer. Ship as one unit, run `php artisan db:seed` at the end to confirm the seeder works against the new schema.
- **Phase 2** (Tasks 9-15, ~3-4 hours): Backend services. Each service has unit tests. Run all Pest unit tests at the end of the phase: `./vendor/bin/pest tests/Unit/Services/Insights/`.
- **Phase 3** (Tasks 16-22, ~3 hours): Form requests, controllers, middleware. Run all feature tests at the end.
- **Phase 4** (Tasks 24-33b, ~4-5 hours): Public frontend. Block renderer + 11 components + public article page + router + hub refactor + landing refactor. Browser-test at the end per the project's rules — visit the hub, visit one bespoke article (should still render original Vue), create a tinker-generated DB article, visit its URL, confirm blocks render.
- **Phase 5** (Tasks 34-41c, ~5-6 hours): Admin frontend. Biggest phase. The editor is the most complex component. Reach the end of this phase and you can publish a real article through the CMS.
- **Phase 6** (Tasks 42-44, ~1-2 hours): Architecture tests, full-suite run, deploy notes.

Rough total: 18-22 hours of focused work. Not a one-shot sitting.

---

## Commit hygiene

Per Fynla's rules:
- One task = one atomic commit
- Never `git add -A` — add specific files
- Never skip hooks (no `--no-verify`)
- Never commit unless the user has said so — but for a planned implementation execution, the commits at the end of each task are part of the plan and implicitly sanctioned. If in doubt, check with the user at phase boundaries.

---

## Deploy — when you're done

Plan's Task 44 writes a deploy guide. The critical points:

- `intervention/image` and `dompurify` install on the host via `composer install` and `npm install` as part of the build — no manual dep upload.
- Three migrations run: `php artisan migrate --force`.
- Seeder runs once: `php artisan db:seed --class=ExistingInsightsMetadataSeeder --force`.
- Feature flag `VITE_INSIGHTS_CMS_ENABLED=false` on production initially; backend Phases 1-3 deploy safely with the old frontend. Verify `/api/insights` returns data, then flip to `true` and rebuild frontend.
- Deploy to **dev** (`csjones.co/fynla`) first. Branch workflow: `feature/csj/insights-cms → dev → main`, never skip dev.
- Caveat from memory: the dev server may be running a DIFFERENT branch than `dev` (e.g. `onboardingFyn`). **ASK the user which branch is deployed** before building and uploading.

---

## Browser testing law (read before declaring any task complete)

From the user's standing memory:

> "Browser tested" means you CLICKED, FILLED, SUBMITTED in Playwright and verified the RESULT. Reading a diff is NOT testing. A snapshot without interaction is NOT testing. NEVER say "verified", "pass", "confirmed" for items you did not interact with. NEVER write a report or declare "complete" until ALL browser testing is finished. If login/registration fails — ASK THE USER.

For this feature, Task 33 and Task 41 have explicit Playwright checklists. Complete every item in them. No shortcuts.

---

## If you get stuck

- Audit findings you didn't expect → spec and plan already resolve them, re-read. If something is genuinely new, ask the user.
- Dependency install fails → check `composer.json` / `package.json`, confirm lock file, try `composer update --lock` / `npm install` without flags.
- Pest test fails in a way the plan doesn't cover → investigate root cause (systematic-debugging skill). Do NOT skip tests or lower expectations.
- Browser behaviour doesn't match the plan → re-check the spec's User Flow section. If the spec says one thing and the plan says another, the spec wins (the plan was derived from the spec).

---

## One-liner for the user when you pick this up

> "Picking up the Admin Insights CMS. I've read the PRD, spec, and plan. Starting Task 1 (`insight_articles` migration). Should I execute inline with phase-boundary checkpoints, or dispatch a subagent per task? You also haven't committed the spec/plan/PRD yet — want me to commit them as a baseline before starting?"
