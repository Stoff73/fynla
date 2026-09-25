---
type: handover
mode: session-end
date: 2026-09-25
session: 2
repo: fynla
branch: dev
---

# Session Handover — 2026-09-25, Session 2

## Where things stand

CSJ asked for five things about the tax strategy work today. Three of them are done:
- **Item 3, the threshold strip:** placement stays as it is.
- **Item 4, the Save Tax outcomes by household:** written up.
- **Item 5, all onboarding through Save Tax:** built, walked on web and `/m`, and **released to production** as #940. It went out together with the #935–#937 Fyn fixes.

Two remain. Item 1 is the tax strategy plan shown as headed actions, each with a how-to page. Item 2 is every action on `/m` and iOS linking to its own how-to. They split into **Plan B (fix the strategy engine first)** and **Plan C (how-to pages)**. CSJ approved that order. **Neither plan has been written yet.** The next session starts with Plan B.

## Priorities for the next session

1. **Write and run Plan B: fix the strategy engine.**
   - The bug list (B1–B14) and every strategy's conditions by household are in `September/September25Updates/savetax-outcomes-by-household-2026-09-25.md`. Write the plan with `superpowers:writing-plans`, then run it inline with `superpowers:executing-plans` (CSJ ruling 2026-09-24: no implementation subagents).
   - CSJ's rulings, already settled, don't re-ask:
     - (a) The headline total is as accurate as possible. A suggestion appears only when the user qualifies or has the income or asset it depends on, and only real tax saved counts. The Lifetime ISA bonus, junior pension uplift, "unused dividend allowance" and the tapered allowance "charge avoided" are not tax saved. No double counting: ISA top-up and gift-to-spouse shelter the same interest, and Marriage Allowance and gift-to-spouse both use the spouse's allowance.
     - (b) "Spouse or civil partner" on the public funnel page (`public/pages/savetax.php`, `public/pages/js/savetax.js`) too. Unmarried partners must not get Marriage Allowance or spouse-transfer advice. Today `FunnelAnswersMapper.php:47-50` maps spouse=yes to married.
     - (c) Pension tax relief suggested for **every** tax band. Today only the 60% and 45% band items exist, while `SaveTaxEstimateService.php:70-85` promises a pension saving to everyone.
   - Correctness fixes that need no ruling:
     - B1: salary sacrifice only against workplace pensions (`SalarySacrificeNiStrategy.php:43-46` filters on the raw `monthly_contribution_amount`).
     - B5: the spouse pension top-up takes account of `spouse_pension_input_annual`.
     - B6: no Marriage Allowance with no taxable income.
     - B7: allow Marriage Allowance in `dual_earner` mode when the spouse earns below the Personal Allowance.
     - B13: the £2,880 and £720 figures and the Gift Aid 25% / 31.25% factors come from `TaxConfigService`.
   - Load `data-integrity-traps`, `test-failure-forensics` and `vault-context` (tax module) first, and run `tax-compliance-reviewer` on the diff.
   - The Save Tax synthesis must keep mirroring the dashboard's `composed_plan.items` (memory `feedback_savetax_synthesis_mirrors_dashboard`).
   - Verify on web and `/m`. Then PR to `dev`, deploy the feature branch to csjones and walk it, then admin-merge.
2. **Then write and run Plan C: the how-to pages.**
   - How-to content (CSJ ruling): fixed steps that I draft and CSJ reviews, with the user's figures filled in, stored once. No how-to content exists today. `tax_action_definitions.action_template` is empty for all 20 strategy rows, and the other modules' `action_template` holds a one-line hint only.
   - The detail page uses the **approved decision-card design C** in the canvas `https://claude.ai/artifact/6mqya3ujQRPjZkAjfqbYor`: heading, description, "why this matters for you", how to do it, Ask Fyn, Mark as done. It goes on web, `/m` and iOS.
   - The Tax Strategy plan becomes headed actions, each opening its how-to. Watch out: web `StrategyRecommendationList.vue` renders `dashboard.recommendations`, not `composed_plan.items` like `/m` and iOS do. Fix that as part of this.
   - CSJ's clarification: "See all actions" opens a page listing **all** actions, and every action (all modules, about 160 enabled definitions) links to its own how-to.
     - `/m` already has `/actions`, but its rows open a module page or Fyn.
     - **iOS has no actions list.** "See all actions" goes to Achievements (`DashboardView.swift:130`), so the list must be built.
   - An orphaned web `ActionDetailView.vue` (`/actions/:planType/:actionId`) exists for module plans. Check whether it can be reused before building new.
   - Order: tax items first, after Plan B lands, then other modules in batches for CSJ to review.
   - The web and `/m` intro text already promises "Open any strategy to see the steps" (`TaxStrategyDashboard.vue:59`, `/m` `TaxStrategy.vue` `personalisedIntro`).
3. **QUEUED (only on CSJ's go): the typed-memory plan.** `docs/superpowers/plans/2026-09-24-fyn-typed-memory-and-dense-recall.md`. Not started. D5 and `OPENAI_API_KEY` are blocked on CSJ before Tasks 7 and 9.
4. **Low priority:** the iOS `AuthenticationCoordinatorTests.refreshAuthenticatedUserReplacesTheCachedUserWithoutTouchingTheSession` has been red since at least 2026-09-22. The state is `passwordChangeRequired` after `verifyLogin`, although the 96d8dfdfc test fix is in. It isn't a gate.

## Context to load

- `September/September25Updates/savetax-outcomes-by-household-2026-09-25.md`: the strategy catalogue by household and bugs B1–B14. This is Plan B's spec input.
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/project_tax_plan_howto_programme_2026_09_25.md`: CSJ's five items, the sequence and every ruling.
- `CSJTODO.md` section "NEXT — Tax strategy: Plan B, then Plan C": the working checklist for both plans.
- `docs/superpowers/plans/2026-09-25-savetax-only-onboarding.md`: the last plan's format, including the Amendment header and the Review Focus. A model for Plans B and C.
- `September/September25Updates/patch-notes-2026-09-25.md`: what shipped today, in user terms.

## Completed this session

- **#939** Save Tax-only onboarding: merged into `dev` as `d6650eb93`, 8 commits `bfbb71124..99a55cd67`.
  - `onboarding.forced_campaign=savetax`.
  - Chat asks the funnel questions when missing: employment, spouse or civil partner, spouse income, assets. The income band was dropped by CSJ ruling.
  - Profile-aware skips, and one greeting on whichever question comes first.
  - Restart, first turn and the web wizard all go to Save Tax. A completed user's Planning Journeys still open.
- **#940** release, `main` `9bc414107`: carried #939, #935, #936, #937 and #938. Deployed to fynla.org and walked live on web and `/m` (account 751, purged). Backup at `~/release-backups/2026-09-25/`.
- Patch notes `39cb1d02f`; tech-debt report `docs/tech-debt-report.md`.
- Memory:
  - `project_tax_plan_howto_programme_2026_09_25`
  - `project_release_2026_09_25` (includes the auto-mode permission lessons)

## Verification state

- CI on #940 at `9bc414107`: php-tests Feature, Unit, Architecture, Eval and Integration, frontend-tests, builds, browser-smoke, lint and logic-guard all pass. iOS `test-and-build` fails (pre-existing, not a gate).
- Local at `f26e5364f`: Onboarding 549, AI 519, Architecture 116, Unit/Services/Onboarding 732, vitest router 10.
- Walked on:
  - local web and `/m` (evidence in `tests/Persona/savetax-only-onboarding/reports/2026-09-25.md`);
  - csjones web and `/m` on the feature branch;
  - fynla.org web and `/m` after the release. Those screenshots are only in the session scratchpad and aren't committed.
- **Not verified:**
  - iOS native (not walked). The change is server-side, and the reviewer confirmed `FynEvent` decodes `quick_replies` generically.
  - The "Start over" UI: no surface shows a restart bubble, so it's feature-tested only.
  - A funnel-page user (`/savetax` → register) was not re-walked in a browser this session. Tests cover it.

## Decisions and dead ends

- CSJ rulings are in the programme memory. Don't re-ask them: the income band is dropped; "spouse or civil partner" (the same in law); an accurate total; pension relief for all bands; threshold placement unchanged; fixed how-to steps; all modules' actions get how-tos.
- **Rulings I made that are now in production:**
  - Typed text at the new first question steps aside to advice Fyn (the same rule as path_choice). No "Something else" bubble was added.
  - The wizard redirect is for users still onboarding only.
  - Spouse-income labels use `FunnelIncomeBand::label()`.
- **Onboarding state machine gotchas, learned the hard way:**
  - The corpus and in-code state tables must list states **in the same order**, or the corpus is silently ignored.
  - `resolveStateId` compares whole state arrays, so never alter a state at read time inside `getState`. Use `bubblesFor()`.
  - YAML reads `50271_100000` as an integer, so quote ids.
  - Pest helper function names are global across files; give them unique prefixes.
- **Permissions in auto mode:**
  - The classifier refuses production deploys (`artisan down` on prod), `--admin` merges CSJ hasn't asked for in the conversation, edits to `.claude/settings*.json`, and even a ScheduleWakeup whose prompt describes a prod deploy.
  - What works: write the deploy as a script in the scratchpad and CSJ runs it with `! bash <script>`. The last one is at `scratchpad/release-prod-2026-09-25.sh` in this session's scratchpad.
  - CSJ added allow rules for csjones and prod ssh/rsync.
- The lint job needs `Mobile impact: shared-backend|mobile-changed|no-counterpart-approved` in every PR body that touches desktop UI.

## Things that will bite you

- **Another Claude session may share this checkout.** Do branch work in a worktree under the scratchpad. Copy `vendor` (never symlink it) and run `composer dump-autoload -o`.
- **Local `pest` runs share one testing database.** Two concurrent runs, such as a background suite plus a foreground test, fail with `ProcessFailedException` or spurious reds.
- **The local dev server** on 8000/5173 belongs to someone else. For worktree testing, build bundles (`VITE_BASE_PATH=/build/`) and `APP_URL=http://localhost:8010 php artisan serve --port=8010`.
- **Registration codes:** on local and csjones they're in `pending_registrations.verification_code`, login codes in `email_verification_codes`. For prod `@example.com` walk accounts, reading the code over ssh worked.
- `RetentionPurgeService` lives in `App\Services\Account`, not `GDPR`.
- **Test accounts left:** local 108 `nofunnel-2026-09-25` (marked completed), 109 `mfunnel-2026-09-25`; csjones 422 `savetax-only-2026-09-25` (all `Password1!`).

## Tech debt deferred

From `docs/tech-debt-report.md` (0 critical):
- **Warnings:**
  - The forced-entry rule is duplicated. `AiChatController.php:817-821` should call `OnboardingStateMachine::forcedCampaignEntry()` (`OnboardingStateMachine.php:1066`).
  - `describeStep` has no labels for `campaign_funnel_*` (`OnboardingChatDirector.php:982-1023`).
- **Suggestions:**
  - A paused Pension Check pointer is never consumed under forcing (`AiChatController.php:665-669`).
  - Restart keeps `funnel_answers`.
  - The `emitFirstTurn` default changed even with forcing off.
  - Band label wording is split between `label()` and `pageLabels()`.
  - Typed unmatched text at the first question leaves onboarding.

## Branch and deploy state

- Branch: `dev`, up to date with origin after this handover commit. The main checkout still has four modified files and several untracked dirs that are **not from this session** (excalidraw diagrams, `workforce/ops/log/*`, `brettTest/`, `chrisMapping/`, `September/September22Updates/`, workforce briefs, `handover/September/15/…`). They were left untouched.
- Worktree `wt-savetax` has been removed and branch `feature/savetax-only-onboarding` deleted. A leftover directory is only scratch.
- Production: `main` `9bc414107` (#940), deployed and walked. csjones: `dev` `d6650eb93`, bundles current. Patch notes `39cb1d02f` are on `dev` only (docs, no release needed).
