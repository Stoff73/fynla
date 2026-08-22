# R-05 — Premium sweep: the newly-unlocked estate surfaces

**Run:** `peak_earners`, Pass A continued · **Environment:** local `http://localhost:8000`
**Purpose:** **defect discovery, not fidelity entry** (team-lead instruction). Entry
work is disposable; the board items are the output.
**Account:** David Jones (16), **tier premium**
**Ran:** 2026-08-21 08:30 – 09:20

**Provisioning, recorded so it is never mistaken for a finding:** premium was granted
**by team-lead**, not by me, replicating the app's own sanctioned test-support shape
from `app/Http/Controllers/TestSupport/E2EController.php:163-176` —
`users.plan='premium'`, `users.tier='premium'`, plus an active `Subscription` row
(premium, monthly, 699, period start now, end +1 month, active, auto_renew). Verified:
`TierResolver::resolve()` returns `premium` for users 16 and 17. I did not and must
not provision tiers.

---

## Done — surfaces swept

| Surface | Result |
|---|---|
| `/estate` + IHT calculation | **GREEN** — hand-verified to the pound |
| Charitable 36% rate threshold | **GREEN** — £40,928, correctly excludes RNRB from the baseline |
| `/estate/will-builder` (10 steps, completed) | mostly GREEN — **W-0023** |
| Charitable bequest total | **W-0020** |
| `/trusts` — £185,000 trust created | GREEN data — **W-0021** (copy) |
| `/valuable-info?section=letter` | mostly GREEN — **W-0022** |
| `/estate/power-of-attorney` | **GREEN** — clean, Rule 9 compliant |
| `/holistic-plan` tax recommendations | **GREEN** — exact to the pound |
| Expenditure at premium | **GREEN** — confirms W-0011 was tier-gated |
| Financial commitments joint split | **GREEN** — correct 50/50, no double-count |
| `TierResolver` contract | **W-0018** |

### GREEN checks worth recording in detail

**IHT, hand-recomputed against the persona and the database:**

```
user_gross_assets      520,000   (425,000 property + 47,500 savings + 47,500 investments)
spouse_gross_assets    604,280
total_gross_assets   1,124,280
liabilities             65,000   (32,500 each)
total_net_estate     1,059,280
NRB   325,000 x2       650,000
RNRB  175,000 x2       350,000
total_allowances     1,000,000
taxable_estate          59,280   = 1,059,280 - 1,000,000        MATCHES UI
iht_liability           23,712   = 59,280 x 40%                 MATCHES UI
```

DC pensions (£500,000) correctly **excluded** under current rules. The 2027 pension
amendment is modelled separately and correctly: net estate £1,559,280, IHT £223,712,
additional £200,000 = £500,000 x 40%.

Every allowance comes from `TaxConfigService`
(`IHTCalculationService.php:95, :129, :1191, :1737`) — no hardcoded values. Rule 2 OK.

**Charitable threshold:** baseline £1,059,280 − £650,000 NRB = £409,280; 10% = £40,928.
Correctly excludes RNRB from the baseline, which is the HMRC rule and easy to get
wrong.

**Holistic plan pension recommendation — exact:**

```
existing contributions   145,000 x 16%  = 23,200
annual allowance         60,000
headroom                 36,800   <-- app recommends exactly £36,800
relief: 19,860 @45% + 16,940 @40%       = 15,713
PA reclaim: PA at 108,200 = 8,470 @40%  =  3,388
TOTAL                                   = 19,101  <-- app says exactly £19,101
```

The engine models the Personal Allowance taper reclaim correctly.

**Financial commitments split — the joint logic working properly:**

```
property monthly costs  700   (320+95+70+40+45+30+100)
plus mortgage           550
household             1,250
each spouse at 50%      625   <-- app shows 625 / 625
household counted once 1,250  <-- app shows 1,250, NOT 2,500
David total  2,450 + 625 = 3,075   MATCHES UI
Household    2,450 + 1,250 = 3,700 MATCHES UI
```

A useful contrast with W-0014/W-0015: property-derived commitments split correctly on
both sides and are counted once at household level. Investments do not.

**Cashflow surplus responds correctly:** £7,406 before expenditure → £4,956 after,
a £2,450 drop exactly matching the manual expenditure entered.

**Will builder quality (aside from W-0023):** personal details correctly prefilled
from the profile; minor-child detection correct (listed Charlotte 16, excluded William
18); a genuinely useful "children under 18 but no guardian" warning; sound legal prose.

**Letter consistency checks:** genuinely good cross-module validation — caught the
executor mismatch between letter and will, and flagged missing property and liability
details. The executor card prefers the will's value and labels it "From Will".

---

## Not done, and why — the five defects found

| W | Sev | Expected vs actual | Screenshot |
|---|---|---|---|
| **W-0018** | low | `TierResolver` docblock says "explicit `users.tier` wins"; `resolve()` never reads it. `PremiumEntitlementResolver` has zero `->tier` references. `isGrandfatheredLegacyPaid()` **does** read it. | none (backend) |
| **W-0023** | high | £10,000 charitable legacy entered in the will builder appears in the generated document but creates **zero** `Bequest` rows — lives only as JSON in `will_documents.specific_gifts`. | `11-...jpg` |
| **W-0020** | high | `WillAnalysisService.php:106` tests `bequest_type === 'specific'`; the enum is `('percentage','specific_amount','specific_asset','residuary')`. Dead branch — charitable **cash** legacies can never trigger the 36% rate. | none (backend) |
| **W-0021** | low | Trust card badge reads `RPT`; Rule 9 forbids acronyms, and `TrustsDashboard.vue:110-112` on the same page spells out "Relevant Property Trust". | `12-...jpg` |
| **W-0022** | high | Letter says "No outstanding liabilities recorded" while a £65,000 mortgage exists — and the consistency panel on the same page says there is 1. Auto-population frozen at row creation (22:51:06), 8 minutes before the mortgage (22:58:55). | none (two panels too far apart for one frame) |

W-0023 and W-0020 **compound**: a charitable legacy fails via the will builder (never
becomes a bequest) and fails via the Bequest API (skipped by the dead enum branch).
Both must land for a charitable cash gift to reduce the IHT rate.

### Not swept

- **Sarah's side of the premium surfaces** — her will, her 3 bequests, her view of the
  trust. Time; and the same defects would recur.
- **`/m` estate and bequests screens.** **I COULD NOT TEST THIS** in this sweep.
- **iOS** — out of scope by dispatch throughout.
- **LPA not built out** — the persona says "Has LPA: Yes" but gives no detail, and the
  page was clean on inspection.

---

## Assumptions

- That the will builder's residuary "if they predecease you" field is meant to hold a
  bare name/phrase. It is interpolated straight into legal prose after "then to", so my
  first input beginning "To ..." produced "then to To ...". **I re-tested with a bare
  phrase, got correct prose, and did NOT raise it** — the app behaved correctly for
  correct input.
- That premium is the intended tier for this persona (see R-03).

---

## Needs

Nothing blocking. Ranking for the fix batch is in the final report.

---

## Noticed

- **Two near-misses I checked before raising, and did not raise:**
  1. `letters_to_spouse` has no `solicitor_*` column and the value looked lost — it
     persists as **`attorney_name` / `attorney_contact`**, which the UI labels
     "Solicitor" (`LetterToSpouse.vue:136-143`). Not a defect, but a genuinely
     misleading name given the app also has a Power of Attorney feature. Noted inside
     W-0022 for whoever touches the file.
  2. 14 of 15 expenditure categories appeared to be dropped on save. Re-testing with
     per-field delays and focus/blur showed all values hold — **my rapid synchronous
     input loop raced the component**, not an app fault. Not raised.
- The will builder's Digital Assets step carries good security copy ("Never include
  passwords, PIN codes, or seed phrases in your will. Your will becomes a public
  document during probate.").
- `/estate/power-of-attorney` spells out "Lasting Power of Attorney" in full
  throughout — the correct pattern that W-0021's trust badge breaks.
