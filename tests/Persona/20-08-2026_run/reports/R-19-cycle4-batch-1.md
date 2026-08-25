# R-19 — Cycle 4, batch 1

**Agent:** `peak-earners-c4` (persona-tester) · **Persona:** `peak_earners` (David & Sarah Jones)
**Surface:** web, local (`localhost:8000`) · **Accounts:** David (user 16) and Sarah (user 17), both premium, spouse link reciprocal, `SpousePermission` accepted both ways
**Started:** 2026-08-22 ~17:39 · **Batch closed:** ~18:11
**Entry route:** none — cycle 4 is a display/regression pass over the household as cycles 1–3 left it.

---

## Done

### State recovery
Recovered the run from disk before touching anything: `tests/Persona/peak_earners.md`,
`20-08-2026_run/{RUN-LOG,RUN-STATE-2026-08-21,COORDINATOR-HANDOVER,PASS-PLAYBOOK}.md`,
reports R-01…R-18, `.remember/today-2026-08-22.md` (the only place the cycle 1–3 history
lives), `workforce/branches/fixes/F-0018…F-0021`, and 22 board items. `git log` on `dev`.
Cycle 1–3 state as reported by the coordinator was confirmed accurate in every particular.

### Browser work
Logged in as David (MFA code from the database), swept dashboard → Wealth Summary →
Personal Valuables (incl. BMW detail, Capital Gains Tax tab, Notes tab) → Liabilities →
Bank Accounts → Investments → Property (list, Manchester detail, Mortgage tab) → the
property/mortgage entry form. Signed out, logged in as Sarah (MFA from the database),
swept dashboard → Protection → Retirement.

### The BMW PCP thread — GREEN
Cycle 4's trigger. All four checks pass.

| Check | Expected (persona) | Actual | Result |
|---|---|---|---|
| Joint 50% split | David £21,000 / full £42,000 | "Your Share £21,000 · Full Value £42,000 · 50.00%" | GREEN |
| Wasting-asset exemption | Capital Gains Tax exempt | Badge "Capital Gains Tax Exempt"; detail states "exempt from Capital Gains Tax regardless of sale price" | GREEN |
| The £23,000 loss is not allowable | No allowable loss offered | Capital Gains Tax Calculator tab offers no loss, states the exemption instead | GREEN |
| Unrealised loss shown | £42,000 − £65,000 = −£23,000 | "Unrealised Gain/Loss −£23,000" | GREEN |
| Persona note captured | "Family SUV. PCP finance ends 2025" | Description "Family SUV"; Notes tab "PCP finance ends 2025" | GREEN |
| No invented vehicle finance | Liabilities: None | `liabilities` table empty; Liabilities page lists only the 3 mortgages | GREEN |

Household chattels reconcile exactly: David £132,250, Sarah £60,750, total £193,000 —
all three match the persona to the pound.

### Also verified GREEN (no action needed)
- **Wealth Summary property**: David £755,500, Sarah £637,500, total £1,393,000. Mike
  Barrett's 60% (£177,000) correctly excluded from the household.
- **Wealth Summary investments/cash**: David £172,500 / £99,750, Sarah £132,500 / £31,030
  — every figure the persona's.
- **Bank Accounts page**: joint Nationwide shown as "£2,250" with "Total: £4,500".
- **Investments page**: joint GIA "Full Value £95,000 / Your Share (50.00%) £47,500 /
  Held with Sarah Jones"; "Current Portfolio £172,500".
- **Property detail (Manchester)**: Tenants In Common, David 40% / Mike Barrett 60%;
  rental income full £1,350, share £540; annual £16,200, share £6,480.
- **W-0186 (joint-life policy invisible to the other life assured) is GREEN.** Sarah's
  `/protection` shows "Vitality · £500,000 · Joint life with David Jones · Recorded on
  David Jones's account", and after a cache clear her Protection card reads £500,000.
  Her earlier £0 was a stale cache, not the defect — see D-04.
- **W-0010 GREEN**: Sarah has an "Add Pension" control with only a Defined Benefit scheme.
- **W-0035 partially GREEN**: a "Set your target" control now exists, and the fallback is
  disclosed in plain words ("Worked out for you from your income, because you have not
  set a target yet").
- **W-0209 GREEN**: 26 nav items resolve.
- Sarah's retirement page shows Guaranteed Retirement Income £35,000/year, NHS Pension
  £35,000, Tax-Free Lump Sum £105,000; State Pension correctly flagged OUTSTANDING.

---

## Defects found — 6

Numbered D-01…D-06 pending board IDs from the coordinator.

### D-01 (HIGH) — The mortgage liability share cannot be entered, and is hardcoded to 50%

**Expected** (`peak_earners.md`, Mortgages §3): Manchester mortgage, Ownership *Tenants in
Common (David 40%)*, Joint Owner *Mike Barrett (60%)* → David liable for £48,000 of £120,000.
**Actual**: `mortgages.id=16` holds `ownership_type='joint'`, `ownership_percentage=50.00`,
`joint_owner_name='Mike Barrett'` → £60,000.

The property section of the wizard offers Ownership Type *Individual / Joint Tenancy /
Tenants in Common / Trust* **and** a "Your Ownership Share (%)" input. The mortgage
section offers only:

- `resources/js/components/NetWorth/Property/PropertyForm.vue:696-697` — `Borrower(s)`
  with exactly two options, `individual` ("Me only") and `joint` ("Joint borrowers").
  **`tenants_in_common` is not offerable for a mortgage at all.**
- `resources/js/components/NetWorth/Property/PropertyForm.vue:1377-1386` — choosing
  "Joint borrowers" silently sets `mortgageForm.ownership_percentage = 50`. There is no
  share input anywhere in the mortgage section (the only percentage fields are the
  repayment/interest-only split and the fixed/variable rate split).

So a household cannot record that one borrower carries 40% of a loan. Every mortgage the
user marks joint is written at 50% regardless of the truth.

Screenshot: `122-web-david-mortgage-form-no-share-field.png`

### D-02 (HIGH) — One mortgage, two mechanisms, four different figures — including 40% and 50% side by side in one panel

Same login, same session, same mortgage (Manchester, £120,000):

| Surface | Shows | Implied share |
|---|---|---|
| Property **list card** | "Your mortgage liability £60,000" | 50% |
| Property **detail** header | "Your Mortgage Share (40%) £48,000" | 40% |
| Property **Mortgage tab** | "Your mortgage liability: **£60,000**" beside "Full Monthly Payment £750 · **Your Share (40%): £300**" | **both, in one panel** |
| **Liabilities** page | "Joint (40.00% yours) · Your Share £48,000" | 40% |
| **Wealth Summary** roll-up | Mortgages £182,500 (= 32,500 + 90,000 + **60,000**) | 50% |

Two mechanisms:

1. `app/Traits/CalculatesOwnershipShare.php:124-140`
   (`calculateUserMortgageAmountShare`) reads the **mortgage** row's
   `ownership_percentage` → 50%. Its own docblock at `:110-111` states the intended rule:
   *"Mortgage liability follows the mortgage borrower(s), not the ownership percentage
   recorded on the linked property."*
2. `app/Http/Controllers/Api/EstateController.php:112-113` copies the **property's**
   ownership onto the mortgage-derived liability:
   ```php
   'ownership_type' => $property->ownership_type ?? 'individual',
   'ownership_percentage' => $property->ownership_percentage ?? 100,
   ```
   → 40%, which contradicts the documented rule but happens to give the persona-correct
   number here.

Rule 20 violation. Note the consequence for cycle 3: **F-0021 measured David £182,500 /
Sarah £122,500 / household £305,000 and signed it off as correct.** The persona figures are
**£170,500 / £122,500 / £293,000**. The cycle-3 fix landed on mechanism 1, which is the one
reading the wrong row.

Also inside the same card: Manchester shows "Tenants in Common (40.00%)" and "Your Share
(40.00%) £118,000" next to a mortgage liability computed at 50%, and an Equity figure of
£58,000 (118,000 − 60,000) that would be £70,000 on the persona's numbers.

Screenshots: `120-web-david-property-manchester-40pct-owner-50pct-mortgage.png`,
`121-web-david-mortgage-panel-60000-at-50pct-beside-300-at-40pct.png`,
`117-web-david-liabilities-170500-vs-wealthsummary-182500.png`

### D-03 (MEDIUM) — "Total Balance Owed £365,000" charges the household a third party's debt

The Liabilities page footer sums full balances with no share applied:
`resources/js/components/NetWorth/LiabilitiesList.vue:195-197`

```js
totalBalance() {
  return this.filteredLiabilities.reduce((sum, l) => sum + parseFloat(l.current_balance || 0), 0);
}
```

£180,000 + £120,000 + £65,000 = £365,000. That includes **Mike Barrett's £72,000**, which
this household does not owe. David owes £170,500; the household owes £293,000. The same
page takes care to print "Total Balance" and "Your Share" separately on every row, then
throws the distinction away in the total.

**Distinct from W-0226**, which is `NetWorthService.php:132` over the `liabilities` table —
this household has zero rows there, so W-0226 cannot be producing this figure.

Screenshot: `117-web-david-liabilities-170500-vs-wealthsummary-182500.png`

### D-04 (HIGH) — The dashboard module cards are a second, wrong answer to figures the rest of the app gets right — wrong in opposite directions for the two spouses

Browser-verified on both accounts, after clearing the per-user caches so these are live
values, not stale ones.

| Card | David shows | David correct | Sarah shows | Sarah correct |
|---|---|---|---|---|
| SAVINGS | **£102,000** | £99,750 | **£28,780** | £31,030 |
| INVESTMENT | **£220,000** | £172,500 | **£85,000** | £132,500 |
| RETIREMENT | £500,000 (correct) | £500,000 | **£0 "Plan your retirement"** | she has £35,000/yr guaranteed |

Two failures, one in each direction, from the same cause — a joint record is a single row
(Rule 6) with `user_id` = the recording spouse:

- **Fraction**: `app/Services/Investment/PortfolioAnalyzer.php:17-20` —
  `calculateTotalValue()` is `$accounts->sum('current_value')`, no share applied. Same in
  `app/Agents/SavingsAgent.php:83` — `$accounts->sum('current_balance')`. So David is
  charged 100% of the joint GIA (+£47,500) and the joint current account (+£2,250).
- **Reach**: `app/Agents/InvestmentAgent.php:72` —
  `InvestmentAccount::where('user_id', $userId)`, no `orWhere('joint_owner_id', …)`. Same
  in `app/Agents/SavingsAgent.php:80` via `User::savingsAccounts()`
  (`app/Models/User.php:732-735`), a `hasMany` on `user_id` alone. So Sarah cannot see the
  joint GIA or the joint current account at all (−£47,500, −£2,250).

The contradiction is inside a **single API response**. `/api/v1/mobile/dashboard` for
Sarah returns, in one payload:

```
net_worth.breakdown.assets.savings      = 31030      modules.savings.total_savings   = 0
net_worth.breakdown.assets.investments  = 132500     modules.investment.portfolio_value = 0
```

(zeros there were the stale cache; live they are 28,780 and 85,000 — still wrong, still
contradicting the `net_worth` half of the same response, which is right.)

**Both surfaces, one fix (Rule 19/20):** the web dashboard and the `/m` dashboard both
consume `/api/v1/mobile/dashboard` →
`app/Services/Mobile/MobileDashboardAggregator.php:200` (`extractSavingsSummary`) and
`:224` (`extractInvestmentSummary`), which pass the agents' totals straight through.
`resources/js/views/GamifiedDashboard.vue:343-355` renders them.

The RETIREMENT £0 is the same family: Sarah's `/net-worth/retirement` states "Guaranteed
Retirement Income · Total Annual Income £35,000/year", while the dashboard card tells her
"£0 · Plan your retirement". A Defined Benefit-only member has no capital value, which is
why the Wealth Summary correctly says "(not incl. Defined Benefit pensions)" — but the
card's fallback (`GamifiedDashboard.vue:336-340`) reads `pot_value` only, so her entire
pension provision disappears from the one retirement signal on her dashboard.

Screenshots: `114-web-david-dashboard-cycle4.png`,
`124-web-sarah-dashboard-savings-28780-invest-85000-retirement-zero.png`,
`115-web-david-wealth-summary-cycle4.png`, `118-web-david-cash-99750-correct.png`,
`119-web-david-investments-172500-vs-dashboard-220000.png`,
`125-web-sarah-retirement-35000-guaranteed-vs-dashboard-zero.png`

### D-05 (HIGH) — A 24-hour dashboard cache served a wrong dashboard for ~21 hours and hid a fix that shipped the same morning

`app/Services/Mobile/MobileDashboardAggregator.php:37`

```php
private const CACHE_TTL = 86400; // 24 hours — invalidated on data change
```

The comment's promise did not hold. Captured before I cleared anything:

| user | `cached_at` | `modules.savings` | `net_worth…savings` | `modules.investment` | `net_worth…investments` |
|---|---|---|---|---|---|
| 16 David | 2026-08-21T21:53:18+01:00 | 102,000 | 99,750 | 220,000 | 172,500 |
| 17 Sarah | 2026-08-21T21:59:10+01:00 | **0** | 31,030 | **0** | 132,500 |

Sarah's records were all created **2026-08-20 22:09–22:36** — a full day *before* that
cache was written, so the zeros were not "no data yet". Both halves of each blob were
computed in the same call, at the same instant, and disagree.

Consequences seen today:
- Sarah's dashboard read **£0 savings, £0 investments, £0 pension, £0 protection** while
  the same response knew she had £31,030 and £132,500.
- **W-0186's fix, merged this morning, was invisible to her** — her Protection card stayed
  at £0 until the cache was cleared, then immediately read £500,000.

Every agent analysis is cached the same way and for the same 24 hours
(`app/Agents/BaseAgent.php:21` → `TaxDefaults::CACHE_TTL_STANDARD = 86400`, keys
`savings_analysis_{id}`, `investment_analysis_{id}`, `protection_analysis_{id}`,
`retirement_analysis_{id}`, `estate_analysis_{id}`), so the staleness is layered.

Evidence file: `.playwright-mcp/sarah-dashboard.json` (the pre-clear payload).
Screenshot: `123-web-sarah-dashboard-all-modules-zero.png`

### D-06 (LOW) — A mortgage can be deleted but never edited

On the property detail's Mortgage tab the mortgage card offers **Delete** only; the
property above it offers Edit and Delete. The full button set on that view is
`Back to Properties | Edit | Delete | Overview | Mortgage | Financials | Add Mortgage |
Delete`. To correct a lender, balance, rate or borrower the user must delete the mortgage
and re-enter it through the property wizard, losing its history.

Screenshot: `121-web-david-mortgage-panel-60000-at-50pct-beside-300-at-40pct.png`

---

## Not done, and why

- **`/m` not yet swept this cycle.** D-04 is a shared-backend defect that will reproduce
  there; I will confirm on csjones once the batch lands rather than duplicate the finding.
- **iOS** — out of scope locally by construction (reads the csjones staging database).
- **Fyn passes (B and C)** — not part of cycle 4's remit.
- **Entry-side re-tests deferred to batch 2**: the missing 10 holdings (W-0039/W-0009 both
  at `handoff`), the missing state pension for both users, the four missing children's
  bequests, the missing "Charlotte's Gap Year Fund" goal, adviser fee 0.75% on four
  accounts, David's risk profile (Upper Medium vs the `medium` stored), and Sarah's will
  naming herself as her own executor (the W-0024 shape). Each needs the form driven, not
  the row read.
- **Not raised, correctly**: W-0037 and W-0038 are still `queued`, so the bequest
  priority/type gaps and the goal essential/joint gaps are known-open, not new.

## Assumptions

- `tests/Persona/peak_earners.md` is the sole source of truth (CSJ ruling, 2026-08-21).
  The PDF was not opened.
- Cycle 4 runs against the household as cycles 1–3 left it. No teardown, no re-entry —
  this is a regression pass, not a fresh entry pass.
- Expenditure headline is the £2,450 category sum, per `PASS-PLAYBOOK.md`.
- I treated the persona's "Liabilities: None" as consistent with the BMW note, since the
  PCP ended in 2025 and today is 2026-08-22.

## Needs

- **Board IDs** for D-01…D-06, and dispatch to `build-lead`. D-01 and D-02 are one
  workstream and should go to one agent, cause before symptom (D-01 is why the row is
  wrong; D-02 is why two pages disagree about it regardless).
- **A decision I cannot take**: D-02 exposes a rule conflict. `CalculatesOwnershipShare`
  documents "mortgage liability follows the borrower, not the property". If that is the
  rule, then the Liabilities page and the property detail are wrong (and only look right
  here by accident), and D-01 must be built so the borrower split is enterable. If instead
  liability should follow the property share, the docblock and mechanism 1 are wrong. Both
  cannot stand. I recommend the docblock's rule — a spouse solely named on the mortgage of
  a jointly-owned home is a common case the property share cannot express — but the choice
  is CSJ's, not mine.

## Noticed

- **I cleared six per-user cache keys** (`mobile_dashboard_`, and the five
  `*_analysis_` keys, for users 16 and 17 only) to get a true reading behind D-04. I
  captured the stale payloads first — they are D-05's evidence. No global `cache:clear`,
  no data was written, nothing else in the shared environment was touched.
- **Two of my pre-pass leads were my error and were retracted**: `chattels` 19/20 and
  `savings_accounts` 29 are all soft-deleted, so there is no probe contamination and no
  duplicate joint record. My first queries did not filter `deleted_at`.
- **Playwright tab wedged** at the start of the run: the original tab evaluated JavaScript
  fine but silently swallowed every click, keypress and screenshot (screenshots timed out
  at 5s after "fonts loaded"). Opening a new tab and closing the old one fixed it
  completely. Worth knowing — it looks exactly like an app defect and is not one.
- `dc_pensions.annual_allowance_used_gbp = 38.67` on a £23,200 contribution against a
  £60,000 allowance — that is 38.67 **percent** stored in a column named `_gbp`. Not yet
  traced to a user-facing figure; flagged for batch 2.
