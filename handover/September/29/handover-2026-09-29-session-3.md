---
type: handover
mode: session-end
date: 2026-09-29
session: 3
repo: fynla
branch: dev
---

# Session Handover — 2026-09-29, Session 3

## Where things stand

- **All work is merged to `dev` (`5629b6b6a`, 109 commits ahead of `main`).** That is every queued PR except the parked #249: this session's own fixes plus all 21 Icecube-acc PRs, each reviewed and fixed, or merged through an integration branch.
- **Nothing from this session is on fynla.org yet.** CSJ: "the release is for the next session".
- **Tests:** each change was verified with the test files it touches and a local browser walk. No full suites were run, and nothing was checked on csjones, because auto mode blocks writes to csjones and production.

## Priorities for the next session

1. **Release `dev` → `main` to fynla.org.** BLOCKED ON CSJ: CSJ has to type `/release`. The `release` skill cannot be invoked by the model, and auto mode blocks production writes, so CSJ runs the deploy commands with `!`. The release must carry:
   - **Migration:** `database/migrations/2026_09_29_000001_add_is_estimate_to_employments_table.php` (#963).
   - **Seeder:** `php artisan db:seed --class=ActionHowToSeeder --force`, because `database/seeders/data/action-how-to/tax.md` changed.
   - **Fyn corpus:** rsync `fyn-memory/`, because `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` changed.
   - **Config:** `config/onboarding.php` has the new key `savetax_isa_assumed_savings_share`. Clear the config cache; never run `route:cache` or `optimize`.
   - **Bundles:** build both, web and `/m`, with `./deploy/fynla-org/build.sh`, from a clean tree or worktree of `main` (CSJ's uncommitted excalidraw and `workforce/` files make `git checkout main` fail).
   - **After deploy:** `php artisan income:repair-spouse-estimates` as a DRY RUN. It lists spouse incomes already doubled on production (the C1 household, `tester+savetax2909spouse`). Show CSJ the list; `--force` is CSJ's call.
2. **Walk fynla.org after the release**, web at 1440px and `/m`, making your own accounts (memory `feedback_verify_prod_yourself_make_accounts`). Purge them afterwards. Cover:
   - Settings → Personal Info save, born in England or Scotland (#998, #1000);
   - web Protection: "Your cover" plus the Coverage gaps figures, which must agree (#1001);
   - the Fyn panel starts collapsed (#1002);
   - Save Tax funnel: partner in the 60% trap, partner-employment screen "3 of 4", "HMRC adds £720" (#1003);
   - `/m` Fyn: "Try again" after a drop, and the conversation kept across an app relaunch (#1005);
   - the `/pensioncheck/plan` social-proof section gone (#1007).
3. **Spouse transfer: the spouse's own onboarding must use what was transferred.**
   - CSJ was angry: ruling 50 said the details transfer and are stored "as per usual", yet they were double counted.
   - Income is fixed (#963, estimate replaced).
   - Savings, ISA, investments, pension and #990's other income can still be asked again and added twice.
   - Fix: extend `app/Services/Onboarding/WalkFormPrefill.php` (#978) so each walk form the transfer answered opens that record as an edit. Then walk a spouse's FULL onboarding on web and `/m`.
4. **Retirement how-to batch (26), then investment (17), then estate (12).** Unchanged from session 2; see `CSJTODO.md` NEXT. CSJ explicitly parked it until after the release.
5. **The rest of `CSJTODO.md` NEXT**, in order, starting with "One rule for 'ISA allowance used this year'".
6. **Finish the vault sync for 2026-09-29 session 3** (CSJ: stopped to move on to the release). The handover is already copied to `fynlaBrain/September/September29Updates/`, but the rest was not done: the git history day log, the September Index session entry, `Home.md` counts and the memory audit. Run `vault-sync` once, after the release.

## Context to load

- `CSJTODO.md` (NEXT section): the release entry and the spouse-transfer entry at the top.
- `docs/tech-debt-report.md`: this session's open issues, including two criticals (spouse double counting; typed-in tax figures on the routed `/savetax/plan/v2` and `/v3` mock-ups).
- `app/Services/Onboarding/SpouseHoldingTransfer.php` and `app/Services/Onboarding/WalkFormPrefill.php`: where priority 3 starts.
- `app/Console/Commands/RepairSpouseIncomeEstimates.php`: the post-release repair (dry run by default).
- `docs/testing/2026-09-29-prod-savetax-mobile-couple.md`: Brett's production run that found C1, H1, H2 and M/L items (all fixed now).

## Completed this session

- **Priority 1 (walk `/m` Protection on fynla.org): done.** Walk account 762, purged afterwards. Web and `/m` agree:
  - life £872,551 short, then £632,551 after 4× death in service;
  - critical illness £180,000 short;
  - income protection £3,000 a month short;
  - the employer benefits save through Fyn landed;
  - the three position cards and their approved steps render.
- **Priority 2 (SSP literal fallbacks): #997.** SSP is read from config with no fallback, and per person: the weekly rate or 80% of normal weekly earnings, whichever is lower (gov.uk/statutory-sick-pay/what-youll-get); no lower earnings limit from 2026/27. The card's SSP reason uses the analyser's figures and never fires for the self-employed.
- **Defects found on the walk, fixed:**
  - #998: UK-born users could not save Personal Info. The picker offers England, Scotland, Wales and NI, but the form only knew "United Kingdom", and it defaulted to England.
  - #999: the `/m` page stayed stale after a Fyn save.
  - #1000: a refused save no longer blanks Settings; residence wording in plain words on web and `/m`.
  - #1001: web Protection shows the server's `coverage_gaps` instead of browser-side literals (75%, 2×, 50%, 4.7%). Two dead copies were deleted, the Affordability and Coverage Summary boxes removed, and the "High" rating tag removed on `/m` (Rule 12).
- **#1002:** the Fyn panel starts collapsed on every web visit (CSJ).
- **Icecube PRs:** merged #961, #963 (with fixes; #969 closed as its duplicate), #970 (production emails redacted), #971/#973/#975/#984/#986/#989/#991/#993 via #1003, #976/#979/#980 via #1005, #977, #978, #982, #983, #990 and #1004. Closed #828 (Ahrefs).
- **#1006:** fixed my own regression in #1003, where the recap would have repeated before every onboarding form.
- **#1007:** removed invented testimonials and member counts from `/pensioncheck/plan` and `/savetax/plan/v4` (CSJ).

## Verification state

- The touched test files passed for each PR (see each PR body). Full suites were not run (CSJ rule), and CI was not waited on.
- On #1004, CI Feature went red only because of the recap regression that #1006 fixed. The 67 affected onboarding tests pass after #1006.
- The browser walks were local, not csjones.
- **Not verified:**
  - anything on csjones;
  - anything on fynla.org after today's merges;
  - the 80% SSP and self-employed branches in a browser (unit only);
  - a spouse's full onboarding after linking;
  - iOS.

## Decisions and dead ends

- **CSJ rulings this session:**
  - Pension first (#993): ratified.
  - A non-earner's £720 is counted, worded "HMRC adds £720 through your pension provider".
  - A non-earner's pension suggestion is capped at recorded cash savings (`TaxStrategyMath::nonEarnerFundableGross`).
  - Spouse details transfer and are stored "as per usual" (ruling 50 restated), so when status is unknown the figure is copied as a pay estimate.
  - The Fyn panel is collapsed by default on web.
  - Remove the invented social proof.
  - Merge all PRs except parked #249; release next session; retirement batch after.
  - Residence question dropped ("all users are currently England resident").
- **Dead end:** I built a separate turn ledger (`FynTurnLedger`) for Try-again idempotency, then found `IdempotencyKeyMiddleware` already on the send route, with iOS already sending `Idempotency-Key`. I removed the ledger; web and `/m` now send the header (Rule 20).
- **Dead end:** narrowing `funnelRecapDue` to `metadata.onboarding_step` broke 15 onboarding tests. Onboarding messages do not all carry it (#1006 reverted the narrowing).
- **Kept as a design decision:** the Fyn panel overlays content when open (commits 564d314c5 and b3c95593e, April). It is now collapsed by default instead of pushing content aside.
- **Removed from web Protection with #1001:** the browser-computed Affordability box and Coverage Summary (unsourced figures and colour bands). If CSJ wants an affordability view back, it has to come from the server.

## Things that will bite you

- **`RefreshDatabase` rebuilds `laravel_testing` from scratch per Pest process.** After killed runs it can take about 4 minutes, which looks like a hang. It isn't: check `information_schema.PROCESSLIST` for `alter table`.
- **Worktrees need a real `vendor` copy** (`cp -R` plus `composer dump-autoload -o`). A symlinked vendor makes `App\` resolve to the main checkout.
- **Computed file lists trip the pest hook.** `pest $(...)` is read as a directory-wide run; name the files.
- **Auto mode:** blocks production reads and writes and csjones writes; hand CSJ the one command. The `release` skill cannot be model-invoked.
- **The repo is PUBLIC:** never put production account emails in docs. The #970 emails remain in git history.

## Tech debt deferred

See `docs/tech-debt-report.md`, which is fully rewritten this session:
- **2 critical:** spouse-transfer double counting; typed-in tax figures on the `/savetax/plan/v2` and `/v3` mock-ups.
- **Warnings include:** Stop no longer stops the server turn; a skip predicate that writes; a fixed 3s retry reload; the idempotency body hash includes `current_route`; the income page still writes the employment total directly; four copies of the protection needs assembly.

## Branch and deploy state

- **Branch:** `dev` at `5629b6b6a`, pushed. The local checkout is clean apart from CSJ's own excalidraw and `workforce/` files; there are no linked worktrees.
- **Unpushed commits:** none, apart from this handover commit.
- **Production:** `main` `f06910ac2` (the protection release). Everything above is pending `/release`.
- **csjones:** last deployed at `dev` `ac5deecea`; today's merges are not on it.
