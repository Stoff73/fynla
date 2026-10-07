---
type: handover
mode: session-end
date: 2026-10-07
session: 3
repo: fynla
branch: dev
---

# Session Handover — 2026-10-07, Session 3

## Where things stand

- **Release v is live on fynla.org.** PR #1127, main `11c05a72c`, about 16:30 UK time.
- **It carries two PRs:**
  - #1125: item 9's open tech-debt findings;
  - #1126: items 10, 11, 12, 13 and 14.
- **Verified:** each change was walked on csjones (web 1440 and /m 390), then on fynla.org as a user.
- **The list:** items 10 to 14 are crossed off in `todoCurrent/TODO.md`, with patch notes and PDF updated (#1128).
- **The checkout:** `dev` at `f26970f14`. It is clean apart from CSJ's own uncommitted files, which were not touched.
- **Item 15 is next.** It has not been started.

## Priorities for the next session

**CSJ's rule, 2026-10-07: one item at a time.**
- An item must be released to fynla.org and walked there before the next one starts.
- One item per PR.
- Waiting on CSJ (a deploy or a decision) is a legitimate stop; do not fill it by starting the next item.
- Memory: `feedback_one_item_until_prod`.

1. **DECISION (CSJ), under item 13.**
   - On fynla.org, the dashboard's action row shows "You could save £720" beside "Pay £2,880 into your spouse's personal pension and HMRC adds £720".
   - CSJ's 29 September ruling (#975) kept the £720 in the total.
   - Ask: should that row also say "HMRC adds £720"?
   - Asked at the end of session 3; not yet answered.
2. **Item 15: find sources for three savings how-tos, then draft them for approval.**
   - The three entries: `offset_mortgage_better`, `excess_cash_bond`, `excess_cash_gia`.
   - The cards show today with no "how to" steps, because MoneyHelper blocks automated fetching.
   - Use gov.uk, the FCA, legislation and HMRC manuals instead.
   - Draft the entries in `database/seeders/data/action-how-to/savings.md` with `status: draft`; CSJ approves each one.
   - Then the normal path for each: PR, csjones walk, release, fynla.org walk.
3. **Item 17:** savings market rates fall back to an invented 4.00% (`RateComparator`).
4. **Item 18:** tax plan items carry no working, so Fyn invents the arithmetic.

## Context to load

- `todoCurrent/TODO.md`:
  - the order of work; item 15 is current;
  - item 13 has the open DECISION;
  - items 11, 13 and 14 have "Found, NOT fixed" lines.
- `database/seeders/data/action-how-to/savings.md`:
  - item 15 writes here;
  - line 15 lists the three entries with no verified source;
  - the approved entries show the format.
- `database/seeders/SavingsActionDefinitionSeeder.php:519`, `:575`, `:594`: the three cards' definitions and figures, which the how-tos must match.
- `docs/tech-debt-report.md`: this session's deferred findings, with `file:line`.

## Completed this session

- **#1125 (item 9 follow-ups):**
  - One `GiftStore` for web and Fyn. Fyn's edit and delete could move a trust's settlement and release the nil rate band (W-0528); now refused.
  - The gift form showed "Small Gift Exemption (£0 limit)", live since release u. The `smallGiftsLimit` getter read a key `/api/tax/config` never sends. Now it shows £250.
  - The form no longer says "will be a Potentially Exempt Transfer" for a gift into a trust.
  - The partner predicate has one home: `EstateIhtExposureDetector::partnerPosition`.
  - A queued web form turn now refreshes the screen behind the chat.
  - `InvestmentAccountStore::existsForUser`.
  - `ihtPositionVars` split out.
- **#1126 (items 10 to 14):**
  - **Item 10:**
    - Nothing is recorded as drawdown at the link.
    - Save Tax steps `campaign_retired_state_pension` and `campaign_retired_db_pension` were added, with a new Fyn final salary form, `CaptureForms::DB_PENSION`.
    - The personal pension form is prefilled from `SpouseHoldingTransfer::pensionIncomeLeftToPlace`.
    - Link-copied other income is moved out when the partner later says "retired".
  - **Item 11:** the inviter's form asks "What they do". It is prefilled only through `financiallySharedSpouse` (a security-review fix).
  - **Item 12:** "Add the funds you hold".
  - **Item 13:**
    - The partner, child and investment-plan cards now say "HMRC adds £720".
    - The investment plan read `TaxDefaults` literals; it now reads tax config.
  - **Item 14:** the label is now "spouse's total income".
- **Release v (#1127), patch notes (#1128), memory:**
  - `project_release_2026_10_07_v`;
  - `feedback_one_item_until_prod`.

## Verification state

- **Changed-file tests:** 363 passed before the release. Every new test was checked to fail without its fix.
- **csjones:** #1125 at `0148e4659`, and #1126 at `972a460be`, both walked on web and /m.
- **fynla.org after release v:**
  - smoke pages returned 200;
  - checksums match;
  - no pending migrations;
  - the final salary step is present;
  - no errors in the log.
- **Walked on fynla.org:**
  - Sam (inviter): "Now your spouse" Retired, £30,000; ISA card "Add the funds you hold".
  - Pat (invited, /m): State Pension £11,500, Council Pension £10,000, form opened at £8,500, income £30,000. Her "Now your spouse." step asked only Sam's status and income.
  - Kim: "Pay £2,880 … HMRC adds £720".
  - All of these accounts were purged.
- **Not verified on fynla.org:**
  - the gift form, which needs a premium account (walked on csjones);
  - item 14's wording, which doesn't arise for a household that gave the partner's income (walked on csjones).
- **Not verified anywhere:** the iPhone app's typed questions for the new steps. That is CI only (item 35).
- **CI:** not watched, per CSJ's rule never to gate on it.

## Decisions and dead ends

- **CSJ 2026-10-07:**
  - Item 10: "Partner's setup asks".
  - Releasing #1126 with five items together: "Ship 1126 so long as everything has been checked, and tested … and walked as a user". This was a one-off; the rule above applies from now on.
- **Item 10 needs no production repair.** There has been one transfer since #990 (user 760, working), with no drawdown recorded.
- **Item 14's longer label failed in the csjones walk.** "spouse's total income a year, including any pension or rent" ran its commas into Fyn's list, so it became "spouse's total income".
- **Item 13's #1024 walk waited for the release,** because item 11 changed that step. It is now walked on fynla.org.

## Things that will bite you

- **CSJ's `! bash <script>` can arrive as a plain message without running.** Check the server (`git log -1`) and run the script yourself. That worked for csjones and for the production release this session.
- **csjones has no Faker.** Create walk users with `new User` + `forceFill` + `Subscription::create`, as `walk-*@example.com`.
- **Production walk with a partner:** register the inviter, then "Yes, invite them" in Fyn. The token is on `SpouseInvitation`, and the partner registers at `/register?invite=<token>`. Codes come from `PendingRegistration` before verification and `EmailVerificationCode` after (`ssh-fynla` MCP). Purge with `RetentionPurgeService` plus `forceDelete`.
- **Building from main locally is blocked** by CSJ's uncommitted excalidraw and handover files. Release v was built from dev, after proving the runtime paths match main.
- **/m keeps its token after clearing storage.** Sign out through the menu, closing the Fyn panel first, because it covers the menu.
- **The first code typed on web login is sometimes rejected,** and the boxes clear. Type it again.
- **GitHub push can return a 500.** Retry.
- **The patch notes converter writes HTML.** Print it with headless Chrome to the `.pdf`.

## Tech debt deferred

See `docs/tech-debt-report.md` (2026-10-07 s3) and the Found line under item 14:
- the typed-in "25%" for the tax-free lump sum (`NonEarnerSpousePensionStrategy.php:108`, `:199`; tax config has `pension.pcls_rate`), Rule 2;
- a duplicate holding lookup in `SpouseHoldingTransfer`;
- gift caches cleared twice;
- `SpouseOptimisationService` as a second engine for the partner top-up (Rule 20).

## Branch and deploy state

- **Branch:** `dev` at `f26970f14` (this handover on `docs/session-end-2026-10-07-s3`).
- **Unpushed commits:** none.
- **fynla.org:** main `11c05a72c` (release v). Backup in `~/release-backups/2026-10-07-v-release/`.
- **csjones:** on dev `6ae6a79e8`. Later commits there are docs only.
- **Scripts** (session scratchpad `91ffb94e-…`): `release-prod-2026-10-07-v.sh` is the template for the next release.
