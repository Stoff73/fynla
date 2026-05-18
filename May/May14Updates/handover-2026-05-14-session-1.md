---
type: handover
mode: end-of-day
date: 2026-05-14
session: 1
branch: audit-rebalancing-cgt-allowance (PR #292 head)
previous_session: 2026-05-13 session 2 (context-clear, PR #291 opened) — repo's last EOD-style handover was 2026-05-13 session 1
---

# Handover — 2026-05-14, Session 1 (end-of-day wrap from 2026-05-13 session 3)

## Where we left off

PR #291 (CGT rate fail-loud) is **merged** into `dev` at `41981c2`. PR #292 (CGT **allowance** fail-loud — the sibling that the tech-debt audit on #291 surfaced) is **OPEN against `dev`** at `audit-rebalancing-cgt-allowance@a456d27` with all 3 CI checks GREEN (logic-guard ✅ snyk ✅ GitGuardian ✅). Mergeable. Local working tree clean. Currently sitting on the PR #292 feature branch — `git checkout dev && git pull` after merge.

## What shipped today

- `41981c2` Merge PR #291 — audit-rebalancing-cgt-rates → dev (CGT rate fail-loud, REVIEW #29)
- `a456d27` fix(audit): fail-loud on missing CGT annual_exempt_amount + drop hardcoded 3000 cap (PR #292 head)

Two CGT-fail-loud PRs in one session: #291 merged (rate), #292 opened (allowance sibling). Plus the tech-debt-session audit on #291 that surfaced the #292 work.

## What's in flight (NOT done)

- **PR #292 not yet merged.** All CI green; next session just needs `gh pr checks 292` then `gh pr merge 292 --merge --admin --delete-branch` (established solo-reviewer pattern — see `feedback_admin_merge_pattern_for_solo_reviewer_prs.md`).
- **Tech-debt-session audit on PR #292 changes** not run yet (Phase 4 of next session-end will cover it once #292 merges).
- **csjones not redeployed** since `dev@f22c9b988` (still pre-#291 + pre-#292). After #292 merges, csjones is 2 PRs behind. Bundle next deploy.
- **Release PR `dev → main`** not opened. The audit batch (#281–#292) is now 12 PRs deep. Notes for the release PR body: PR #284 `npm ci` requirement, PR #282 .htaccess template change, PR #290 behaviour change for pension contributors, PR #291 CGT rate behaviour change (0.18/0.24 instead of 0.10/0.20), PR #292 CGT allowance fail-loud (no behaviour change in happy path; throws on broken seed instead of silent £3,000).
- **RebalancingCalculator.vue orphan** (REVIEW #29 frontend half) — CSJ decision pending: delete or wire up.

## Deploy status

**Nothing deployed this session.** csjones.co/fynla still on `dev@f22c9b988`. Production (fynla.org) still on `main@f15e068` (pre-audit-batch). No deploy notes generated — code only moved to remote feature branch + merged into dev once.

## Tech debt found this session

The tech-debt-session audit on PR #291's three files surfaced 7 findings (1 critical + 4 warnings + 2 suggestions) — full report at repo root `tech-debt-report.md`. The critical + 2 sibling warnings were **all addressed in PR #292**. The remaining 4 findings (still open after this session):

- **Warning #4** — `RebalancingCalculationController::getAccountRebalancing` is ~154 lines (god-method). Extract `resolveAccountRiskProfile()` into a service or model method.
- **Warning #5** — `RebalancingCalculationController.php` is 639 lines, past the 500-line guideline. Consider splitting `RebalancingCalculationController` (portfolio actions) from a new `AccountRebalancingController` (account-level). Would also unblock Warning #4.
- **Suggestion #6** — Tax-config mutation block duplicated across two test cases in `TaxAwareRebalancerCgtRateTest.php` (lines 116–122 and 161–166). Extract `unsetCgtConfigKey()` helper when a third sibling test arrives.
- **Suggestion #7** — `TaxAwareRebalancer::optimizeSellOrder` docblock promises step 3 (holding-period tiebreaker) that the implementation never delivers. Drop step 3 from the docblock.

## Known issues / blockers

- **Vault-sync subagent went off-rails this EOD.** The Haiku 4.5 subagent dispatched by Phase 7 (vault-sync skill) wrote a `handover-2026-05-14-session-1.md` into the **vault's** `/Users/CSJ/Desktop/fynlaBrain/May/May14Updates/` folder containing **fynlaInternational** content (branch `refactor/uk-pack-relocation`, "G-4-b slice 3", "fynla_inter") — NOT UK Fynla content. The subagent appears to have read a fynlaInternational session-3 handover (`handover-2026-05-13-session-3-clear.md`, mtime 13:29 today) sitting in the shared vault and conflated the two projects. Practical impact: the UK Fynla EOD handover for tomorrow is in the **repo** (canonical, what session-start reads first) as `handover-2026-05-14-session-1.md`; the vault mirror was written as `handover-2026-05-14-session-1-ukfynla.md` to avoid clobbering the rogue fynlaInternational file. CSJ to decide whether to delete the rogue vault file, rename it with a project suffix, or leave it.
- **Vault is shared across two projects.** Confirmed today: the `/Users/CSJ/Desktop/fynlaBrain/` vault is used by both UK Fynla AND fynlaInternational. May Index narrative is currently fynlaInternational-focused ("May is the **architecture realignment month** for fynlaInternational"). UK Fynla session handovers land in `May/May*Updates/` folders but are not currently indexed in the May Index. This was always true but the cross-project pollution is becoming visible — worth a CSJ decision about whether to split the vault or add a project-discriminator field to handovers.
- **CLAUDE.md metric drift** flagged by vault-sync Phase 1: Service directories CLAUDE.md says 32, actual 38 (+6); API services CLAUDE.md says 45, actual 50 (+5). Other metrics current. Update CLAUDE.md table in a future doc-only PR if/when convenient.

## Rules reinforced this session

- **Sibling tech-debt pattern** — after a fail-loud fix, audit the same lines for sibling soft-fallbacks. PR #291 (rate) → tech-debt-session audit → PR #292 (allowance) is a strong pattern. Consider memorialising as a feedback rule next session: when shipping a Wave 2.5-style fail-loud fix to a tax/financial calculation, scan the immediately-surrounding lines for the same soft-fallback shape on sibling values. The vault-sync subagent flagged this as a potential new memory; deferring the actual write to next session.

## Next session should

1. **Merge PR #292.** `gh pr checks 292` (already all green at session close) → `gh pr merge 292 --merge --admin --delete-branch`. Sync local dev (`git checkout dev && git pull`).
2. **Run tech-debt-session audit on PR #292 changes** — skipped this session because the audit was on PR #291 and #292 was the fix. Quick check on the three modified files.
3. **Resolve the vault-sync rogue-file issue** — surface it to CSJ. Options: (a) delete `/Users/CSJ/Desktop/fynlaBrain/May/May14Updates/handover-2026-05-14-session-1.md` if confirmed not-needed, (b) rename it to add `-international` suffix, (c) leave it. Then promote the project-suffix mirror-naming pattern into the session-end skill so future EODs don't collide.
4. **Pick next CSJTODO item.** Top remaining candidates (priority order):
   - **Tech debt #4/#5** — split `RebalancingCalculationController` + extract `resolveAccountRiskProfile()`. Same-file follow-up to #291 + #292.
   - **REVIEW §4 High #28** — Frontend `taxConfig.js` hydrate from backend.
   - **REVIEW §4 High #32** — CoordinatingAgent 7 raw `orWhere` joint queries → `forUserOrJoint` scope.
   - **REVIEW §4 High #33 / Rule #5** — 6 ownership_type enums missing `tenants_in_common`.
   - **`RebalancingCalculator.vue` orphan** — separate PR, delete or wire up per CSJ direction.
   - **SRS in `calculateInterestTaxDetailed`** — PR #287 follow-up; needs TaxBandTracker API change.
   - **Gift Aid BRT-band extension** — PR #290 follow-up.
5. **Eventually:** open the release PR `dev → main` to ship audit batch #281–#292 to fynla.org. Don't auto-recommend — wait for CSJ to say "ship".

## Context hints

- Active branch type: **feature** (audit-rebalancing-cgt-allowance — PR #292 head)
- Currently checked-out: `audit-rebalancing-cgt-allowance` at `a456d27`. Switch to `dev` after PR #292 merges (`git checkout dev && git pull`).
- `dev` last seen at `41981c2` (post-#291 merge); `main` unchanged at `f15e068`
- Uncommitted: **none on the PR #292 branch** — tech-debt-report.md update is on its own follow-up commit pending in this EOD wrap
- Last commit on feature branch: `a456d27` — fix(audit): fail-loud on missing CGT annual_exempt_amount + drop hardcoded 3000 cap
- Test sweep: 430 passed, 1 skipped, zero failures (Investment + Architecture suites, +6 new Pest cases in `TaxAwareRebalancerCgtAllowanceTest`)
- CSJTODO updated: yes (this session — section appended for session 3)
- Vault sync: ran but partially off-rails (see Known issues above)
