---
type: handover
mode: context-clear
date: 2026-05-29
session: 1
branch: pureFreemium
---

# Context Clear Handover — 2026-05-29, Session 1

## Immediate state
Just finished writing the **pure-freemium implementation plan**; waiting for CSJ to choose the execution approach (subagent-driven vs inline). **No freemium code written yet** — only the spec + plan docs exist on the `pureFreemium` branch.

## The thread
1. **Revolut bug (DONE + DEPLOYED):** user Azlan (606) reported "can't subscribe, just spinning wheel". Root cause was NOT the widget/freemium — prod ran with **config uncached**, so `.env` intermittently failed to load → DB fell back to default `forge`/no-password creds → auth + `/payment/create-order` 500'd → widget spun. Live-fixed prod with `php artisan config:cache` (0 forge errors since). Shipped the code hardening via hotfix.
2. **Then CSJ asked why the trial banner still shows** despite freemium being "live". Investigation: freemium tier gating IS enforcing (`PAYMENT_ENABLED=true`), but signup still creates a 7-day trial (SP2 kept it by design); the "341 days" CSJ saw was stale csjones seed data (john). CSJ decided: go **pure freemium — no trial**.
3. **Scoped pure-freemium via brainstorming → spec → plan.** Key realisation: it's not just "remove the trial" — `CheckSubscription` currently locks non-paid/non-trial users to read-only, so the **Free tier must be made usable** (writes allowed, `DbTierGate` caps), and data-retention/deletion must be decoupled from free users.

## Files touched this session
- **Revolut fix (committed, merged, DEPLOYED to prod + dev):** `resources/js/views/Auth/CheckoutPage.vue`, `app/Providers/AppServiceProvider.php`, `deploy/fynla-org/build.sh` (commit `9586aa7` → PR #421 dev, cherry-picked `2edd2af` → PR #422 main).
- **Pure-freemium docs (committed + pushed on `pureFreemium`, NOT deployed — no code yet):** `docs/superpowers/specs/2026-05-29-pure-freemium-signup-design.md`, `docs/superpowers/plans/2026-05-29-pure-freemium-signup.md`.
- Memory added: `reference_prod_forge_uncached_config.md` (the forge/uncached-config incident class).

## What the next Claude needs to know
- **Prod is already protected** by the cached config; the Revolut code hardening is live on prod (`main` @ `13e88ad`) and dev. Do NOT re-diagnose the spinner.
- **Azlan (user 606) IS a paying customer** — `pro/yearly`, invoice #15, paid £2 with discount code #4. He thinks he failed; CSJ may reassure him.
- **csjones is on `dev`** (caught up, 78 commits pulled, migrated, config cached). It currently serves the merged-dev bundle.
- **The freemium plan's big/risky pieces:** PR2 reworks global middleware `CheckSubscription` (Free = no subscription = writable; only churned *paid* users keep the lockout); PR4 is a **data-safe migration** (`freemium:convert-trial-users`, `--dry-run`, must leave NO free user on a deletion countdown — `data_retention_starts_at`). Decisions locked: trials gone ENTIRELY, convert existing trialing→Free on deploy, FULL removal of trial machinery, keep grace/data-retention for paid churn only.
- Plan supersedes the SP2 spec's 7-day-trial decision (`2026-05-16-sub-project-2-freemium-tier-model-design.md` line 165).
- Only code changes this session = the 3-file Revolut fix; Pint + Vue-compile clean, browser-tested on csjones sandbox. No tech-debt audit run for the docs-only freemium branch.

## Pick up from here
Ask CSJ which execution approach for the plan (subagent-driven recommended), then start **PR1 Task 1.1** of `docs/superpowers/plans/2026-05-29-pure-freemium-signup.md` — registration sets `tier='free'`, no trial. First confirm the exact verify-registration route + `PendingRegistration` fields against the passing tests in `tests/Feature/Auth/RegistrationTest.php` (flagged inline in the plan).
