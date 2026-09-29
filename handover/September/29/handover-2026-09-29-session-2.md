---
type: handover
mode: session-end
date: 2026-09-29
session: 2
repo: fynla
branch: dev
---

# Session Handover — 2026-09-29, Session 2

## Where things stand

- **The protection module is released to fynla.org.** Two releases today, `main` `aba5f4821` then `f06910ac2`:
  - protection cards now come from the action definitions;
  - all 29 protection how-tos are approved and live;
  - employer benefits can be entered on web, `/m` and through Fyn;
  - the new cover position: three cards and a "Your cover" section.
- **Verification:** web on production was walked through the Carter demo persona and is right. **`/m` on production was never seen rendering.** That walk was in progress (registering an account on fynla.org) when the session ended.
- **Branches:** local and csjones are on `dev` `ac5deecea`. Its tree matches `main`, apart from CSJ's own uncommitted excalidraw and `workforce/` files.

## Priorities for the next session

1. **Walk `/m` on fynla.org yourself, now.**
   - There are no `/m` demo personas. Register a walk account at `https://fynla.org/register` (e.g. `walk-2026-09-30@example.com` / `Password1!`).
   - Fetch its code over plain ssh: `ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org`, then `cd ~/www/fynla.org/public_html` and tinker on `EmailVerificationCode`.
   - Add income and a policy so the figures mean something.
   - Walk `/m/app/protection`: "Your cover", the three position cards, and employer benefits through Fyn ("Edit employer benefits" opens Fyn's form; save one value).
   - Then purge the account.
   - **Never ask CSJ for an account or a code.** CSJ was furious about this today; see memory `feedback_verify_prod_yourself_make_accounts`. If auto mode blocks the prod read, say so in one line and hand CSJ the single command.
2. **Fix the Rule 2 critical in the path:** `app/Services/Protection/CoverageGapAnalyzer.php:480-482` still carries literal SSP fallbacks (`116.75`, `28`, `125`). Small change with its own test; release it with the next batch.
3. **Retirement how-to batch (26), then investment (17), then estate (12)**, one module at a time: draft, CSJ approves, walk, release.
   - **Check first that the module's cards really come from its definitions** and carry `definition_key` and `figures`. Protection did not: its cards came from a 7-rule `RecommendationEngine` until #972. Retirement, investment and estate adapters do read `definition_key`, but confirm that `figures` reach the card (`buildRecommendation` → adapter `extra` → aggregator → `ActionCardService`).
   - List which keys fire on real local households, and group keys that are the same action.
   - Model the steps on `protection.md` and `savings.md`.
   - Add the module to `ActionHowToSeeder::SOURCES`.
   - The draft must be on `dev` when CSJ is asked to review it.
4. **One rule for "ISA allowance used this year"**, carried over from session 1 (see `CSJTODO.md` NEXT).
5. **The rest of `CSJTODO.md` NEXT**, in order.

## Context to load

- `CSJTODO.md` (the NEXT section): the ranked list, pruned today.
- `database/seeders/data/action-how-to/protection.md`: the approved model for module batches, including the "folded" and "not written" header rules and the position entries' branching.
- `docs/superpowers/specs/2026-09-29-protection-cover-position-design.md`: what the cover position is and why. Read it before touching protection cards.
- `docs/tech-debt-report.md`: today's deferred items, including the SSP critical.
- Memory `project_release_2026_09_29_protection.md` and `feedback_verify_prod_yourself_make_accounts.md`.

## Completed this session

- **#972, protection cards from the definitions.**
  - `ProtectionStrategySource` now evaluates `ProtectionActionDefinitionService` over the comprehensive plan; it used to call the old 7-rule engine.
  - `buildRecommendation` carries `definition_key`, `figures` and `policy_id` (per-policy ids).
  - The plan no longer crashes for readiness-blocked users.
  - SSP fallbacks are removed from the definition service.
  - `RecommendationRouting` has the new keys.
  - `protection.md` draft written.
- **#974, #987 and #995, CSJ approvals:** 25 plus 1 plus 3 how-tos, 29 approved in all.
- **#981, employer benefits input.**
  - `EmployerBenefitsWriter` is the one writer. The web endpoint `PUT /api/protection/employer-benefits` and the form on the Protection page use it, and `/protection/employer-benefits` opens the form.
  - `/m` has a section whose button opens Fyn.
  - Fyn has a capture form and tool: `capture_employer_benefits`, a corpus entry, and the edit path in `RecordEditForms`.
  - `employer_benefits_recorded_at` makes "none" count as an answer.
  - Readiness no longer passes everyone on the defaulted `has_employer_pmi`.
  - A contextual conversation now opens on a record's form (`RecordEditForms::CONTEXTUAL_FORMS`).
  - Remove is only offered on removable records (`REMOVABLE_TYPES`).
- **#985 and #988:** the spec and the implementation plan for the cover position.
- **#992, cover position:**
  - `ProtectionCoverPosition` is one calculation.
  - The plan's `coverage_analysis` reads it. The hardcoded 3× income and 70%-of-net needs are gone, and the life gap no longer subtracts critical illness cover.
  - Three position cards (`life_cover_position`, `critical_illness_position`, `income_protection_position`) fold the gap and reliance definitions in as reasons.
  - "Your cover" section on web and `/m`.
  - `protection.dis_reliance_percent` is seeded, with no fallbacks.
- **Releases to fynla.org:** #994 (`aba5f4821`) and the how-to follow-up (`f06910ac2`). Backups are in `~/release-backups/2026-09-29-protection/`.

## Verification state

- **Tests:** only the touched files were run each time (145 cases at #992), all green. Full suites were not run (CSJ rule), and CI was not waited on.
- **csjones:** every PR was walked on its branch on web and `/m` before merging. User 402 went from 8 protection cards to 4.
- **fynla.org web** (Carter demo, 1440px):
  - "Your cover" shows life £131,953 short, critical illness £225,000 short, income protection £3,750 a month short;
  - the life card and the income protection card match the page, and their approved steps render;
  - the dashboard Protection tab shows the three position cards plus "Review your existing protection policies", and no folded cards;
  - the employer benefits section shows.
- **Server checks on fynla.org:** smoke 200 on `/` and `/m`, the new bundle is served, and no errors in the log since 13:00.
- **Not verified:**
  - `/m` on fynla.org (priority 1);
  - the iPhone app;
  - the whole-branch independent review of #992, which was stopped at CSJ's instruction ("finish it now").

## Decisions and dead ends

- **CSJ rulings today:**
  - Protection cards come from the definitions.
  - Build the employer benefits input.
  - Show and consolidate the overlap, in cards and on the page.
  - Fold the gap and reliance cards; per-policy cards, "no policies", premium cost and review stay separate.
  - Over-insured becomes a review card.
  - Execute plans natively, not with subagents.
  - "This should take minutes": speed matters. Don't sit on reviews, and never park waiting on CSJ.
- **Needs come from configuration and the page's own calculation.** Critical illness is gross income × `protection.income_multipliers.critical_illness`. Income protection is `income_protection_max_benefit` × gross, shown monthly. This changed user 402's income protection shortfall from £3,052 to £3,600 a month.
- **Reliance threshold:** `protection.dis_reliance_percent` (0.5). "Depends on your job" means the job's share is above the threshold.
- **Dead end: `php artisan tinker file.php`** hangs waiting on stdin. Use a plain PHP bootstrap script (`require vendor/autoload.php` and `bootstrap/app.php`) instead.
- **Dead end: the local web login hangs** after clearing `localStorage` in a shared tab. Open a fresh tab.
- **Dead end: a stale revoked bearer in shared storage** causes a 401 on `verify-code`.

## Things that will bite you

- **zsh does not split `$SSH` variables.** Write the ssh command out in full. This bit again today.
- **Release scripts:** `git checkout main` fails on CSJ's uncommitted excalidraw edits, and a `&&` chain does not trip `set -e`, so today's build ran on `dev`. That was harmless only because the tree matched `main`. Build from a worktree of `main`, or check the tree first.
- **Auto mode blocks editing a prod-deploy script after it's written.** Get it right before the first save.
- **Local `/m`:** after `./deploy/csjones-fynla/build.sh` the local `/m` bundle has the csjones base path. Rebuild with `npm run build:mobile`.
- **`.superpowers/` is not git-ignored** in this repo. Stage explicit paths only.
- **Protection mortgage rules read Mortgage records**, not `protection_profiles.mortgage_balance` (W-0227). Test fixtures need a Mortgage row.
- **Test users:**
  - locally, 11 (John) and 110 (`planb-single-2026-09-25@example.com` / `Password1!`, no subscription row, so free-tier writes work);
  - 16 and 17 have expired subscriptions, which block writes with a 403.

## Tech debt deferred

In `docs/tech-debt-report.md`:
- **Critical:** literal SSP fallbacks at `CoverageGapAnalyzer.php:480-482`.
- **Warnings:**
  - four copies of the needs and coverage assembly (Rule 20);
  - a duplicate `getEnabled()` query in `consolidate()`;
  - the `evaluateCoverPosition()` stub;
  - cover and benefit wording written twice, once per bundle;
  - `app()` used as a locator in `handleCaptureEmployerBenefits`.
- **Also still open:** `RecommendationEngine` still feeds `ProtectionAgent::analyze()`.

## Branch and deploy state

- **Branch:** `dev` at `ac5deecea`, pushed.
- **Unpushed commits:** none, apart from this handover commit.
- **Production:** `main` `f06910ac2` (== `dev` tree).
- **csjones:** `dev` `ac5deecea`.
