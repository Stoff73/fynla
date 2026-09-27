# Help content audit, 2026-09-26

Read-only inventory of `resources/js/views/Help.vue` (859 lines) and `public/pages/help.php` (598 lines). Nothing was edited apart from this file. Branch: `dev`.

Scope: every `<h3>` block in Help.vue (58 blocks), mapped to its help.php counterpart. Each block's UI claims were checked against the live router and components on web and `/m`. Each tax or finance claim was checked against gov.uk, HMRC or legislation, and against the TaxConfigService key the app reads.

---

## 0. The headline finding: users never see Help.vue

- `routes/web.php:187-194` serves `public/pages/help.php` for `GET /help`.
- The route sits inside the `redirect.authed` group, and it is exempted for signed-in users at `app/Http/Middleware/RedirectAuthenticatedToDashboard.php:44-46`. As a result, **signed-in users also get help.php**.
- Every link into help starts a full page load:
  - the SPA router's `isServerRenderedPage` list includes `/help` (`resources/js/router/index.js:1673-1681`);
  - it forces `window.location.assign` for any in-app navigation to `/help`;
  - web links to `/help` exist at `AppNavbar.vue:111`, `AppFooter.vue:28`, `SitemapPage.vue:73` and `utils/chatNavigationRouter.js:62`.
- On `/m`, the Help row at `resources/mobile/views/Settings.vue:26` calls `openPublicWebPath('/help')`, which is the same PHP page.
- The Vue route `resources/js/router/index.js:1411-1418`, which loads `Help.vue`, is therefore **unreachable**. Help.vue is dead code. Everything a user actually reads is in **help.php**.

This is why the two files drift. The 2026-09-25 ISA and Inheritance Tax fixes were made in both, but other edits were not. The ones that reached only help.php are:
- the Defined Contribution / Defined Benefit wording;
- spelling out International Securities Identification Number;
- the support address;
- removing the demo disclaimer.

**Recommendation:** delete Help.vue and its route, or redirect it, so there is one source (the Rule 20 principle). This audit still covers Help.vue in full, as asked.

Neither file has any help content for `/m`. `/m` users are sent to a page that describes only web screens, and describes them wrongly (see section 3).

---

## 1. Summary counts

Classification is per Help.vue `<h3>` block (58 blocks). A block can carry more than one class; the **primary** class is counted first.

| Class | Primary count |
|---|---|
| ACCURATE | 7 |
| STALE-UI | 36 |
| WRONG-FACT | 12 |
| UNSOURCED | 1 |
| HARDCODED-FIGURE | 2 |
| **Total blocks** | **58** |
| MISSING (screens with no help) | 24 screens (section 3) |

Secondary classes are shown in the Class column of each table. Seven further blocks also carry WRONG-FACT as a secondary class, five carry UNSOURCED, and section 4 lists 13 hardcoded-figure rows across both files.

Other rule breaches, counted separately:
- **Rule 9** (cold acronyms): 11 occurrences.
- **Rule 11 / Rule 8** (colour): 4 occurrences, all in Help.vue.
- **Rule 12** (scores): 2 blocks.
- **Rule 15** (Unicode as icons): 3 arrows.
- **British English**: 3 occurrences.

The 7 ACCURATE blocks are:
- Welcome to Fynla;
- How is Inheritance Tax calculated? (FAQ);
- What's the difference between money purchase and final salary? (FAQ);
- Can I have multiple ISAs? (FAQ);
- How do I link my spouse account? (FAQ);
- Restarting Onboarding;
- Report a Bug.

---

## 2. Section-by-section tables

Column key:
- **V** = Help.vue line.
- **P** = help.php line. `—` means there is no counterpart.
- **Class** = primary class first.

### 2.1 Getting Started

| Heading | V | P | Class | What is wrong | What the live UI shows (file:line) | Source |
|---|---|---|---|---|---|---|
| Welcome to Fynla | 64 | 150 | ACCURATE | Lists protection, estate, retirement, investment and savings. It omits Tax Strategy, Goals and Fyn, which is minor. | Side nav sections at `components/SideMenu.vue:55-98` | n/a (product description) |
| First Time Setup | 71 | 157 | STALE-UI | Steps 2 and 3 say you choose a focus area, then complete an onboarding wizard. While `onboarding.forced_campaign` is set, **every** new registrant goes to the Dashboard with Fyn open (`views/Register.vue:529-541`). That setting defaults to `savetax` (`config/onboarding.php:92`) and has been live on prod since release #940. The wizard and focus-area branches are dormant (`Register.vue:530-531`, comment). Step 5, "link spouse accounts", is now an **invitation** (see 2.8). | Dashboard, then the Fyn Save Tax walk. `/m` has no register route (`resources/mobile/router.js:58-103`). | `config/onboarding.php:92`; `Register.vue:536-541` |
| Key Concepts | 82 | 168 | WRONG-FACT, STALE-UI | (1) "Five main areas" is wrong. The side nav has Cash Management, Finances, Family/Personal Affairs and Planning, covering 20+ items, and CLAUDE.md names seven modules. (2) "Agents: AI-powered analysis engines" is wrong. `app/Agents/{Protection,Estate,Retirement,Investment,Savings,Goals,TaxOptimisation}Agent.php` are deterministic PHP. The only AI the user meets is **Fyn** (`app/Agents/CoordinatingAgent.php:331`), and help never names Fyn. (3) "Data Sharing: control what information is shared" is wrong. Sharing is all-or-nothing (see 2.8). | `SideMenu.vue:55-98`; `components/UserProfile/SpouseDataSharing.vue:66` | code only |

### 2.2 Dashboard Overview

| Heading | V | P | Class | What is wrong | What the live UI shows (file:line) | Source |
|---|---|---|---|---|---|---|
| Main Dashboard | 100 | 184 | STALE-UI | Describes a card grid of "key metrics". The dashboard is now the gamified layer (CSJ-approved, Rule 12 carve-out). It has a Level header, "X of Y actions to your next level" and "You're ahead of N% of people". Focus tabs (Save tax, Retirement, Protection, Savings, Investment, Estate, Goals) each hold a Recommendations list with a tick, Skip and "Get more recommendations" (which opens Fyn). Below that is "Your finances". | `views/Dashboard.vue:22` mounts `views/GamifiedDashboard.vue`. Header `:131-146`, tabs `:148-160`, recommendations `:162-187`, finances `:190-210`. Empty state "Let's build your plan" `:9-21`. `/m`: `resources/mobile/views/Dashboard.vue` | CSJ gamification carve-out (CLAUDE.md Rule 12) |
| Dashboard Cards | 107 | 191 | STALE-UI | Names five cards. The "Estate Planning" card and the "Plans" card **do not exist**. The "Protection" panel shows the **amount of cover** ("Cover in place" / "Add your cover"), not a gap analysis. Missing from help: the Savings panel (emergency fund bar), Retirement panel ("% of target"), Investment panel, and the Recommendations tabs. "Trusts" is a section of trust rows (name, type, value), shown only with full Estate capability (`GamifiedDashboard.vue:217-220`, `:321-322`). It does not show a count and total. | Panels at `GamifiedDashboard.vue:383-387`. Captions at `utils/dashboardCards.js:95,105,122`. `TrustsOverviewCard.vue:1-45` | code only |
| Quick Actions | 118 | 202 | STALE-UI | There is no "Plans card" and no Quick Actions on the dashboard. Plans are reached from the side nav, Planning section, "Plans" (`/plans`). | `SideMenu.vue:93`; `router/index.js:1153` (`PlansDashboard.vue`) | code only |

### 2.3 User Profile & Settings

| Heading | V | P | Class | What is wrong | What the live UI shows (file:line) | Source |
|---|---|---|---|---|---|---|
| Personal Information | 132 | 214 | STALE-UI | Help says you can edit the National Insurance number. **The personal form has no NI field.** Its fields are First Name, Surname, Email, Date of Birth, Gender, Marital Status, Phone, University, Student Number, Education Level, Address (5 lines), Job Title, Employer, Industry, Employment Status, Retirement Age, Country of Birth and Date Moved to UK. Help does not name the location: Settings, then Personal Info (`/settings/personal`). | `components/UserProfile/PersonalInformation.vue:187-518` (labels); `router/index.js:600` | code only |
| Income & Occupation | 139 | 219 | STALE-UI, WRONG-FACT | Income is no longer in "profile". It is in **Valuable Info, Income tab** (`/valuable-info?section=income`), which the side nav shows as Cash Management, then Income. Occupation is on Personal Info. The claim that occupation "affects protection insurance recommendations" is not true in the code: `RecommendationEngine.php` never reads occupation. It appears only as display text in the plan summary (`ComprehensiveProtectionPlanService.php:242`). | `views/ValuableInfo.vue:100-103`; `SideMenu.vue:64`; `/m`: `/income` (`mobile/router.js:66`) | code only |
| Health Information | 146 | 224 | WRONG-FACT | Says health status, smoking status and education level "help provide accurate protection recommendations and estimate insurance premium costs". The Health page saves to `users` (`HealthInformation.vue:215`). The code states "**Nothing anywhere reads `users.smoking_status` for protection**". Premium loading uses `protection_profiles.smoker_status` (`RecommendationEngine.php:182-186`). | `ComprehensiveProtectionPlanService.php:195-213`; `/settings/health` (`router/index.js:613`) | code only |
| Domicile Information | 153 | 229 | WRONG-FACT | (1) The claim that domicile "is critical for inheritance tax calculations… domicile status affects tax liability" is wrong in law and in the app. **Law:** since 6 April 2025, Inheritance Tax scope follows the **long-term UK residence** test, at least 10 of the previous 20 tax years (IHTA 1984 s6A, HMRC IHTM47020). It applies "regardless of an individual's common law domicile". **App:** `IHTCalculationService.php` never reads domicile. Domicile is used only as a data-readiness check (`EstateDataReadinessService.php:168-172`) and for will jurisdiction (`WillDocumentService.php:350-353`). (2) The UI it points to also states a **repealed rule**. `PersonalInformation.vue:157-165` says "deemed domiciled… resident for at least 15 of the last 20 tax years" and "after {15 − years} more year(s)". That is the pre-2025 deemed-domicile test, and the figure 15 is hardcoded, which breaks Rule 2. (3) There is no separate "Domicile Information" screen. Domicile is a read-only block inside Personal Info. | `PersonalInformation.vue:135-166` | https://www.gov.uk/hmrc-internal-manuals/inheritance-tax-manual/ihtm47020 (IHTA 1984 s6A) |
| Family Tab | 160 | 234 | STALE-UI, British English | "Family Tab" is now **Settings, then Family** (`/settings/family`), which also holds the spouse data-sharing panel. It says "dependents"; the British noun is "dependants", and the form itself says "Other Dependant" (`FamilyMemberFormModal.vue:36`). | `views/Settings/FamilySettings.vue:35`; `router/index.js:636` | n/a |

### 2.4 Protection Module

| Heading | V | P | Class | What is wrong | What the live UI shows (file:line) | Source |
|---|---|---|---|---|---|---|
| Overview | 174 | 244 | STALE-UI | It lists four cover types. The app has **five**: Sickness/Illness is missing (`PolicyFormModal.vue:58-62`). | Page header "Protection Planning" (`views/Protection/ProtectionDashboard.vue:14`) | code only |
| Current Situation Tab | 181 | 249 | STALE-UI | **There are no tabs.** `ProtectionDashboard.vue:52-64` renders one `ProtectionModuleOverview`. The button is "**Add New Policy**" (or "Add Protection" when empty), not "Add Policy". There is also an "Upload Document" button. Life policy types are Decreasing, Family Income Benefit, Level Term and Whole of Life (`PolicyFormModal.vue:77-80`). The claimed critical illness choice of "Standalone, Accelerated, or Additional" **does not exist in the form**: it is silently set to standalone (`PolicyFormModal.vue:1000`). Income protection frequency options are Monthly, Weekly or Lump Sum (`:437-439`). Disability has Accident Only or Accident and Sickness (`:480-481`). | `ProtectionModuleOverview.vue:31,65,74`. `/m`: "Coverage gaps", "Policies" (`mobile/views/modules/Protection.vue:25,77`) | code only |
| Gap Analysis Tab | 194 | 260 | STALE-UI, WRONG-FACT, HARDCODED-FIGURE | (1) There is no tab. The on-page section is "**Protection Shortfall**", with Debt Protection, Income Replacement, Critical Illness, Sickness Cover and Disability Cover. After it come "Affordability Assessment" and "Coverage Summary" (`ProtectionModuleOverview.vue:147-448`). (2) The definition "Human Capital: value of your future earnings" is wrong. The app computes the **lump sum that sustains the family's annual income need at a 4.7% withdrawal rate** (need ÷ 0.047, `CoverageGapAnalyzer.php:30-43`, key `protection.withdrawal_rates.human_capital`). (3) "Final Expenses (£7,500)" is a **hardcoded figure**. It should come from `protection.final_expenses`, which is 7500 at `TaxConfigurationSeeder.php:1029`, read at `CoverageGapAnalyzer.php:152`. (4) Help omits the **education funding** need (`protection.education_cost_per_year` 9000, to age 21, `CoverageGapAnalyzer.php:130-146`). (5) The note that spouse income reduces need is accurate only when the spouse is linked, married and has **accepted** sharing (`CoverageGapAnalyzer.php:394-405`). Help does not say so here. | as left; `/m` `Protection.vue:25-75` | `CoverageGapAnalyzer.php` / TaxConfigService keys; **UNSOURCED externally**: £7,500 funeral and £9,000 tuition have no cited source in the seeder comments |
| Strategy Tab | 210 | 272 | STALE-UI, WRONG-FACT | There is no Strategy tab. Protection recommendations appear in (a) the dashboard's Protection focus tab, (b) `/actions` ("Actions & Recommendations"), and (c) `/plans/protection`. They are produced by rule-based `RecommendationEngine.php`, **not AI-generated**. | `GamifiedDashboard.vue:266`; `router/index.js:1042,1178` | code only |
| Policy Details Tab | 217 | 277 | STALE-UI | There is no tab. Policies show as cards on `/protection`, each opening `/protection/policy/:policyType/:id` (`PolicyDetail.vue`). Help says they are grouped by type; the code shows a filtered grid, and I could not confirm grouping. | `ProtectionModuleOverview.vue:82-89`; `router/index.js:847`; `/m` `mobile/router.js:83` | code only |

### 2.5 Estate Planning Module

| Heading | V | P | Class | What is wrong | What the live UI shows (file:line) | Source |
|---|---|---|---|---|---|---|
| Overview | 231 | 287 | STALE-UI | This is accurate in outline. Help does not say that **free users see a teaser only**: a headline, an estimated exposure and an upgrade link (`EstateDashboard.vue:39-84`). | `EstateDashboard.vue:86-115` | n/a |
| Current Situation Tab | 238 | 292 | STALE-UI | There are no tabs. `/estate` is a grid of cards (`IHTPlanning.vue:99-372`): **Inheritance Tax Summary** (Taxable Estate, liability now and at the estimated age at death, click-through to `/estate/inheritance-tax`), Will, Power of Attorney, Charitable Bequest, Life Policy, Gifting, and Trust (only when the taxable estate exceeds £2m). The full asset/liability table is on `/estate/inheritance-tax` (`router/index.js:962`). Help's "Tax-free threshold" and "residence allowance" figures are correct in law. In Help.vue they come from **frontend fallback constants**, not TaxConfigService (see section 4). The UI labels them "Tax-Free Allowance" and "Home Allowance" (`IHTPlanning.vue:434,438`). | as left; `/m` `mobile/views/modules/Estate.vue:24-96` | NRB £325,000, RNRB £175,000: https://www.gov.uk/inheritance-tax; https://www.gov.uk/guidance/inheritance-tax-residence-nil-rate-band; keys `inheritance_tax.nil_rate_band`, `.residence_nil_rate_band` (`TaxConfigurationSeeder.php:357-358`) |
| Inheritance Tax Planning Tab | 251 | 303 | STALE-UI | There is no tab. The second-death view is on `/estate/inheritance-tax`, headed "**Inheritance Tax Calculation (Joint Death Scenario)**" (`IHTPlanning.vue:380`), not "Second Death analysis". The combined figures (2 × NRB, 2 × RNRB) are correct in law. | `IHTPlanning.vue:376-399` | Transferable NRB IHTA 1984 s8A and RNRB s8L; https://www.gov.uk/inheritance-tax ("any unused threshold can be added to your partner's threshold"); keys `transferable_nil_rate_band`, `transferable_rnrb` (`TaxConfigurationSeeder.php:365-366`); spouse exemption IHTA 1984 s18 |
| Gifting Timeline | 258 | 308 | STALE-UI, WRONG-FACT | (1) The timeline (`DualGiftingTimeline`) renders **only for married users with second-death data** (`IHTPlanning.vue:593-599`). Single users see only a "Gifting" card. (2) The Gifting card, the Life Policy card and the Trust card emit `switch-tab`. `EstateDashboard.vue:114` listens only for `@will-updated`, so **clicking them does nothing** (`IHTPlanning.vue:1758-1775`). This is a live dead-click defect found in passing. (3) "Chargeable Lifetime Transfers: Gifts to trusts, subject to Inheritance Tax immediately" is wrong as stated. The lifetime charge is 20% **only on the value above the available threshold**. (4) "Gifts older than 7 years are outside the estate" holds for Potentially Exempt Transfers. A Chargeable Lifetime Transfer can affect cumulation for 14 years; the app models `clts_7_to_14_years` (`FailedGiftTaxCalculator.php:71`). (5) Taper bands are correct: 3-4 years 32%, 4-5 years 24%, 5-6 years 16%, 6-7 years 8%. (6) Adjacent: the Gifting card hardcodes "**£250 per person**" (small gift allowance) at `IHTPlanning.vue:333-335`, which breaks Rule 2. | as left | https://www.gov.uk/inheritance-tax/gifts (7-year rule, taper table, £3,000, £250); https://www.gov.uk/guidance/trusts-and-inheritance-tax ("20%… Inheritance Tax is due on everything above the threshold"); key `entry_charge_rate` 0.20 (`TaxConfigurationSeeder.php:1176`) |
| Will Planning Tab | 270 | 318 | STALE-UI | There is no tab. Side nav "Will" goes to `/estate/will-builder`. With no will, a 10-step **Will Builder**: Introduction, Personal Details, Executors, Guardians, Specific Gifts, Residuary Estate, Funeral Wishes, Digital Assets, Review, Signing Guide (`WillBuilderWizard.vue:143-152`). With a will, the `WillPlanning` summary. **"Death scenario (user only or simultaneous)" no longer exists** (0 hits in `components/Estate`). Spouse bequest percentage, executors and last updated still exist (`WillPlanning.vue:107-120,309-313`). The builder is for England and Wales only (`EstateDashboard.vue:103`), and help does not say so. | `views/Estate/WillBuilderView.vue:6-18`; `SideMenu.vue:82` | code only |
| Letter to Spouse | 277 | 323 | STALE-UI | It is now in the side nav as "Letter to Spouse", or "**Expression of Wishes**" for users without a spouse. It lives at `/valuable-info?section=letter`. **"View your spouse's letter to you" has no UI**: the endpoint `GET /api/user/letter-to-spouse/spouse` (`routes/api.php:330`) has no caller in `resources/js` or `resources/mobile` (0 grep hits). | `SideMenu.vue:83`; `ValuableInfo.vue:100` | code only |

### 2.6 Retirement Planning Module

| Heading | V | P | Class | What is wrong | What the live UI shows (file:line) | Source |
|---|---|---|---|---|---|---|
| Overview | 291 | 333 | STALE-UI | "Retirement module": `/retirement` now redirects to `/net-worth/retirement` (`router/index.js:907`). The page shows the cards "Guaranteed Retirement Income", "Will I have enough income for retirement?", "Am I saving enough for retirement?" and "How should I draw down my pension?" (the last only within 10 years of retirement). | `components/NetWorth/PensionList.vue:85,194,219,241`; `/m` `/retirement` (`mobile/router.js:86`) | n/a |
| Pension Types | 298 | 338 | STALE-UI (Help.vue only) | Help.vue says "Money Purchase" and "Final Salary". The UI labels are "Defined Contribution Pension" and "Defined Benefit Pension" (`PensionList.vue:272,297`). help.php already uses the UI labels. The files **disagree**. The State Pension NI-based statement is correct. | as left | https://www.gov.uk/new-state-pension/what-youll-get (qualifying NI years) |
| Money Purchase Pension Holdings | 307 | — | STALE-UI, Rule 9, Rule 12 | Pension detail shows Pension Details, Fund Value, Contributions, Retirement, Fees, "10-Year Fee Impact" and "Projected Pension Pot Growth" (`PensionDetailInline.vue:76-459`). Holdings entry exists through HoldingForm, which has an ISIN field. **Alpha, Beta, Sharpe, VaR and Max Drawdown are not rendered anywhere live.** `RiskAnalysisSection.vue`, `BenchmarkComparison.vue` and `PerformanceAttribution.vue` are imported by nothing (grep). "Diversification analysis" is not rendered, and a diversification rating is also banned by Rule 12. "Low-cost alternatives comparison" is not found as a UI label. Cold acronyms: ISIN, VaR. | as left | code only |
| Annual Allowance | 320 | 347 | HARDCODED-FIGURE | "**£60,000**" is literal in both files (Help.vue:322, help.php:348). It should read `pension.annual_allowance` (60000, `TaxConfigurationSeeder.php:256`). Carry forward of 3 years is correct (`carry_forward_years` 3, `:280`). Help omits the **tapered annual allowance** and the **money purchase annual allowance**. The UI exists: "Am I saving enough", then the Capital tab, "Annual Allowance Progress" and "Carry Forward" (`CapitalAdequacyTab.vue:63-110`). Help.vue appends `{{ currentTaxYear }}` from `getCurrentTaxYear()`, so the year is not hardcoded. | `PensionList.vue:219` then `setActiveTab('capital')` | https://www.gov.uk/tax-on-your-private-pension/annual-allowance ("£60,000", "previous 3 tax years", taper above £200,000 threshold / £260,000 adjusted income, MPAA) |
| Retirement Readiness | 327 | 352 | STALE-UI, Rule 12 | There is no "Retirement Readiness" screen or label. The nearest is the card "Will I have enough income for retirement?" (Target Income against Projected Gross Income, `PensionList.vue:194-205`). A "readiness assessment" implies a rating, which Rule 12 bans. The orphaned `StrategiesTab.vue` holds the only "readiness" text. | as left | n/a |

### 2.7 Investment & Savings

| Heading | V | P | Class | What is wrong | What the live UI shows (file:line) | Source |
|---|---|---|---|---|---|---|
| Investment Module | 341 | 362 | STALE-UI, WRONG-FACT | (1) The "Portfolio Analysis: risk metrics, asset allocation, fee analysis" tabs are **commented out** (`NetWorth/InvestmentList.vue:178-200`). The page shows "**Analytics — Coming Soon**" (`:159-163`). (2) "Efficient Frontier" is in Help.vue only. `EfficientFrontier.vue` is reachable only through `PortfolioOptimization.vue`, which nothing imports, so it is **not live**. (3) "Monte Carlo 1,000 iterations" is correct (`MonteCarloSimulator.php:52`; key `monte_carlo_iterations` 1000, `TaxConfigurationSeeder.php:1069`), but 1,000 is literal text in help. (4) The account type list omits Private Company, Crowdfunding, SAYE, Company Share Option Plan (CSOP), Enterprise Management Incentives (EMI), Unapproved Share Options and Restricted Stock Units (RSUs) (`Investment/AccountForm.vue` options). "National Savings & Investments" is a **Savings** type (`SaveAccountModal.vue:80`), not an investment type. (5) Help.vue uses "ISIN" cold; help.php spells it out. | `/investment` redirects to `/net-worth/investments` (`router/index.js:897`); side nav "Investments". `/m` `/investment` | code only |
| ISA Allowance Tracking | 355 | 373 | HARDCODED-FIGURE (help.php) | help.php:374 hardcodes "**£20,000**". The same file's FAQ at `:9` computes it from `getISAAllowances()['annual_allowance']`, so the page is internally inconsistent. Help.vue uses the frontend constant (section 4). The tax-year dates are correct. The ISA allowance is shown in Tax Strategy (`/tax-strategy`, `components/TaxStrategy/AllowanceGrid.vue`, `/m` `TaxStrategy.vue`), which help never mentions. | as left | https://www.gov.uk/individual-savings-accounts/how-isas-work ("£20,000", "6 April to 5 April"); key `isa.annual_allowance` (`TaxConfigurationSeeder.php:229`) |
| Savings Module | 362 | 378 | WRONG-FACT, STALE-UI, UNSOURCED, British English | (1) "Recommended emergency fund (3-6 months)" is wrong for the app. `EmergencyFundCalculator.php:86-94` targets **6 months if employed, 9 if self-employed or contractor, 3 if retired**. These figures are hardcoded in PHP, not config. `EmergencyFund.vue:220` also says "3-6 months". There is no official source for either. (2) Account types now include Easy Access, Instant Access, Notice, Fixed Term, National Savings & Investments, Cash ISA and **Junior ISA** (`SaveAccountModal.vue:80-92`). (3) The web screen is side nav "Bank Accounts" (`/net-worth/cash`, `SideMenu.vue:63`), with groups Current Accounts, Savings Accounts, Cash ISAs, NS&I (`CashOverview.vue:68-209`). "NS&I" is cold there. (4) Savings goals now live in **Goals** (`/goals`). (5) "toward" should be "towards". | as left | UNSOURCED (no gov.uk rule; the app's own targets are `EmergencyFundCalculator.php:86-94`) |
| Investment & Savings Plan | 375 | — | STALE-UI | "Access from Quick Actions on the dashboard": Quick Actions do not exist. It is `/plans/investment` from side nav "Plans". | `router/index.js:1165`; `SideMenu.vue:93` | n/a |

### 2.8 Family & Spouse Management

| Heading | V | P | Class | What is wrong | What the live UI shows (file:line) | Source |
|---|---|---|---|---|---|---|
| Adding Family Members | 389 | 394 | STALE-UI, Rule 15 | (1) The path "User Profile → Family tab" is now **Settings, then Family**. (2) The relationship options are Spouse, **Partner**, Child, **Step Child**, Parent and **Other Dependant**. There is **no sibling and no "other"** (`FamilyMemberFormModal.vue:31-36`). (3) There is **no National Insurance field and no annual income field**. The form has email (spouse), first, middle and last name, DOB, gender, is-dependent, education status, receives child benefit, is disabled, and notes (`:59-234`). (4) "→" is Unicode used as an icon (Rule 15). | as left | code only |
| Spouse Account Linking | 403 | 406 | WRONG-FACT | (1) The claim "If spouse is new: create a new account with a temporary password sent via email" is **wrong**. Since 2026-08-23 no account is created for a supplied address. **Both** cases return "Invitation sent. Your spouse will be asked to confirm the link before anything is shared." (`FamilyMembersController.php:228-262`). (2) Help.vue only: "linked bidirectionally with marital_status set to 'married'" is **wrong**. The linking no longer forces 'married', because that demoted civil partnerships (`:150`). It also exposes a raw field name. (3) Help.vue only: "Spouse income is synced to the spouse's user account" is not the behaviour. The spouse's income is their own account's (`CoverageGapAnalyzer.php:401-405`). (4) The live form's own helper text repeats the stale claim: "A user account will be created for your spouse if they don't have one yet" (`FamilyMemberFormModal.vue:38-39`). That is an adjacent UI defect. | as left | code only (CSJ decision 2026-08-23, W-0347/W-0349) |
| Data Sharing Permissions | 416 | 415 | WRONG-FACT | Help lists four separate permissions (protection, estate, gifts, letter). Sharing is **one switch**. If accepted, "you will each be able to see the other's assets, liabilities and income, and your accounts will be recorded as one household". One party asks, the other accepts or declines, and either can stop sharing. Stopping "does not undo the household record". "Permissions must be accepted by both parties" is wrong: one party requests and the other accepts. | `SpouseDataSharing.vue:66,134,224`, on `/settings/family` (`FamilySettings.vue:35`); `/m` `/spouse-sharing` (`mobile/router.js:101`) | code only |
| Joint Ownership | 430 | — | STALE-UI | This block is missing from help.php. Ownership types are `individual`, `joint`, `tenants_in_common` (property only) and `trust` (CLAUDE.md Rule 4). Help omits tenants in common. "Specify the joint owner from your family members list" is not verified. Joint ownership records a share, which defaults to 50/50 except for property (memory: joint ownership defaults). "ISAs must always be individually owned" is **correct**. | n/a | https://www.gov.uk/individual-savings-accounts ("You cannot hold an ISA with someone else") |

### 2.9 Onboarding Process

| Heading | V | P | Class | What is wrong | What the live UI shows (file:line) | Source |
|---|---|---|---|---|---|---|
| Focus Area Selection | 452 | 432 | STALE-UI | New registrants do not see this while `forced_campaign=savetax` (`Register.vue:529-541`). Where the wizard still runs (`?stage=`), `FocusAreaSelection` is a **life-stage map** with step tiles such as About You, Student Loan, Income & Career, Your Savings and First Home (`FocusAreaSelection.vue:309-327`). It is not the five focus areas listed. "Tax Optimisation (Coming soon)" in Help.vue is wrong: Save Tax is the **live, forced** onboarding. | as left | `config/onboarding.php:92` |
| Onboarding Steps | 466 | 443 | STALE-UI | The nine-step list describes the dormant wizard. Live onboarding is Fyn's chat walk: employment, income band, pension, "Anything else?", then an inline form (memory: release 2026-09-25). The wizard's current step set is personal-info, student-loan, income, expenditure, assets, liabilities, protection-insurance, family, will-estate and goals (`OnboardingWizard.vue:429-442`). The domicile step still exists (`:410`). Help's "Domicile Information" item carries the same wrong premise as 2.3. | as left | code only |
| Skip vs Complete | 484 | — | UNSOURCED | The general claim that you can skip and return is not verified against the Fyn walk. Missing from help.php. | `OnboardingWizard.vue` `@skip` (`:256`) | code only |
| Restarting Onboarding | 491 | — | ACCURATE | There is no user-facing restart control. `restartOnboarding` has no `.vue` caller, and only `POST /api/onboarding/restart` exists (`routes/api.php:290`). "Contact support" is therefore consistent. "Will not delete your existing data" is consistent with `OnboardingController.php:219-231`, which returns flags only. Missing from help.php. | n/a | code only |

### 2.10 Frequently Asked Questions (Help.vue) / Common Questions (help.php `$module['items']`, 359-370)

| Heading | V | P | Class | What is wrong | What the live UI shows (file:line) | Source |
|---|---|---|---|---|---|---|
| How do I add a protection policy? | 505 | 360 | STALE-UI | The button is "**Add New Policy**", not "Add Policy". "Life" should be "Life Insurance". | `ProtectionModuleOverview.vue:65` | code only |
| Why is my spouse income not showing in Gap Analysis? | 512 | — | STALE-UI, WRONG-FACT | "Gap Analysis" should be "Protection Shortfall". Step 1, "added your spouse in the Family tab **with their income**", cannot be done because the family form has no income field. Spouse income comes from the **spouse's own account** once linked, married and sharing is accepted (`CoverageGapAnalyzer.php:394-405`). Missing from help.php. | as left | code only |
| How is inheritance tax calculated? | 519 | 361 | ACCURATE (see section 4 for Help.vue sourcing) | Correct in law. help.php takes every figure from TaxConfigService (`:13-17`). Help.vue uses frontend fallback constants. It omits the 36% reduced rate for 10% to charity, which the app models (`IHTPlanning.vue:254`), and the reliefs. | n/a | https://www.gov.uk/inheritance-tax (40%, £325,000, transfer to partner); https://www.gov.uk/guidance/inheritance-tax-residence-nil-rate-band (£175,000; "£1 for every £2… £2 million"; step, adopted and foster children and lineal descendants); keys at `TaxConfigurationSeeder.php:357-361` |
| What's the difference between money purchase and final salary pensions? | 526 | 363 | ACCURATE (the files disagree) | Help.vue leads with Money Purchase / Final Salary and claims "full holdings management… with portfolio analysis". Portfolio analysis is not live (see 2.6). help.php leads with Defined Contribution / Defined Benefit, which matches the UI, and drops that claim. | `PensionList.vue:272,297` | https://www.gov.uk/workplace-pensions/types-of-workplace-pension |
| Can I have multiple ISAs? | 533 | 365 | ACCURATE | Correct: you can subscribe to several ISAs of the same type; only one Lifetime ISA per year. On wording: a **child** can hold one cash **and** one stocks and shares Junior ISA. The Junior ISA limit (£9,000) is the child's, separate from the adult £20,000. "Only one of each" is ambiguous. Help.vue adds "Fynla automatically tracks your ISA allowance usage" without saying where (Tax Strategy). | n/a | https://www.gov.uk/individual-savings-accounts/how-isas-work; https://www.gov.uk/junior-individual-savings-accounts ("one or both types", £9,000 in 2026 to 2027); The Individual Savings Account (Amendment) Regulations 2024, SI 2024/350; key `isa.annual_allowance` |
| How do I link my spouse account? | 540 | 367 | ACCURATE | Matches `FamilyMembersController.php:252`. This FAQ **contradicts** the "Spouse Account Linking" section on the same page (2.8). | `/settings/family` | code only |
| What is the Emergency Fund calculator? | 547 | — | WRONG-FACT, UNSOURCED | "3-6 months" is wrong; the app uses 3/6/9 by employment status (see 2.7). There is no calculator by that name. The UI shows "Emergency Fund Runway" and "Emergency Fund Status" (`SavingsModuleOverview.vue:133`, `SaveAccountModal.vue:182`). Missing from help.php. | as left | UNSOURCED |
| What are the portfolio risk metrics? | 554 | — | STALE-UI, Rule 9 | None of these metrics is rendered in a live route (see 2.6 and 2.7). "VaR" is cold. Missing from help.php. | n/a | code only |
| Can I export my data? | 561 | 369 | STALE-UI (Help.vue), help.php partly right | Help.vue says CSV export is "in development". Settings, then **Privacy & Data**, then "**Export Your Data**" already offers JSON or CSV (`views/Settings/PrivacySettings.vue:52-98`). help.php says "available in the account settings", which is correct but vague. **Every** plan has Print (`views/Plans/*Plan.vue` via `planPrintMixin`), not only Protection and Estate. "PDF" and "CSV" are cold. | as left | code only |
| Is my data secure? | 568 | — | WRONG-FACT, Rule 9 | "For demonstration purposes, this system should not be used for real financial planning" is false for production, which has paying customers (memory: 8 legacy paying customers). "Laravel Sanctum", "API" and "HTTPS" are jargon or cold acronyms. Missing from help.php. | n/a | UNSOURCED |

### 2.11 Troubleshooting

| Heading | V | P | Class | What is wrong | What the live UI shows (file:line) | Source |
|---|---|---|---|---|---|---|
| I can't see my policies in Gap Analysis | 582 | 507 | STALE-UI | "Gap Analysis" and "Policy Details tab" do not exist. "Clear your browser cache" is not useful advice. Help.vue only: "policies may need re-entry". | `ProtectionModuleOverview.vue` | n/a |
| Inheritance tax calculation seems wrong | 595 | 516 | WRONG-FACT | (1) "Check domicile status (affects Inheritance Tax liability)" is wrong. See 2.3: IHTA 1984 s6A, and the calculator does not read domicile. (2) Help.vue only: "For **married** couples, check if tax-free allowance transfer from **deceased** spouse is set correctly". The transfer applies to **widowed** users (`IHTCalculationService.php:205`). **No web or `/m` form writes `nrb_transferred_from_spouse`** (0 grep hits in `resources/js` and `resources/mobile`), so there is nothing for the user to check. (3) "Estate module" should be "Estate Planning". Assets are entered under Finances (Property, Investments and so on), not in Estate. | as left | IHTM47020 (above) |
| Data not saving | 609 | 526 | STALE-UI, UNSOURCED | Help.vue only: "F12 → Console" (Rule 15 arrow; developer advice). "Required fields marked with *" was not verified across forms. | n/a | n/a |
| Spouse account linking failed | 623 | 536 | STALE-UI | Three causes are listed, but the user now sees **one** message for all of them: "That email address cannot be linked to your household" (`FamilyMembersController.php:309`). The self-link message is "You cannot add yourself as a spouse" (`:187`). Not mentioned in help: the rate limit "Too many household invitations. Please try again in an hour." (5 an hour, `:159-166`). | as left | code only |
| Numbers not displaying correctly | 636 | — | WRONG-FACT | "Verify date formats are correct (YYYY-MM-DD)" is wrong. The app displays DD/MM/YYYY (`utils/dateFormatter.js` `formatDate`), and inputs are date pickers. "Clear browser cache" again. Missing from help.php. | n/a | code only |
| Monte Carlo simulation not running | 649 | — | STALE-UI, UNSOURCED | "Refresh the page after 30 seconds" is stale: the frontend polls the job itself (`utils/poller.js` `pollMonteCarloJob`, used in `store/modules/investment.js`). Whether holdings are required was **not verified**. Missing from help.php. | n/a | code only |

### 2.12 Contact Support

| Heading | V | P | Class | What is wrong | What the live UI shows (file:line) | Source |
|---|---|---|---|---|---|---|
| Support Information | 673 | 554 | WRONG-FACT (Help.vue), UNSOURCED (both) | Help.vue:675 gives **support@fynla.com**. Every other surface uses **support@fynla.org**; Help.vue is the lone `.com`. "Within 24 hours" and "Monday to Friday, 9am to 5pm GMT" have no source in the repo (`public/pages/contact.php` states no hours). "GMT" is wrong for half the year (BST). Help.vue:672 uses `bg-blue-50 border-blue-200`, which breaks the Rule 11 palette. | n/a | UNSOURCED |
| Important Note | 682 | 563 | WRONG-FACT (Help.vue), Rule 9 | Help.vue says "a demonstration financial planning system", which is wrong for production. help.php already says "financial planning tool". "FCA" is cold in both; it should be "the Financial Conduct Authority (FCA)". Help.vue:681 `bg-yellow-50 border-yellow-200` breaks Rules 8 and 11 (yellow is not in the palette; warnings use `violet-*`). | n/a | FCA perimeter: UNSOURCED in help (no link) |
| Report a Bug | 689 | — | ACCURATE (the files disagree) | Help.vue only. The app has an in-app bug report: `components/BugReportModal.vue` on web and `mobile/views/BugReportSheet.vue`. Help asks users to email instead. | as named | n/a |

Page-level Help.vue issues (outside any `<h3>`):
- `:28` `text-red-600` breaks Rule 11; it should be `raspberry-*`.
- `:854` `bg-blue-100` breaks Rule 11.
- `:25` the search placeholder is fine.
- help.php has **no search box**. Help.vue does. The two files disagree.

---

## 3. Screens users see that help does not explain

Web, from `resources/js/router/index.js` and `components/SideMenu.vue`:
1. **Fyn** (the AI assistant, on every surface). Help never names it.
2. **Gamified dashboard**: Level, actions to next level, "ahead of N%", focus tabs, Recommendations with tick and Skip (`GamifiedDashboard.vue`).
3. **Tax Strategy** `/tax-strategy` (`TaxStrategyDashboard.vue`; allowance grid, asset shifting, household coordination).
4. **Actions & Recommendations** `/actions`, and action detail `/actions/:planType/:actionId`.
5. **Holistic Plan** `/holistic-plan`.
6. **Plans** hub `/plans` and `/plans/{investment,protection,retirement,estate,goal/:id}`.
7. **Journeys** `/planning/journeys`.
8. **What If Scenarios** `/planning/what-if`, including Death of Spouse.
9. **Goals** and **Life Events** `/goals`, `/goals?tab=events`.
10. **Net Worth**, `/net-worth/wealth-summary` and its history `/net-worth/history`.
11. **Property** `/net-worth/property`.
12. **Liabilities** `/net-worth/liabilities`.
13. **Business** `/net-worth/business`.
14. **Personal Valuables** `/net-worth/chattels`.
15. **Risk Profile** `/risk-profile`, levels and factors.
16. **Trusts** `/trusts`, `/trusts/:id`.
17. **Power of Attorney** `/estate/power-of-attorney` and the LPA wizard `/estate/lpa/create/:type`.
18. **Will Builder** as a 10-step builder (section 2.5 covers it only as a stale "tab").
19. **Valuable Info**: Income, Expenditure and Risk Profile tabs.
20. **Settings**: Security (two-factor authentication), Privacy & Data (export, delete), Planning Assumptions, Notifications, Subscription (`/settings/*`).
21. **Upgrade / teaser** `/teaser` and the free-tier Estate teaser. Help does not explain what free users cannot see.
22. **Joint account history** `/net-worth/joint-history`.
23. **Advisor dashboard** `/advisor`. This is for advisers; confirm whether help should cover it.
24. **`/m` as a whole** (`resources/mobile/router.js:58-103`): Dashboard, Tax Strategy, Holistic Plan, Income and Income detail, Expenditure, **Achievements**, Actions, **Conversation History**, Net Worth and categories, Protection, Savings, Retirement, Investment, Estate and **Estate Bequests**, Goals, Personal Information, Settings, Notifications, Spouse Sharing, Subscription. `/m` Estate sends users to the web app for the IHT table and LPAs ("Open on the web app", `mobile/views/modules/Estate.vue:119,139`). Help does not explain this hand-off.

---

## 4. Every hardcoded figure (Rule 2)

| # | File:line | Literal | Should come from |
|---|---|---|---|
| 1 | Help.vue:201 | £7,500 (final expenses) | `protection.final_expenses` (`TaxConfigurationSeeder.php:1029`) |
| 2 | help.php:265 | £7,500 | same |
| 3 | Help.vue:322 | £60,000 (Annual Allowance) | `pension.annual_allowance` (`TaxConfigurationSeeder.php:256`) |
| 4 | help.php:348 | £60,000 | same |
| 5 | help.php:374 | £20,000 (ISA allowance) | `isa.annual_allowance`, which the same file already computes at `:9` |
| 6 | Help.vue:246, 253, 357, 521, 535 | NRB, RNRB, 2×NRB, 2×RNRB, ISA allowance, RNRB taper threshold, 40% rate | Rendered from `resources/js/constants/taxConfig.js:34,127-130`. These are **frontend fallback constants**, and the file's own header says they "should NOT be treated as the source of truth" (`taxConfig.js:1-18`). They are not TaxConfigService or the store. Correct today, but they will not follow a config change. |
| 7 | Help.vue:349 / help.php:259 | 1,000 iterations | `monte_carlo_iterations` (`TaxConfigurationSeeder.php:1069`) |
| 8 | Help.vue:260, 263; help.php:200, 202 | 7 years | Statutory (IHTA 1984 s7 / gov.uk gifts). Arguably a rule, not a rate, but it is not config-driven. |
| 9 | Help.vue:265; help.php:204 | 3-7 years (taper) | Taper schedule in TaxConfigService (see `FailedGiftTaxCalculator.php:23-26`) |
| 10 | Help.vue:322; help.php:348 | "previous 3 years" (carry forward) | `pension.carry_forward_years` (`TaxConfigurationSeeder.php:280`) |
| 11 | Help.vue:369, 549; help.php:274 | 3-6 months (emergency fund) | Wrong as well as hardcoded. The app uses 3/6/9 (`EmergencyFundCalculator.php:86-94`, itself hardcoded PHP, not config) |
| 12 | Help.vue:521; help.php:362 | "£1 for every £2" | `inheritance_tax.rnrb_taper_rate` 0.5 (`TaxConfigurationSeeder.php:360`) |
| 13 | Help.vue:535; help.php:366 | "6 April to 5 April" | Statutory tax-year dates. Acceptable, but not config-driven. |

Adjacent hardcoded figures in the **live UI** that help points users to (found in passing, not in the help files):
- `PersonalInformation.vue:159,162,164`: "15 of the last 20 tax years" / `15 - yearsResident`. This is a repealed rule as well as hardcoded.
- `IHTPlanning.vue:335`: "£250 per person".
- `IHTPlanning.vue:346`: `> 2000000` (Trust card threshold).
- `EmergencyFund.vue:220`: "3-6 months".
- `PensionList.vue:201-203`: a comment references a former invented £35,000; it is not live.

---

## 5. Rule-by-rule tally

**Rule 9, cold acronyms:**
- Help.vue: ISIN (309, 347), VaR (313, 556), FCA (684), PDF and CSV (563), API and HTTPS (570), GMT (677), F12 (617), YYYY-MM-DD (643).
- help.php: FCA (456), PDFs (370), GMT (449).
- The UI help points to: `CashOverview.vue:209` "NS&I".

**Rule 12:**
- Help.vue:315 "Diversification analysis" (a diversification rating is banned).
- Help.vue:329 and help.php:353 "Retirement readiness assessment".

**Rule 15:**
- "→" at Help.vue:391, Help.vue:617 and help.php:286 (`&rarr;`).
- The help.php FAQ chevron is a CSS border (`global.css:323-331`), which is not a glyph.

**Rules 8 and 11:** Help.vue:28 `text-red-600`; `:672` `bg-blue-50/border-blue-200`; `:681` `bg-yellow-50/border-yellow-200`; `:854` `bg-blue-100`. help.php uses palette tokens only.

**British English:**
- "dependents" (noun): Help.vue:162, help.php:126.
- "toward": Help.vue:370, help.php:275.

---

## 6. Where Help.vue and help.php disagree

| Topic | Help.vue | help.php |
|---|---|---|
| Pension labels | Money Purchase / Final Salary | Defined Contribution / Defined Benefit (matches UI) |
| ISIN | cold acronym | spelled out |
| Efficient Frontier | listed | absent |
| Money Purchase Pension Holdings block | present | absent |
| Investment & Savings Plan block | present | absent |
| Joint Ownership block (with correct ISA rule) | present | absent |
| Spouse linking detail (marital_status, income sync) | present, and wrong | trimmed |
| Tax Optimisation "(Coming soon)" | present, and wrong | absent |
| Skip vs Complete, Restarting Onboarding | present | absent |
| FAQs | 10 | 6 |
| Troubleshooting | 6 | 4 |
| Data export | "CSV in development" (stale) | "available in account settings" |
| Support email | support@fynla.**com** | support@fynla.**org** |
| Disclaimer | "demonstration system… not for real planning" | "a financial planning tool" |
| ISA allowance figure | constant (fallback) | literal £20,000 in the body; TaxConfigService in the FAQ |
| IHT figures | frontend fallback constants | TaxConfigService |
| Search box | yes | no |
| Report a Bug | present (email only) | absent |

---

## 7. Live defects found in passing (not help text; reported, not fixed)

1. The Estate Gifting, Life Policy and Trust cards emit `switch-tab`, but nothing listens (`IHTPlanning.vue:1758-1775` against `EstateDashboard.vue:114`), so the clicks do nothing.
2. `PersonalInformation.vue:157-165` states the pre-6 April 2025 "15 of 20 years deemed domicile" rule. The current test is IHTA 1984 s6A, long-term residence, 10 of 20 years (IHTM47020).
3. `FamilyMemberFormModal.vue:38-39` tells users "A user account will be created for your spouse". The backend now only invites (`FamilyMembersController.php:252`).
4. `GET /api/user/letter-to-spouse/spouse` (`routes/api.php:330`) has no client caller. A spouse's letter cannot be viewed.
5. `nrb_transferred_from_spouse` has no web or `/m` input. A widowed user cannot enter a transferred nil-rate band except through the API or Fyn.
6. `RiskAnalysisSection.vue`, `BenchmarkComparison.vue`, `PerformanceAttribution.vue`, `PortfolioOptimization.vue` (and so `EfficientFrontier.vue`), `AnnualAllowanceTracker.vue` and `StrategiesTab.vue` are imported by nothing (grep). This is dead code.
7. Critical illness cover type (standalone, accelerated or additional) cannot be chosen. It is forced to standalone (`PolicyFormModal.vue:1000`).

---

## Sources used

- https://www.gov.uk/inheritance-tax
- https://www.gov.uk/inheritance-tax/gifts
- https://www.gov.uk/guidance/inheritance-tax-residence-nil-rate-band
- https://www.gov.uk/guidance/trusts-and-inheritance-tax
- https://www.gov.uk/hmrc-internal-manuals/inheritance-tax-manual/ihtm47020 (IHTA 1984 s6A)
- https://www.gov.uk/individual-savings-accounts and https://www.gov.uk/individual-savings-accounts/how-isas-work
- https://www.gov.uk/junior-individual-savings-accounts
- https://www.legislation.gov.uk/uksi/2024/350/contents/made. The contents page was fetched; the regulation text itself was not retrieved. The Junior ISA and Lifetime ISA exclusion is taken from gov.uk, as quoted above.
- https://www.gov.uk/tax-on-your-private-pension/annual-allowance
- https://www.gov.uk/new-state-pension/what-youll-get and https://www.gov.uk/workplace-pensions/types-of-workplace-pension. These were cited but **not fetched in this audit**; treat them as pointers to verify.
- TaxConfigService keys, from `database/seeders/TaxConfigurationSeeder.php` (2026/27 inherits the 2025/26 block; `ACTIVE_TAX_YEAR` at `:19`).

Not browser-tested. I COULD NOT TEST THIS in Playwright. The audit is static code reading only, as dispatched (read-only).
