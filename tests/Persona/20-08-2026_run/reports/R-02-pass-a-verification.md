# R-02 — Pass A, verification (web + /m, both accounts)

**Run:** `peak_earners`, Pass A · **Environment:** local `http://localhost:8000`
**Surfaces:** desktop web · `/m` mobile web · **iOS: not in scope for this dispatch**
**Accounts:** David Jones (16) and Sarah Jones (17), both logged in separately
**Ran:** 2026-08-20 23:05 – 23:35 · **Written:** 2026-08-21 08:10 (backfilled)

---

## Done — checks that came back GREEN

Each verified in the live browser and cross-checked against the database.

| # | Check | Result |
|---|---|---|
| 1 | Spouse link reciprocal | `spouse_id` 16↔17, `SpousePermission` accepted **both ways** |
| 2 | Joint property stored as ONE row (Rule 6) | 1 row, `joint`, pct 50.00, `joint_owner_id` 17, `current_value` 850000.00 full |
| 3 | **Joint property share, David's login** | Full £850,000 · Your Share (50.00%) **£425,000** |
| 4 | **Joint property share, Sarah's login** | Full £850,000 · Your Share (50.00%) **£425,000** |
| 5 | No property double-count | Household total £850,000 for an £850,000 asset |
| 6 | Joint mortgage share, both logins | £32,500 each · total £65,000 |
| 7 | Property equity | £392,500 each side = 425,000 − 32,500 |
| 8 | Savings isolation | Sarah sees only Barclays £6,280 + Nationwide £22,500. David's HSBC £25,000 and his Cash ISA are correctly absent |
| 9 | Individual savings ownership | all four accounts `individual`, pct 100 |
| 10 | ISA allowance backend | `ISATracker::getISAAllowanceStatus` → `cash_isa_used` 10000, `remaining` 10000, for both spouses |
| 11 | **Household wealth summary arithmetic** | every line recomputed by hand — exact, see below |
| 12 | **Cross-surface parity, net worth** | web £987,500 = `/m` £987,500 = `/m` dashboard "£12,500 from £1,000,000" |
| 13 | Wealth summary mirrors correctly | Sarah's view is David's with columns swapped, same figures |

### The hand-recomputed wealth summary

```
                 David       Sarah      Total     hand-check
Pensions       £500,000   DB only    £500,000     180,000 + 320,000            OK
Property       £425,000   £425,000   £850,000     850,000 joint 50/50          OK
Investments     £47,500   £132,500   £180,000     85,000 + 95,000 (disputed)   see W-0015
Cash            £47,500    £28,780    £76,280     25,000+22,500 / 6,280+22,500 OK
Valuables            £0    £18,000     £18,000     engagement ring             OK
Total Assets £1,020,000   £604,280 £1,624,280                                  OK
Mortgages       £32,500    £32,500     £65,000    65,000 joint 50/50           OK
Net Worth      £987,500   £571,780 £1,559,280                                  OK
```

Evidence: `pass-a-web/07-web-sarah-wealth-summary-household-split.jpg`

---

## Not done, and why — checks that came back RED

### RED-1 · Joint investment share, David — W-0014, W-0015

- **Expected** (`peak_earners.md:306-316`, Rule 6): Joint GIA £95,000 → Your Share (50.00%) **£47,500**
- **Actual:** `Full Value £95,000 · Your Share (100.00%) £95,000`
- **DB:** `investment_accounts.id 14` — `ownership_type joint`, `joint_owner_id 17`, `ownership_percentage 100.00`
- **Screenshot:** none for David's login specifically — see gap note below
- **W-item:** W-0014 (storage), W-0015 (rendering)

### RED-2 · Joint investment share, Sarah — the same £95,000 claimed twice — W-0015

- **Expected:** Sarah is the *joint owner*; her share should be complementary — £47,500
- **Actual:** `Full Value £95,000 · Your Share (100.00%) £95,000`, and `Current Portfolio £180,000` (85,000 + the FULL 95,000)
- **Why it matters:** both spouses are told they own 100% of the same single record — £190,000 of claimed ownership against a £95,000 asset. Precisely what Rule 6 exists to prevent.
- **Screenshot:** `pass-a-web/05-web-sarah-investments-your-share-100pct-95000.jpg`
- **W-item:** W-0015

### RED-3 · Same account, two figures, same session — W-0015

- **Expected:** `/net-worth/investments` and `/net-worth/wealth-summary` agree
- **Actual:** investments page £95,000 · wealth summary £47,500 — £47,500 apart
- **Cause:** three mechanisms compute one share — the `CalculatesOwnershipShare` trait (which silently rewrites a stored 100 → 50 at `:73`), `InvestmentList.vue:86-87` client-side raw arithmetic, and `InvestmentController.php:972` server-side raw arithmetic
- **Screenshots:** `05-...jpg` and `07-...jpg`
- **W-item:** W-0015

### RED-4 · `/m` carries the same contradiction — W-0015

- **Expected:** `/m` agrees with itself and with web
- **Actual:** `/m/app/net-worth` "£987,500 · £1,020,000 in assets" (investments at £47,500) vs `/m/app/investment` "Total portfolio value £95,000". No ownership share and no joint indicator shown at all on `/m`.
- **Screenshot:** `pass-a-web/09-m-sarah-investments-full-95000-no-share.jpg`
- **W-item:** W-0015

### RED-5 · Property card names the viewer as co-owner — W-0016

- **Expected:** viewed as Sarah, "Joint with **David Jones**"
- **Actual:** "Joint with **Sarah Jones**" — she is told the property is joint with herself
- **Note:** all *figures* on that card are correct; label only
- **Screenshot:** `pass-a-web/06-web-sarah-property-425000-correct-but-joint-with-self.jpg`
- **W-item:** W-0016

### RED-6 · Health, smoking, education never display — W-0006

- **Expected** (`peak_earners.md:26-28`): Yes / Never / Postgraduate
- **Actual:** all three read "Not specified" after a hard reload, on **both** accounts. DB: `health_status` NULL, `smoking_status` NULL, `education_level` 'postgraduate' (persisted but not exposed by `UserResource`)
- **Screenshot:** `pass-a-web/01-web-health-not-specified-W-0006.jpg`
- **W-item:** W-0006

### RED-7 · Estate block unreachable — blocks the IHT check

- **Expected:** wills, 6 bequests, £185,000 trust, LPA, letter to loved ones, IHT liability
- **Actual:** `/estate`, `/estate/will-builder`, `/trusts`, `/estate/power-of-attorney`, `/valuable-info?section=letter`, `/holistic-plan` all redirect to `/teaser` on free tier
- **Screenshot:** `pass-a-web/08-web-estate-premium-gate-blocks-wills-trusts.jpg`
- **W-item:** none — provisioning, not a defect. See R-03.

---

## Checks I COULD NOT RUN

- **iOS** — out of scope by dispatch (native reads the csjones staging DB, cannot see local data).
- **Manchester tenants-in-common: David 40% → £118,000, never £295,000; Mike Barrett's 60% belonging to no account.** This was the dispatch's stated priority-1 item. Blocked by the 1-property free cap. **I COULD NOT TEST THIS.**
- **IHT liability, decumulation, Monte Carlo, goal trajectories, retirement projections against persona targets.** Target retirement income has no entry point I could find, so the app derives its own (David £100,050 vs persona £75,000; Sarah £116,250 vs £55,000). Any projection check would have been against the wrong input.
- **Emergency fund runway, protection gap, cashflow surplus, goal affordability** — all downstream of expenditure, which cannot be saved on free tier (W-0011). `/m` confirms this from the user's side: Savings and Investment modules render **LOCKED** with "Monthly expenditure is required".
- **Holdings detail** — ticker/ISIN/units/prices/OCF for all 10 persona holdings (W-0009).

---

## Assumptions

- That the wealth summary's £47,500 for investments is "right by persona, wrong by data" — it happens to match the persona because the trait's `100 → 50` fallback cancels the storage bug. Both still need fixing; the fallback is masking, not correctness.
- That `/m` reading £95,000 is the same defect as web rather than a separate mobile bug — same value, same shape, no ownership rendering on either.

---

## Needs

Same single blocker as R-01: a decision on premium provisioning. Until then the
priority-1 tenants-in-common check and the entire estate/IHT block stay unverifiable.

---

## Noticed

- `/m` investments shows "1 of 2 accounts used" while listing **2** accounts for Sarah — the counter appears to exclude joint accounts. Minor; not raised as its own item, folded into the W-0015 observation set.
- `/m` net worth surfaces an honest caveat — "A mortgage principal repayment is not projected because its monthly interest portion is not recorded" — which is the `monthly_interest_portion` gap noted in W-0012.
- The `/m` dashboard gamification (Level, "ahead of 71% of people") is the CSJ-approved engagement layer per CLAUDE.md Rule 12 carve-out. **Deliberately not flagged.**

---

## Screenshot gap for this phase

Six screenshots exist, all from the Sarah-side and `/m` verification. **David's
`/net-worth/investments` "Your Share (100.00%) £95,000" was read and quoted from the
live DOM but not captured to disk**, because the capture rule landed after the fact.
Sarah's identical screen (05) evidences the same defect from the other side, and the
DB row is in W-0014. Re-capture on the next run rather than backfill.
