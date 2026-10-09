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
- [x] A9. Fyn: "How did you work out the £… pension figure?" gives the plan's own working (web and /m), with no mention of past pension contributions.
- [!] A10. /m after onboarding: sign in as a phone user → dashboard, actions and module screens match web.

## B. Save Tax, couple, then the partner by invite

- [x] B1. Home page "Save tax now" with partner = yes (partner income band) → estimate → register.
- [!] B2. Onboarding started on web (income), then sign out and continue on /m: Fyn resumes at the right step ("Welcome back …"), not from the start; on /m: a **joint** savings account (saved as joint, "Your 50% of …" on Bank Accounts), pension, spouse form, spending.
- [ ] B3. Plan: partner pension top-up and own pension figures checked by hand; Personal Savings Allowance uses the user's own share of joint interest.
- [x] B4. Invite the partner from Fyn ("Yes, invite them" → name + email) → invitation created.
- [!] B5. Partner opens the invite link → register page prefilled → account created → both accounts linked; invitation accepted.
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

## Where the walk stopped (2026-10-09, session 2, paused for CSJ)

- **A done; B1–B4 done; B5 found R17 (security).** Paused at B5/B6 on fynla.org: Sam (809) cannot be walked until R17 is released.
- **Fixed and retested on csjones this session:** R2 (/m), R4–R8, R10, R12–R18. Dev `71cbec1c2`. PRs #1166, #1168–#1182.
- **Waiting on CSJ:** R17 hotfix release and session clearing; R9 and R11 decisions.
- **Open, mine:** R3 (walk F); B6–B8, C–H.
- **Release needs:** migration `2026_10_09_100000_make_savings_interest_rate_nullable`; both bundles; (CSJ) clear `storage/framework/sessions/`.
- **Walk accounts:** fynla.org Ellis 807, Morgan 808, Sam 809; csjones Rory 504, Quinn 505, Pat 506, Alex (r17-partner2).

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
  - **Retested on csjones (web 1440, Rory):** the Savings card reads "£30,000 · Add your monthly spending" (`shots/R2-retest-csjones-dashboard-2.png`). Rory's cached dashboard needed clearing once, since csjones skips `cache:clear`; the release's cache clear covers prod. **Retested on csjones /m 390 (Quinn Retest, joint £40,000, no spending yet):** the Savings card reads "£20,000 · Add your monthly spending · Target not set" (`shots/R2-retest-m-dashboard.png`). **Fynla.org retest: after the release.**
- **R3. The holistic plan reads an unknown emergency fund as 0 months** (found in R2's path). `CoordinatingAgent::mapSavingsAnalysis` (`app/Agents/CoordinatingAgent.php:886`) sends `?? 0`. `HolisticPlanner` then treats "under 3 months" as urgent (`:333`, `:391`) and carries typed-in 6s (`:120`, `:511`). Status: to check in walk F (actions) and fix.
- **R4. Page content runs under the collapsed Fyn rail on web** (found in A8, Ellis, Tax Strategy at 1440). "Sorted by potential savin…" is cut off and the right-hand cards sit against the rail (`shots/A8b-tax-strategy-collapsed.png`). Yesterday's admin-page note ("the right-hand card and the Administrator badge are clipped") is the same fault in `AppLayout`. Status: fixed in #1166 (dev `7275e3d26`): `AppLayout::contentMarginClass` adds `lg:mr-10` while the docked chat is collapsed.
  - **Retested on csjones (web 1440, Rory, 2026-10-09 ~09:00):** Dashboard and Tax Strategy stop short of the rail; "Sorted by potential saving" in full (`shots/R4-retest-02-dashboard.png`, `shots/R6-retest-01-tax-strategy-web.png`). The margin made the footer jam: see R7. **Fynla.org retest: after the release.**
- **R5. The salary sacrifice card says "no change to your take-home pay"** (A8, Ellis). The National Insurance saving raises take-home pay by the £72 the card quotes. Already noted under item 18 ("with no change to your take-home pay"). Status: fixed in #1166: `SalarySacrificeNiStrategy` now says take-home pay rises by the saving.
  - **Retested on csjones (web 1440, Rory after adding Aviva 5%/3% through pay on the Retirement page):** "Switching your £3,600 annual workplace pension contribution to salary sacrifice saves £72 in National Insurance every year, so your take-home pay rises by that much" (£3,600 × 2%, pay stays above £50,270) (`shots/R5-retest-03-tax-strategy.png`). **Fynla.org retest: after the release.**
- **R6. The salary sacrifice card's link reads "Open a pension"** (A8, Ellis), though the switch is about the workplace pension he already has. Clicking it on fynla.org opened the **Dashboard**, because `/pension` is not a web route (`shots/R6-open-a-pension.png`). "Open investments" (`/investments`) is the same, and "See income & tax" opened Personal details, not Income (`shots/R6-see-income-tax.png`). /m kept its own copy with other labels ("Open retirement"). Status: fixed in PR #1166 (NOT merged): one server map, `App\Support\StrategyNextStep`, gives each composed plan item a `next_step` {label, destination}; web (`StrategyRecommendationList.vue`) and /m (`TaxStrategy.vue`) resolve it through their destination maps; "Open a pension" becomes "Open pensions". Merged #1166.
  - **Retest found R6b:** the main pension card ("Pay £21,200 more into your pension…", type `pension_tax_relief`) had no link beside a salary sacrifice card that did. The map took its keys from the old client maps: four keys no strategy emits (`joint_savings_split`, `asset_shifting_savings`, `asset_shifting_isa`, `cross_spouse_dividends`), seven emitted types missing (`pension_tax_relief`, `savings_to_spouse`, `isa_topup_spouse`, `isa_coordination`, `gia_to_spouse`, `gia_rebalance`, `joint_savings_psa_split`). **Fixed:** #1168 (dev `d68ad3df2`), map keyed on the 21 emitted types; `StrategyNextStepTest` reads the strategy sources and fails on a missing or dead key (red on the old map).
  - **Retested on csjones:** web 1440 "Open pensions" → Retirement, "Open savings & ISAs" → Bank Accounts (`shots/R6-retest-02-open-pensions-web.png`, `shots/R6-retest-03-open-savings-web.png`); /m 390 labels "Open pensions", "Open savings & ISAs", "Open pensions", landing on Retirement and Bank Accounts (`shots/R6-retest-m-03-open-pensions.png`, `shots/R6-retest-m-04-open-savings.png`). Not yet seen: "Open investments" and "See income & tax" (no such item for Rory); check in walk B. **Fynla.org retest: after the release.**

- **R7. The web footer jams beside the collapsed rail** (found retesting R4, csjones 1440). The row is `justify-between` with no gap; with R4's 40px gone, "fynla.org" ran into "Privacy Policy" (Bank Accounts) and the links wrapped onto three lines (Retirement) (`shots/R6-retest-03-open-savings-web.png`, `shots/R6-retest-02-open-pensions-web.png`). **Fixed:** #1169 (dev `7e332b9bd`), `AppFooter` gap, the middle text takes the slack, the links do not wrap. **Retested on csjones:** nothing touches; the middle line wraps "fynla.org" under it (`shots/R7-retest-01-footer-bank-accounts.png`). Also seen: the footer logo is missing on csjones only (`AppFooter` `/images/logos/…` ignores the `/fynla/` base); production serves from `/`.
- **R8. Answered chips stay clickable on web** (found finishing Rory's onboarding, csjones). After "Yes, that's right" the check-step chips stayed live under the date-of-birth and spending forms, so the answer could be sent again; the earlier "Continue" chips did grey. `AiChatPanel::latestQuickRepliesIndex` kept the newest chips live whatever followed. **Fixed:** #1169, chips grey once a user message follows; `AiChatPanelQuickReplies.test.js` (red before). /m already removes answered bubbles. **Retested on csjones (web 1440, Quinn Retest, new Save Tax account):** after "No, that's everything" and "Okay" were answered, both sets of chips were disabled while the savings form followed (`shots/R8-retest-01-web-chips.png`).
- **R9. Two pension pot projections on one page** (csjones Retirement, web 1440, Rory after adding a £40,000 pension at £480 a month): "Am I saving enough… Projected Capital £561,689" beside "Pension Pot Projection… Projected Value (middle outcome) £463,839" (`shots/R5-retest-02-pension-added.png`). One figure, two answers. Cause: two engines. "Projected Capital" is the planning projection (`headline.dc_value_at_retirement`, the pot behind the £26,399 income and the shortfall; switched to it in the 1 October snapshot `9b7f0a1af`); "Projected Value (middle outcome)" is the Monte Carlo median (`pension_pot_projection.median_at_retirement`, put beside the 20th percentile by W-0259). With volatility the median sits below a fixed-rate projection, so the two never agree. **DECISION (CSJ):** which calculation is "your pot at retirement"? Recommendation: the planning projection (it drives the income, the shortfall and the target), with the Monte Carlo card showing only its range around it, so the page carries one pot figure.
- **R10. A Cash ISA with no rate given shows "0.00%"** (/m Bank Accounts, Rory's Nationwide). The onboarding ISA form makes the rate optional (`CaptureForms.php:847`) and `savings_accounts.interest_rate` is `NOT NULL DEFAULT 0.0000`, so "not given" is stored and shown as 0% (`shots/R6-retest-m-04-open-savings.png`). **Fixed:** #1170 (dev `15f65efdf`): migration `2026_10_09_100000` makes the column nullable (existing rows unchanged); web reads the rate through one formatter (`utils/interestRate.js`, five copies removed); /m says "Not recorded"; the edit modal keeps a missing rate empty; the zero-rate card skips an unrecorded rate. Found in the path and fixed there: two savings decision traces printed a stored 4.25% as "425.00%" and computed interest as balance × 4.25. `InterestRateNotRecordedTest` (red before).
  - **Retest found R10b:** clearing the rate on the web edit form failed with "Failed to update account. Please try again." (`UpdateSavingsAccountRequest` `sometimes|numeric`). **Fixed:** #1171 (dev `5aa6d3405`), `nullable`; `SavingsApiTest` (red before). Also seen: the modal shows a generic failure, not the field's message.
  - **Retest found R10c:** "Interest Rate: Not recorded" beside "Annual Interest: £0". **Fixed:** #1172 (dev `cb5899d32`), the model's annual and monthly interest are null with no rate; web and /m say "Not recorded".
  - **Retested on csjones:** web 1440 Nationwide → Edit → rate cleared → Update Account → "Interest Rate: Not recorded", "Annual Interest: Not recorded" (`shots/R10-retest-06-rate-not-recorded-web.png`, `shots/R10-retest-07-web-annual-not-recorded.png`); /m 390 Bank Accounts "4.25%" / "Not recorded", account page rate, monthly and annual interest "Not recorded" (`shots/R10-retest-08-m-bank-accounts.png`, `shots/R10-retest-09-m-nationwide.png`). Migration run on local and csjones (one file). **Release needs the migration. Fynla.org retest: after the release.**
- **R11. /m top action titles get about 87px of a 271px row** (/m 390, Rory): "Pay £18,300 more into your pension and save £7,320 in tax" wraps one word per line (`shots/walk-m-action-row.png`). The tick (52px), dismiss (40px), padding, bulb and chevron take the rest. From the June "Top-actions box redesign" (`62450350d`), part of the approved /m dashboard layer. **DECISION (CSJ):** how should the title get room?
- **R12. /m account page heading says "Savings and emergency fund"** while the list page and the menu say "Bank Accounts" (`shots/R10-retest-09-m-nationwide.png`). **Fixed:** #1177 (dev `0774827a8`). **Retested on csjones /m:** Santander's page headed "Bank Accounts" (`shots/R12-retest-m-heading.png`).
- Seen (accessibility): the Bank Accounts card "+" buttons have no accessible name, and the web savings edit form's inputs have no linked labels.
- **A9 (fynla.org, Ellis, 2026-10-09 ~09:55):** web 1440 "How did you work out the £17,600 pension figure?" and /m 390 "Where does the £17,600 in my pension action come from?" both gave the plan's working: £72,765 − £3,600 through pay = £69,165; higher rate from £50,270 raised to £51,020 by £750 Gift Aid; £500 of interest at 0% under the Personal Savings Allowance; £17,645 at 40% → £17,600 → £7,040. No past-contribution claim. Web wrote whole pounds with pence ("£72,765.00"), /m did not (`shots/A9-02-web-fyn-working.png`, `shots/A9-04-m-fyn-working.png`).
- **A10 (fynla.org, Ellis):** /m and web agree: Level 6 "2 of 4"; Tax Strategy £7,368; Retirement £40,993 projected, £51,874 target; Bank Accounts £30,000 (`shots/A10-*.png`). Found R13 (State Pension left out silently), and R9 and R11 on production too.
- **R13. Retirement says "a shortfall of £10,880 a year" with the State Pension left out and nothing saying so** (A10, /m; web said so only inside the completeness panel). **Fixed:** #1173 (`RetirementHeadline::state_pension_note`, the income tab's existing message as one constant, shown under the projection on web and /m with the add action); retest found the /m "Add it" invisible on the dark hero (#1174), opening the general pension form with no State Pension option (#1175, which also fixes the drawing view's "Add it" since item 6) and then edit wording for a record that did not exist (#1176). **Retested on csjones (Rory, no State Pension):** web note + "Add State Pension" → form → £230.77 a week saved → note gone (`shots/R13-retest-01..05`); /m note + "Add it" (`shots/R13-retest-07-m-add-link.png`) → "Fill this in and save, and I'll add it to your records." → "Not yet", £12,000, 20 years → "Saved" → note gone; income sources "State Pension from age 68, £12,000 a year" (`shots/R13-retest-10..13`). Seen in setup only: `capture_state_pension` inserts beside a soft-deleted row and hits the unique key; users cannot delete a State Pension (no route), so not reachable. **Fynla.org retest: after the release.**
- **B1–B2 (fynla.org, Morgan Walker, user 808, `slaterjoneschris+walk-b1@gmail.com`):** funnel full-time, £50,271–£100,000, partner up to £50,270, savings + pension → estimate "up to £4,000" (the funnel's 10% target at £100,000; CSJ item 5: leave the funnel as is) → registered → Fyn recap right → web income Acme Ltd £80,000 → "No, that's everything" → signed out → /m signed in → "Welcome back, Morgan… mid-onboarding" → Continue resumed at the accounts consent step (`shots/B2-07-m-continue.png`) → joint Nationwide £40,000 at 4% → Bank Accounts check "Your 50.00% of £40,000" (R14), "Target (6 months) £0" (R15) → DOB → Legal & General 5% + 5% not salary sacrifice → spouse full-time £30,000 with a £20,000 Aviva pension paying £1,500 → spending £3,500 → plan: pension £21,500 / £8,600 (R16), ISA wrap £7,500 / £120, salary sacrifice £80, total £8,800. PSA uses Morgan's own £800 share of the joint interest (£40,000 × 4% × 50%).
- **R14. /m share label "Your 50.00% of £40,000".** **Fixed:** #1179 (dev `ed158b60a`): "Your 50% of". **Retested on csjones /m (Quinn):** "Your 50% of £40,000" (`shots/R14-R15-retest-m-bank-accounts.png`).
- **R15. /m Bank Accounts "Target (6 months) £0" with no spending recorded.** **Fixed:** #1179: the target row and bar hide until spending is known, as the web emergency fund does. **Retested on csjones /m (Quinn):** no target row; "Add your monthly spending" shows.
- **R16. A percentage pension saved through Fyn's form took nothing off spending, so the pension suggestion offered money already paid in** (B3 hand check, Morgan). The plan's working: slice £26,030, capped at "what you can afford … £21,546" = 12 × £1,436.45 ÷ 0.8; the surplus was take-home £59,237.40 less £42,000, with the £4,000 a year already paid in left in. `getFinancialCommitments` called `PensionContributionRule::monthlyEmployee` without the owner's pay, and Fyn's form saves no scheme salary, so the rule gave £0 (`PensionContributionRule.php:49-53`). **Fixed:** #1178 (dev `f0b0855b6`), the owner's pay passed (also `DCPension::getContributionIncludesReliefAttribute`); `AvailableSurplusOneCountTest` (red before). Morgan's plan after release: about £16,500 / £6,600. **Retested on csjones (Rory, second pension added through Fyn on /m with no scheme salary, D3):** commitments £7,200 a year (£600 a month on /m and web Expenditure), take-home £55,856.40 by hand, spare £12,656.40; the plan stays slice-capped at £14,795 → £14,700 / £5,880; salary sacrifice £144; total £6,130 (`shots/R16-retest-*.png`). **Fynla.org retest: after the release (Morgan).**
- **B4 (fynla.org, Morgan, /m):** "Shall I send them an invitation?" → "Yes, invite them" → "What's their first name and email address?" (the corpus's free-text step `campaign_spouse_invite_details`) → "Sam, slaterjoneschris+walk-b2@gmail.com" → "Done. I've sent an invitation…"; invitation 7 created (`shots/B4-04-m-invite-sent.png`).
- **R17 (SECURITY). The password step signs the browser session in before the emailed code; an invited partner registered into the inviter's account** (B5, fynla.org). After Morgan signed out of /m, Sam opened the invite link in the same browser and registered: register page prefilled (Sam, invited email, "Morgan has invited you to plan together"), account 809 created and linked both ways, invitation accepted, but the dashboard showed **Morgan Walker** and Morgan's plan, and `/api/ai-chat/onboarding/start` returned 409 (`shots/B5-04-sam-dashboard.png`). Cause: `AuthController::login` checked the password with `Auth::attempt()` (`:310`, since the initial commit), which signs the web session in; Sanctum authenticates requests from our own domain by that session before the bearer token, and the session outlives sign-out. Proven on csjones: (1) password only, code box open, no token stored → `/api/auth/user` and `/api/savings` 200 (verification bypass; the authenticator check follows the same line); (2) Rory signs in and out on web and /m, Pat registers from Rory's invite → Pat's token (1116) is never used and every call answers as Rory (`shots/R17-repro-csjones-after-verify.png`). **Fixed:** #1182 (dev `71cbec1c2`), `Auth::validate()`; `LoginDoesNotSignInSessionTest` (red before); auth tests 36 passed. **Retested on csjones:** password only → 401 on both (`shots/R17-retest-01-password-only-refused.png`); normal sign-in with the code works; Quinn signs in and out on web and /m, Alex registers from Quinn's invite → Alex's own account and onboarding (`shots/R17-retest-02-partner-own-account.png`). **Production still has this until released. Sessions already signed in stay so until they expire (file sessions): clearing `storage/framework/sessions/` at release ends them without signing anyone out of the apps (bearer tokens). DECISION (CSJ): hotfix release now, and clear sessions?** Walk account Sam (809) is affected; B6 on fynla.org waits for the release.
- **R18. The cookie banner showed over the signed-in dashboard** (CSJ 2026-10-09: "this should NEVER show in the dashboard, because a user can not log in without accepting cookies"). Seen on csjones after the walk cleared the browser's cookies with a sign-in token still held: `CookieBanner` read only the `fyn_cookie_consent` cookie. **Fixed:** #1181 (dev `7bd92a057`): never shown to someone signed in; consent is on the account (`CookieConsentService::claimFor`). `CookieBanner.test.js` (red before). **Retested on csjones:** signed in as Quinn, consent cookie deleted, dashboard reloaded → no banner (`shots/R18-retest-01-no-banner-signed-in.png`); signed out, the /m sign-in page still asks first, as it must.
- Observation (not a defect): /m loaded one minute after the pension was added showed Level 4 "2 of 4"; web two minutes later showed Level 6 "1 of 4", and /m matched on reload.
- Observation: R3's spending case. Rory's Savings card now reads "10 / 6 months, on track" (£30,000 ÷ £3,000) on /m. R2 on /m (no spending recorded) still to see, with a new csjones account.


## Walk accounts used

- fynla.org: Ellis Walker, user 807, `slaterjoneschris+walk-a1@gmail.com` / `Password1!` (walk A; purge at the end).
- csjones: Rory Retest, user 504, `slaterjoneschris+r1-retest@gmail.com` / `Password1!` (R1/R2 retests; mid-onboarding at the pensions step).
