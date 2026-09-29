# SaveTax scenario matrix — is the campaign achieving its objective? — 29 September 2026

**Question (Brett):** across different kinds of people, does SaveTax actually find them tax savings, and is what we promise in the funnel what we deliver in the plan?

**Method.**
1. **Promise:** the live results page `fynla.org/savetax/plan?...` for nine answer combinations (no account needed).
2. **Delivery:** the live plan engine (`TaxStrategyService::getDashboardPayload`, code on `main` at `a87432b71`, identical to production) run against nine hand-built households in the `laravel_testing` database via a throwaway Pest harness (deleted afterwards). Every recommendation was checked by hand against 2026/27 rules.
3. **End to end:** the live couple journey in `2026-09-29-prod-savetax-mobile-couple.md`.

Fyn's chat could not be run locally (no `ANTHROPIC_API_KEY` in local `.env`), so layer 2 tests the engine that produces the plan, not Fyn's wording of it.

## Verdict

**Partly.** For a single employed person, SaveTax does what it says: it finds the real levers, prices them correctly, and says how to act. For couples, retirees and people without earnings, which is where the campaign pitches its biggest numbers, it does not yet deliver what the funnel promises, and in places it overstates.

| Who | Objective met? | Why |
|---|---|---|
| Basic-rate single | Yes, modestly | Correct but small (£240). Funnel promises £1,005. |
| Higher-rate single | **Yes** | Pension to £50,270, ISA wrap, salary sacrifice, Gift Aid — all correct (Sam's live plan: £7,212). |
| £100k tax-trap single | Mostly | 60% trap correctly priced, but the plan stops at £100,000 and never mentions the 40% relief on contributions below it. |
| Additional-rate | **Yes** | £22,228 from a £41,800 contribution, correctly bands dividends and Gift Aid. Funnel under-promises (£7,087). |
| Self-employed | Yes | Correct 40% relief and ISA wrap. Copy mentions a "workplace scheme". |
| Couple, non-earning spouse | Partly | Right levers appear (Marriage Allowance, spouse pension, gift savings), but three recommendations fix the same taxable interest and are added together. Funnel promises £4,719 including a £2,262 "move income to your spouse" figure the plan never produces. |
| Couple, both earning | **No** (for savings) | No savings-shifting strategy exists for dual earners; the spouse's savings are never asked. Plus the live invite bug doubles the spouse's income. |
| Retired | Weak | Only an ISA wrap and a spouse pension top-up. Misses moving savings to a spouse with unused starting-rate band. Funnel promises £1,005 of pension relief a retiree cannot get (non-earner cap is £720). |
| Not employed, high-earning spouse | **No** | Funnel cannot record "no income" for the user, so it promises 20% relief on £5,027 they cannot get, and says nothing about the spouse's 60% trap. |

## Layer 1 — what the funnel promises

| # | Answers | Headline "up to" | Card that drives it |
|---|---|---|---|
| A | Full-time, ≤ £50,270, single, bank/savings/ISA | £1,005 | £5,027 pension × 20% |
| B | Full-time, £50k–£100k, single, pension/ISA/savings | £4,000 | £10,000 pension × 40% |
| C | Full-time, £100k–£125k, single | £15,134 | £25,140 pension × 60% ("60% tax trap") |
| D | Full-time, > £125,140, single, everything | £7,087 | £15,000 pension × 45% |
| E | Full-time, ≤ £50,270, spouse with no income | £4,719 | pension £1,005 + Marriage Allowance £252 + "Spouse's Personal Allowance could save £2,262" |
| F | Self-employed, £50k–£100k | £4,000 | £10,000 pension × 40% |
| G | Retired, ≤ £50,270, spouse ≤ £50,270 | £1,005 | £5,027 pension × 20% |
| H | Not employed, ≤ £50,270, spouse £100k–£125k | £1,005 | £5,027 pension × 20% |
| I | Part-time, ≤ £50,270, no assets | £1,005 | £5,027 pension × 20% |

Findings:
- **F1 (High) — E overstates.** "Your spouse earns nothing, so moving income or savings to them uses their £11,310 … could save £2,262/yr" prices £11,310 × 20%, i.e. it assumes £11,310 a year of *income* can be moved. Salary cannot be moved, and moving savings only shelters the interest on them. The plan engine never produces this figure (see S5).
- **F2 (High) — G, H and I promise pension relief that does not exist.** A retiree, a non-earner or a part-timer earning under £5,027 is capped at £3,600 gross / £720 relief (FA 2004 s190) — the plan engine already knows this (S8 caps the tile at £3,600). The funnel does not.
- **F3 (High) — H misses the real opportunity.** The spouse is in the 60% trap; the card shows "Spouse's Personal Allowance (tapered) £0 — Available to you" with no saving. The funnel also has no "no income" option for the user themselves (it exists only for the spouse), so a non-earner must pick "Up to £50,270".
- **F4 (Medium) — the headline is not what the plan finds.** It is a band-maximum, not an average: D under-promises by £15,000; C (£15,134) is only reachable at the very top of the band — a £110,000 earner's plan finds £3,050 (S3).
- **F5 (Low)** — C shows "Personal Allowance (tapered) £0 — Available to you" for anyone from £100,001, where the allowance is still almost £12,570.

## Layer 2 — what the plan engine delivers

All households: born 1982 unless stated, £2,500 a month spending, easy-access savings at the rate shown, Free-tier data only.

| # | Household | Plan (engine output) | Plan total | Hand check |
|---|---|---|---|---|
| S1 | £30k employed single; £20k savings 4.5%; pension 5%/3% | Pay £600 more into pension, save £120; salary sacrifice £120 | £240 | Correct. Interest £900 < £1,000 PSA, rightly no ISA action. |
| S2 | £60k employed single; £30k savings 4.5%; pension 5%/5% | Pension £6,700 saves £2,680; ISA wrap £18,889 saves £340; salary sacrifice £60 | £3,080 | All correct (57,000 − 50,270 = 6,730; 850 / 4.5% = 18,889; 850 × 40%; 3,000 × 2%). |
| S3 | £110k employed single; £10k savings; pension 5%/5% | Reclaim Personal Allowance: £4,900 saves £2,940; salary sacrifice £110 | £3,050 | 60% slice correct (ANI £104,950 incl. interest). **Stops at £100,000** — contributions from £100,000 down to £50,270 still get 40% and are not mentioned. |
| S4 | £140k employed; £5k dividends; Gift Aid £1,200; pension 5%/8% | Shift out of 45% band: £41,800 saves £22,228; salary sacrifice £140; Gift Aid £375 | £22,743 | Correct, including the Gift-Aid-extended band (£126,640) and the Annual Allowance cap (£60,000 − £18,200). |
| S5 | £40k employed, non-earning spouse (savings confirmed £0); £40k savings 4.5% in user's name | Pension £800 saves £160; ISA wrap saves £160; salary sacrifice £160; **gift savings to spouse saves £160**; spouse pension £2,880 gets £720 relief; Marriage Allowance £252; spouse ISA; **50/50 joint savings saves £160** | £1,772 | Each figure right, but ISA wrap, gift and 50/50 all remove the same £800 of taxable interest — only one can be taken. Achievable ≈ £1,452. |
| S6 | £115k employed, non-earning spouse (savings £0); £50k savings 4.5% | Reclaim PA £11,500 saves £6,900; ISA wrap saves £540; salary sacrifice £115; gift savings saves £1,150; spouse pension £720; spouse ISA; 50/50 saves £675 | £10,100 | Same overlap, and the savings items are priced at 60% although the pension action takes the user out of the trap. Achievable ≈ £8,400. |
| S7 | Self-employed £55k profit; no pension; £15k savings 4% | Pension £4,700 saves £1,880; ISA wrap £2,500 saves £40 | £1,920 | Correct. Copy says "a workplace scheme gives the relief through your pay" to a self-employed user. |
| S8 | Retired (born 1958), £25k drawdown; £60k savings 4.5%; spouse £14k pension income | ISA wrap £20,000 saves £180; spouse pension £2,880 gets £720 relief | £900 | ISA correct. **Missed:** spouse has £3,570 starting rate + £1,000 PSA unused — moving ~£38k of savings saves ~£340. The user's own £2,880 top-up (same £720) is not offered, only the spouse's. No age-75 caveat on relief. |
| S9 | £105k employed, spouse £20k employed; £40k savings 4.5% | Reclaim PA £2,600 saves £1,560; ISA wrap saves £540; salary sacrifice £84; **spouse pension £15,200 saves £3,800** | £5,984 | Stops at £100,000 again. Recommends the spouse pay £15,200 (of a £20,000 salary) at 20% relief while the user has 40% headroom below £100k — the same £15,200 in the user's pension would earn £6,080. **No savings shift** to the basic-rate spouse (≈ £620 a year). |

### Engine findings

| # | Severity | Finding | Where |
|---|---|---|---|
| E1 | Medium | **Overlapping actions are shown as if they stack.** ISA wrap, "gift savings to your spouse" and "share savings 50/50" each remove the same taxable interest. The live run (Layer 3) shows the headline total *does* de-duplicate them, but the cards are not labelled as alternatives, and savings actions are priced at the pre-pension marginal rate, overstating by ≈ 7% (≈ £450 for the £110k household). | Tax Strategy cards; savings strategies' pricing |
| E2 | High | **Dual-earner couples get no savings-shifting strategy**, and the spouse step for a working spouse never asks about the spouse's savings. Moving savings to a lower-rate spouse is the most common couple lever. | `CrossSpouseBundleStrategy` (GIA/ISA only); `CaptureForms` dual-earner spouse form |
| E3 | Medium | **Tax-trap plans stop at £100,000.** After reclaiming the Personal Allowance, relief at 40% down to £50,270 is not shown (S3, S9). | `IncomeBandStrategy` / `PensionTaxReliefStrategy` interplay |
| E4 | Medium | **Spouse pension top-up can outrank a better own-pension move** and ignores affordability: £15,200 from a £20,000 earner at 20% while the user gets 40% (S9). | `NonEarnerSpousePensionStrategy` |
| E5 | Medium | **Retiree couples miss the spouse's starting-rate band** for savings; the user's own £720 non-earner relief is offered for the spouse only (S8). | Savings-shift strategies require `single_earner_couple` |
| E6 | Medium | **ISA-wrap copy is wrong when the £20,000 allowance caps it:** "£2,250 of interest, of which £900 is above your £500 Savings Allowance" — actually £1,750 is above; £900 is what £20,000 can shelter (S6, S8, S9). | ISA top-up strategy description |
| E7 | Low | Self-employed users are told relief comes "through your pay" from a workplace scheme (S7). | `PensionTaxReliefStrategy` copy |
| E8 | Low | Savings-shift strategies stay silent unless spouse savings are explicitly £0; correct and conservative, but it means any couple who skips that question gets none of them. | `AssetShiftingBundleStrategy`, `JointSavingsStrategy` |

## Layer 3 — live run: £110k earner, non-earning spouse (fynla.org, /m)

**Account (please purge):** `isenbret+savetax2909b@gmail.com` — "Jordan Hale", Free tier. Brett registered; Claude drove the rest.

**Household:** Head of Data, £110,000; workplace pension 5% + 5%, not salary sacrifice, £160,000; Chase easy access £50,000 at 4.5% (sole name); home £400,000, mortgage £180,000, joint 50/50; born 20 June 1980; spouse no income, nothing in their own name (confirmed through the spouse-assets form); no childcare or donations.

**Funnel promise:** "up to £22,562 a year" — £15,084 (a £25,140 pension at 60%) + £5,028 ("Spouse's Personal Allowance … moving income or savings to them", £12,570 × 40%).

**Plan delivered (onboarding and Tax Strategy agree): "around £6,000 a year".**

| Action | Shown | Check |
|---|---|---|
| Reclaim Personal Allowance | £6,700 saves £4,020 | ANI £104,500 + £2,250 interest = £106,750; £6,700 × 60% = £4,020. Correct. |
| Gift £50,000 of savings to spouse | £1,150 | Correct at the pre-pension 60% rate; after the pension action Jordan is at 40%, so ≈ £700. |
| Spouse pension top-up | £720 | Correct (£2,880 grossed up to £3,600). |
| 50/50 joint savings | £675 | Correct in isolation; alternative to the gift. |
| ISA wrap £20,000 | £540 | Alternative to the gift; copy says "£900 is above your £500 Savings Allowance" (actually £1,750). |
| Salary sacrifice | £110 | Correct (2% of £5,500). |
| **Total** | **£6,000** | The total already de-duplicates the overlapping savings actions (it counts the gift only). Achievable with the pension first: ≈ £5,550 (pension £4,020 + gift ≈ £700 + £720 + £110). Overstated by ≈ £450 because the gift is priced at 60%. |

Findings from this run:

| # | Severity | Finding |
|---|---|---|
| L3-1 | High | **Promise vs delivery: £22,562 promised, ≈ £5,550–£6,000 delivered.** The funnel prices a £25,140 pension (top of the band, not this user) and £5,028 of "moving income" to a spouse that cannot be done with salary. A £110k user feels short-changed by ~£16,500 at the first screen after sign-up. |
| L3-2 | High | **Fyn reply died silently.** First question in a new conversation (940): Fyn wrote two lines ("Let me pull the full details…"), then the stream ended (`POST /api/ai-chat/conversations/940/messages` saves `net::ERR_ABORTED`) with no answer and no error shown. Re-asking worked. |
| L3-3 | Medium | **Fyn's overlap answer contradicts itself** — says the ISA and gift are alternatives, then says do "the ISA top-up and either the gift or the joint split" — and repeats the wrong "£900 of interest currently taxed" figure. |
| L3-4 | Medium | **"Is there any point putting more in?" went unanswered.** After reaching £100,000, contributions still get 40% relief down to £50,270 and £49,000 of Annual Allowance remains; neither the plan nor Fyn says so (confirms E3 live). |
| L3-5 | Medium | **Overlapping cards are not labelled as alternatives.** The Tax Strategy page lists gift, 50/50 and ISA wrap as separate "Saves £…" cards; they visibly add up to more than the £6,000 headline with no explanation. (E1 above is therefore about the *cards*, not the total — the total is de-duplicated.) |
| L3-6 | Low | Bank Accounts recommended actions only suggest "Consider a Cash ISA"; the far better spouse gift appears only on Tax Strategy. |
| L3-7 | Low | Fyn says the pension payment "does not reduce the cash available" for the savings moves. |

What worked: the funnel answer "spouse has no income" carried straight through (no re-asking); the non-earning spouse form's "save with none chosen" correctly recorded zero savings and unlocked the spouse strategies; the 60% reclaim was priced exactly; every save stored the right figure.

## What would move the needle most

1. Fix the live invite income doubling (C1 in the couple report — fix in progress).
2. Bring the funnel's per-card estimates in line with the engine — the single biggest trust gap (£22,562 promised vs ≈ £5,550 delivered for the £110k couple): cap non-earner relief at £720, drop the £2,262 / £5,028 "move income" figures, price the trap at the user's likely income rather than the band top, add a "no income" answer for the user, and model the spouse's trap.
3. Label overlapping actions as alternatives, and re-price savings actions after the pension action.
4. Ask a working spouse about their savings and add a dual-earner savings shift.
5. Extend the trap plan below £100,000 at 40%.
