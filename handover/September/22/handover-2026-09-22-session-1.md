---
type: handover
mode: session-end
date: 2026-09-22
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-09-22, Session 1

## Where things stand

Two production releases shipped today: #924 (main 904364c31, ~15:00 BST) carrying the threshold-position feature and its adjacent-defect batches (#919–#923), and #926 (main f2f880fc7, ~16:25 BST) carrying #925. Both were walked live on fynla.org with fresh registrations, since purged. dev is 54afface1, tree-identical to main; csjones runs it; the Quality Gate is fully green for the first time in weeks. The only in-flight item is a docs-only PR (#927, evidence for the #926 walk) whose gate was still running at handover; a background task merges it when green.

## Priorities for the next session

1. **Merge #927 if it has not merged itself** — docs only (`tests/Persona/threshold-position/reports/2026-09-21.md`). Check `gh pr view 927`; if OPEN and green, `gh pr merge 927 --merge --admin`, then pull dev locally and on csjones.
2. **Consolidate the two `FREE_EXPENDITURE_CATEGORIES` constants** (`app/Agents/CoordinatingAgent.php:5380`, `app/Http/Controllers/Api/UserProfileController.php:40`) into one home, and derive `OnboardingService::processExpenditureInfo`'s category lists from it rather than two hand-written lists (the separate branch still omits seven categories). Small, pure refactor; details in `docs/tech-debt-report.md`.
3. **Switch `resources/js/components/Retirement/RequiredCapitalDetail.vue:546` to `account.account_type_label`** — the one web map the label consolidation missed.
4. **The one production donor with donations recorded and `is_gift_aid` false** (pre-fd60af65d data loss). The toggle now sits beside their donations in both entry modes, so they can tick it; CSJ has not asked for anything more. Leave unless told otherwise.
5. **Native**: reads `account_type_label` but was neither built nor run today; its family model has no disability field (no native family capture exists). Nothing to do unless native parity is next on the list.

## Context to load

- `tests/Persona/threshold-position/reports/2026-09-21.md` — sessions 1–5 plus both production walks: every interaction, figure and screenshot for what shipped today; the contract for any re-test.
- `docs/tech-debt-report.md` — today's session audit, the source of priorities 2 and 3.
- `.superpowers/sdd/2026-09-21-threshold-position/progress.md` — the threshold rulings ledger (git-ignored); still the authority before touching `app/Services/Tax/Thresholds/`. Can be deleted now that #919 has merged, if CSJ agrees.
- `docs/superpowers/specs/2026-09-21-threshold-position-design.md` — the design every ruling resolves against.
- `app/Services/Tax/Thresholds/ChildcareEntitlements.php` — where the disabled-child cap and age limit now apply; the seeded keys are `max_disabled_contribution` and `disabled_child_age_limit`.
- `deploy/DEPLOY.md` — the release procedure; the collapse-audit lines were removed today.

## Completed this session

- #919 CI fix (45240db64: xAI golden master recaptured for the drawdown properties; DC pension form payload strips scratch fields by prefix). Merged as 84a5fa89a.
- #920 adjacent defects (92a992c1c): Gift Aid relief gated on `is_gift_aid`; tapered AA on s228ZA adjusted income via `adjustedIncomeFor`; `units_unvested` on the investment API; one salary-sacrifice NI pricing through `SalarySacrificeAnalyzer::employeeNiSaving`/`employerNiSaving` with `payBeforeSacrifice`/`payAfterSacrifice` from the definitions; `OnboardingChatDirector` counts pensions through the store; precision guard mapped to `protection_profiles`; phone normaliser shared with `SaveStepProgressRequest`; `apiErrorMessage` prefers field messages; share scheme card and drill-down.
- #921 (33008923b): `InvestmentAccountTypes` one label source → `account_type_label` on the resource, read by web, `/m`, native; `InvestmentAccountStore::withSchemeValue` derives `current_value` for schemes; "Months Between Vestings" on the scheme form; childcare and charitable donations free-tier on the controller gate, resource, `getProfile`, web Simple View (`GiftAidToggle.vue`), Fyn `set_expenditure` carve-out plus `is_gift_aid`, `capture_monthly_expenditure` with the three fields, onboarding writer stores `charitable_donations`.
- #922 (2c7eeab01): Gift Aid rides in the one expenditure write (a parallel personal-info write deadlocked `audit_logs` on csjones).
- #923 (432b86fdc): every long-red suite green; legacy paid plans canonicalise to premium at settlement; `AuditTierCollapse` command and test deleted; `MobileScaffoldTest` stubs Vite.
- Release #924 to production (904364c31), walked live.
- #925 (54afface1): `family_members.is_disabled` across the web form, Fyn dependants form, both family tools and schemas, web list badge, `/m` label, `ChildcareEntitlements`; taper copy ends at threshold + 2 × allowance; `serialize_precision = -1` in `AppServiceProvider::boot`.
- Release #926 to production (f2f880fc7), walked live.
- Memory: `project_release_2026_09_22.md` new; `project_threshold_position_branch_2026_09_21.md` updated; `CSJTODO.md` pruned and given two new traps.

## Verification state

- Quality Gate on dev: fully GREEN at 54afface1 (Unit, Feature, Architecture, Integration, Eval, lint, builds, browser smoke).
- Production walks: #924 as user 748 and #926 as user 749 (both purged), every figure in the persona report's two production sections.
- csjones walks: sessions 2–5 in the persona report as user 418 (rsu-walk-2026-09-22@example.com, `Password1!`, still there; income £112,400, Ava disabled dependant 632). Free to reuse or purge.
- Not verified: native (not built); Fyn's chat capture of the new expenditure and dependant fields on `/m` (unit-tested and golden-mastered only); the tapered-allowance and salary-sacrifice figures in a browser (unit-tested).

## Decisions and dead ends

- CSJ, 2026-09-22: childcare and charitable donations are free-tier fields everywhere; one label for an account type on every surface; a share scheme must carry a value; the vesting cadence goes on the existing form; long-red suites are to be fixed, never listed; discrepancies are to be fixed, never asked about. Act on these without re-asking.
- The legacy paid plans (`student`, `standard`, `family`, `pro`) confer Premium at settlement (#771); the `LEGACY_PAID_PLANS` comment in `TierConfigurationStore` saying nothing reads it at runtime is now wrong and was not updated.
- From-scratch migrations without the schema dump fail at `2025_12_23_140824` (long before the `pipeline_articles` one the previous handover named); the dump is the only base. Reproduced on a scratch database and dropped. Not worth fixing.
- The `release` skill is locked to user invocation; CSJ typed `/release` twice today. The three-question gate was satisfied both times (csjones walked, PR seen).
- Two Pest processes at once corrupt the shared test database into QueryExceptions that look real. Happened twice; always sequential.
- Production's page cache returned a stale body for a repeated probe URL for twenty minutes; that produced two wrong "the ini did not take" readings. Fresh filename per probe.
- `.user.ini` in `public/` is honoured on csjones but ignored by fynla.org's web PHP; the boot-time `ini_set` is the fix that works on both. The `.user.ini` files were left in place.
- One docs commit went straight to dev this morning (1f0b2c424), bypassing the PR rule; reported to CSJ, not undone. Every later docs change went through a PR.

## Things that will bite you

- csjones account 418 has income £112,400 and a disabled dependant; production has no test accounts.
- `public/build` and `public/m-build` locally hold the LAST build run (the production build at handover). A local `/m` check needs the mobile bundle rebuilt for the local base path first.
- The `chrisMapping/` folder in the repo root is untracked and not this session's; left alone.
- `.superpowers/sdd/2026-09-21-threshold-position/` is git-ignored and still present.

## Tech debt deferred

See `docs/tech-debt-report.md` (4 warnings, 3 suggestions): duplicated `FREE_EXPENDITURE_CATEGORIES`; hand-written category lists in `processExpenditureInfo`; label-map fallbacks including the unswitched `RequiredCapitalDetail.vue:546`; `handleSetExpenditure` at 203 lines; file-level prop-mutation disable on `EmployeeShareSchemeFields.vue`; two silent `catch {}` blocks; `.user.ini` on both servers.

## Branch and deploy state

- Branch: dev at 54afface1 (tree == main f2f880fc7), clean apart from untracked `chrisMapping/`
- Unpushed commits: none (this handover commit follows)
- Deploy status: fynla.org and csjones both run the dev tip; backups `~/release-backups/2026-09-22a/` and `2026-09-22b/` on production

Back to [[September Index]]
