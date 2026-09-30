---
type: handover
mode: session-end
date: 2026-09-30
session: 2
repo: fynla
branch: dev
---

# Session Handover — 2026-09-30, Session 2

## Where things stand

**Released and walked on fynla.org this session:** `main` `11caa4b38`, release PR #1012:
- #1009: the date of birth no longer slips a day, dates read the same in every time zone, and `/m` shows them in words;
- #1010: servers hold runtime files only;
- #1011: a question typed at a bubble step is answered, never ticked.

**On `dev`, NOT released:**
- #1013: the spouse's own walk opens each transferred record to confirm. It also fixes Edit forms dropping dividends, drawdown and lump sum, and opening workplace pensions and GIAs as the wrong kind.
- #1014: a retired partner's pension arrives as pension income, and their pension step is no longer skipped. Marriage Allowance uses the income given when a linked spouse's records hold none.
- Both were walked on csjones on web (1440px) and `/m` (390px).

**Servers:**
- csjones is on `dev` `9c53dd34e`, as a sparse checkout.
- fynla.org was cleaned; `~/release-backups` was pruned from 1.3 GB to 58 MB.

## Priorities for the next session

1. **Release #1013 + #1014 to fynla.org. BLOCKED ON CSJ: they said nothing yet about shipping these.**
   - Ask first. When approved, write the script from `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e2e2738b-f1ec-44d9-93e1-68f8d87999fe/scratchpad/release-prod-2026-09-30-s2.sh`.
   - Contents: app code only. No migrations, seeders, config or bundles. #1013 and #1014 are PHP-only; check `git diff origin/main origin/dev -- resources` first.
   - Walk on fynla.org afterwards, as a user, clicking through: invite a spouse from a real inviter account; for the retired partner, check the Income page shows "Pension income".
2. **Retirement page for someone already retired.**
   - Evidence, csjones user Pat (retired, born 1958, retired 2020, £200,000 pot drawing £30,000): "Years to go 1", "Retirement age 67", "Projected Gross Income £9,235", and a required capital, as if still saving.
   - Cause: `RetirementProjectionService::projectPensionPot` (around line 83) never reads `users.retirement_date` and clamps `max(1, $retirementAge - $currentAge)`.
   - Needs a decumulation view for retired users across the web Retirement page (`resources/js/components/NetWorth/PensionList.vue:371`) and `/m`. CSJ has not specced the view: check the spec, contract and vault first, and ask only if nothing covers it.
3. **Retirement how-to batch (26), then investment (17), then estate (12).**
   - One module at a time: draft, CSJ approves, walk, release.
   - Follow the headers of `database/seeders/data/action-how-to/protection.md` and `savings.md`: the user's own money, never "Fynla", sourced, household branches.
   - First check the module's cards come from its definitions and carry `definition_key` and `figures`.
4. **Vault sync:** run by this session-end (see below). If it failed, rerun `vault-sync` once.
5. **`CSJTODO.md` NEXT, in order** (ISA-used sums, 4.00% rate fallback, and so on).

## Context to load

- `CSJTODO.md` (NEXT section): today's done items, the open "Retirement page is wrong for someone already retired" entry, and the backlog order.
- `app/Services/Retirement/RetirementProjectionService.php:83-160`: where retired users go wrong (priority 2).
- `docs/tech-debt-report.md`: this session's debt. The date-formatter time-zone class and the dividend running total are the two to know.
- `deploy/server-runtime-paths.txt`: the keep-list. Every upload and csjones checkout stays inside it.

## Completed this session

- **Server cleanup (CSJ: "must be sorted now before anything else"):**
  - #1010 added `deploy/server-runtime-paths.txt`, each entry citing the code that reads it.
  - csjones is a non-cone sparse checkout of that list: 3.3 GB to 960 MB.
  - fynla.org went from 690 to 633 MB; removed files were moved to `~/release-backups/2026-09-30-server-cleanup/`.
  - Mockups moved from `public/` to `docs/mockups/`; fynla.org had been serving them with a 200.
- **#1009:** walked on csjones and fynla.org. The csjones walk found two more faults, both fixed: `/m` showed the date of birth as `1985-05-01`, and dates showed and saved as the previous day west of Greenwich. `dateFormatter.formatDateOnlyLong` was added, and `formatDateForInput` now passes a date-only value through unchanged. Two CI failures that had been red since #1006 (`onboardingChatEvents.test.js` fixtures) were fixed.
- **#1011:** the question-at-bubbles guard. `OnboardingStateMachine::isExactBubbleAnswer` decides what counts as a tap. Walked locally, on csjones and on fynla.org, web and `/m`: "What is the ISA allowance this year?" gets £20,000 with nothing ticked, and "not married" is no longer recorded as No.
- **Release #1012** (`main` `11caa4b38`): script run by CSJ; walked on fynla.org with account 764, purged afterwards.
- **#1013:** `WalkFormPrefill` opens the savings, ISA, investment and pension forms on the single transferred record, narrowed to its kind. Also fixed in the Edit forms:
  - dividends kept, and `users.annual_dividend_income` moved by the change;
  - drawdown and lump sum kept;
  - workplace pensions and GIAs open as the right kind.
  - Walked on csjones with two full spouse onboardings (users 434 web, 435 `/m`).
- **#1014:**
  - retired partner's income becomes drawdown on the transferred pension;
  - `nextFromPensionMore` no longer skips the personal pension step for someone not employed;
  - `TaxStrategyMath::linkedSpouseWithIncome`.
  - Walked on csjones: Pat (web, "Pension Income £30,000"), Pat 2 (`/m`, a prefilled pension step showing £30,000 drawn, and "Pension income £30,000"), and user 435 (Marriage Allowance gone on web and `/m`).
- **Patch notes** for 29 and 30 September (Markdown and PDF): `September/September29Updates/`, `September/September30Updates/` (`a9be181f6`).
- **CSJ decisions actioned:**
  - user 668's date of birth left as is;
  - production `~/release-backups` pruned to the three 2026-09-30 folders;
  - CSJ will flush the csjones SiteGround cache themselves.

## Verification state

- **Touched test files:**
  - #1009: 25 frontend tests plus 53 PHP tests;
  - #1011: 119;
  - #1013: `LinkedSpouseOnboardingTest` 25, plus 287 and 242 across related files;
  - #1014: 278, 259 and 142 across related files.
  - All green. Every new test was shown to fail without its fix.
- **Full suites:** not run (CSJ rule). CI was not waited on; #1009's CI frontend and lint jobs were green before merge.
- **fynla.org:** #1009 and #1011 walked live. #1013 and #1014 are not released, so not on fynla.org.
- **Not verified:** the iPhone app, for everything.

## Decisions and dead ends

- **CSJ was furious that obvious defects were reported as questions.** A figure a user would see wrong is fixed, not asked about. Intent: ruling 50, "stored as per usual" = where the user's own form would store it. Memory `feedback_fix_adjacent_defects_in_path` is updated.
- **"Headless" to CSJ includes verifying with tinker queries or typed deep URLs.** Setup through tinker is fine. Every check is a screenshot of the page reached by clicking through the menus. Memory `feedback_never_walk_headless_use_chrome` is updated.
- **A retired partner's non-earnings income becomes pension drawdown.** For anyone else it stays other income, because it could be rent.
- **A linked spouse's records win only when they hold income** (keeps the 2026-09-28 ruling); otherwise the income given is used, or the action waits for it.
- **Walk forms prefill with the narrowed Edit schema under the walk's own name.** The Edit save reads the first kind only, so a full multi-kind form would silently drop a second kind.
- **Dividends on edit:** the user's total is adjusted in `handleUpdateRecord`, mirroring create, not in `InvestmentAccountStore`. Putting it in the store would change web, import and seeder behaviour. The underlying two-homes problem is in the tech-debt report.

## Things that will bite you

- **Never `git reset --hard` or `checkout -f` on csjones.** `public/.htaccess` is a deliberate local edit (`RewriteBase /fynla/`). Move branches with `git fetch origin && git checkout -B <branch> origin/<branch>`. If it's lost, restore from `deploy/csjones-fynla/.htaccess` (memory `feedback_never_reset_hard_on_csjones`).
- **The csjones proxy cache still serves the wrong site** for `/fynla/login` and `/fynla/m` until CSJ flushes Site Tools, Speed, Caching. Use a query string (`?cb=x`) meanwhile. `PURGE` is refused with 403.
- **csjones ssh from Bash is allowed** by CSJ's permission rule. Production writes still go through the `mcp__ssh-fynla__ssh_exec` tool or a script CSJ runs with `!`.
- **csjones is sparse:** `deploy/` is not checked out there. Read the list with `git show HEAD:deploy/server-runtime-paths.txt`.
- **In zsh, an unquoted `$FILES` does not word-split.** Write paths out in git commands.
- **The main checkout must stay on `dev`.** Use worktrees, each with a real `vendor` copy, `.env` and a `node_modules` symlink, and remove them after merging.
- **Local web bundle builds need `VITE_BASE_PATH=/build/`,** or lazy chunks 404 with MIME errors.
- **The policy lint reads `#1009`-style text in code comments as a hex colour.** Write "PR 1009".

## Tech debt deferred

See `docs/tech-debt-report.md`:
- the date-formatter time-zone class (`dateFormatter.js:18, 51, 79, 109, 126, 203`);
- `users.annual_dividend_income` as a running total the web edit never moves (`CoordinatingAgent.php:3433, 6576`);
- two employment-status label maps (`PersonalInformation.vue:731`, `ProfileReviewPanel.vue:88`);
- the csjones `.htaccess` fragility;
- the double `incomePartsFor` (`TaxStrategyMath.php:558, 631`);
- `formSaved` loading all metadata (`WalkFormPrefill.php:152`).

## Branch and deploy state

- **Branch:** `dev` at `31de81ed6` plus this handover. Pushed; no unpushed commits.
- **Worktrees:** none.
- **Local uncommitted files are CSJ's own and were left alone:** `docs/diagrams/*.excalidraw`, `workforce/ops/log/*`, and the edit to `handover/.../session-1.md` (priority item 4 added).
- **Production:** `main` `11caa4b38`.
- **csjones:** `dev` `9c53dd34e`, with `.htaccess` intact.
- **csjones walk accounts left:** 431, 432–435 (spouse walk couples), and the `pensionwalk-*` accounts. Free to reuse or purge.
