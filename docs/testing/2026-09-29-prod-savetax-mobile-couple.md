# Production SaveTax campaign run, mobile (/m), married couple — 29 September 2026

**Tester:** Claude (driven by Brett Isenberg), built-in browser at 375 x 812 (mobile preset), fynla.org production.
**Accounts (please purge after review):**
- `isenbret+savetax2909@gmail.com` — "Sam Taylor", registered via the SaveTax funnel, Free tier.
- `isenbret+savetax2909spouse@gmail.com` — "Alex Taylor", registered from Sam's spouse invitation, Free tier.

Brett completed both registrations and codes by hand; everything else was driven by Claude. No code changes were made.

**Update, 29 September 2026:** C1 is fixed in [Stoff73/fynla#969](https://github.com/Stoff73/fynla/pull/969) and H1 in [Stoff73/fynla#961](https://github.com/Stoff73/fynla/pull/961). Both are open against `dev` and not yet on production. See [Fixes since the run](#fixes-since-the-run).

## Verdict

The primary user's journey is strong. Five taps into the questionnaire, about seven minutes of Fyn-led capture, every figure saved correctly, and a plan worth £7,212 a year whose four actions all check out arithmetically. A follow-up question to Fyn about the real cost of the pension contribution and splitting it with the spouse got a correct, useful answer. This is genuinely valuable to a higher-rate earner.

The spouse journey is where it breaks. The invitation links the household correctly and the spouse lands with the shared home, pension and ISA already on the dashboard, which is impressive. But when the spouse then confirms their own salary in onboarding, it is added to the figure the primary user entered for them, so a £32,000 basic-rate teacher is stored and taxed as a £64,000 higher-rate earner. The spouse's plan then recommends a £4,680 higher-rate pension saving that does not exist. That is wrong tax guidance shown to a real user, and it happens on the exact path the campaign's "invite your spouse" step sends people down.

## The household

| | Sam (primary) | Alex (spouse) |
|---|---|---|
| Work | Project Engineer, Brightwell Engineering Ltd, £72,000 | Teacher, Harbour Lane Primary School, £32,000 |
| Born | 12 April 1982 | 3 September 1985 |
| ISA | Vanguard S&S ISA £18,000 (£4,000 paid this year); Nationwide Cash ISA £6,000 at 4.1% (£2,000 paid) | Hargreaves Lansdown ISA £9,000 (entered by Sam) |
| Cash | Chase easy access £15,000 at 4.5% | Monzo easy access £4,000 at 3.5% (entered by Alex) |
| Pension | Aviva workplace £85,000, 5% + 5%, not salary sacrifice | Nest £41,000, £1,600 a year (entered by Sam) |
| Property | Home £450,000, mortgage £220,000, joint 50/50 | same record |
| Other | Gift Aid £50 a month | none |

## Sam's plan — every figure checked

| Action | Shown | Check |
|---|---|---|
| Pay more into pension | £17,300, saves £6,920 | £72,000 − £3,600 pension − £750 grossed Gift Aid − £50,270 ≈ £17,380 at 40% |
| Gift Aid reclaim | £150 | £600 net = £750 gross × 20% |
| Salary sacrifice | £72 | £3,600 × 2% NI above the upper earnings limit |
| Wrap cash in ISA | £3,889, saves £70 | £675 interest − £500 PSA = £175; £175 / 4.5% = £3,889; £175 × 40% = £70 |
| Total | £7,212 | sums correctly |

Dashboard: assets £349,000 (£21,000 cash + £18,000 ISA + £225,000 home share + £85,000 pension), liabilities £110,000, net worth £239,000. Correct.
Alex's dashboard: assets £275,000 (£225,000 + £41,000 + £9,000), net worth £165,000. Correct.

## Defects

### Critical

| # | Where | What happened | Evidence |
|---|---|---|---|
| C1 | Spouse onboarding (Fyn), income | Alex entered £32,000; Fyn replied "Got it — £64,000 a year, noted." The £32,000 Sam entered for Alex and Alex's own £32,000 were summed. Stored and taxed as £64,000. Alex's plan then says "Pay £11,700 more into your pension and save £4,680 in tax" at 40% relief; Alex is a basic-rate taxpayer. The Savings Allowance shows £500 (higher-rate) instead of £1,000. Sam's figures were not affected. | `GET /api/user/profile` as Alex: `income_occupation.annual_employment_income = 64000.00`, `income_tax = 13032`, `adjusted_net_income = 62000.05`. `/m/app/income` shows "Your total annual income £64,000". Likely mechanism: `EmploymentIncomeService::sameRole()` treats a blank *incoming* employer as a match but not a blank *stored* one, so the placeholder job created from Sam's spouse figures and Alex's named job are two rows that `syncTotals()` sums (unverified in the DB). **Confirmed and fixed in [#969](https://github.com/Stoff73/fynla/pull/969)**, not yet deployed. |

### High

| # | Where | What happened | Evidence |
|---|---|---|---|
| H1 | `/m` sign out, then invite link | After Sam signed out on `/m`, opening the spouse invite link `/register?invite=…` landed on "Sign in — Welcome back". The desktop SPA inside the `/m` frame still held Sam's `sessionStorage.auth_token`, treated the user as signed in, bounced the guest-only `/register` towards the dashboard, which handed off to `/m/app`, which (correctly logged out) showed the mobile login. The token itself is revoked server-side (`GET /api/auth/user` → 401), so this is not a security hole. Clearing the stale token made the invite page render correctly. Affects anyone who signs out on `/m` and then taps "Create an account" or opens an invite on the same phone. | Frame `sessionStorage` keys after sign-out: `["auth_token"]`; `resources/js/router/index.js` handoff guard at ~1658. **Fixed in [#961](https://github.com/Stoff73/fynla/pull/961)**, not yet deployed. |
| H2 | `fynla.org/m/savetax` | 404 "Oh no, we messed up!". The working mobile entry is `/savetax`, which redirects to `/m?to=/savetax`. | Direct navigation. |

### Medium

| # | Where | What happened |
|---|---|---|
| M1 | Spouse onboarding | Fyn re-asks what it already knows through the household link: the spouse's earnings band, "Does your spouse work?", and Sam's income by hand — although Alex's profile API already returns Sam's £72,000 and employer via the link. The own-income form is also blank despite Sam having entered Alex's £32,000. |
| M2 | Spouse Tax Strategy | "Your spouse's allowances" all show "Current-year use not confirmed", including Pension Annual Allowance, though Sam's linked account has £7,200 used. Same on Sam's side for Alex's pension (£1,600 a year entered). |
| M3 | Dashboard Investment card | "0 accounts, £0, Add your investments" for both users, although the Investments page shows the £18,000 and £9,000 ISAs. |
| M4 | Fyn multi-select ("Which of these do you have? Tap each one") | Each tap is a round trip; tapping several quickly kept only the first (Bank account) and dropped ISA, Pension and Property, so those capture steps were skipped. |
| M5 | Campaign results page | Headline "An average estimated saving of up to £4,000" mixes two claims, says it is "bigger because of you and your partner" though the £4,000 is only the user's pension relief, and does not match the £7,212 plan. The spouse rows say "Available to you". |
| M6 | Dashboard after the plan | Returning to the dashboard reopens Fyn full-screen and the previous conversation (the pension question) is gone. |

### Low

| # | Where | What happened |
|---|---|---|
| L1 | Investments page | "2 of 2 accounts used" beside "Across 1 account" — the Cash ISA counts against the investment cap. |
| L2 | Bank Accounts actions | "Consider a Cash ISA — open a Cash ISA" when the user already has one with £14,000 of allowance left. |
| L3 | Property card | Shows £225,000 without saying it is the user's 50% share of £450,000. |
| L4 | Invite email | Greeting is "Hello," although Fyn asked for Alex's first name. |
| L5 | Dashboard | "Estate Planning — Warning" as a bare word. |
| L6 | Tax Strategy ISA action | "£675.00" and "£175.00" in pence; everything else is whole pounds. |
| L7 | Fyn capture forms | `.m-field` has no focus style, so the browser default outline shows. |
| L8 | Fyn capture forms | Ownership radio label `for="fyn-form-easy_access-ownership_type"` points at no element. |
| L9 | Campaign questionnaire | Step counter changes from "3 of 4" to "4 of 5" after answering Yes to spouse. |
| L10 | Campaign results page | Unicode ✓ and – used as allowance markers (Rule 15, unless approved). |

## Fixes since the run

### C1: the spouse's own salary was added to the figure the inviter gave

**Status:** fixed in [Stoff73/fynla#969](https://github.com/Stoff73/fynla/pull/969), branch `fix/spouse-income-doubling`, open against `dev`. Not yet on csjones or production.

**Cause.** The mechanism suspected in the defect row was right. When Alex's account linked, `SpouseHoldingTransfer::transfer()` (`app/Services/Onboarding/SpouseHoldingTransfer.php:48-50`) copied the £32,000 Sam gave for Alex across as a job with no employer or role. When Alex then gave Fyn an employer, role and £32,000, `EmploymentIncomeService::sameRole()` (`app/Services/Income/EmploymentIncomeService.php:124`) did not match the blank employer already on file. `recordJob()` therefore added a second job, and `syncTotals()` summed the two to £64,000.

**Fix (design agreed with Brett, 29 September 2026):**

1. A new column, `employments.is_estimate`, marks a job someone else told us about. `SpouseHoldingTransfer` marks the copied salary as an estimate.
2. The first job the spouse states themselves replaces the estimate, whatever figure they give: £34,500 replaces the inviter's £32,000, and a switch to self-employment moves the job to the self-employed total. Later jobs add up as before. A spouse editing the job in the edit form also confirms it.
3. The job-matching rule in `sameRole()` is unchanged on purpose. Matching a blank employer on file everywhere would bring back the older bug where a second onboarding job overwrote the first salary.

Accounts already stored with a doubled salary are not repaired (fix forward only, agreed 29 September 2026). Alex's account (`isenbret+savetax2909spouse@gmail.com`) still shows £64,000 until it is purged.

**Verification:**

| Check | Result |
|---|---|
| New feature test replaying the run: link, then the spouse's own job at £32,000 | £64,000 without the fix; £32,000 and one job with it |
| 7 new unit tests (same figure, different figure, no employer, second job still counts, employer-only keeps the estimate, move to self-employment, edit confirms) | All pass |
| Onboarding, income and architecture suites | 1,479 passed, 1 skipped |
| Local: inviter and spouse linked, then the spouse's `capture_work_details` (Harbour Lane Primary School, Teacher, £32,000) | One job, £32,000, no longer marked as an estimate |
| Web, `/valuable-info?section=income`, as the spouse | Total Annual Income £32,000, taxed at the basic rate only (£19,430 at 20% = £3,886) |
| `/m`, `/m/app/income`, opened from the menu | "Your total annual income £32,000", one job "Harbour Lane Primary School · Teacher" |

I COULD NOT TEST THIS: a live Fyn conversation. The spouse's turn was the exact save call Fyn makes when the spouse gives their job, sent directly.

**Still to do:** re-run the spouse onboarding on production once #969 is released, with a new couple, to confirm Fyn reports the spouse's real salary and the spouse's plan uses basic-rate relief.

### H1: `/m` sign-out left the framed desktop app signed in

**Status:** fixed in [Stoff73/fynla#961](https://github.com/Stoff73/fynla/pull/961), branch `fix/m-logout-clears-framed-desktop-token`, open against `dev`. Not yet on csjones or production.

**Cause.** Signing out of `/m` cleared only the `/m` token (`localStorage.m_scaffold_token`, `resources/mobile/store.js` `logout()`). The desktop app in the frame keeps its own copy in the same tab (`sessionStorage.auth_token`, `resources/js/services/tokenStorage.js`), and that copy survived. The desktop app counts anyone holding a token as signed in (`resources/js/store/modules/auth.js:22`, `isAuthenticated: !!state.token`), even when the server has revoked it. So `/register` sent the invitee away.

**Fix, in two layers:**

1. Signing out of `/m` now clears the desktop token too (`resources/mobile/store.js`). This covers the menu's Sign out, the dashboard's sign-out and the session-expiry path.
2. On sign-in-only pages such as `/register`, and on public pages, the desktop app now checks a stored token with the server before redirecting on it (`resources/js/router/storedSessionPolicy.js`, called from `resources/js/router/index.js`). It checks once per token per page load. A 401 or 419 drops the token and the page renders normally; a network error keeps the old behaviour. To support this, `resources/js/services/api.js` now passes the 401 status through on the `/auth/user` sign-in check.

**Verification** (local server from the fix branch, built bundles, 375 x 812):

| Step | Result |
|---|---|
| Signed in through the framed desktop login on `/m`, then handed off to `/m/app/dashboard` | Desktop token and `/m` token both set |
| Tapped Sign out in the `/m` menu | `/m` login shown; `m_scaffold_token` and `auth_token` both empty (before the fix, `auth_token` stayed) |
| Tapped "Create an account" | Registration form shown (first name, last name, email, password) |
| Planted a revoked desktop token (server returns 401), opened `/register?invite=…` | Token dropped; registration form shown |
| Planted a valid desktop token, opened `/register?invite=…` | Still sent to `/m/app/dashboard` ("Good morning, John"), so signed-in users are unaffected |

Automated tests: 7 new Vitest tests (`resources/js/__tests__/storedSessionPolicy.spec.js`, `resources/mobile/__tests__/logoutClearsFramedDesktop.spec.js`). The `/m` test fails on the old code. The full frontend suite passes: 166 files, 1481 tests.

**Still to do:** re-run the spouse invite step on production once #961 is released, to confirm the invitee lands on the registration form after the primary user signs out on the same phone.

## What works well

- The funnel on a phone: five taps, clear copy, correct allowance logic (Marriage Allowance correctly unavailable to a higher-rate earner).
- Fyn carries the questionnaire into onboarding, and the "check this page, does it look right?" loop after each section is reassuring.
- Joint property handled as one record with a 50% share on both dashboards.
- Free-tier caps are explained politely at the point they bite.
- The invite email is clear on consent, and the household link populates the spouse's dashboard immediately.
- Fyn's free-text answer on net cost and splitting contributions was accurate and genuinely helpful.

## Not tested

- Signing back in as Sam after Alex joined, to see whether Sam's plan switches to Alex's real figures (needs Brett to sign in).
- Native iOS.
