# tax-compliance-reviewer — Estate / Inheritance Tax verdict, 2026-08-23

**Scope:** commits `0385fe6cc` and `6302cd661`, then a re-review pinned to `a1d36b90b`.
**Gate on:** W-0154, W-0463, W-0091, W-0464. **Report-only** — no code or database changes.
**Recorded here because the reviewer wrote nothing to disk.** Both reviews existed only in
the coordinator's conversation; this file is the durable record.

## Standing correction the reviewer volunteered

It began expecting the Agricultural/Business Property Relief allowance to be **£1,000,000**
(Autumn Budget 2024) and found it is **£2,500,000** — uplift announced 23 December 2025,
enacted by **FA 2026 Sch 12** inserting **IHTA 1984 s124D(2)**. Our configured figure was
right and its own prior was stale.

**Its agent definition is out of date and should be refreshed** — it states tax year 2025/26
and "frozen until April 2028", where the active configuration is 2026/27 with the freeze to
5 April 2031, and it carries no relief cap at all. The reviewer grepped the live config for
every figure rather than trusting its table; **the next reviewer may not.**

## Round one — 18 findings

| | Finding | Direction | Outcome |
|---|---|---|---|
| F1 | Residence band capped BEFORE the taper; statute tapers the allowance then caps (s8D(5)(g), s8E(4)-(5)) | **Overstates tax** | Fixed `a1d36b90b` |
| F2 | Business relief subtracted from the taper base E, which is measured before reliefs (IHTM46023) | Understates | Fixed, then over-corrected — see R2 |
| F3 | `business_relief_deduction` published, zero frontend consumers — estate stops adding up | Unreadable | Fixed `33966d9e0` (web) |
| F4 | Taper relief shown on gifts bearing no tax; IHTM14611 forbids it | Misleading | Fixed `33966d9e0` |
| F5 | `failed_gift_tax` computed, published to nobody | Invisible | Fixed `33966d9e0` |
| F6 | No credit for the lifetime charge on a chargeable lifetime transfer | **Overstates tax** | Fixed `a1d36b90b` |
| F7 | Flat 14-year running band instead of per-transfer 7-year lookback (s7(1)(b)) | **Invents tax** | Fixed `a1d36b90b` |
| F8 | Out-of-window transfers reducing the death estate's band (IHTM14503) | **Overstates tax** | Fixed `a1d36b90b` |
| F9 | Largest-first relief allocation; s124D(7) mandates pro rata | Attribution | Fixed `a1d36b90b` |
| F10 | `getGiftTaxRate($y,'clt')` returned 40% at every year | Wrong rate | Fixed `a1d36b90b` |
| F11 | 50%-rate relief categories and `excluded_businesses` unread and unregistered | Understates | Registered |
| F12 | APR/AIM exclusions defensible as engineering, not as disclosure | Both directions | **Open — W-0466** |
| F13 | Cap £2.5m, date 2026-04-06, rate 50%, `min_ownership_years` — **all correct** | — | Approved |
| F14 | `suppressRateOnNilLiability` — **legally required**, not cosmetic (Sch 1A para 1(1)(b)); Sch 1A ordering survives | — | Approved |
| F15 | s8G claim **correct**; remarried-widow identity gap | Display | Fixed `a1d36b90b` |
| F16 | `/m` teaser attributes a pooled second-death figure to "your estate" | Misleading | **Open — W-0467** |
| F17 | Two exclusions-register reasons had become false | Hygiene | Fixed |
| F18 | Unused import; no lifetime exemptions in the gift walk; historic configs carry the 2026 cap | Minor | Partly fixed |

## Round two — re-review of `a1d36b90b`

**Discharged:** C1, C6, C8, C9, C10, C11, C13, C14, F15. **Half:** F19 (nil rate band since fixed).

**Two HIGH regressions the fix introduced — both since fixed in `19bd1c83f`:**

- **R1** — survived potentially exempt transfers were cumulating. s3A(4) makes such a transfer
  **exempt**; IHTM14513 omits it from cumulation. Two £300,000 gifts at 8 and 2 years produced
  **£110,000 of invented tax**. A regression against the code it replaced. The reviewer confirmed
  the window predicate itself was correct — the defect was the collection it filtered.
- **R2** — the F2 fix double-counted. A partly relieved business is not exempt, so its full value
  was already in gross; adding the relief back inflated E by exactly `business_relief_deduction`.

**R3** (estate band reduced by band-used rather than transferred VALUES, IHTM14503), **R4**
(zero-band message misattribution), **R5** (messages quoting the taper base as "your estate") —
all fixed in `19bd1c83f`.

**Independently verified by the reviewer, standalone:** the F1 worked example (taper £100,000,
cap £0, available £250,000), the reconciliation identity across **all six** branch combinations,
the F15 displacement across four, and the F9 pro-rata arithmetic (£2,571,428.57 / £428,571.43).

## Surfaced, not caused by these commits — now on the board

- **R6 → W-0465** — the projection applies **no business relief at all**.
- **R7 → W-0468** — same-day transfers do not cumulate against each other (`gap > 0`); s124D(5)
  shows same-day transfers are a live statutory case.
- **R8** — the F8 test did not discriminate on its original numbers; corrected in `19bd1c83f`.

## Stated assumptions, recorded rather than buried

- Death is assumed to be **today** — consistent with the rest of the service. Approved.
- The lifetime charge is **reconstructed on today's nil rate band**; `gifts` records neither the
  historical band nor the charge. Safe while the band is frozen to 2031, wrong across a change.
- **Grossing-up not modelled** (`lifetime_rate_grossed_up`); `gifts` cannot express who bore the tax.
- **s8E(7)** — the residence band can never exceed VT — is not modelled. Registered.

## What the reviewer could NOT verify

- **It did not run the test suite** (report-only + no DB writes; Pest uses `RefreshDatabase`).
- **No fixture can exercise any of this**: no persona holds a business above the cap, a gift above
  the band, or a gift older than seven years. Every worked example is hand-computed from the code
  path, not observed. This is why all of it survived a persona run.
- It did not drive the endpoint, and did not examine the trust allowances (ss.124G-124K), which
  `business_interests.trust_id` makes reachable.

## Citations — all read 2026-08-23

legislation.gov.uk: IHTA 1984 ss.3A, 7, 8A, 8D, 8E, 8G, 8L, 104, 105, 106, 124D, 124E, 124F, Sch 1A;
Finance Act 2026 c.11 s.65 and Sch 12.
HMRC IHT Manual: IHTM14503, 14513, 14517, 14533, 14571, 14576, 14611, 14612, 25510, 25521, 25523,
25524, 25570, 45001, 45002, 45009, 45031, 46022, 46023, 46024, 46026, 46040, 46042, 46043, 46044.
gov.uk: APR/BPR changes TIIN (updated 3 March 2026); relief threshold news (23 December 2025); OOTLAR.
