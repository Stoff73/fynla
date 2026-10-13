---
type: handover
mode: session-end
date: 2026-10-13
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-10-13, Session 1

Follows the 2026-10-12 session 6 handover (`handover/October/12/handover-2026-10-12-session-6.md`). This session ran from the evening of 12 October into the morning of 13 October.

## Where things stand

The walk fix plan (`October/October12Updates/walk-2026-10-11-fix-plan.md`) moved from T20 to T25. T20 to T25 are each ticked to **Dev walk**, walked as a user on csjones at web 1440 and /m 390. Nothing was released: production is still main `f45bc67fd` (release ab). csjones runs dev `ea4c4b75e`, with T25's migration run and both bundles built at 09:48 BST from that tree. Dev is `fa9aee492` (docs only after `ea4c4b75e`). CSJ said "stop after t25", so T26 is next.

## Priorities for the next session

1. **DECISIONS waiting on CSJ (ask at the start):**
   - **F11:** where the spouse's date of birth is kept. On the Save Tax path the spouse's figures live on `TaxStrategyHouseholdInput`, and there is no spouse family-member row until the invite (production 812's row 1435 appeared at the invite). It was not built in T24.
   - **Wording to approve:**
     - T21: "Annual profit", its hint, and the line forms.
     - T23: closing turn, closing step, "Take me to my actions", the partner invite with "actions". The bullet suffix uses the dashboard row's own line ("you could save £7,080"), not the plan draft's "saves around £X a year", because not every action's figure is yearly.
     - T24: the form labels, and "… counts with your ISAs.".
   - **Recorded without confirmation:** CSJ answered the T20 report with "Good go to T21"; I took that as approving T20's wording. Confirm.
   - **Production rows:** mortgages 386 and 387 and property 531 keep their placeholders. Clearing them is a database change for CSJ's OK.
   - Still open from before: #1257 keep or revert, `Money` vs `FormatsCurrency`, #1242, #1248, T50, T55, T56, T57, and the T58, T18 and T17 wording and decisions (session 6 handover, priority 3).
2. **T26: workplace pensions get their scheme type; production rows corrected (N2).** Next in plan section 0b. Read N2 and RC9 in the plan first, and check the production evidence before building.
3. **Then T27 to T60 in plan order:**
   - T27: joint records link to the partner;
   - T28: the Liabilities page shows mortgages to Free users;
   - …
   - new this session: T59 (life cover shortfall for a single person with no dependants) and T60 (rate type "fixed" and the 25-year term are still invented).
4. **Purge csjones walk accounts:**
   - this session: Priya 521, Tom 522, Ellie 523, Tunde 524, Amara 525, Kofi 526, Nia 527, Zain 528, Sasha 529, Ravi 530, Meera 531;
   - session 6: Maya, Leo, Asha, Ravi, Nadia, Omar, Lena, Theo, Iris;
   - session 5: Robin 509, Priya 510, Sanjay 511.

   All are `slaterjoneschris+…@gmail.com`, `Password1!`. Robin 509 holds test family members, property 228 and two life policies.

## Context to load

- `October/October12Updates/walk-2026-10-11-fix-plan.md`: the task list (section 0b), the rules R1 to R5 (section 0), and each task's evidence log. T26 is next. Sections 2 (RC9) and 3 (N2) carry T26's evidence.
- `todoCurrent/TODO.md`, item 18, "Where we stopped (2026-10-12, session 7)": the list position, the decisions and the Found lines from this session.
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_bubble_steps_are_taps_never_typed_matching.md`: still binding. Typed words are never matched to bubbles.
- `app/Services/Onboarding/CaptureForms.php`: the form schemas T24 extended (`workFor`, `shown_when`, `MORTGAGE_DETAIL_FIELDS`, the savings, protection and pension kinds). T26 will likely touch the pension kinds again.

## Completed this session

- **T20, one form per person named (P8):**
  - #1272 (`c9cefb517`): `TypedFormFill` reads one entry per new record; the queue lives on the form message (`capture_form_queue`) and is opened by `handleCreateFormTurn` and `openQueuedWalkForm`.
  - #1273 (`2347b09ba`): each capture form numbers its own ids, so a second form of one kind labels and focuses its own fields.
  - The limit sentence now has one home in the director (`OnboardingStateMachine::freeLimitSentence`).
- **T21, self-employed profit (M4):** #1275 (`f4ed40f19`), #1276. `CaptureForms::workFor` asks for "Annual profit"; the line says profit; `Money::exact` is used for the work read-back and `CaptureForms::pounds`.
- **T22, normal registration opens on the bubbles:** #1278 (`3aa6d46b2`). `FUNNEL_STATES` asks assets first. "That's everything" goes on to the questions not yet answered and records `funnel_assets_answered`.
- **T23, the Save Tax report is for Save Tax arrivals only:** #1279 (`47c2037df`).
  - One arrival test, `OnboardingStateMachine::isSaveTaxArrival`: `campaign = savetax`, or a pre-stamp row carrying the `/savetax` income answer (production 637, 647, 648).
  - The chat no longer stamps `campaign`, and an invitee copies the inviter's campaign only when the inviter has one.
  - `actionsSynthesis` (from `NextActionsService::build`) and the `campaign_actions_terminal` step to `/actions`.
  - `applyCampaignAffinity` gives tax-first to arrivals only (plan point 7 did not follow automatically).
  - `registrations:clear-chat-campaign-stamp` (a read-only count picks the plan's 10 production users).
  - iOS `DeepLinkParser` maps `/actions`.
- **T24, Fyn's forms gain the fields whose columns exist:** #1281 (`d5cbd18d6`) and #1282 (`44df734d1`).
  - Savings: Premium Bonds and Junior ISA kinds.
  - Property: town, mortgage details (shown once an amount is given), letting costs.
  - "Is it written in trust?".
  - A defined benefit kind on the pension step.
  - Edit forms carry the same fields.
  - The bank loop names a Junior ISA.
- **T25, no placeholders stored as facts:** #1283 (`ea4c4b75e`).
  - Migration `2026_10_13_100000_make_mortgage_type_nullable`.
  - Null on every Fyn property and mortgage write, shown "Not recorded" on web and /m.
  - Fyn's record reads keep null.
- **Docs PRs with each task's evidence:** #1274, #1277, #1280, #1284.

## Verification state

- **Tests:** only the files each change touched were run, and all were green.
  - T20: 15 + 381; frontend 48 + 73.
  - T21: 50 + 280.
  - T22: 258.
  - T23: 450 + 58.
  - T24: 459; frontend 105.
  - T25: 389; frontend 7.
  - No full suite; R5 puts it once at T43.
- **Existing failure, not from these changes:** `tests/Feature/Fyn/FynRankedRecommendationsAreTheActionsListTest.php` (`900.0` vs `900`) fails the same way on dev `ad952eca2`.
- **csjones walks:** web 1440 and /m 390 for every task, with screenshots `October/October12Updates/shots/T20-*` to `T25-*`. Evidence is in the plan's task log.
- **Not verified:**
  - native iOS (deferred; T23's DeepLinkTests runs in CI);
  - production (nothing released);
  - the setup walk's own children step (`base_dependants_detail`): **I COULD NOT TEST THIS** as a user, because nothing a user can click reaches the journey path today. Covered by `OneFormPerPersonTest`.
  - The web dashboard has no top-actions list, so T23's figure-for-figure check was done on /m.

## Decisions and dead ends

- **One form at a time (T20).** Web and /m keep only the newest form open (`AiChatPanel::latestCaptureFormIndex`, `onboardingChat.js:405`), so several records are a queue, not forms side by side. No frontend change.
- **Mortgage type is never rewritten on a saved mortgage.** `UpdateRecordAllowlistTest` cites INV-2.7.3 ("changes the contract type"). The T24 edit form doesn't ask it; it is asked only at creation.
- **Every ISA counts with investments** (CSJ 2026-09-15), including a Junior ISA, so the bank loop count leaves it out and a separate line names it.
- **The legacy funnel fallback in `applyCampaignAffinity`** ("non-empty funnel without a campaign key = Save Tax") would have made every normal registrant a Save Tax arrival once the chat stopped stamping. Arrival is now recognised by the `/savetax` income answer instead.
- **No data correction on csjones.** Its dry run lists 34 old walk accounts. Fresh registrations are not stamped, so it was not run there.
- **Skipped from the session-end skill:**
  - the tech-debt pass (R5: no tech-debt audits);
  - the vault sync, because it dispatches a background agent (R1 and CSJ's 2026-10-12 no-sub-agents rule). The handover file is mirrored to the vault directly.

## Things that will bite you

- **The auto-mode classifier blocks some csjones SSH commands.**
  - Blocked: SSH commands chained with cache clears ("Remote Shell Writes").
  - Allowed when run alone: `git pull origin dev`, `php artisan migrate --force` and rsync of the bundles. The cache clears were never needed this session, since no config, routes or views changed.
- **Opening /m signs the web tab out (T33).** After /m, the web front door lands on sign-in.
- **/m Playwright selectors must go through the iframe:** `iframe[title="Fynla"] >> internal:control=enter-frame >> role=…`. `wait_for` text checks cannot see inside the iframe; use snapshots.
- **The web chat toggle closes an open panel.** Check for `textbox "Ask Fyn..."` before clicking "Chat with Fyn".
- **Pest file lists built in a shell variable are blocked by the hook.** List the test files explicitly.
- **MySQL JSON columns reorder keys,** so compare stored arrays with `toEqual`, not `toBe`.
- **Worktree:** this session used `wt-t20` in its scratchpad (`/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/2c66046a-…/scratchpad/wt-t20`), with copied `vendor` and a symlinked `node_modules`. The main checkout stays on dev.

## Tech debt deferred

(No tech-debt pass was run, per R5. These are items seen while working.)

- `SaveAccountModal.vue:262-266` writes "£20,000" and "£9,000" into copy (Rule 2: from `taxConfig.js`).
- "Not recorded" lives in three places:
  - the web savings rate (`utils/interestRate.js`);
  - web mortgage formatters inline in `PropertyDetailInline.vue`;
  - /m `MortgageDetail.vue` inline.

  It needs one shared helper per surface.
- `NextActionsService` (Mobile) now reads `OnboardingStateMachine::isSaveTaxArrival`. The arrival test belongs somewhere both can own, for example a small support class.
- The Free-limit sentence is still written once per entity in `CoordinatingAgent` (`:3136` to `:4112`). The director's two copies now use `freeLimitSentence`.
- `OnboardingChatDirector.php` keeps growing (about 9,800 lines).

## Branch and deploy state

- Branch: `dev` at `fa9aee492` before this handover's PR. No unpushed commits.
- csjones: dev `ea4c4b75e`, with migration `2026_10_13_100000` run and both bundles from that tree (09:48 BST).
- fynla.org: unchanged (`f45bc67fd`).
- **The next release needs:**
  - migrations `2026_10_12_120000_one_weekly_fyn_limit_per_tier` (T48) and `2026_10_13_100000_make_mortgage_type_nullable` (T25);
  - both bundles;
  - `php artisan registrations:clear-chat-campaign-stamp --dry-run`, then without `--dry-run` (T23; 10 users);
  - the corpus file ships with the code (T23's `campaign_actions_terminal`).
- The main checkout still has CSJ's own uncommitted changes (`brettTest/` deletions, `workforce/ops` logs and briefs, an untracked October 12 app-map handover). They are not mine; left alone.
