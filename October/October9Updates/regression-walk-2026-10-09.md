# Regression walk, 9 October 2026 (after release aa)

**Asked by CSJ (2026-10-09):** before item 18, full walks to check everything is good and nothing has regressed. These cover Save Tax registration for a single person and for a couple, a partner joining by invite, onboarding through normal registration (same route, Fyn as expected), additions, edits, actions and how-to pages.

**Where:** fynla.org (production, main `33477b8a0`, release aa, Fyn on GPT-6 Luna).

**Fix loop (CSJ 2026-10-09: "added to the test task list, fixed and then you retest the flow"):** each defect is logged below, fixed on its own PR to dev, deployed to csjones and retested there by walking the same flow. The walk carries on on fynla.org meanwhile. When it ends, the fixes go out in one release and every fixed flow is retested on fynla.org.

**How:** walked as a user in the Playwright browser. I click, type and submit, and take a screenshot of every checked state. Web runs at 1440 × 900 and /m at 390 × 844 with an iPhone browser. Cookies are accepted, never declined. Tinker is used only for setup: verification codes and the invite link, since I can't read the inbox. Figures are checked against hand-worked sums from tax config.

**Walk accounts:** CSJ's `+walk` Gmail addresses, password `Password1!`. Each is purged at the end (`RetentionPurgeService`), and every one is listed in the results.

**Key:** `[ ]` not done · `[x]` done, passed · `[!]` done, defect found (see Defects) · `[-]` could not test (reason given)

---

## A. Save Tax, single person (onboarding completed on web; then checked on /m)

*Changed 2026-10-09 at CSJ's question: the single walk finishes onboarding on web so every web form is walked. The web-to-/m switch part-way through moved to walk B.*

- [x] A1. Home page "Save tax now" → 4 questions (full time, £50,271–£100,000, no partner, savings + pension + ISA) → estimate page shows "up to £…", figure checked against the band top.
- [x] A2. Register on the estimate page → code → dashboard with Fyn open and the recap of the 4 answers.
- [x] A3. Web: income form (employer, gross pay) saved; read-back matches; first card figure checked by hand.
- [!] A4. Web: savings form (provider, balance, rate) saved; read-back matches.
- [x] A5. Web: ISA form and pension form (provider, value, contributions, salary sacrifice yes/no).
- [x] A6. Web: date of birth and spending (monthly figure + Gift Aid) forms.
- [x] A7. Web: onboarding finishes → plan shows; every figure on it checked by hand (pension relief slice, Gift Aid, ISA, salary sacrifice).
- [!] A8. Tax Strategy page on web and /m shows the same figures as the plan.
- [ ] A9. Fyn: "How did you work out the £… pension figure?" gives the plan's own working (web and /m), with no mention of past pension contributions.
- [ ] A10. /m after onboarding: sign in as a phone user → dashboard, actions and module screens match web.

## B. Save Tax, couple, then the partner by invite

- [ ] B1. Home page "Save tax now" with partner = yes (partner income band) → estimate → register.
- [ ] B2. Onboarding started on web (income), then sign out and continue on /m: Fyn resumes at the right step ("Welcome back …"), not from the start; on /m: a **joint** savings account (saved as joint, "Your 50% of …" on Bank Accounts), pension, spouse form, spending.
- [ ] B3. Plan: partner pension top-up and own pension figures checked by hand; Personal Savings Allowance uses the user's own share of joint interest.
- [ ] B4. Invite the partner from Fyn ("Yes, invite them" → name + email) → invitation created.
- [ ] B5. Partner opens the invite link → register page prefilled → account created → both accounts linked; invitation accepted.
- [ ] B6. Partner onboarding: the work form prefilled from the inviter's spouse form; the joint account is not added twice (the "same one?" question); spending is shared (each half shown and named as half).
- [ ] B7. Partner's plan and Tax Strategy page: their own Personal Savings Allowance, pension and surplus figures checked by hand.
- [ ] B8. Both owners can see and edit the joint account; the owner stays the inviter.

## C. Normal registration (not Save Tax)

- [ ] C1. Home page "Get started for free" → register → code → dashboard.
- [ ] C2. Fyn onboarding starts and follows the same route (same steps, same forms) as Save Tax, where the flows share steps; any differences are noted.
- [ ] C3. Fyn answers a question during onboarding and comes back to the step.
- [ ] C4. Onboarding finishes → plan and dashboard show; Fyn is available on web and /m.

## D. Additions (web and /m)

- [ ] D1. Web: add a savings account through the module page's "Add" form → appears on the page with the right figures.
- [ ] D2. Web: add through Fyn ("add my … account") → form opens filled → Save → read-back → appears on the page.
- [ ] D3. /m: add a pension or investment through Fyn → form → Save → appears on the /m screen.
- [ ] D4. Adding a record that already exists is asked about ("the same one?"), not added twice.

## E. Edits (web and /m)

- [ ] E1. Web: edit a savings account through its edit form → the change saved, and the read-back says what changed.
- [ ] E2. Fyn edit ("change my … balance to £…") → edit form opens with the change filled → Save → updated.
- [ ] E3. An unchanged save says "Already on file", with no rename or type change.
- [ ] E4. /m: edit details on a record → saved.
- [ ] E5. Joint owner edits the joint account (web and /m) → saved; the owner and both owners' figures are right.

## F. Actions (web and /m)

- [ ] F1. Web dashboard and Actions list: actions show with their figures and labels; no scores, no amber or orange, no new icons.
- [ ] F2. Open an action card → why, outcome and figures; "Ask Fyn about this" → Fyn answers about that card.
- [ ] F3. Mark an action as done → it leaves the list, the next one replaces it, and the level count moves.
- [ ] F4. /m: action rows, the action card, mark as done, and the "Tell me more about: …" chips → grounded answer.

## G. How-to pages (web and /m)

- [ ] G1. Web: an action's how-to steps open, read correctly and are sourced; "learn more" links work.
- [ ] G2. /m: the same how-to opens and matches the web.
- [ ] G3. At least one how-to per module that has them (Savings, Tax/pension, Investment, Protection, Estate) opens.

## H. Across all walks

- [ ] H1. Every Fyn turn on fynla.org ran on `gpt-6-luna`, priced; OpenAI keeps nothing (`store: false`).
- [ ] H2. No errors in the fynla.org log during the walks.
- [ ] H3. Every walk account purged afterwards.

---

## Where the walk stopped (2026-10-09, context clear)

- **A (single, fynla.org, Ellis Walker, user 807, `slaterjoneschris+walk-a1@gmail.com`):** A1–A8 walked on web. Onboarding finished and the plan was checked by hand: £17,600 / £7,040 pension, £150 Gift Aid, £6,235 / £106 ISA wrap, £72 salary sacrifice, total £7,368. The Tax Strategy page matched. **Next:** A9 (Fyn "How did you work out the £17,600 pension figure?" on web and /m), then A10 (/m after onboarding).
- **Fix loop state:**
  - R1 (#1164) and R2 (#1165) are merged to dev and retested on csjones.
  - R4–R6 are in PR #1166, not merged. Next: vitest (the strategy list and /m tax strategy specs, if any), merge, csjones pull, web and /m bundles from a clean dev worktree, then retest the Tax Strategy links (web and /m), the salary sacrifice wording and the rail margin.
  - R3 (holistic plan reads unknown emergency fund as 0): investigate during walk F.
- **Still to walk:** A9, A10, B (couple and invite), C (normal registration), D (additions), E (edits), F (actions), G (how-tos), H (checks).
- **Then:** one release of #1164, #1165, #1166 and later fixes, and a retest of every fixed flow on fynla.org. Purge every walk account: prod Ellis 807; csjones Rory Retest, user 504.

## Results

| Step | Result | Evidence |
|---|---|---|

## Defects found

*Every anomaly is logged here, fixed and the flow retested (CSJ 2026-10-09: "it does not matter if an issue was from before or not").*

- **R1. Onboarding "check your accounts" step opens a page with no accounts on it** (found in A4, Ellis Walker, web). After the savings step Fyn said "Here's your savings and ISA accounts page — take a look and check everything's correct" and opened `/savings`. Its "Cash Overview" tab shows the accounts only to demo users (`resources/js/components/Savings/SavingsModuleOverview.vue:10`, `v-if="isPreviewMode"`). Everyone else gets "Connect to Open Banking — Coming Soon", so the user can't check anything. The route table calls `/savings` "Bank Accounts" (`app/Constants/GateRoutes.php:78`), while the menu's Bank Accounts page is `/net-worth/cash`, which does list them (Santander £18,000, Nationwide Cash ISA £12,000). **CSJ 2026-10-09:** "as we are not connecting to open banking at the moment, we can remove the open banking screen, take user to the correct screen". Fix: remove the Open Banking screen (Cash Overview lists the accounts for everyone); "Bank Accounts" goes to `/net-worth/cash`.
  - **Fixed:** #1164 (dev `0761a4bef`). The onboarding check step names its screen and web opens Bank Accounts (`GateRoutes` web `/net-worth/cash`; /m keeps `/savings`). The Savings Cash Overview shows accounts, ISA allowance and totals to everyone. The Premium Open Banking cards on Bank Accounts and Investments are removed.
  - **Retested on csjones (web 1440, new Save Tax account Rory Retest):** income, Cash ISA, easy access savings, then "does it look right?" opened Bank Accounts showing Santander £18,000 and Nationwide £12,000 (`shots/R1-retest-csjones-bank-accounts.png`). The Savings dashboard shows both accounts and no Open Banking (`shots/R1-retest-csjones-savings-dashboard.png`). **Fynla.org retest: after the next release.**
- **R2. Dashboard Savings card says "0 / 6 months" when no spending is recorded** (found retesting R1, csjones, Rory: £30,000 saved, spending not yet asked). The Savings dashboard's runway card correctly says "Add your monthly spending … before we can work out how long your cash would last" (W-0495: "cannot be calculated" is not "zero months of runway"). One figure, two answers.
  - **Fixed:** #1165 (dev `a7c2f84af`). `MobileDashboardAggregator` turned the null runway into 0 (`:225`); it now passes null, and the card says "Add your monthly spending" with an empty bar.
  - **Retested on csjones (web 1440, Rory):** the Savings card reads "£30,000 · Add your monthly spending" (`shots/R2-retest-csjones-dashboard-2.png`). Rory's cached dashboard needed clearing once, since csjones skips `cache:clear`; the release's cache clear covers prod. /m retest with the next /m walk. **Fynla.org retest: after the release.**
- **R3. The holistic plan reads an unknown emergency fund as 0 months** (found in R2's path). `CoordinatingAgent::mapSavingsAnalysis` (`app/Agents/CoordinatingAgent.php:886`) sends `?? 0`. `HolisticPlanner` then treats "under 3 months" as urgent (`:333`, `:391`) and carries typed-in 6s (`:120`, `:511`). Status: to check in walk F (actions) and fix.
- **R4. Page content runs under the collapsed Fyn rail on web** (found in A8, Ellis, Tax Strategy at 1440). "Sorted by potential savin…" is cut off and the right-hand cards sit against the rail (`shots/A8b-tax-strategy-collapsed.png`). Yesterday's admin-page note ("the right-hand card and the Administrator badge are clipped") is the same fault in `AppLayout`. Status: fixed in PR #1166 (`fix/r4-r6-walk-batch`, NOT merged): `AppLayout::contentMarginClass` adds `lg:mr-10` while the docked chat is collapsed. Retest on csjones still to do.
- **R5. The salary sacrifice card says "no change to your take-home pay"** (A8, Ellis). The National Insurance saving raises take-home pay by the £72 the card quotes. Already noted under item 18 ("with no change to your take-home pay"). Status: fixed in PR #1166 (NOT merged): `SalarySacrificeNiStrategy` now says take-home pay rises by the saving. Retest still to do.
- **R6. The salary sacrifice card's link reads "Open a pension"** (A8, Ellis), though the switch is about the workplace pension he already has. Clicking it on fynla.org opened the **Dashboard**, because `/pension` is not a web route (`shots/R6-open-a-pension.png`). "Open investments" (`/investments`) is the same, and "See income & tax" opened Personal details, not Income (`shots/R6-see-income-tax.png`). /m kept its own copy with other labels ("Open retirement"). Status: fixed in PR #1166 (NOT merged): one server map, `App\Support\StrategyNextStep`, gives each composed plan item a `next_step` {label, destination}; web (`StrategyRecommendationList.vue`) and /m (`TaxStrategy.vue`) resolve it through their destination maps; "Open a pension" becomes "Open pensions". Retest still to do: every link on web and /m.


## Walk accounts used

- fynla.org: Ellis Walker, user 807, `slaterjoneschris+walk-a1@gmail.com` / `Password1!` (walk A; purge at the end).
- csjones: Rory Retest, user 504, `slaterjoneschris+r1-retest@gmail.com` / `Password1!` (R1/R2 retests; mid-onboarding at the pensions step).
