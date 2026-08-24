---
id: W-0332
title: The /m bank-accounts screen counts a joint account whole against both spouses, and contradicts its own detail screen
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: done
severity: high
surfaces: [m]
created: 2026-08-22T23:20:00Z
claimed: 2026-08-22T23:20:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0274, F-0019, ownership.js, mobile Investment.vue]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Found by asking, per **Rule 19**, what the `/m` counterpart of W-0274's web defect
was. **Not raised by any tester.** It is the mirror image of the web fault: the web
store charged the co-owner the PRIMARY owner's share; `/m` charged both of them the
WHOLE balance.

`resources/mobile/views/modules/Savings.vue` — `balanceOf()` preferred
`full_balance`, and `totalCash` summed it across every visible account. So a joint
account was counted at 100% for each spouse, and `runwayMonths`, `runwayBarWidth`
and `runwayCovered` all inherited it.

`/m`'s own account **detail** screen (`SavingsAccount.vue`) has always read
`calculateUserShare`, so `/m` contradicted itself one tap apart.

## Acceptance

1. A co-owner sees their own share, not the whole balance and not the primary
   owner's share. ✅ `tests/frontend/mobile/SavingsOwnershipShare.test.js`, 70/30.
2. The runway, the bar and "% of target" follow the same figure. ✅
3. Routed to the shared `ownership.js`, not a `/m` copy (Rule 20). ✅ reached by
   relative path, as four other `/m` screens already do.
4. Verified in a browser on `/m`. ✅ — bundle rebuilt by team-lead and proven to
   carry the change before the screen was believed. See below.

## Working notes

Row layout mirrors the `/m` investment list exactly: the headline is what the viewer
owns, the line beneath reads "Your 30.00% of £20,000". Not invented — copied.

## Browser verification — build-lead, 2026-08-23 00:45

**Both accounts, web and `/m`, MFA codes fetched from the database throughout.**
Identity confirmed from `fynla-state.auth.user` (id 16 / id 17) rather than by
recognising a figure — the figures are the things under test.

| Surface | David (16) | Sarah (17) |
|---|---|---|
| `/dashboard` | 79.8 / 6 months · £99,750 | 25.3 / 6 months · £31,030 |
| **`/savings` → Emergency Fund** | **79.8 · £99,750** · *"Emergency fund target achieved!"* | **25.3 · £31,030** · *"Emergency fund target achieved!"* |
| `/risk-profile` | 79.8 months · Upper-Med | 25.3 months · Upper-Med |
| **`/m` bank accounts** | **£99,750**, 80 months of cover | **£31,030**, 25 months of cover |

Was `Months Runway 0.0`, `Current Fund £0`, *"Priority: Build your emergency fund"*
on both accounts. **`/m` reads £31,030 for Sarah, not £33,280**, and the joint
Nationwide account renders **£2,250** with **"Your 50.00% of £4,500"** beneath it on
both logins.

**Bundle proof, because a stale `/m` build fails by AGREEING.** `full_balance` is the
wrong discriminator — it legitimately survives, since `ownership.js`'s `VALUE_FIELDS`
lists it and `balanceOf()` still uses it for the context line. Grepped instead for
`ms-acct__share` (**present**; did not exist before) and the old
`reduce(...balanceOf` summing expression (**absent**). The page then confirmed it was
serving `main-BljqEql8.js`, the same file grepped.

**What this pass CANNOT prove.** The persona's joint account is **50/50**, so David's
half and Sarah's half are the same £2,250 — the browser cannot distinguish "shows the
viewer's share" from "shows the primary owner's share". It proves only that neither
spouse is charged the whole £4,500, which is the defect that was live. The asymmetric
discrimination lives in the tests, at 70/30. **The Collision, on the very screen under
test.**

Screenshots: `W-0274-web-david-16-savings-emergency-fixed.png`,
`W-0274-web-sarah-17-savings-emergency-fixed.png`,
`W-0332-m-sarah-17-savings-own-share.png`, `W-0332-m-david-16-savings-own-share.png`.

No persona row was written.
