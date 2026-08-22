# Pass playbook — `peak_earners` (David & Sarah Jones)

**Written:** 2026-08-21 by persona-tester (replacement agent, post-R-08 handover)
**Purpose:** a screen-by-screen script for the clean re-run, so the tester derives
nothing at the keyboard. Entry values, expected displayed values, per-account
visibility, and a regression check for all 24 board items are all precomputed here.

**Contract:** `tests/Persona/peak_earners.md`. Where this playbook and that file
disagree, the persona file wins and this file is wrong. The persona file is never
edited to make a check pass.

---

## 0. How to use this

### 0.1 Standing rules for the whole run

| | |
|---|---|
| Driver | **Playwright MCP, visible desktop Chrome.** Never headless, never a background context. Real pointer clicks — the previous run used dispatched DOM clicks and could not see overlay / z-index / pointer-events / disabled-state defects. Closing that blind spot is a purpose of the re-run. |
| Verification standard | Every write confirmed against the MySQL row via `php artisan tinker`, never against the screen alone. Every display figure hand-recomputed against §2 of this file. |
| MFA | Fetch the code from the DB yourself, never ask CSJ. Enter the six digits **one box at a time with ~300 ms gaps** — setting all six at once is rejected. |
| Expenditure fields | Set with ~400 ms gaps and focus/blur. A rapid synchronous loop races the component and silently drops categories (a cleared false positive from the last run — do not re-raise it as a defect). |
| Screenshots | Continue the existing sequence in `pass-a-web/` from **18**. Naming `NN-<surface>-<account>-<what>`. Capture every form before submit, every form after submit, every data view, every Fyn turn that writes. |
| Reports | `R-NN` from **R-09**, indexed in `RUN-LOG.md`. One per pass start, pass end, defect, fix attempt, re-check, loop iteration, retry, and environment move. |
| Next board id | **W-0041.** The board is shared — other agents are allocating IDs concurrently, so check `ls workforce/ops/board/` immediately before claiming one. (W-0019 is CSJ's mirror-wills direction, not a run defect.) |
| Never | Provision tiers/subscriptions/entitlements yourself · edit `.env` · patch a DB row to make a check pass · run any table-dropping migration command · point a run at `fynla.org`. |

### 0.2 Preconditions before Pass A starts

1. **Teardown is the coordinator's, not yours.** David Jones (16) and Sarah Jones (17)
   are the fix batches' reproduction data. Do not delete them. Ask the coordinator to
   force-delete by exact email and confirm zero remaining rows for both users.
2. **Premium must already be provisioned on both accounts** by the coordinator, using
   the app's own test-support shape (`users.plan='premium'`, `users.tier='premium'`,
   plus an active `Subscription` row — mirrors
   `app/Http/Controllers/TestSupport/E2EController.php:163-176`).

   **Exactly what free tier blocks, read live from `TierConfigurationStore` on
   2026-08-21** — this is the precise list, not an estimate:

   | Capability | Free | Premium |
   |---|---|---|
   | `property` · `investment` · `goals` · `life_events` · `savings_account` · `pension_account` | **limited** (capped count) | full |
   | `estate` | **teaser** | full |
   | `expenditure_detailed` | **none** | full |
   | `letter_to_spouse` | **none** | full |
   | `joint_household_view` | **none** | full |
   | `holistic_plan` · `what_if` · `retirement_decumulation` | **none** | full |
   | `document_upload` · `statement_upload` | **none** | full |
   | `investments_exotic` (Venture Capital Trust, Enterprise Investment Scheme) | **none** | full |
   | `investment_cost_analysis` · `property_buy_to_let_analysis` | **none** | full |
   | `advisor_export` | **none** | full |
   | `protection` · `chattels` · `income` · `expenditure` · `liabilities` · `risk_profile` · `tax_strategy` · `family_module` · `dashboard` · `future_value_projections` | full | full |

   So at free tier this persona loses: all three properties beyond the cap, the
   Venture Capital Trust holding outright (`investments_exotic: none`), all six
   bequests and both wills and the trust (`estate: teaser`), the letter to loved ones,
   the 15 detailed expenditure categories, the Buy-to-Let analysis on two properties,
   and the whole joint household view — which is the run's entire purpose.

   **Reading the modes correctly matters.** `TeaserGate::allows()` returns true only
   for `full`, so it returns **false** for a `limited` capability even when the user is
   nowhere near the cap. `allows() === false` means "not unlimited", **not** "cannot
   add". And `capabilityFor()` returns `'none'` for any key absent from the matrix —
   a bogus key returns `none` too — so a `none` reading is only meaningful for keys
   that are actually in the matrix above.
3. **The fix batches' surfaces must have landed**, or a red result on those surfaces
   is a phantom, not a defect.
4. `php artisan db:seed` after anything that disturbed local data.

### 0.3 Emails

Use the persona's own addresses so the DB queries in this file work verbatim:

- David: `david.jones@example.com`
- Sarah: `sarah.jones@example.com`

---

## 1. Entry map — Pass A (desktop web module UI forms)

Order matters: later screens depend on earlier records existing. Do not reorder.

### 1.0 Household setup (both accounts, before any financial data)

| # | Screen | Fields, in order | Values |
|---|---|---|---|
| 1 | `/register` | Register David | `david.jones@example.com` |
| 2 | MFA | Six digits from DB, one box at a time | — |
| 3 | `/settings/personal` → **Edit** | first_name, surname, date_of_birth, gender, marital_status, employment_status, employer, occupation, target_retirement_age, address_line_1, address_line_2, city, county, postcode, country_of_birth | David, Jones, 1976-11-08, Male, Married, Employed, Global Finance Corp, Finance Director, 60, "The Willows, 15 Chestnut Lane", —, Guildford, Surrey, GU1 4RH, United Kingdom |
| 4 | `/settings/health` → **Edit** | health_status, smoking_status, education_level | Yes (good health), Never smoked, Postgraduate Degree |
| 5 | `/valuable-info?section=income` → **Edit** | annual_employment_income | 145000 |
| 6 | `/settings/family` → **Add** ×2 | first_name, last_name, relationship, date_of_birth, gender, is_dependent, education_status | William / Jones / Child / 2007-09-15 / Male / Yes / Year 13, private school · Charlotte / Jones / Child / 2010-02-28 / Female / Yes / Year 10, private school |
| 7 | Register Sarah in a second browser context | `sarah.jones@example.com` | — |
| 8 | Sarah `/settings/personal` | as above | Sarah, Jones, 1978-04-22, Female, Married, Employed, Surrey NHS Trust, GP Partner, 60, same address |
| 9 | Sarah `/settings/health` | health_status, smoking_status, education_level | Yes, Never smoked, Postgraduate Degree |
| 10 | Sarah `/valuable-info?section=income` | annual_employment_income | 120000 |
| 11 | `/settings/family` → spouse link | Link David to Sarah | reciprocal `users.spouse_id`; reciprocal `FamilyMember` |
| 12 | Spouse data sharing (`SpouseDataSharing.vue`, on `/settings/family`) | **Accept from BOTH sides** — see note | `SpousePermission` accepted in both directions |

> **Correction, verified 2026-08-21 (R-11).** There are two linking paths and they
> behave differently. If you add the spouse from `/settings/family` → Add → Spouse
> **with their email**, the app *creates* their account and `SpousePermission` is
> **auto-accepted in both directions** — there is no manual accept step, and
> `users.spouse_id` plus the reciprocal `FamilyMember` are all set in one action.
> The manual accept-from-both-sides step applies only when linking an account that
> already exists. Verify which path you took before hunting for a missing accept.
>
> **Do not add the spouse during onboarding step 2** — that form has no email field,
> creates an unlinkable orphan that cannot be edited or deleted, and leaves a
> permanent duplicate once you link properly (W-0051).

**Gate:** nothing joint renders until step 12 is accepted both ways. Confirm before
continuing:

```
php artisan tinker --execute='$d=App\Models\User::where("email","david.jones@example.com")->first(); $s=App\Models\User::where("email","sarah.jones@example.com")->first(); echo "david={$d->id} spouse={$d->spouse_id} / sarah={$s->id} spouse={$s->spouse_id}\n"; echo App\Models\SpousePermission::whereIn("user_id",[$d->id,$s->id])->get(["user_id","spouse_id","status"])->toJson();'
```

### 1.1 Properties and mortgages — `/net-worth/property` → **Add Property**

One wizard covers property + mortgage + monthly costs + rental. Enter as **David**;
Sarah sees them through the joint link.

**Property 1 — The Willows (main residence, joint 50%)**

| Step | Field | Value |
|---|---|---|
| 1 | property_type | Main Residence |
| 1 | address_line_1 / city / county / postcode | 15 Chestnut Lane / Guildford / Surrey / GU1 4RH |
| 1 | purchase_date / purchase_price | 2012-04-01 / 625000 |
| 1 | Current Value (£) | 850000 |
| 1 | tenure_type | Freehold |
| 1 | ownership_type | **Joint Tenancy** → joint owner = Sarah Jones |
| 1 | bedrooms / council tax band | 5 / G |
| 1 | "This property has a mortgage" | tick |
| 2 | Lender Name | HSBC |
| 2 | mortgage_type | Repayment |
| 2 | original_loan_amount | 450000 |
| 2 | Outstanding Balance (£) | 65000 |
| 2 | interest_rate | 4.29 |
| 2 | rate_type | Fixed |
| 2 | rate_fix_end_date | 2027-04-01 |
| 2 | Monthly Payment (£) | 550 |
| 2 | maturity_date | **2039-08-20** — see note below |
| 2 | Borrower(s) | Joint, Sarah Jones |
| 3 | monthly_council_tax / gas / electricity / water / building_insurance / contents_insurance / maintenance | 320 / 95 / 70 / 40 / 45 / 30 / 100 |

> **Wizard mechanics, verified live 2026-08-21.** The Add Property wizard is
> **2 steps for a main or secondary residence** (1 Basic Info, 2 Costs) and
> **3 steps for a Buy-to-Let** (a "BTL Details" step appears with Monthly Rental
> Income, Tenant Name, Tenant Email, Lease Start/End and Managing Agent). The final
> button is labelled **"Save Property"**, not "Save" — an exact-match locator on
> "Save" silently stalls on the Costs step. The modal is `fixed inset-0` and covers
> the page beneath it, so a page-level read while it is open returns the background,
> not the form.

> **Maturity date note (a decision already taken — do not re-litigate).** The persona
> gives "Remaining Term 156 months"; the wizard offers a date, not a term. 156 months
> from the run date 2026-08-20 is **2039-08-20**. W-0012 is written on that basis.
> If the run date shifts, recompute: `run_date + 156 months`.

**Property 2 — City Centre Flat (Buy-to-Let, joint 50%)**

| Step | Field | Value |
|---|---|---|
| 1 | property_type | Buy-to-Let |
| 1 | address | Flat 42, Riverside Apartments / London / — / SE1 8XX |
| 1 | purchase_date / purchase_price / Current Value | 2018-10-15 / 340000 / 425000 |
| 1 | ownership_type | Joint Tenancy → Sarah Jones |
| 1 | bedrooms | 2 |
| 2 | Lender / type / original / outstanding | Barclays / **Interest Only** / 272000 / 180000 |
| 2 | interest_rate / rate_type | 5.19 / **Tracker** |
| 2 | Monthly Payment | 650 |
| 2 | maturity_date | **2041-08-20** (180 months from 2026-08-20) |
| 2 | Borrower(s) | Joint, Sarah Jones |
| 3 | building_insurance / service charge / maintenance / other | 35 / 285 / 100 / 150 |
| 3 | monthly_rental_income | **1800** (enter 100% — the form splits it) |
| 3 | tenant / lease | Mr. & Mrs. Johnson / 2024-03-01 to 2025-02-28 |

**Property 3 — Manchester Investment Property (Buy-to-Let, tenants in common, David 40%)**

*This is the run's most important single record — the only third-party co-ownership
in the persona, and the one the whole ownership question turns on.*

| Step | Field | Value |
|---|---|---|
| 1 | property_type | Buy-to-Let |
| 1 | address | Unit 12, Victoria Mill, Ancoats / Manchester / — / M4 6AG |
| 1 | purchase_date / purchase_price / Current Value | 2021-09-15 / 240000 / 295000 |
| 1 | ownership_type | **Tenants in Common** |
| 1 | ownership_percentage | **40** |
| 1 | Co-Owner | **not the spouse** — free-text Co-Owner Name = `Mike Barrett` |
| 1 | bedrooms | 2 |
| 2 | Lender / type / original / outstanding | NatWest / Repayment / 168000 / 120000 |
| 2 | interest_rate / rate_type / rate_fix_end_date | 5.49 / Fixed / 2026-09-15 |
| 2 | Monthly Payment | 750 |
| 2 | maturity_date | **2044-08-20** (216 months from 2026-08-20) |
| 2 | Borrower(s) | Joint borrower name = `Mike Barrett` |
| 3 | building_insurance / service charge / maintenance / other | 28 / 195 / 85 / 120 |
| 3 | monthly_rental_income | **1350** (enter 100%) |
| 3 | tenant / lease | Ms. Rachel Green / 2024-06-01 to 2025-05-31 |

### 1.2 Savings — `/net-worth/cash`

Entered as the stated owner. `SaveAccountModal.vue` fields.

| # | Owner | Institution | Product Type | Balance | Interest | ISA fields | Ownership Type |
|---|---|---|---|---|---|---|---|
| 1 | David | HSBC | Current Account | 25000 | — | — | Individual |
| 2 | Sarah | Barclays | Current Account | 6280 | — | — | Individual |
| 3 | David | Nationwide | Current Account | **4500** (full balance) | — | — | **Joint Owner → Sarah** |
| 4 | David | Nationwide | Cash ISA | 22500 | 4.25 | Already Subscribed This Tax Year = **10000** | Individual |
| 5 | Sarah | Nationwide | Cash ISA | 22500 | 4.25 | Already Subscribed This Tax Year = **10000** | Individual |
| 6 | David | NS&I | Premium Bonds | **50000** (full balance) | — | — | **Joint Owner → Sarah** |

> Rule 6: joint accounts are ONE row holding the FULL balance, with `joint_owner_id`
> and `ownership_percentage = 50`. Never two rows.
> Joint ISAs do not exist in UK law — accounts 4 and 5 are correctly individual.
>
> **CORRECTION 2026-08-21 (R-18): account 6, NS&I Premium Bonds, is ALSO individual —
> for the same reason as the ISAs.** Premium Bonds cannot be held jointly; the savings
> form removes the Ownership Type control entirely when "Premium Bonds" is selected,
> which is correct. Enter it as **David, Individual, £50,000**. The household cash total
> is unchanged at £130,780; only the split moves.

### 1.3 Investments — `/net-worth/investments`

| # | Owner | Provider | Type | Current Value | Ownership | Platform fee | Adviser fee | Risk |
|---|---|---|---|---|---|---|---|---|
| 1 | David | Hargreaves Lansdown | ISA (Stocks & Shares) | 95000 | Individual | 0.45 | 0.75 | Adventurous / High |
| 2 | Sarah | Hargreaves Lansdown | ISA (Stocks & Shares) | 85000 | Individual | 0.45 | 0.75 | Balanced / Medium |
| 3 | David | AJ Bell | General Investment Account | **95000** (full value) | **Joint Owner → Sarah** | 0.25 | 0.75 | Balanced / Upper Medium |
| 4 | David | Various | VCT | 30000 | Individual | — | 0.75 | High |

David's Stocks & Shares ISA also carries **Planned Lump Sum £5,000 (March 2026)**.

**Holdings** — all ten. Each needs ticker, ISIN, type, units, cost/unit, price/unit,
allocation and OCF. Enter via the account's **Details** link per holding.

*David's Stocks & Shares ISA (£95,000)*

| Holding | Ticker | ISIN | Type | Units | Cost/Unit | Price/Unit | Alloc | OCF |
|---|---|---|---|---|---|---|---|---|
| Fundsmith Equity | FUND | GB00B41YBW71 | Fund | 351 | 85.50 | 99.86 | 36.8% | 0.95 |
| Scottish Mortgage IT | SMT | GB00BLDYK618 | UK Equity | 2500 | 8.40 | 10.00 | 26.3% | 0.34 |
| Vanguard FTSE All-World | VWRL | IE00B3RBWM25 | ETF | 318 | 93.00 | 109.99 | 36.9% | 0.22 |

*Sarah's Stocks & Shares ISA (£85,000)*

| Holding | Ticker | ISIN | Type | Units | Cost/Unit | Price/Unit | Alloc | OCF |
|---|---|---|---|---|---|---|---|---|
| Vanguard LifeStrategy 80 | VGLS80 | GB00B4PQW151 | Fund | 333 | 225.00 | 255.00 | 100% | 0.22 |

*Joint General Investment Account (£95,000)*

| Holding | Ticker | ISIN | Type | Units | Cost/Unit | Price/Unit | Alloc | OCF |
|---|---|---|---|---|---|---|---|---|
| iShares Core MSCI World | SWDA | IE00B4L5Y983 | ETF | 625 | 68.00 | 80.00 | 52.6% | 0.20 |
| Vanguard UK Govt Bond | VGOV | IE00B42WWV65 | Bond | 1316 | 19.50 | 19.00 | 26.3% | 0.12 |
| iShares Physical Gold | SGLN | IE00B4ND3602 | Alternative | 84 | 195.00 | 238.00 | 21.1% | 0.12 |

*David's SIPP (£320,000)* — entered under Retirement, holdings via the pension detail

| Holding | Ticker | ISIN | Type | Units | Cost/Unit | Price/Unit | Alloc | OCF |
|---|---|---|---|---|---|---|---|---|
| Vanguard Global Equity | VHVG | IE00BKX55S42 | Fund | 4211 | 32.50 | 38.00 | 50% | 0.23 |
| BlackRock Corporate Bond | SLXX | IE0032895942 | Bond | 800 | 125.00 | 120.00 | 30% | 0.18 |
| L&G UK Property | LGUKP | GB00BK35DT11 | Property | 50000 | 1.35 | 1.28 | 20% | 0.68 |

### 1.4 Pensions — `/net-worth/retirement`

**Order trap:** enter David's **Defined Contribution** pensions first. Entering a
Defined Benefit pension first hides the "Add Pension" control entirely (W-0010).
After the W-0010 fix lands, deliberately re-test the Defined-Benefit-first order too.

*David, Defined Contribution 1 — Global Finance Corp*

| Field | Value |
|---|---|
| Provider | Fidelity |
| Type | Occupational / Workplace |
| Fund Value | 180000 |
| Employee Contribution | 8% |
| Employer Contribution | 8% |
| Employer Matching Limit | 8% |
| Annual Salary | 145000 |
| Retirement Age | 60 |
| Risk Preference | Upper Medium |
| Platform Fee | 0.35 |

*David, Defined Contribution 2 — SIPP*

| Field | Value |
|---|---|
| Provider | AJ Bell |
| Type | SIPP |
| Fund Value | 320000 |
| Retirement Age | 60 |
| Risk Preference | Upper Medium |
| Platform Fee | 0.25 |

*Sarah, Defined Benefit — NHS Pension Scheme* (`DBPensionForm.vue`)

| Field | Value |
|---|---|
| Employer / Scheme Name | Surrey NHS Trust — NHS Pension Scheme (2015) |
| Scheme Type | **Career average / public sector** if offered; otherwise `final_salary` (W-0017 — record which was used) |
| Annual Income at Retirement (£) | 35000 |
| Service Years | 18 |
| Normal Retirement Age | 60 |
| Spouse Pension (%) | 50 |
| Inflation Protection | **CPI** |
| Pension Commencement Lump Sum Available (£) | 105000 |

*State Pension* (`StatePensionForm.vue`) — enter on **both** accounts

| Field | Value |
|---|---|
| Forecast Weekly Amount (£) | 221.20 |
| Qualifying Years | 30 |

> **Assumption to flag, not to bury.** The persona gives one State Pension block with
> no owner. This playbook enters it on **both** accounts, since both spouses have a
> forecast in reality and the app models them per user. 221.20 × 52 = £11,502.40,
> which rounds to the persona's stated £11,502 annual. If CSJ decides it is David's
> only, remove Sarah's and re-verify §2.7.

### 1.5 Protection — `/protection` → **Add Protection**

| Field | Life policy | Critical Illness policy |
|---|---|---|
| Policy Type | Life Insurance | Critical Illness |
| life_policy_type / coverage_type | Level Term | Standalone |
| Provider | Vitality | Legal & General |
| coverage_amount | 500000 | 200000 |
| premium_amount / frequency | 85 / Monthly | 125 / Monthly |
| start_date | 2020-01-01 | 2020-01-01 |
| end_date | 2040-01-01 | 2040-01-01 |
| in_trust | **Yes** | — |
| joint_life | **Yes** | — |
| policy_number | VIT-LT-456789 | LG-CI-789123 |
| beneficiary_name + additional_beneficiaries | **Sarah Jones, William Jones, Charlotte Jones** | — |

### 1.6 Chattels — `/net-worth/chattels` → **Add Valuable**

| # | Name | Type | Value | Purchase Price | Ownership | Joint owner | Notes |
|---|---|---|---|---|---|---|---|
| 1 | Contemporary Art Collection | Art | 35000 | 22000 | Joint 50% | Sarah | Including Damien Hirst lithograph |
| 2 | 1967 Jaguar E-Type Series 1 | Vehicle | 85000 | 45000 | Individual (David) | — | Fully restored. CGT exempt — wasting asset |
| 3 | Sarah's Engagement Ring | Jewellery | 18000 | 12000 | Individual (**entered on Sarah's account**) | — | 2.5 carat diamond solitaire |
| 4 | Georgian Writing Desk | Antique | 8500 | 6200 | Joint 50% | Sarah | Inherited from Sarah's grandmother |
| 5 | First Edition Book Collection | Collectible | 4500 | 2800 | Individual (David) | — | Ian Fleming Bond novels. Below £6k CGT threshold |
| 6 | BMW X5 xDrive40i | Vehicle | 42000 | 65000 | Joint 50% | Sarah | Family SUV. PCP finance ends 2025 |

> Every joint chattel MUST get an explicit joint owner. W-0025: the form saves a joint
> chattel with a NULL `joint_owner_id` and no error, orphaning 50% of it.

### 1.7 Expenditure — `/valuable-info?section=expenditure` → **Detailed View**

Set each with ~400 ms gaps and focus/blur.

| Category | £ |
|---|---|
| Food & Groceries | 450 |
| Transport & Fuel | 150 |
| Healthcare & Medical | 50 |
| Insurance | 100 |
| Mobile Phones | 50 |
| Internet & TV | 40 |
| Subscriptions | 30 |
| Clothing & Personal Care | 100 |
| Entertainment & Dining | 100 |
| Holidays & Travel | 100 |
| School Fees | 1000 |
| School Lunches | 50 |
| School Extras | 80 |
| Children Activities | 100 |
| Gifts & Charity | 50 |
| **Sum of categories** | **2450** |

> **Persona-file inconsistency — flag, do not fix.** The User Profile table and the
> Expenditure heading both say **£2,500/month**, but the fifteen categories sum to
> **£2,450**. Enter the categories as written; expect `monthly_expenditure = 2450` and
> `annual_expenditure = 29400`. Record the £50 discrepancy for product-lead. Do not
> edit the persona file, and do not invent a £50 category to make it balance.

### 1.8 Trust — `/trusts` → **Create Trust** (premium)

| Field | Value |
|---|---|
| trust_name | Jones Children's Education Trust |
| trust_type | Discretionary |
| trust_creation_date | 2020-09-01 |
| initial_value | 150000 |
| current_value | 185000 |
| settlor | David Jones |
| beneficiaries | William Jones, Charlotte Jones |
| trustees | David Jones, Sarah Jones, Barclays Trustee Services |
| purpose | Education funding including university fees, accommodation, and living expenses |
| is_active | Yes |

Relevant Property Trust status is **derived** from `trust_type = discretionary`, not
entered.

### 1.9 Wills — `/estate/will-builder` (premium)

**W-0019 (CSJ direction): married users get mirror wills only.** Do not test the
Simple Will path for this persona; if the Simple Will option is still offered to a
married user, that is itself the W-0019 defect.

*David's will:*

| Field | Value |
|---|---|
| Type | **Mirror Will** |
| Spouse primary beneficiary | Sarah Jones, 100% |
| Executors | Sarah Jones **and** Barclays Wealth |
| Guardians | must be offered (minor child Charlotte) |
| Gifts / bequests | William Jones 50% percentage, priority 2, "Receive at age 25, held in trust" · Charlotte Jones 50% percentage, priority 2, same condition · **Cancer Research UK £10,000 specific amount, priority 1** |
| Last updated | 2022-03-15 |
| Executor notes | Mirror wills prepared by Henderson & Co Solicitors. Life insurance policies held in trust outside estate. |

Then **Generate Spouse's Will** (mirror) and complete it as Sarah:

| Field | Value |
|---|---|
| Executors | David Jones **and** Barclays Wealth — must NOT be Sarah herself (W-0024) |
| Charity | **British Heart Foundation £10,000** — must NOT default to Cancer Research UK (W-0024) |
| Guardians | must be offered on Sarah's will too (W-0024) |
| Bequests | William 50% / Charlotte 50%, priority 2, same conditions |

> **Do not start with the word "To".** The will builder's review prose reads
> "then to To …" if a gift description begins with "To" — that was tester input, not
> a defect. Cleared last run; do not re-raise.

### 1.10 Lasting Power of Attorney — `/estate/power-of-attorney`

Persona: "Has LPA: Yes". Record both types where the flow offers them
(`/estate/lpa/create/:type`).

### 1.11 Letter to loved ones — `/valuable-info?section=letter` (premium)

Eight immediate actions and four key contacts, verbatim from the persona, plus
employer benefits and funeral wishes.

> The "Solicitor" field is stored as **`attorney_name` / `attorney_contact`**
> (`LetterToSpouse.vue:136-143`) — there is no `solicitor_*` column. A cleared false
> positive; do not re-raise it.

### 1.12 Goals — `/goals` → **Add Goal**

| # | Owner | Name | Type | Target | Current | Target date | Priority | Monthly | Ownership |
|---|---|---|---|---|---|---|---|---|---|
| 1 | David | Max Pension Contributions | Retirement | 60000 | 45000 | **2026-04-05 (PAST)** | High | 2500 | — |
| 2 | David | William's House Deposit Help | Custom (Child Support) | 40000 | 28000 | 2027-09-01 | Medium | 500 | **Joint** |
| 3 | David | Charlotte's Gap Year Fund | Custom (Child Support) | 15000 | 12000 | **2026-08-01 (PAST)** | Low | 400 | **Joint** |
| 4 | David | ISA Wealth Building | Wealth Accumulation | 500000 | 185000 | 2035-04-05 | High | 3333 | — |
| 5 | **Sarah** | Sarah's ISA | Wealth Accumulation | 400000 | 120000 | 2035-04-05 | High | 1667 | — |
| 6 | David | Early Retirement Fund | Retirement | 200000 | 95000 | 2033-01-01 | Critical, **Essential** | 1500 | **Joint** |

Goals 1 and 3 have past target dates and are blocked by W-0029.
Streaks (36 and 60 months) are earned through contributions, not typed — by design.

### 1.13 Life events — `/goals?tab=events` → **Add Life Event**

| Event | Type | Amount | Impact | Date | Certainty |
|---|---|---|---|---|---|
| Previous Inheritance (David's Aunt) | Inheritance | 45000 | Income | **2020-03-15 (PAST)** | Confirmed / Completed |
| Parents' Estate (David) | Inheritance | 200000 | Income | 2035-06-01 | Possible |
| Kitchen & Extension | Home Improvement | 85000 | Expense | 2027-04-01 | Likely |
| William's Wedding Contribution | Gift Given | 25000 | Expense | 2030-08-01 | Speculative |
| Annual Bonus | Bonus | 35000 | Income | **2026-04-01 (PAST)** | Likely |
| Charlotte's University Costs | Education Fees | 45000 | Expense | 2028-09-01 | Likely |
| Replace BMW X5 | Large Purchase | 55000 | Expense | 2028-03-01 | Confirmed |
| Downsizing Property Sale | Property Sale | 350000 | Income | 2046-06-01 | Possible |
| World Cruise | Large Purchase | 45000 | Expense | 2046-11-08 | Speculative |
| Grandchildren Education Fund | Gift Given | 100000 | Expense | 2041-01-01 | Speculative |

The two past-dated events are blocked by W-0029.

---

## 2. Expected values, precomputed

Every figure below is derived by hand from `peak_earners.md`. Tax values are the
**live** values read from `TaxConfigService` on 2026-08-21, not remembered:

| Value | From `TaxConfigService` |
|---|---|
| Nil Rate Band | £325,000 |
| Residence Nil Rate Band | £175,000 |
| Residence Nil Rate Band taper threshold / rate | £2,000,000 / £1 per £2 |
| Inheritance Tax standard / charitable reduced rate | 40% / 36% |
| Charitable threshold | 10% of baseline |
| Pension funds enter the estate from | **2027-04-06** |
| Personal Allowance / taper threshold / rate | £12,570 / £100,000 / £1 per £2 |
| Income Tax bands | 20% to £50,270 · 40% to £125,140 · 45% above |
| Pension Annual Allowance | £60,000 (taper starts at adjusted income £260,000, threshold income £200,000) |
| Capital Gains Tax annual exempt amount | £3,000 |
| Chattels exemption / marginal relief limit | £6,000 / £15,000 |
| ISA allowance | £20,000 |
| Retirement target income percent (fallback) | 75% |
| Safe withdrawal rate | 4% |

**If any of these has moved by run day, recompute — do not use the numbers below
blind.** A hardcoded threshold is a defect even when the number looks right.

### 2.1 Ownership shares — every record, both sides

`ownership_percentage` is the **primary owner's** share; the spouse's is
`100 − ownership_percentage` (Rule 6). One row per asset, never two.

**Properties**

| Record | Full value | Type | David sees | Sarah sees | Third party | Household |
|---|---|---|---|---|---|---|
| The Willows | £850,000 | joint 50% | **£425,000** | **£425,000** | — | £850,000 counted once |
| City Centre Flat | £425,000 | joint 50% | **£212,500** | **£212,500** | — | £425,000 counted once |
| Manchester | £295,000 | tenants in common, David 40% | **£118,000** | **£0 — she is not an owner** | Mike Barrett 60% = **£177,000, belongs to no account here** | £118,000 |
| **Property total** | | | **£755,500** | **£637,500** | | **£1,393,000** |

Arithmetic: David 425,000 + 212,500 + 118,000 = 755,500 · Sarah 425,000 + 212,500 + 0
= 637,500 · Household 850,000 + 425,000 + 118,000 = 1,393,000.

> **£177,000 must never appear anywhere in this household's figures.** Not in David's
> net worth, not in Sarah's, not in household roll-ups, not in the estate. If any
> screen shows the Manchester property at £295,000 to either account, that is a defect.

**Mortgages**

| Record | Full balance | Type | David | Sarah | Household |
|---|---|---|---|---|---|
| HSBC — The Willows | £65,000 | joint 50% | £32,500 | £32,500 | £65,000 |
| Barclays — City Centre Flat | £180,000 | joint 50% | £90,000 | £90,000 | £180,000 |
| NatWest — Manchester | £120,000 | tenants in common 40% | £48,000 | £0 | £48,000 |
| **Mortgage total** | | | **£170,500** | **£122,500** | **£293,000** |

**Net property equity:** David 755,500 − 170,500 = **£585,000** · Sarah 637,500 −
122,500 = **£515,000** · Household 1,393,000 − 293,000 = **£1,100,000**.

**Savings / cash**

| Record | Balance | Type | David | Sarah |
|---|---|---|---|---|
| HSBC Current (David) | £25,000 | individual | £25,000 | **£0 — must not appear** |
| Barclays Current (Sarah) | £6,280 | individual | **£0 — must not appear** | £6,280 |
| Nationwide Current (joint) | £4,500 | joint 50% | £2,250 | £2,250 |
| Nationwide Cash ISA (David) | £22,500 | individual | £22,500 | **£0** |
| Nationwide Cash ISA (Sarah) | £22,500 | individual | **£0** | £22,500 |
| NS&I Premium Bonds (**individual, David**) | £50,000 | individual | **£50,000** | **£0** |
| **Cash total** | | | **£99,750** | **£31,030** |

> **CORRECTED 2026-08-21 (R-18).** This row read "joint 50% / £25,000 / £25,000" and a
> cash total of £74,750 / £56,030. Premium Bonds are individual-only, so the correct
> split is £99,750 / £31,030. **The household total is unchanged at £130,780**, and
> 99,750 + 31,030 = 130,780 still cross-checks.

Household cash: 25,000 + 6,280 + 4,500 + 22,500 + 22,500 + 50,000 = **£130,780**.
Cross-check: 74,750 + 56,030 = 130,780 ✓

**Investments**

| Record | Value | Type | David | Sarah |
|---|---|---|---|---|
| Hargreaves Lansdown Stocks & Shares ISA (David) | £95,000 | individual | £95,000 | **£0** |
| Hargreaves Lansdown Stocks & Shares ISA (Sarah) | £85,000 | individual | **£0** | £85,000 |
| AJ Bell General Investment Account (joint) | £95,000 | joint 50% | **£47,500** | **£47,500** |
| Venture Capital Trust holdings | £30,000 | individual | £30,000 | **£0** |
| **Investment total** | | | **£172,500** | **£132,500** |

Household investments: 95,000 + 85,000 + 95,000 + 30,000 = **£305,000**.
Cross-check: 172,500 + 132,500 = 305,000 ✓

> The joint General Investment Account is the exact record W-0014 and W-0015 are about.
> **£47,500 on both sides, from one row.** Not £95,000 twice, not £95,000 and nothing.

**Pensions (Defined Contribution capital)**

| Record | Value | David | Sarah |
|---|---|---|---|
| Fidelity — Global Finance Corp | £180,000 | £180,000 | £0 |
| AJ Bell SIPP | £320,000 | £320,000 | £0 |
| **Defined Contribution total** | | **£500,000** | **£0** |

Sarah's NHS Defined Benefit is **income, not capital** — £35,000 a year from age 60,
plus a £105,000 Pension Commencement Lump Sum entitlement. It should not appear as a
current asset in net worth. If it does, check whether that is deliberate and record
which figure is used.

**Chattels**

| Record | Value | Type | David | Sarah |
|---|---|---|---|---|
| Contemporary Art Collection | £35,000 | joint 50% | £17,500 | £17,500 |
| 1967 Jaguar E-Type | £85,000 | individual (David) | £85,000 | **£0** |
| Sarah's Engagement Ring | £18,000 | individual (Sarah) | **£0** | £18,000 |
| Georgian Writing Desk | £8,500 | joint 50% | £4,250 | £4,250 |
| First Edition Book Collection | £4,500 | individual (David) | £4,500 | **£0** |
| BMW X5 xDrive40i | £42,000 | joint 50% | £21,000 | £21,000 |
| **Chattels total** | | | **£132,250** | **£60,750** |

Household chattels: 35,000 + 85,000 + 18,000 + 8,500 + 4,500 + 42,000 = **£193,000**.
Cross-check: 132,250 + 60,750 = 193,000 ✓

> These two totals were **independently verified GREEN on `/m` last run**
> (screenshots 16 and 17). They are the reference proof that this arithmetic method
> matches the app when the app is right.

**Trust** — Jones Children's Education Trust £185,000 is settled property. It belongs
to neither spouse's personal net worth and sits outside the estate. It shows on
`/trusts` only.

### 2.2 Net worth totals

| Line | David | Sarah | Household |
|---|---|---|---|
| Property (gross) | £755,500 | £637,500 | £1,393,000 |
| Cash | £74,750 | £56,030 | £130,780 |
| Investments | £172,500 | £132,500 | £305,000 |
| Pensions (Defined Contribution) | £500,000 | £0 | £500,000 |
| Chattels | £132,250 | £60,750 | £193,000 |
| **Total assets** | **£1,635,000** | **£886,780** | **£2,521,780** |
| Liabilities (mortgages) | £170,500 | £122,500 | £293,000 |
| **Net worth** | **£1,464,500** | **£764,280** | **£2,228,780** |
| Net worth excluding pensions | £964,500 | £764,280 | **£1,728,780** |

> The persona header says "Net Worth Range £1.5m–£2m". The full arithmetic gives
> **£2,228,780** including pensions and **£1,728,780** excluding them — the header is
> consistent only with the ex-pensions reading. Note it; do not edit the persona file.

### 2.3 Income, rental and monthly commitments

| Line | David | Sarah | Household |
|---|---|---|---|
| Employment income | £145,000 | £120,000 | £265,000 |
| Rental — City Centre Flat (50% of £1,800/mo) | £900/mo = £10,800/yr | £900/mo = £10,800/yr | £21,600/yr |
| Rental — Manchester (40% of £1,350/mo) | £540/mo = £6,480/yr | **£0** | £6,480/yr |
| **Rental total** | **£17,280/yr** | **£10,800/yr** | **£28,080/yr** |

Monthly property commitments (running costs + mortgage), split by ownership:

| Property | Running costs | Mortgage | Total | David | Sarah |
|---|---|---|---|---|---|
| The Willows | 320+95+70+40+45+30+100 = £700 | £550 | **£1,250** | £625 | £625 |
| City Centre Flat | 35+285+100+150 = £570 | £650 | **£1,220** | £610 | £610 |
| Manchester | 28+195+85+120 = £428 | £750 | **£1,178** | £471.20 | **£0** |
| **Total** | | | **£3,648** | **£1,706.20** | **£1,235** |

> The Willows split (£1,250 → £625 each, counted once) was verified GREEN last run
> (screenshot 13). The other two are new.

### 2.4 Expenditure

| Line | Value |
|---|---|
| Sum of the 15 categories | **£2,450/month** |
| `users.monthly_expenditure` | **2450** |
| `users.annual_expenditure` | **29400** |
| `users.expenditure_entry_mode` | `detailed` |
| Persona's stated headline | £2,500/month — **£50 unexplained** |

### 2.5 Inheritance Tax

The app models the household second-death estate. All figures below assume that;
if the run shows a per-user estate instead, recompute on that basis and record the
difference.

**Estate composition**

| In the estate | £ |
|---|---|
| Property (household share) | 1,393,000 |
| Cash | 130,780 |
| Investments | 305,000 |
| Chattels | 193,000 |
| **Gross estate** | **2,021,780** |
| less mortgages | (293,000) |
| **Net estate** | **1,728,780** |

| Correctly OUTSIDE the estate | £ | Why |
|---|---|---|
| Defined Contribution pensions | 500,000 | Until 2027-04-06 |
| Life cover, Vitality | 500,000 | Written in trust |
| Jones Children's Education Trust | 185,000 | Settled 2020-09-01, relevant property trust |
| Mike Barrett's 60% of Manchester | 177,000 | Not the household's asset |
| Sarah's Pension Commencement Lump Sum entitlement | 105,000 | Not yet held |

**Liability, current tax year**

| Step | £ |
|---|---|
| Net estate | 1,728,780 |
| Nil Rate Band × 2 | 650,000 |
| Residence Nil Rate Band × 2 | 350,000 |
| Taper? Net estate £1,728,780 < £2,000,000 | **no taper** |
| Total allowances | **1,000,000** |
| Taxable estate | **728,780** |
| Inheritance Tax at 40% | **£291,512** |

> **CORRECTION, 2026-08-21 (R-17) — this table omits a £150,000 chargeable lifetime
> transfer, and the omission is material.** The persona settles **£150,000** into the
> Jones Children's Education Trust on **2020-09-01** (`peak_earners.md`, Trusts §1).
> That is a chargeable lifetime transfer, and on the run date it is **5 years 11 months**
> old — inside the seven-year window — so it reduces the nil rate band pound for pound.
> The application records it as `gifts.id 10` and applies it.
>
> | Line | Playbook above | Corrected |
> |---|---|---|
> | Nil Rate Band × 2 | 650,000 | **500,000** (650,000 − 150,000) |
> | Total allowances | 1,000,000 | **850,000** |
> | Taxable estate | 728,780 | **878,780** |
> | Inheritance Tax at 40% | £291,512 | **£351,512** |
>
> The charitable baseline moves with it: 1,728,780 − 500,000 = **1,228,780**, so the 10%
> threshold is **£122,878**, not £107,878, and the persona's £20,000 of legacies is
> further short than §2.5 states. The transfer becomes seven years old on **2027-09-01**,
> after which the playbook's original figures are correct — so any run must recompute
> against its own run date rather than reusing either table blind.
>
> The 2027 pensions-in-estate table below inherits the same £150,000 error.

**Charitable 36% test**

| Step | £ |
|---|---|
| Baseline (net estate − Nil Rate Bands; Residence Nil Rate Band correctly excluded) | 1,728,780 − 650,000 = **1,078,780** |
| 10% threshold | **107,878** |
| Persona's charitable legacies (£10,000 David + £10,000 Sarah) | **20,000** |
| Result | **below threshold — rate stays 40%**, shortfall £87,878 |
| Potential saving if met (4% of baseline) | £43,151.20 |

> **This matters for W-0020's acceptance criterion.** That item asks for "a charitable
> cash legacy of 10%+ of the baseline moves the rate to 36%, verified end to end
> against the persona" — but against the **full** persona estate, £20,000 is nowhere
> near £107,878, so the persona alone cannot demonstrate the 36% rate. Verify W-0020
> in two parts: (a) the persona's £20,000 is counted at all — `charitable_total` must
> read 20000, not 0, which is the actual bug; and (b) the rate flips to 36% using a
> deliberately oversized temporary legacy, then remove it. Do not report W-0020 as
> unverifiable, and do not edit the persona's legacy amounts.

**From 2027-04-06, pensions in the estate**

| Step | £ |
|---|---|
| Net estate + Defined Contribution pensions | 1,728,780 + 500,000 = **2,228,780** |
| Residence Nil Rate Band taper: (2,228,780 − 2,000,000) × 0.5 | 114,390 |
| Residence Nil Rate Band after taper | 350,000 − 114,390 = **235,610** |
| Total allowances | 650,000 + 235,610 = **885,610** |
| Taxable estate | **1,343,170** |
| Inheritance Tax at 40% | **£537,268** |

The 2027 view must show the Residence Nil Rate Band tapering. If it still shows
£350,000 against a £2,228,780 estate, the taper is not being applied.

### 2.6 Income tax and pension allowance — David

| Step | Value |
|---|---|
| Gross income | £145,000 |
| Personal Allowance taper: (145,000 − 100,000) × 0.5 = 22,500, capped at 12,570 | **Personal Allowance = £0** |
| Threshold income £145,000 < £200,000 | **no Annual Allowance taper** — Annual Allowance stays £60,000 |
| Employee contribution 8% of £145,000 | £11,600 |
| Employer contribution 8% of £145,000 | £11,600 |
| Total contributions | £23,200 |
| **Annual Allowance headroom** | **£36,800** |

Tax saving on contributing the full £36,800 headroom (income falls £145,000 → £108,200):

| Slice | £ | Rate | Saving |
|---|---|---|---|
| £125,140 → £145,000 | 19,860 | 45% | £8,937 |
| £108,200 → £125,140 | 16,940 | 40% | £6,776 |
| Personal Allowance restored: 12,570 − (108,200 − 100,000) × 0.5 = £8,470, relieved at 40% | 8,470 | 40% | £3,388 |
| **Total saving** | | | **£19,101** |

> **£36,800 and £19,101 were both verified exact last run.** They are the strongest
> single proof that the tax engine is right. Re-verify them, and treat any drift as a
> regression in the tax layer rather than a rounding difference.

### 2.7 Retirement — inputs first, output second

**Verify the inputs the projection claims to use before judging any output.**

| Input | David | Sarah |
|---|---|---|
| Date of birth / age on 2026-08-21 | 1976-11-08 / 49 | 1978-04-22 / 48 |
| Target retirement age | 60 | 60 |
| Reaches 60 on | 2036-11-08 (~10.2 yrs) | 2038-04-22 (~11.7 yrs) |
| **Target retirement income** | **£75,000** | **£55,000** |
| Defined Contribution pot today | £500,000 | £0 |
| Annual contributions | £23,200 | £0 |
| Defined Benefit | — | £35,000/yr from 60, CPI-linked, 50% spouse pension, £105,000 lump sum |
| State Pension | £11,502/yr from 67 | £11,502/yr from 67 |

**The single most diagnostic check on this whole module:**

```
GET /api/retirement/required-capital
```

The response carries `income_source` and `required_income`
(`RequiredCapitalCalculator.php:106-145`).

| | Expected | What it reads today |
|---|---|---|
| `income_source` | `profile` | `calculated` |
| `required_income` (David) | **75000** | **100050** |
| `required_income` (Sarah) | **55000** | **116250** |

The wrong figures are not random — they are the documented fallback,
`(gross income − pension contributions) × 75%`:

- David: (145,000 − 11,600) × 0.75 = 133,400 × 0.75 = **£100,050** ✓ matches what the
  app showed last run.
- Sarah: 155,000 × 0.75 = **£116,250** — her £120,000 salary **plus the £35,000 NHS
  Defined Benefit pension counted as income she receives today**, at age 48, against a
  Normal Retirement Age of 60. That is a second, separate defect: **W-0036**. It also
  corrupts her income tax, Personal Allowance and Child Benefit position, so it must be
  fixed *before* W-0035 — an explicit £55,000 target would hide it on this screen while
  it kept corrupting the others.

**Why:** `retirement_profiles.target_retirement_income` is written by exactly two
places — `OnboardingService.php:482/498` (which only ever sets
`target_retirement_age`, never the income) and `CoordinatingAgent.php:5628` (Fyn's
capture handler). **There is no module UI form and no API route that sets it.** See
§7.2, W-0035.

Consequence for the run: **in Pass A the target income cannot be entered at all**, so
every retirement projection runs on the fallback. Pass A must record the projection
as running on the wrong input rather than judging the output correct or incorrect.
Passes B and C, which enter through Fyn, are where £75,000 / £55,000 can actually
reach the profile — so the same projection must be re-checked there and must move.

**Sanity band for the output** once the input is right (verify against the app's own
stated assumptions on `/settings/assumptions`, do not assume a growth rate):

- Required capital at 4% safe withdrawal, net of State Pension:
  David (75,000 − 11,502) / 0.04 = **£1,587,450**; gross of State Pension
  75,000 / 0.04 = £1,875,000.
- David's pot at 60 is roughly £1.05m–£1.2m on a 4–5% net growth assumption
  (£500,000 compounding for ~10.2 years plus £23,200 a year). Expect the module to
  report a **shortfall**, and a larger one between 60 and 67 while no State Pension is
  payable. Direction and magnitude are the test; an exact figure is not.

### 2.8 Holdings — values, and the rounding the persona carries

| Account | Holding | Units × price | Value | Persona alloc | Computed alloc |
|---|---|---|---|---|---|
| David Stocks & Shares ISA | Fundsmith | 351 × 99.86 | £35,050.86 | 36.8% | 36.9% |
| | Scottish Mortgage | 2,500 × 10.00 | £25,000.00 | 26.3% | 26.3% |
| | Vanguard FTSE All-World | 318 × 109.99 | £34,976.82 | 36.9% | 36.8% |
| | **Sum** | | **£95,027.68** vs account £95,000 | | |
| Sarah Stocks & Shares ISA | Vanguard LifeStrategy 80 | 333 × 255.00 | £84,915.00 | 100% | 100% |
| | **Sum** | | **£84,915** vs account £85,000 | | |
| Joint General Investment Account | iShares Core MSCI World | 625 × 80.00 | £50,000.00 | 52.6% | 52.6% |
| | Vanguard UK Govt Bond | 1,316 × 19.00 | £25,004.00 | 26.3% | 26.3% |
| | iShares Physical Gold | 84 × 238.00 | £19,992.00 | 21.1% | 21.0% |
| | **Sum** | | **£94,996** vs account £95,000 | | |
| David SIPP | Vanguard Global Equity | 4,211 × 38.00 | £160,018.00 | 50% | 50.0% |
| | BlackRock Corporate Bond | 800 × 120.00 | £96,000.00 | 30% | 30.0% |
| | L&G UK Property | 50,000 × 1.28 | £64,000.00 | 20% | 20.0% |
| | **Sum** | | **£320,018** vs account £320,000 | | |

Holdings sums differ from stated account values by −£85 to +£28. That is the persona's
own rounding, not a bug. **Do not raise a defect for it**, and do not adjust units to
force a match. A difference of more than about £100, or an allocation off by more than
0.2 percentage points, is worth investigating.

**Capital Gains Tax context** — the joint General Investment Account is the only
unwrapped holding:

| Holding | Cost | Value | Gain/(loss) |
|---|---|---|---|
| iShares Core MSCI World | 625 × 68.00 = 42,500 | 50,000 | +7,500 |
| Vanguard UK Govt Bond | 1,316 × 19.50 = 25,662 | 25,004 | (658) |
| iShares Physical Gold | 84 × 195.00 = 16,380 | 19,992 | +3,612 |
| **Net unrealised gain** | | | **+£10,454** |

Joint 50%: **£5,227 each**. Against a £3,000 annual exempt amount, each spouse has
about **£2,227** of taxable gain if realised in full. Any Capital Gains Tax
harvesting suggestion should be built on £5,227 per person, not £10,454.

**Chattels and Capital Gains Tax** — the persona's own notes are the expected treatment:

| Chattel | Gain | Expected treatment |
|---|---|---|
| Jaguar E-Type | +£40,000 | **Exempt** — wasting asset (private vehicle) |
| BMW X5 | (£23,000) | Exempt; loss not allowable |
| Contemporary Art | +£13,000 | Chargeable; above the £6,000 chattels exemption |
| Georgian Writing Desk | +£2,300 | Chargeable; proceeds £8,500 above £6,000, marginal relief applies |
| First Edition Books | +£1,700 | **Exempt** — proceeds £4,500 below £6,000 |
| Engagement Ring | +£6,000 | Chargeable (Sarah's) |

### 2.9 Protection

| Line | Value |
|---|---|
| Life cover, sum assured | £500,000, joint life, **in trust** |
| Life premium | £85/mo = **£1,020/yr** |
| Critical illness, sum assured | £200,000 |
| Critical illness premium | £125/mo = **£1,500/yr** |
| Total premiums | **£210/mo = £2,520/yr** |
| Both policies run | 2020-01-01 to 2040-01-01 |

The in-trust life cover must be **excluded** from the Inheritance Tax estate (§2.5) and
**included** in any protection-gap analysis. Joint life means it pays on first death.
If a gap analysis shows £500,000 of cover for each spouse separately, that is
double-counting one policy.

### 2.10 Goals — progress

| Goal | Current / Target | Progress |
|---|---|---|
| Max Pension Contributions | 45,000 / 60,000 | **75.0%** |
| William's House Deposit Help | 28,000 / 40,000 | **70.0%** |
| Charlotte's Gap Year Fund | 12,000 / 15,000 | **80.0%** |
| ISA Wealth Building | 185,000 / 500,000 | **37.0%** |
| Sarah's ISA | 120,000 / 400,000 | **30.0%** |
| Early Retirement Fund | 95,000 / 200,000 | **47.5%** |

Total monthly goal contributions: 2,500 + 500 + 400 + 3,333 + 1,667 + 1,500 =
**£9,900/month**. Against household income of £265,000/yr (£22,083/mo) that is 44.8%.
If any screen shows this as affordable without comment, note it.

### 2.11 ISA allowances this tax year

| | David | Sarah |
|---|---|---|
| Cash ISA subscribed | £10,000 | £10,000 |
| Stocks & Shares ISA subscribed | £0 (a £5,000 lump sum is *planned*, not subscribed) | £0 |
| **Used** | **£10,000** | **£10,000** |
| **Remaining of £20,000** | **£10,000** | **£10,000** |

This is exactly the W-0007 scenario: on a cold load of `/net-worth/investments` the
modal must read **Cash ISA £10,000 used, £10,000 remaining** — not £0 used and
£20,000 remaining.

---

## 3. Per-account verification matrix

Run the whole matrix twice: once as David, once as Sarah. A run that only ever logs
in as David is half a test.

The "must NOT see" column is the security half of this run and is checked by looking
for absence — reading the page and confirming the record is not listed, not just that
the total happens to be right.

### 3.1 Web (`localhost:8000`)

| Screen | David must see | Sarah must see | Sarah must NOT see |
|---|---|---|---|
| `/dashboard` | Net worth £1,464,500 | Net worth £764,280 | Any figure that includes David's individual assets |
| `/net-worth` overview | Assets £1,635,000 · liabilities £170,500 | Assets £886,780 · liabilities £122,500 | — |
| `/net-worth/wealth-summary` | Household £2,228,780, his share £1,464,500 | Household £2,228,780, her share £764,280 | The Manchester property at more than £118,000 in any household line |
| `/net-worth/property` | 3 properties · £425,000 + £212,500 + £118,000 = £755,500 | **2 properties** · £425,000 + £212,500 = £637,500 | **The Manchester property at all** — she owns none of it |
| Property card co-owner line | "Joint with Sarah Jones" | "Joint with David Jones" | Her own name as her co-owner (W-0016) |
| The Willows detail | £425,000 his share, £850,000 full, mortgage £32,500 of £65,000 | £425,000 her share, same full values | — |
| Manchester detail | £118,000 of £295,000 · co-owner **Mike Barrett** · mortgage £48,000 of £120,000 | — | The whole record |
| `/net-worth/cash` | £74,750 across 4 accounts | £56,030 across 4 accounts | David's HSBC £25,000 · David's Cash ISA £22,500 |
| Joint Nationwide account | £2,250 of £4,500 | £2,250 of £4,500 | — |
| Premium Bonds | £25,000 of £50,000 | £25,000 of £50,000 | — |
| `/net-worth/investments` | £172,500 across 3 accounts | £132,500 across 2 accounts | David's Stocks & Shares ISA £95,000 · the Venture Capital Trust £30,000 |
| Joint General Investment Account card | **"Your Share (50.00%) £47,500"** of £95,000 | **"Your Share (50.00%) £47,500"** of £95,000 | £95,000 shown as hers |
| `/net-worth/retirement` | 2 Defined Contribution pensions, £500,000 · State Pension £11,502 | Defined Benefit £35,000/yr, lump sum £105,000 · State Pension £11,502 | David's Fidelity or SIPP pots |
| `/net-worth/chattels` | **£132,250** across 5 items | **£60,750** across 4 items | The Jaguar £85,000 · the First Edition books £4,500 |
| `/protection` | Life £500,000 in trust joint life · Critical Illness £200,000 · £210/mo | Same joint-life policy visible to her as a life assured | — |
| `/goals` | 5 goals (his 4 + joint) | Her ISA goal + the 3 joint goals | "ISA Wealth Building" and "Max Pension Contributions" as hers |
| `/goals?tab=events` | 8 events entered (10 less the 2 past-dated) | Household-visible events only | Events flagged individual-only |
| `/estate` | Net estate £1,728,780 · liability £291,512 · 40% rate | Same household estate | — |
| `/estate/will-builder` | His mirror will, executors **Sarah Jones + Barclays Wealth**, gift Cancer Research UK £10,000 | Her mirror will, executors **David Jones + Barclays Wealth**, gift British Heart Foundation £10,000 | **Herself named as her own executor** (W-0024) · Cancer Research UK as her charity (W-0024) |
| `/trusts` | Jones Children's Education Trust £185,000, "Relevant Property Trust" spelled out | Same (she is a trustee) | "RPT" as a bare acronym (W-0021) |
| `/valuable-info?section=letter` | Liabilities section naming the £65,000 mortgage, not "No outstanding liabilities recorded" (W-0022) | Her own letter | David's letter content |
| `/valuable-info?section=expenditure` | £2,450/mo, 15 categories | Her own expenditure | David's categories as hers |
| `/tax-strategy` | Annual Allowance headroom **£36,800**, saving **£19,101** | Her own headroom, computed from £120,000 and her Defined Benefit accrual | David's figures |
| `/plans/retirement` | `required_income` **£75,000** once enterable — today £100,050 from the fallback (W-0035, W-0036) | £55,000 — today £116,250 | — |

### 3.2 `/m` (`localhost:8000/m/app/...`)

Every figure must match its web counterpart **to the penny**. A difference between
surfaces is a defect on at least one of them, and Rule 20 says the fix is one change
in one place, not two.

| `/m` screen | Must match |
|---|---|
| `/m/app/dashboard` | Web `/dashboard` net worth. The Level wheel, "X of Y actions complete" and "ahead of X% of people" are **approved gamification** — never flag them (Rule 12 carve-out). |
| `/m/app/net-worth` and `/m/app/net-worth/:category` | §2.1 and §2.2 per account |
| `/m/app/net-worth/property/:id` | The Willows £425,000 · Manchester £118,000 for David, absent for Sarah |
| `/m/app/net-worth/mortgage/:id` | £32,500 / £90,000 / £48,000 |
| `/m/app/savings` and `/savings/account/:id` | £74,750 / £56,030 |
| `/m/app/investment` and `/investment/account/:id` | **£47,500** on the joint General Investment Account, both accounts |
| `/m/app/retirement` and `/retirement/pension/:type/:id` | £500,000 David · Defined Benefit £35,000 Sarah |
| `/m/app/protection` and `/protection/policy/:type/:id` | £500,000 in trust, £200,000, £210/mo, both start/end dates |
| `/m/app/estate` and `/m/app/estate/bequests` | Estate £1,728,680–£1,728,780 band per §2.5; **all six bequests listed** (W-0023) |
| `/m/app/goals` | Goals **and life events** — the page is titled "Goals and life events" (W-0028) |
| `/m/app/expenditure` and `/m/app/income` | £2,450/mo · £145,000 / £120,000 |
| `/m/app/tax-strategy` | £36,800 / £19,101 |
| `/m/app/personal-information` | Health "Yes, good health", smoking "Never smoked", education "Postgraduate" (W-0006) |

### 3.3 Native iOS (`Fynla-Staging`, csjones database only)

iOS cannot see local data. Passes A and B verify web and `/m` locally, then pick up
their iOS leg on the dev re-run after the PR. Screens to cover, both accounts:
dashboard, net worth and each category, property detail, savings, investment account
detail (the £47,500 check), retirement, protection, estate and bequests, goals and
life events, tax strategy, personal information.

Use the `ios-simulator` skill. Never open a simulator yourself; if none is available
and the skill's recovery ladder does not reach a working one, stop and ask CSJ.

---

## 4. Regression check per defect

One line per board item: the exact click path, and the observable that proves it
fixed. Run these **after** entry, on the surface that failed **and** on the other
surfaces — a fix in one place is the only kind Rule 20 accepts.

| Item | Click path | Observable that proves it fixed |
|---|---|---|
| **W-0006** health/lifestyle never persist | `/settings/health` → Edit → Yes / Never smoked / Postgraduate → Save → **hard reload** | Page reads "Yes, good health" / "Never smoked" / "Postgraduate Degree". `users.health_status`, `smoking_status`, `education_level` all non-NULL. Same three values render on `/m/app/personal-information`. |
| **W-0007** modal ignores Cash ISA usage | Enter David's £22,500 Cash ISA with £10,000 subscribed → **navigate directly to `/net-worth/investments` and hard-reload** → Add Account → ISA → "Show additional information" | Panel reads **Cash ISA £10,000 used, £10,000 remaining**. Typing 15000 into "Already Subscribed This Tax Year" raises an over-allowance error and blocks the save. |
| **W-0008** adviser fee cannot be entered | `/net-worth/investments` → Add Account → "Show additional information" | An adviser fee input exists; entering 0.75 persists `investment_accounts.advisor_fee_percent = 0.7500`; `FeeBreakdown.vue` shows 0.75%; the net-of-fees projection moves **down**. |
| **W-0009** holding update discards payload | Account → Edit → "Show additional information" → holding **Details** → fill Ticker/ISIN/Purchase Price/Current Price/OCF → Update Holding | `PUT /api/investment/holdings/{id}` carries a **non-empty** body; the `holdings` row shows FUND / GB00B41YBW71 / 85.50 / 99.86 / 0.95. A failed save shows an error instead of closing silently. Repeat from all three entry points. |
| **W-0010** no Add Pension with only a Defined Benefit pension | Fresh account → `/net-worth/retirement` → add the NHS Defined Benefit pension **first** | An "Add Pension" control is present afterwards; a Defined Contribution pension and a State Pension can both be added. Repeat for all four orders: Defined-Contribution-first, Defined-Benefit-first, State-Pension-first, mixed. |
| **W-0011** free tier cannot save expenditure | Free-tier account → `/valuable-info?section=expenditure` → Simple View → 2500 → Save | `users.monthly_expenditure = 2500`, `annual_expenditure = 30000`, `expenditure_entry_mode = 'simple'`. No 403 on `PUT /api/user/profile/expenditure`. The Detailed View is gated **before** entry, not after submit. |
| **W-0012** mortgage term hardcoded, rate fix date dropped | Enter all three mortgages per §1.1 | `mortgages.rate_fix_end_date` = 2027-04-01 / NULL / 2026-09-15. `remaining_term_months` = **156 / 180 / 216**, not 300, and consistent with each `maturity_date`. Check the standalone mortgage create/update path too, not only the wizard. |
| **W-0013** joint savings cannot be created | `/net-worth/cash` → Add Account → Nationwide, 4500, Ownership Type "Joint Owner", Joint Owner = Sarah | Saves with no 422. Row: `ownership_type='joint'`, `ownership_percentage=50`, `joint_owner_id` = Sarah, `current_balance = 4500` (the **full** balance). Both accounts show £2,250. |
| **W-0014** joint investment saves 100% | `/net-worth/investments` → Add Account → General Investment Account, AJ Bell, 95000, Ownership Type "Joint Owner" **on the create modal** → Sarah | The Joint Owner select **appears on create** and the choice survives the save. `investment_accounts.ownership_percentage = 50`. Card reads "Your Share (50.00%) £47,500". |
| **W-0015** one share computed three ways | Same account → compare `/net-worth/investments` card, `/net-worth/wealth-summary` Investments row, and `app(CrossModuleAssetAggregator::class)->calculateInvestmentTotal($id)` | All three read **£47,500** for both accounts. `InvestmentList.vue` renders the API's `user_share` with no client-side arithmetic. The joint badge shows for any account with a `joint_owner_id`, including a deliberate 100/0 split. The `100 → 50` fallback at `CalculatesOwnershipShare.php:73` is **removed**. |
| **W-0016** card names the viewer as co-owner | Log in as **Sarah** → `/net-worth/property` | The Willows card reads "Joint with **David Jones**". Also check the Manchester card as David: "**Mike Barrett**" (a non-spouse, free-text co-owner). Same check on chattels, savings, investments. |
| **W-0017** Defined Benefit form gaps | `/net-worth/retirement` → Add Pension → Defined Benefit | Form accepts Normal Retirement Age 60, Spouse Pension 50%, Inflation Protection **CPI** from an enum selector, and a career-average / public-sector scheme type. Row matches all seven persona fields. `HouseholdPlanningService.php:791` uses the recorded 50 rather than its `?? 50` fallback. |
| **W-0018** TierResolver docblock contradicts code | Gated on a CSJ decision | Do not test until CSJ decides (a) or (b). Report as **blocked on decision**, not as failed. |
| **W-0019** married users get mirror wills only | As David (married) → `/estate/will-builder` | The Simple Will option is **not offered**. Requesting anything other than a mirror will — including through Fyn — returns "we cannot do this, speak to a solicitor" with no proceed-anyway escape. Unmarried users still get the simple will. Check on web, `/m` and iOS. |
| **W-0020** charitable total checks a nonexistent enum | Enter David's £10,000 Cancer Research UK legacy → `/estate` | `WillAnalysisService::getCharitableBequestTotal()` returns **10000**, not 0. Then, with a temporary oversized legacy (see §2.5), the rate flips to **36%**; remove it afterwards. Grep for any other comparison against `'specific'`. |
| **W-0021** bare acronym on the trust card | `/trusts` after creating the discretionary trust | The badge reads "**Relevant Property Trust**" in full, matching `TrustsDashboard.vue:110-112`. Sweep the module for other acronyms. Check `/m` too. |
| **W-0022** letter denies an existing mortgage | Visit `/valuable-info?section=letter` **before** adding property → add The Willows with its £65,000 mortgage → return to the letter | The liabilities section names the £65,000 HSBC mortgage. It never reads "No outstanding liabilities recorded" while one exists. The consistency panel on the same page agrees. Check `beneficiary_info`, `children_education_plans`, `financial_guidance` and `immediate_funds_access` for the same frozen-at-creation fault. |
| **W-0023** will-builder gifts never become bequests | `/estate/will-builder` → step 5 Gifts → the three persona gifts → Complete & Finalise | `SELECT * FROM bequests WHERE will_id = <new will>` returns **rows**, with correct `bequest_type` (`percentage` / `specific_amount`), amounts, `beneficiary_name`, `priority_order` and `conditions`. Re-editing and re-completing **updates** rather than duplicates. `/m/app/estate/bequests` lists them. |
| **W-0024** mirror will copies executors | Complete David's mirror will naming Sarah executor → Generate Spouse's Will → log in as Sarah | Sarah's will appoints **David Jones**, never Sarah. Her charity is **British Heart Foundation**, not David's Cancer Research UK. Guardians are offered on her will too. `relationship` reads from her perspective. `wills.executor_name` is regenerated, not inherited. |
| **W-0025** joint chattel saves with no joint owner | `/net-worth/chattels` → Add Valuable → Ownership Type Joint → leave Joint Owner unset → Save | The save is **rejected server-side**, not just in the form. Then set Sarah and save: `chattels.joint_owner_id` = Sarah. Existing orphan rows (`ownership_type='joint'` with NULL `joint_owner_id`) are identified and reported. |
| **W-0026** policy end date silently dropped | `/protection` → Add Protection → Critical Illness with start 2020-01-01 and end 2040-01-01; then Life Insurance with the same dates | Both rows carry `policy_end_date = 2040-01-01` and `policy_start_date = 2020-01-01`. The Life form **has** date fields. Check `IncomeProtectionPolicy`, `DisabilityPolicy`, `SicknessIllnessPolicy` `$fillable` too. |
| **W-0027** single beneficiary, no joint-life flag | `/protection` → Add Protection → Life Insurance → open the Beneficiary select | **William and Charlotte appear** alongside Sarah. All three can be recorded with shares and persist. A joint-life control exists and saves `joint_life = 1`. Confirm what "Add Beneficiary" does — it may already be the intended route, making this discoverability rather than a missing capability. |
| **W-0028** `/m` goals page shows no life events | `/m/app/goals` with events recorded | Life events render, each with amount, date, impact direction and certainty. Verified on both accounts. Check whether the `/m` net-worth projection already consumes events through another endpoint. |
| **W-0029** goals and events cannot be past-dated | `/goals` → Add Goal, target date 2026-04-05; `/goals?tab=events` → Add Life Event, 2020-03-15 | Both save (assuming product-lead decides "yes"). Also confirm an **existing** goal or event can still be edited once its date passes — otherwise every goal eventually becomes read-only. If the decision is "no", the persona's dates need refreshing and that is a recorded decision, not a quiet tester edit. |
| **W-0035** (new, §7.2) target retirement income has no entry point | `/net-worth/retirement`, `/settings/personal`, `/plans/retirement` | An input exists that writes `retirement_profiles.target_retirement_income`. `GET /api/retirement/required-capital` then returns `income_source: "profile"` and `required_income: 75000` for David, `55000` for Sarah. |
| **W-0036** (new, §7.2) Defined Benefit counted as income in payment | As Sarah (48, Normal Retirement Age 60, £35,000 pension) → `/valuable-info?section=income` | Total annual income reads **£120,000** plus rental — **not £155,000**. `annual_pension_income` is **0**. Her Personal Allowance, income tax and Child Benefit position are recomputed. `required_income` falls to £90,000 (120,000 × 0.75) before W-0035, and to £55,000 after it. |
| **W-0037** (new, §7.2) bequest priority cannot be entered | `/estate/will-builder` → bequests → Add | A priority input exists; the persona's 1 / 2 / 2 persist to `bequests.priority_order`. Beneficiary type is recorded explicitly; a legacy to "Shelter" still counts as charitable. |
| **W-0038** (new, §7.2) goal essential / joint ownership cannot be entered | `/goals` → Add Goal | An "essential" toggle and an ownership control exist. Early Retirement Fund saves `is_essential = 1`. A joint goal saves ONE row with `joint_owner_id`, and both spouses see their share. |
| **W-0039** (Batch A, **high**) holding form has no units input | `/net-worth/investments` → account → Edit → "Show additional information" → holding **Details** | A quantity/units input exists. Entering 351 for Fundsmith persists `holdings.units = 351`, and units × price reconciles to the holding value per §2.8. **A faithful Pass A is blocked until this lands** — all ten of the persona's unit counts are unenterable even now W-0009 is fixed. |
| **W-0040** (Batch A, product-lead) deliberate 100/0 joint split | Set a shared asset's ownership percentage explicitly to 100 | Gated on a CSJ / product-lead decision — **do not test until decided**, and report as blocked, not failed. Note the current behaviour for the record: `SharedOwnership::primaryOwnerPercentage()` coerces an explicit 100 on a shared type to 50, and the backfill migration did the same to stored rows. The persona is unaffected — its joint assets are all 50/50 or tenants-in-common at 40%, both of which work. |

---

## 4.1 Independent confirmation of Batch A

Batch A (Ownership & Net Worth) is complete and **verified its own work in the
browser**. A fix agent verifying itself is not verification, so these are the checks
that must be re-run by someone who did not write the fix. Batch A's numbers look
right; the job here is to prove them without taking its word.

### 4.1.a What I already confirmed statically — do not redo

These were checked by reading the code on 2026-08-21, so the re-run can skip straight
to the live behaviour:

| Claim | Status |
|---|---|
| `app/Support/SharedOwnership.php` exists and is the single write rule | **Confirmed.** Read by 20 files: all four Store normalisers, both savings and both chattel FormRequests, `MortgageService`, `CrossModuleAssetAggregator`, `CalculatesOwnershipShare`, five controllers, and three tax strategies. |
| `resources/js/utils/ownership.js` is the single display rule | **Confirmed.** Imported by 6 web components + the investment store, and **5 `/m` views** (`Investment`, `InvestmentAccountDetail`, `NetWorthCategory`, `PropertyDetail`, `SavingsAccount`). |
| The `100 → 50` read-side fallback is **removed** from `CalculatesOwnershipShare.php` | **Confirmed.** Gone, with a comment naming W-0014 and W-0015 as the cause. The trait now trusts the stored percentage and derives the joint owner's share from `SharedOwnership::jointOwnerPercentage()`. |
| Existing 100/0 rows are repaired, not left stale | **Confirmed, and I had assumed otherwise.** `database/migrations/2026_08_21_000000_normalise_shared_ownership_percentage.php` rewrites shared rows stored at 100 to 50 across properties, savings, investments, mortgages and chattels; `business_interests` is deliberately excluded because there the percentage is a shareholding. It has run — the repro household's joint General Investment Account (`investment_accounts.id 14`) now reads `ownership_percentage = 50.00`, where R-08 recorded it at 100. |

**Why that last one mattered.** Removing the read-side rewrite without repairing stored
rows would have turned the £190,000 double-count into a *disappearance* — the primary
owner showing 100% and the spouse £0. The backfill closes it. Worth knowing so a
tester who sees a stale figure does not misdiagnose it.

### 4.1.b Check 1 — the joint-share consolidation, both accounts

The single most important check in the run. **Confirm the £190,000 double-count is
genuinely gone**, on web **and** `/m`, from **both** logins.

| Observable | David | Sarah |
|---|---|---|
| Joint AJ Bell General Investment Account card | "Your Share (50.00%) **£47,500**" of £95,000 | "Your Share (50.00%) **£47,500**" of £95,000 |
| **Sum across both accounts** | **£47,500 + £47,500 = £95,000** — never £190,000 | |
| Current Portfolio total | £172,500 | **£132,500** (was £180,000 before the fix) |
| `/net-worth/wealth-summary` Investments row | £172,500 | £132,500 — must agree with the card **to the penny** |
| Property card co-owner line | "Joint with Sarah Jones" | "Joint with **David Jones**" — not her own name |
| `/m` equivalents | same figures | same figures |

Cross-check the household roll-up separately: the joint account must appear **once**
at £95,000 in any household total, not twice. Then confirm the same for the joint
Nationwide current account (£2,250 each of £4,500), Premium Bonds (£25,000 each of
£50,000), the three joint chattels, and The Willows (£425,000 each of £850,000).

> Note from the live data: the joint savings account currently on the repro household
> (`savings_accounts.id 29`) has **Sarah as the primary owner** and David as
> `joint_owner_id`, the opposite way round from playbook §1.2. That is not a defect —
> primary owner is simply whoever entered it — but it makes this a useful second test
> case, because it exercises the joint-owner side of the arithmetic from David's login
> rather than Sarah's.

### 4.1.c Check 2 — a non-reproduction that needs DOM evidence

W-0014's repro claimed the **create** modal does not reveal the Joint Owner select, so
joint ownership was reachable only through the edit form. **It did not reproduce for
Batch A** — the select rendered and the ownership type survived the save.

This is exactly the class of defect the previous run could not see: it drove buttons
with **dispatched DOM click events**, which fire the same handlers but bypass overlay,
z-index, `pointer-events` and disabled-state checks. A select that is present in the
DOM but not actually clickable would have looked fine to that run and looks fine to a
snapshot.

So test it with **real pointer clicks**, and settle it either way:

1. `/net-worth/investments` → **Add Account** (create, not edit).
2. Choose Account Type "General Investment Account", provider AJ Bell, value 95000.
3. Set Ownership Type to **Joint Owner** with a real click.
4. **Capture the DOM at that moment** — is the Joint Owner select present, visible,
   enabled, and hit-testable at its centre point? Record `display`, `visibility`,
   `pointer-events`, `disabled`, and whether any element overlays it.
5. Select the spouse, save, and confirm `ownership_type` and `ownership_percentage`
   survived.

**If it reproduces:** raise it as its own board item with the DOM evidence — do not
reopen W-0014, which is fixed for its own stated cause.
**If it does not:** record the non-reproduction **explicitly** in the run report, with
the DOM evidence showing the select was reachable. Leaving it unstated turns it into
folklore that costs someone a day later.

### 4.1.d Check 3 — does an upgraded user's cap lift without re-login?

Batch A could not enter the tenants-in-common property or two of the three mortgages,
reporting a free-tier property cap. But server-side, `TeaserGate::allows(user 16,
'property')` returns **true** and premium carries `property => full`. So the block was
almost certainly **stale client-side capability state**, cached from before premium was
provisioned mid-session.

That distinction is commercially load-bearing: **a real customer who upgrades must see
their caps lift without logging out and back in.** Test it deliberately:

1. **Fresh login as premium.** Log out fully, log back in, and confirm the property cap
   is lifted — all three properties addable, both remaining mortgages addable.
   If this fails, the problem is server-side after all and is a much bigger item.
2. **In-session upgrade.** With a free-tier account in an open session, have the
   coordinator provision premium **without touching the browser**. Then, without
   reloading, attempt a capped action.
   - If the cap lifts: no defect. Record it.
   - If the cap persists until reload or re-login: **that is a genuine defect** and
     needs its own board item. Capture the capability payload the client is holding,
     the endpoint that would refresh it, and whether anything invalidates it on
     subscription change.
3. Either way, record which of the two it was — Batch A's blocker was diagnosed, not
   observed, and this run should settle it.

### 4.1.e Batch A's own stated evidence gaps — mine to close, not to assume

Batch A named these plainly. They are open, not passed:

| Gap | What closes it |
|---|---|
| **`/m` was not tested live** — code written and building, but local `/m` needs `public/m-build/` rebuilt | Per the `verify-m` skill, `/m` is verified on **csjones**, not locally. Ask the coordinator for the rebuild or run the dev leg. Until then `/m` is **untested**, and the five `/m` views importing `ownership.js` are unproven at runtime. |
| **iOS unchecked** | Dev leg, `Fynla-Staging`, per §3.3. |
| **Fee-drag movement not measured** after entering an adviser fee | W-0008's acceptance requires the projected value to move **down** by roughly the right magnitude. Enter 0.75 on David's Stocks & Shares ISA and measure the before/after, don't just confirm the field persists. |
| **The non-spouse co-owner was not verified live** | The Manchester property, tenants in common, David 40%, **Mike Barrett 60%**. This is the single record the whole ownership question turns on: David must see **£118,000**, Sarah must see the property **not at all**, and **£177,000 must appear nowhere** in either account or any household total. Also confirm the co-owner line names Mike Barrett — that is W-0016's non-spouse case, which used a different code path from the linked-spouse case. |

---

## 5. Pass B — entry through Fyn on `/m`

Pass B proves the capture handlers, not the forms. On `/m` and native, **Fyn drives
the input**, so that is how it must be tested. The verification afterwards is
identical to Pass A: §2 for the figures, §3 for the matrix.

**Before starting:** the coordinator tears the household down and confirms zero rows
for both emails. A pass that inherits Pass A's rows proves nothing about its own
entry route.

### 5.1 How the surface works

- One endpoint for both surfaces: `POST /api/ai-chat/conversations/{id}/messages` →
  `AiChatController::sendMessage`. Read/write dispatch is **server-side**; `/m` must
  not branch client-side.
- Write intents in advice mode route through the unseen `delegate_to_capture` handoff
  into `OnboardingChatDirector::handleInlineCapture` and then the same direct-write
  handlers in `CoordinatingAgent`. The synthetic `handoff` SSE event never reaches the
  frontend. If a "capturing" pill, a `persona_state_change` event, or any UI that
  distinguishes the two states appears, that is a canonical-contract violation
  (INV-2.4.1) and a defect in itself.
- Fyn speaks in plain text. **No icons, emoji or glyphs anywhere in the chat window**
  (Rule 15) — message text, quick replies, header chrome, system messages, streaming
  indicators, buttons. The Fyn avatar is always allowed and is never a violation.

### 5.2 The capture surface Fyn actually has

Confirmed in `app/Agents/CoordinatingAgent.php`:

`create_property` · `create_mortgage` · `create_savings_account` ·
`create_investment_account` · `create_holding` · `create_pension` ·
`create_protection_policy` · `create_chattel` · `create_goal` · `create_life_event` ·
`create_trust` · `create_will` · `create_estate_gift` · `create_family_member` ·
`create_liability` · `create_asset` · `create_business_interest` ·
`create_power_of_attorney` · `set_expenditure` · `update_profile` · `update_record` ·
`update_will` · `capture_personal_details` · `capture_spouse_details` ·
`capture_dependants` · `capture_retirement_goals` · `capture_state_pension` ·
`capture_charitable_giving` · `capture_work_details` · `capture_pension_history` ·
`capture_salary_sacrifice`

**No handler writes the letter to loved ones.** If the persona's letter cannot be
entered through Fyn, that is a Pass B gap to report, not a tester failure.

**`capture_retirement_goals` is the only writer of
`retirement_profiles.target_retirement_income`** (`CoordinatingAgent.php:5628`).
Pass B is therefore the **first** chance in the whole run to set £75,000 / £55,000 —
make it an explicit check, not an afterthought.

### 5.3 The conversation script

Say these to Fyn in order, as a user would. Do not paste structured data; the point is
that natural phrasing reaches the right handler. After **every** turn: screenshot the
chat window including the turn that writes, then confirm the DB row.

| # | Say to Fyn | Handler expected | DB row to confirm |
|---|---|---|---|
| 1 | "I'm David Jones, born 8 November 1976, married, I live at 15 Chestnut Lane, Guildford, Surrey GU1 4RH." | `capture_personal_details` / `update_profile` | `users` name, dob, marital_status, address |
| 2 | "I'm a Finance Director at Global Finance Corp earning £145,000 a year." | `capture_work_details` | `users.employer`, `occupation`; income £145,000 |
| 3 | "I'm in good health and I've never smoked. I have a postgraduate degree." | `update_profile` | `health_status`, `smoking_status`, `education_level` |
| 4 | "My wife is Sarah Jones, born 22 April 1978, a GP Partner at Surrey NHS Trust earning £120,000." | `capture_spouse_details` | spouse record |
| 5 | "We have two children — William born 15 September 2007 and Charlotte born 28 February 2010. Both dependent." | `capture_dependants` / `create_family_member` | 2 `family_members` |
| 6 | "I want to retire at 60 on £75,000 a year." | **`capture_retirement_goals`** | `retirement_profiles.target_retirement_age = 60` **and `target_retirement_income = 75000`** |
| 7 | "Our home, The Willows at 15 Chestnut Lane Guildford, is worth £850,000. We own it jointly 50/50. We bought it in April 2012 for £625,000." | `create_property` | 1 row, £850,000, joint, 50, `joint_owner_id` = Sarah |
| 8 | "There's an HSBC repayment mortgage on it, £65,000 left, 4.29% fixed until April 2027, £550 a month, 156 months to run." | `create_mortgage` | £65,000, repayment, 4.29, rate_fix_end_date 2027-04-01, **remaining_term_months 156** |
| 9 | "We also own a Buy-to-Let flat — Flat 42 Riverside Apartments, London SE1 8XX — worth £425,000, joint, rented for £1,800 a month." | `create_property` | £425,000, joint 50 |
| 10 | "And a Manchester Buy-to-Let, Unit 12 Victoria Mill Ancoats M4 6AG, worth £295,000. **I own 40% as tenants in common with Mike Barrett who owns 60%.** It rents for £1,350 a month." | `create_property` | £295,000, `ownership_type='tenants_in_common'`, `ownership_percentage=40`, `joint_owner_name='Mike Barrett'`, `joint_owner_id` **NULL** |
| 11 | "I have £25,000 in an HSBC current account, and a Nationwide Cash ISA with £22,500 in it — I've put £10,000 in this tax year." | `create_savings_account` ×2 | 2 rows, `isa_subscription` 10000 |
| 12 | "We have a joint Nationwide current account with £4,500, and £50,000 in joint Premium Bonds with NS&I." | `create_savings_account` ×2 | both joint 50, full balances 4500 and 50000 |
| 13 | "I have a Hargreaves Lansdown Stocks & Shares ISA worth £95,000, and a joint AJ Bell General Investment Account worth £95,000." | `create_investment_account` ×2 | ISA individual 95000; GIA **joint, ownership_percentage 50** |
| 14 | "The AJ Bell account holds 625 units of iShares Core MSCI World at £80, 1,316 of Vanguard UK Gilt at £19, and 84 of iShares Physical Gold at £238." | `create_holding` ×3 | 3 `holdings` with units and prices |
| 15 | "I've got £30,000 in Venture Capital Trusts." | `create_investment_account` | VCT 30000 individual |
| 16 | "My workplace pension with Fidelity is worth £180,000 — I pay in 8% and my employer matches 8%. I also have an AJ Bell SIPP worth £320,000. I want to retire at 60." | `create_pension` ×2 | 2 `dc_pensions`, £180,000 and £320,000 |
| 17 | "My State Pension forecast is £221.20 a week and I have 30 qualifying years." | **`capture_state_pension`** | weekly 221.20, qualifying_years 30 |
| 18 | "We have a Vitality joint life level term policy for £500,000, £85 a month, written in trust, running from January 2020 to January 2040. Sarah and both children are the beneficiaries." | `create_protection_policy` | £500,000, `in_trust=1`, **`joint_life=1`**, start/end dates, 3 beneficiaries |
| 19 | "And a Legal & General standalone critical illness policy, £200,000, £125 a month, same dates." | `create_protection_policy` | £200,000, **`policy_end_date = 2040-01-01`** |
| 20 | "We own a £35,000 art collection jointly, a 1967 Jaguar E-Type worth £85,000 that's mine, a Georgian writing desk worth £8,500 jointly, £4,500 of first edition books that are mine, and a BMW X5 worth £42,000 jointly." | `create_chattel` ×5 | 5 rows; every joint one has `joint_owner_id` set (W-0025) |
| 21 | "We spend about £2,450 a month — £450 food, £150 transport, £1,000 school fees…" (read the full list) | `set_expenditure` | `monthly_expenditure = 2450`, 15 categories |
| 22 | "I settled a discretionary trust in September 2020 for the children's education — the Jones Children's Education Trust, now worth £185,000." | `create_trust` | £185,000, discretionary, relevant property trust derived |
| 23 | "I want a mirror will leaving everything to Sarah, with Sarah and Barclays Wealth as executors." | `create_will` | `wills` row, `will_type='mirror'` |
| 24 | "Leave £10,000 to Cancer Research UK, and split the rest 50/50 between William and Charlotte, held in trust until they're 25." | `create_estate_gift` | **`bequests` rows** — the W-0023 check on the Fyn path |
| 25 | "I want to save £40,000 by September 2027 to help William with a house deposit — I'm putting away £500 a month and I've got £28,000 so far." | `create_goal` | goal 40000 / 28000 / 2027-09-01 |
| 26 | "We're planning a kitchen extension in April 2027 costing £85,000." | `create_life_event` | £85,000, expense, 2027-04-01, likely |
| 27 | "I have a Lasting Power of Attorney." | `create_power_of_attorney` | LPA row |

Then repeat the household-relevant turns **as Sarah** in her own conversation: her
income, her Cash ISA, her Stocks & Shares ISA, her NHS Defined Benefit pension, her
State Pension, her engagement ring, her mirror will and her bequests, and
**"I want to retire at 60 on £55,000 a year"**.

### 5.4 What the transcript itself must show

Beyond the DB rows, the conversation is evidence in its own right:

- Fyn **confirms back the figure it wrote**, so the user can catch a mistranscription.
- Fyn never invents a value the user did not give. Any number in a confirmation that
  is not traceable to the user's words is a fabrication defect.
- Ownership language is correct and consistent: the Manchester property is described
  as 40% tenants in common with Mike Barrett, not as joint with Sarah.
- Mid-conversation questions follow the decided policy: simple ones answered inline
  and definitively, complex ones acknowledged and deferred, never re-raised.
- Asking for anything other than a mirror will (married user) gets the W-0019 refusal.
- No icons or emoji anywhere in the chat.

---

## 6. Pass C — entry through Fyn on native iOS

Pass C is **dev-only start to finish**: the `Fynla-Staging` scheme reads the csjones
staging database, and there is no local iOS run. Testers register on
`csjones.co/fynla`, never `fynla.org` — an account created on production does not
exist in the staging database and login returns 401 with audit
`reason: user_not_found`.

The conversation script is **identical to §5.3**, because Rule 20 means there is one
Fyn behind one endpoint and the native app must reach exactly the same answers. That
identity is itself the test: any turn where native captures a different value, asks a
different question, or produces different confirmation wording than `/m` did in Pass B
is a Rule 20 violation.

**Additionally on native, check per turn:**

| Check | Why |
|---|---|
| The stream renders progressively and completes | Native is a third SSE consumer; a missing event type is the classic Rule 20 failure |
| Every route Fyn emits resolves on native | A `navigation` event pointing at a route native does not have is a dead end |
| Fresh **and** resumed conversations both capture | "All surfaces" means all paths — first turn, repeat turn, every dispatch branch |
| The chat loads the existing transcript when logged in, never an empty box | — |
| No icons, emoji or glyphs in the chat | Rule 15; the Fyn avatar is exempt |
| Figures match `/m` and web to the penny afterwards | §3.2, §3.3 |

Use the `ios-simulator` skill; never open a simulator yourself. Once green on dev,
deploy to TestFlight per `ios-native/TESTFLIGHT.md`.

**The native paywall cannot work and is not a defect of this run.** There are zero
in-app purchase products in App Store Connect for either app record, so StoreKit
returns nothing and the paywall reads "Premium subscriptions are unavailable". That is
configuration, not code. Premium for the persona must be provisioned by the
coordinator on the csjones database, exactly as locally.

---

## 7. Persona lines with no home in the UI

These are lines in `peak_earners.md` that no Pass A screen can currently accept. Each
needs either a fix, or an explicit decision that it is out of scope. **None of them
justifies editing the persona file.**

### 7.1 Already on the board

| Persona line | Item |
|---|---|
| Adviser fee 0.75% on four investment accounts | W-0008 |
| All ten holdings' ticker / ISIN / units / prices / OCF | W-0009 |
| David's and Sarah's State Pension (unreachable when a Defined Benefit pension is entered first) | W-0010 |
| Mortgage remaining term and rate fix end date | W-0012 |
| Joint Nationwide current account and joint Premium Bonds | W-0013 |
| Defined Benefit Normal Retirement Age, Spouse Pension %, CPI inflation protection, career-average scheme type | W-0017 |
| All six will bequests | W-0023 |
| Life and critical illness policy start and end dates | W-0026 |
| Life policy's three beneficiaries and joint-life flag | W-0027 |
| Two goals and two life events with past dates | W-0029 |
| All ten holdings' **unit counts** (separate from W-0009 — the field itself is missing) | W-0039 |

### 7.2 New — raise as board items

**W-0035 — Target Retirement Income has no module-UI entry point, so every retirement
projection for this household runs on a derived figure the user never chose.**

- Persona: David "Target Retirement Income £75,000"; Sarah "£55,000".
- `retirement_profiles.target_retirement_income` exists as a column.
- It is written by exactly two places: `OnboardingService.php:482` and `:498` (which
  set only `target_retirement_age`, never the income) and
  `CoordinatingAgent.php:5628` (Fyn's `capture_retirement_goals`).
- There is **no form field and no API route** that sets it. `users` has no such
  column at all.
- `RequiredCapitalCalculator.php:121-132` therefore always falls back to
  `(gross income − pension contributions) × 75%`, giving David **£100,050** and Sarah
  **£116,250** instead of £75,000 and £55,000 — every retirement projection,
  decumulation figure, capital-adequacy readout and Monte Carlo run for this household
  is built on the wrong target.
- Secondary, and a smaller point: `CapitalAdequacyTab.vue:323` and
  `PensionList.vue:593` end their fallback chain in a **hardcoded `35000`**. The key
  they read (`profile.target_retirement_income`) is the right one — `profile` is the
  `RetirementProfile` from `GET /api/retirement` (`RetirementController.php:95,125`),
  not the user profile — so the hardcode only fires when both the API's
  `required_income` and the profile value are absent. It is still a planning
  assumption living in a component rather than in configuration, and worth fixing
  alongside the entry point.
- Owner: `build-lead`. Severity: high.

**W-0036 — A Defined Benefit pension is counted as income in payment from the day it
is entered.**

Found by reverse-engineering Sarah's £116,250 target: £116,250 ÷ 0.75 = £155,000, and
£155,000 − £120,000 = £35,000 — exactly her NHS pension.

- `UserProfileService::calculateAnnualPensionIncome()`
  (`app/Services/UserProfile/UserProfileService.php:338-356`) adds **any** Defined
  Benefit pension with a non-zero `accrued_annual_pension` to current income. No age
  check, no Normal Retirement Age check, no in-payment flag. The state pension branch
  four lines below **does** gate correctly on `already_receiving`, so the two halves of
  one function disagree.
- The docblock says "Includes DB pensions (**if in payment**)". The code does not do
  that.
- The value it reads is a future figure: `DBPensionForm.vue:89` labels the input
  "**Annual Income at Retirement (£)**" and `dbPensionFields.js:56` maps it to
  `accrued_annual_pension`. The table also has a separate
  `projected_annual_pension_at_nra_gbp` column, so the schema already distinguishes
  the two and the form writes into the wrong one.
- Sarah is **48**; her Normal Retirement Age is **60**. She is treated as receiving
  £35,000 a year today.
- This corrupts more than the retirement target: `total_annual_income` £155,000 pushes
  her past the £125,140 additional-rate threshold and through the whole Personal
  Allowance taper, so her modelled income tax, net income and Personal Allowance are
  wrong, and the Child Benefit / High Income Child Benefit Charge position computed at
  `UserProfileService.php:609` is wrong too.
- **Fix this before W-0035.** Setting an explicit £55,000 target would override the
  derived £116,250 and hide the phantom income on the retirement screen, while it kept
  corrupting tax and Child Benefit.
- Owner: `build-lead`. Severity: high.

**W-0037 — Bequest form cannot record priority order.**

- Persona: every one of the six bequests carries a Priority (1 for the charity, 2 for
  the children).
- `bequests.priority_order` exists as a column.
- `BequestForm.vue` exposes only `beneficiary_name`, `bequest_type`,
  `percentage_of_estate`, `specific_amount`, `specific_asset_description` and
  `conditions`. No priority input, and no beneficiary-type or charity-registration
  input either — charitable status is inferred from the beneficiary's **name**
  (`Bequest::isCharitable()` matches on words like "cancer" and "foundation"), which
  happens to work for both persona charities but would not for a charity whose name
  contains none of those words.
- Owner: `build-lead`. Severity: medium. Overlaps W-0023 — fix together.

**W-0038 — Goal form cannot record "essential" or joint ownership.**

- Persona: goal 6 "Essential: Yes"; goals 2, 3 and 6 "Ownership: Joint".
- `goals` has `is_essential`, `ownership_type`, `joint_owner_id` and
  `ownership_percentage` columns.
- `GoalFormModal.vue` has v-models for `goal_name`, `goal_type`,
  `custom_goal_type_name`, `description`, `target_amount`, `current_amount`,
  `target_date`, `monthly_contribution`, `priority`, `show_in_projection`,
  `show_in_household_view` and the first-time-buyer fields — **but none for
  `is_essential` or ownership**. A joint goal therefore cannot be created, so no goal
  splits between the spouses.
- Owner: `build-lead`. Severity: medium. Batch D's surface — coordinate with W-0029.

### 7.3 By design — do not raise

| Line | Why |
|---|---|
| Goal streaks (36 and 60 months) | Earned through recorded contributions, not typed |
| Relevant Property Trust status | Derived from `trust_type = discretionary` |
| Domicile status "UK domiciled" | Derived; `DomicileInformation.vue` takes country of birth and UK arrival date |

### 7.4 Persona-file inconsistencies — for product-lead, not for the tester to fix

1. **Expenditure £50 short.** Headline £2,500/month; the fifteen categories sum to
   £2,450. (§2.4)
2. **Net worth range.** Header says £1.5m–£2m; the data gives £2,228,780 including
   pensions, £1,728,780 excluding. Only the ex-pensions reading fits. (§2.2)
3. **Holdings do not sum to account values.** −£85 to +£28 across the four accounts,
   and two allocation percentages are transposed in David's Stocks & Shares ISA
   (36.8 / 36.9). Rounding, not error. (§2.8)
4. **State Pension has no owner.** One block, no "Owner: Spouse" marker, in a persona
   where every other spouse-owned record carries one. This playbook enters it on both
   accounts. (§1.4)
5. **The charitable legacies cannot demonstrate the 36% rate.** £20,000 against a
   £107,878 threshold. W-0020 needs a two-part verification. (§2.5)
6. **Early Retirement Fund describes retiring at 58**, but both target retirement ages
   are 60. Enter as written; note the mismatch.
7. **The stated ages are stale.** The persona says David is 48 and Sarah 46, but from
   their dates of birth they are **49** and **48** on 2026-08-21. Harmless — age is
   derived from date of birth, never entered — but do not type the stated ages
   anywhere, and expect the app to show 49 / 48.

---

## 8. Sequencing the re-run

0. **Blocked until W-0039 lands** — the holding form has no units input, so all ten
   holdings are unenterable and §2.8 cannot be verified. Everything else can proceed;
   holdings entry cannot.
1. Coordinator tears down David (16) and Sarah (17) and confirms zero rows.
2. Coordinator provisions premium on both fresh accounts.
3. **Pass A** — §1 entry on web, §2/§3 verification on web and `/m`, §4 regression
   checks. Loop until green per Rule 14.
4. PR to dev; re-run Pass A on csjones; **pick up the iOS leg there**.
5. Teardown. **Pass B** — §5 on `/m`, same verification.
6. PR to dev; re-run Pass B on csjones; iOS leg there.
7. Teardown. **Pass C** — §6, dev only, then TestFlight.

Report per pass, never averaged. A green Pass A and a red Pass B is exactly what the
run exists to find.

---

## SOURCE OF TRUTH — CSJ ruling, 2026-08-21

**`tests/Persona/peak_earners.md` is the only source for persona figures. Never the PDF.**
Where a precomputed expected value in this playbook disagrees with the markdown, the
markdown wins and the playbook is corrected. The PDF's internal inconsistencies
(expenditure £2,500 vs £2,450; the net-worth range) are out of scope and are not defects.
