---
type: handover
mode: context-clear
date: 2026-05-13
session: 2
branch: audit-rebalancing-cgt-rates (clean tracked tree; standing untracked carry-over only)
previous_session: 2026-05-13 session 1 (end-of-day, audit-adjusted-net-income → PR #290 OPEN at the time)
trigger: context-clear by CSJ via /session-end after tripwire fired at ~259k tokens (>97.5% of budget)
---

# Context Clear Handover — 2026-05-13, Session 2

## Immediate state

PR #291 (Rebalancing CGT-rate fail-loud) is OPEN against `dev` at branch tip `461d4c5`. CI partially reported at close: logic-guard ✅ pass · snyk ✅ pass · GitGuardian not yet reported (expected within minutes per PR #290's pattern). Local working tree clean. No deploys this session beyond the early csjones step.

## The thread

- Session 1 (end-of-day) merged the four-PR audit batch (#287/#288/#289/#290) into `dev`. PR #290 was the last open one; this session merged it cleanly via the established admin-merge pattern.
- Deployed `dev → csjones.co/fynla` early in the session: server now on `dev@f22c9b988`, smoke verified (chris@fynla.org login + MFA via staging DB lookup → canonical Net Worth £598,250 + zero JS errors).
- Vault-sync backlog (Haiku 4.5 subagent at high effort) flushed May 11 sessions 6–12 + May 12 sessions 1–8 + May 13 session 1. Subagent flagged `UKTaxes.md` 7-day stale despite the audit batch touching tax engines (PA-taper, SRS, salary sacrifice, ANI, BADR).
- Took on REVIEW §4 High #29 ("RebalancingCalculator.vue:246 hardcoded taxRate: 0.20"). Discovered the Vue file is dead code (orphaned — zero imports anywhere in `resources/js`). Real defect surface was in `app/Services/Investment/Rebalancing/TaxAwareRebalancer.php` (4 hardcoded pre-30-Oct-2024 rate sites) + `app/Http/Controllers/Api/Investment/RebalancingCalculationController.php` (4 hardcoded `?? 0.20` fallbacks).
- CSJ chose "Backend fix only — drop the .vue change" via AskUserQuestion. Implemented the Wave 2.5 BADR-sibling fail-loud pattern (matches PR #289): private `resolveTaxRate()` helper, throws `FinancialCalculationException::taxConfigError` when neither caller rate nor config `basic_rate` is available. Tax-loss harvesting potential_tax_benefit + potential_tax_saving now thread the resolved rate instead of hardcoded `* 0.20`.
- 7 new Pest cases, 380-test Investment + Business + Architecture sweep clean, PR #291 pushed and opened.

## Files touched (uncommitted or recently committed)

**Committed this session** (commit `461d4c5` on `audit-rebalancing-cgt-rates`):

- `app/Services/Investment/Rebalancing/TaxAwareRebalancer.php` — added `resolveTaxRate()` helper, threaded `$taxRate` through `identifyTaxLossHarvesting`, dropped 2 `?? 0.10` fallbacks + 2 `* 0.20` hardcoded literals, updated docblock.
- `app/Http/Controllers/Api/Investment/RebalancingCalculationController.php` — replaced 4 hardcoded `?? 0.20` defaults with `?? null` so the service handles the lookup centrally.
- `tests/Unit/Services/Investment/Rebalancing/TaxAwareRebalancerCgtRateTest.php` — new file, 7 Pest cases.

**Vault sync wrote** (separately, via Haiku subagent — not in repo commits):

- `May/May13.md` (git history), `May2026 Commits.md` index, `May Index.md` session entries for May 11/12/13, frontmatter added to 8 May12 review files.

**Uncommitted at close:** none (standing untracked carry-over only — FCA/, campaigns/, fyn/, personas/, prompts/, tools/, etc.).

## What the next Claude needs to know

- **PR #291 is `audit-rebalancing-cgt-rates → dev`**. Use `gh pr checks 291` to confirm GitGuardian reported, then `gh pr merge 291 --merge --admin --delete-branch` per the established solo-reviewer pattern (`feedback_admin_merge_pattern_for_solo_reviewer_prs.md`).
- **`RebalancingCalculator.vue` is orphaned dead code** — Task #5 captured this. CSJ to choose: delete the file entirely or restore the missing wire-up. Don't silently delete during another PR.
- **csjones is live at `dev@f22c9b988`.** When #291 merges, csjones will be 1 PR behind (no need to redeploy immediately — bundle with next batch).
- **Production (fynla.org) still on `main@f15e068`** — pre-audit. A release PR `dev → main` carrying #281–#291 hasn't been opened yet. Notes for that release PR: PR #284 `npm ci` requirement, PR #282 .htaccess template change, PR #290 behaviour change for pension contributors (£5,000 tax saving on £110k+£10k-pension users), PR #291 CGT behaviour change (more accurate rates: 0.18/0.24 instead of 0.10/0.20). 2 prod migrations pending from session 7 batch.
- **`UKTaxes.md` (vault Current State doc) is 7-day stale** despite the audit batch touching tax engines. Refresh in a future tax-focused session.
- **Vite canonical port is 5173** (memory rule) — early in this session Vite cache went stale post-`npm ci`; killed only the fynla Vite pid (NOT `pkill -f vite` which would clobber fynlaInternational), cleared `node_modules/.vite`, restarted via `npm run dev`. Pattern worked cleanly.
- **Tech-debt-session audit skipped** under context-budget pressure (tripwire fired at ~259k). The PR #291 changes were implicitly audited via the 380-test touched-module sweep, but no formal `tech-debt-session` ran.

## Pick up from here

1. **Confirm PR #291 CI green and admin-merge.** `gh pr checks 291` → if GitGuardian now reported pass, `gh pr merge 291 --merge --admin --delete-branch`. Sync local dev (`git checkout dev && git pull && git fetch --prune`).
2. **Run a tech-debt-session audit** on the three files touched in PR #291 (skipped this session). If clean, move on.
3. **Pick next CSJTODO item.** Top candidates (remaining REVIEW §4 High items):
   - Frontend `taxConfig.js` hydrate from backend (REVIEW #28).
   - CoordinatingAgent 7 raw `orWhere` joint queries → `forUserOrJoint` scope (REVIEW #32).
   - 6 ownership_type enums missing `tenants_in_common` (REVIEW #33, Rule #5).
   - SRS in `calculateInterestTaxDetailed` (PR #287 follow-up — needs TaxBandTracker API change).
   - Gift Aid BRT-band extension (PR #290 follow-up).
   - **Address RebalancingCalculator.vue orphan** (Task #5) — separate PR, delete or wire up per CSJ direction.
4. **Eventually:** open the release PR `dev → main` to ship the audit batch (#281–#291) to fynla.org. Don't auto-recommend — wait for CSJ to say "ship".
