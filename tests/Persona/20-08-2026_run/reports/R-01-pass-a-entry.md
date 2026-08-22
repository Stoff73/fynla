# R-01 — Pass A, data entry (local, web forms)

**Run:** `peak_earners`, Pass A · **Environment:** local `http://localhost:8000`
**Accounts:** David Jones (id 16, primary) · Sarah Jones (id 17, spouse)
**Started:** 2026-08-20 21:44 · **Entry ended:** 2026-08-20 23:05
**Written:** 2026-08-21 08:07 — **backfilled.** The run-report rule was issued after
this work was done. See "Not done, and why".

---

## Done

### Accounts and household

| Step | Result | Verified by |
|---|---|---|
| Registered David Jones, MFA from DB | user id 16 | DB row |
| Personal details: DOB 1976-11-08, male, married, The Willows / 15 Chestnut Lane / Guildford / Surrey / GU1 4RH, Finance Director, Global Finance Corp, employed, retirement age 60, UK domiciled | all persisted | DB row |
| Children: William Jones 2007-09-15, Charlotte Jones 2010-02-28, both dependent | 2 `family_members` | DB row |
| Registered Sarah Jones, MFA from DB | user id 17 | DB row |
| Sarah personal details: DOB 1978-04-22, female, married, same address, GP Partner, Surrey NHS Trust, employed, retirement age 60, UK domiciled | all persisted | DB row |
| Linked the two accounts (Family tab, spouse by email) | `users.spouse_id` 16↔17 reciprocal; `SpousePermission` **accepted both ways**; reciprocal `FamilyMember` created | DB rows |

Ages shown by the app (William 18, Charlotte 16, David 49, Sarah 48) are correct for
the run date. The persona file's stated ages are a snapshot from when it was written;
**date of birth is the contract**, and every DOB matches.

### Records entered and verified against the database

| Record | Persona line | Stored | Ownership |
|---|---|---|---|
| The Willows, main residence | :75-99 | `current_value` 850000.00, purchase 625000.00, 2012-04-01 | `joint`, pct 50.00, `joint_owner_id` 17 — **ONE row** (Rule 6) |
| HSBC mortgage on it | :154-166 | balance 65000.00, original 450000.00, repayment, 4.29%, fixed, £550/mo | `joint`, pct 50.00, joint 17 |
| David HSBC current account | :201-209 | 25000.00 | individual 100% |
| David Nationwide Cash ISA | :231-240 | 22500.00, 4.25%, subscription 10000.00, year 2026/27 | individual 100% |
| Sarah Barclays current account | :211-219 | 6280.00 | individual 100% |
| Sarah Nationwide Cash ISA | :242-251 | 22500.00, 4.25%, subscription 10000.00, year 2026/27 | individual 100% |
| Sarah Hargreaves Lansdown Stocks & Shares ISA | :288-298 | 85000.00, platform fee 0.4500, risk medium | individual 100% |
| Joint AJ Bell General Investment Account | :306-316 | 95000.00, platform fee 0.2500, risk upper_medium, `joint_owner_id` 17 | `joint` but **pct 100.00 — WRONG**, see W-0014 |
| Global Finance Corp Pension (Fidelity) | :344-358 | 180000.00, salary 145000.00, employee 8%, employer 8%, retirement 60 | individual |
| David's SIPP (AJ Bell) | :360-370 | 320000.00, retirement 60 | individual |
| Sarah NHS Defined Benefit pension | :382-394 | accrued 35000.00, service 18 yrs, lump sum 105000.00 | individual |
| Sarah's engagement ring | :477 | 18000.00, purchase 12000.00, `chattel_type` jewelry | individual 100% |
| Sarah's ISA goal | :623-635 | target 400000, current 120000, 2035-04-05, module investment, priority high, £1,667/mo | individual |
| David income | :19 | `annual_employment_income` 145000 | — |
| Sarah income | :52 | `annual_employment_income` 120000 | — |

Every row above was read back with `php artisan tinker` after saving. Nothing in this
table was accepted on the strength of what the screen said.

---

## Not done, and why

### Screenshots — the honest position

**This entry phase has almost no visual evidence.** Six screenshots exist for the
whole run, and all six were taken during the *verification* phase (R-02), not during
entry. No screenshot exists of any form being filled, of any submit, or of any
post-submit confirmation.

Cause: the screenshot rule and the run-report rule were both issued after this work
was complete. I am **not** retro-fabricating screenshots for steps already past — the
instruction was explicit about that, and a screenshot taken now would not evidence the
state the check ran in.

Consequence to be plain about: the DB evidence for every row above is solid and
reproducible, but if CSJ wants to *see* the entry happening, this phase has to be
re-run. Everything from R-02 onward is captured as it happens.

Also, existing files are `.jpg` not `.png` — the capture tool emits JPEG. Renaming the
extension would misrepresent the format, so they are left as-is. Numbering has gaps
(01, 05, 06, 07, 08, 09) because the sequence was planned around a full capture set
that the rule change now supersedes.

### Persona records NOT entered

| Not entered | Why |
|---|---|
| City Centre Flat, Manchester Investment Property (+ 2 mortgages) | Free tier caps `property` at **1** |
| Joint Current Account £4,500; Premium Bonds £50,000 | W-0013 — joint savings cannot be created at all |
| David's Stocks & Shares ISA £95,000; Venture Capital Trust £30,000 | Free tier caps `investment` at **2** |
| David's State Pension; Sarah's State Pension | Free cap `pension_account` 2 (David); W-0010 dead-end (Sarah) |
| All 10 holdings' ticker / ISIN / units / cost-per-unit / price-per-unit / OCF | W-0009 — payload discarded; and no units input exists |
| Adviser fee 0.75% on all four investment accounts | W-0008 — no input exists |
| David's expenditure (£2,500 and the 15 categories) | W-0011 — free tier cannot save any expenditure |
| Both wills, all 6 bequests, the £185,000 trust, LPA, letter to loved ones | Premium gate on `/estate/*`, `/trusts`, `/valuable-info?section=letter` |
| 5 of 6 goals; all 10 life events | Free caps `goal` 2, `life_event` 1 |
| Both protection policies; 5 of 6 chattels | Not reached before the run halted on the tier blocker |
| Target retirement income (David £75,000, Sarah £55,000) | **I could not find any entry point.** The app derives it instead — David £100,050, Sarah £116,250 |

### Fields the forms silently could not hold

- Defined Benefit pension: no Normal Retirement Age, no Spouse Pension %, and only a
  numeric revaluation rate where the model wants a CPI/RPI/fixed/none enum. Sarah's row
  landed `normal_retirement_age NULL`, `spouse_pension_percent NULL`,
  `inflation_protection 'none'` against a persona saying 60 / 50% / CPI. No
  career-average or public-sector option either, so the NHS 2015 scheme had to be
  entered as `final_salary`. **Not yet written up as a W-item** — awaiting team-lead
  direction on whether to fold it into one item.
- DC pension quick-add form has no platform fee field (persona: 0.35% and 0.25%).

---

## Assumptions

1. **Joint records are owned primarily by David, with Sarah as `joint_owner_id`.** The
   persona names no primary owner; David is the primary account. If wrong, every
   ownership column needs re-entering.
2. **"Remaining Term 156 months" was entered as maturity date 2039-08-20** (exactly 156
   months from the run date) because the wizard offers a date, not a term. W-0012 is
   written on that basis.
3. **Entering the NHS 2015 career-average scheme as `final_salary`** was the only option
   available, not a correct classification.
4. Sarah's Stocks & Shares ISA was given no current-year subscription, since the persona
   specifies £10,000 only against the Cash ISAs.

---

## Needs

**One decision, and it halts the pass:** should Pass A run on **premium** accounts?

Both users resolve to `tier=free`. Free caps vs persona requirement:

| Entity | Free cap | Persona needs |
|---|---|---|
| property | 1 | 3 |
| savings_account | 2 | 6 |
| investment | 2 | 4 |
| pension_account | 2 | 5 |
| goal | 2 | 6 |
| life_event | 1 | 10 |

I will not grant a tier myself — that is the "never patch a DB row" line, and a real
payment flow is out of bounds for me. Tell me the sanctioned provisioning route.

Note: the seeded preview personas are also `tier=free` but bypass the gate via
`is_preview_user`, so they are no guide to the intended tier for a real account.

---

## Noticed

- **The persona file contradicts itself.** The 15 expenditure categories at :488-504
  sum to **£2,450**; lines :25 and :486 both state £2,500. I did not edit the file.
- **`Current State/Retirement.md` is stale** — it claims
  `app/Services/Retirement/ContributionOptimizer.php` has hardcoded tax bands. No such
  file exists. Routed to: archivist (vault correction).
- **The £125,140 additional-rate threshold shown on `/m` is NOT a hardcoded-tax
  defect** — it resolves from `TaxConfigService`; the `?? 125140` occurrences are
  post-read fallbacks. Checked before raising; deliberately not raised.

---

## Tooling deviation — declared, not hidden

Playwright MCP is declared in `.mcp.json` (`--browser chrome`, headed) but **its tools
are not exposed to this agent session** — re-checked after the rule change, still
absent. The run is driven through `mcp__claude-in-chrome__*` against CSJ's own real,
headed Chrome instead.

What that means for the visibility rule:
- The window is CSJ's actual Chrome, not headless — `list_connected_browsers` reports
  `isLocal: true`, and `resize_window` calls (1600x1100, 1500x1000, 430x930 for `/m`)
  succeeded, which only works on a real OS window.
- `request_access` reported Chrome windows present on **both** displays (27E6QC and
  the built-in Retina).
- **I could not take a desktop screenshot to confirm the window is unobscured and
  frontmost.** `request_access` returned `granted` for Google Chrome, but
  `list_granted_applications` came back with an empty allowlist immediately after, so
  `screenshot` refused. Flagging rather than claiming a check I did not complete.

Button activation uses dispatched DOM click events, because the extension's synthetic
pointer clicks proved unreliable on this app's Vue buttons; field entry uses the
extension's `form_input` or a native-setter plus `input`/`change` dispatch. Both fire
the same handlers and produce the same HTTP requests. Every write was confirmed
against the MySQL row.
