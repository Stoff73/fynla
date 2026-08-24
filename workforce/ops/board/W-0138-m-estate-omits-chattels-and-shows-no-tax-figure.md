---
id: W-0138
title: The /m estate screen omits chattels entirely, shows an individual estate where web shows a household one, and displays no Inheritance Tax figure under an Inheritance Tax heading
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0016-cycle1-m-chattels-and-plan-expenditure.md
owner: build-lead
status: queued
severity: high
surfaces: [m, web, ios]
created: 2026-08-21T20:30:00Z
claimed: 2026-08-21T21:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: REJECTED 2026-08-24 quality-lead — see ops/handoffs/quality-lead/cycle4-recertification-2026-08-24.md
prior_art_checked: 2026-08-21
prior_art_found: [W-0154, W-0134, EstateAssetAggregatorService, NetWorthService, UserProfileService, MobileDashboardAggregator]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, local `localhost:8000/m/app/estate`, both persona
accounts, 430×930 viewport. Rule 19 — done is web **and** `/m`.

### Expected

The `/m` estate screen states "Estimated estate value" and is headed "Inheritance tax
exposure and planning". It should show the same estate the web surfaces show, built from
the same assets, and — given its own heading — an Inheritance Tax figure.

The persona's chattels total **£193,000** and are itemised on web: £132,250 to David,
£60,750 to Sarah. That split was independently verified GREEN on `/m` in an earlier pass
of this run (screenshots 16 and 17), so `/m` can render them.

### Actual

| | Web (household) | `/m` David | `/m` Sarah |
|---|---|---|---|
| "Estimated estate value" | £1,234,280 | **£487,500** | **£553,780** |
| Property | £850,000 household | £425,000 | £425,000 |
| Investments | | £47,500 | £132,500 |
| Cash & savings | | £47,500 | £28,780 |
| **Chattels** | **£132,250 / £60,750** | **absent** | **absent** |
| Liabilities | £65,000 household | £32,500 | £32,500 |
| Inheritance Tax liability | £149,712 / £89,712 | **not shown** | **not shown** |

Verbatim, David (`76-m-david-estate.png`):

```
Estate — Inheritance tax exposure and planning
Estimated estate value
£487,500
£520,000 in assets, less £32,500 of liabilities.
ESTATE BREAKDOWN
Property £425,000 · Investments £47,500 · Cash & savings £47,500
Liabilities £32,500 · Net estate £487,500
ESTATE PLANNING
Lifetime gifts 1 gift · Trusts 1 trust · Will In place · Specific bequests 1 bequest
```

Three separate faults:

1. **Chattels are missing.** £425,000 + £47,500 + £47,500 = £520,000 exactly, so the
   breakdown is internally consistent and simply has no chattels class. David's £132,250
   and Sarah's £60,750 — six recorded valuables including an £85,000 Jaguar — are absent
   from a figure presented as their estate.
2. **The basis differs from web.** `/m` shows the individual's share; web shows the
   household second-death estate. £487,500 against £1,234,280 for the same user at the
   same moment. If individual is the intended `/m` basis, the label must say so; today
   both screens say "estate" and mean different things.
3. **No Inheritance Tax figure at all**, under a subtitle that promises "Inheritance tax
   exposure". The screen shows what the estate is worth and never what it would cost.

### Impact

A `/m` user is shown an estate £746,780 smaller than the one the same account shows on
web, with £132,250 of their own assets silently excluded, and no tax exposure at all on
the screen whose stated purpose is tax exposure. Rule 19 makes `/m` a first-class
surface, and this is the estate module's headline number on it.

### Repro

1. `http://localhost:8000/m/app/login`, sign in as `david.jones@example.com`, enter the
   six-digit code and press **Verify and continue** (`/m` needs the explicit click).
2. `/m/app/estate`, wait ~10s.
3. Read "Estimated estate value" and the ESTATE BREAKDOWN rows. No chattels class; no
   tax figure.
4. Compare with `/estate/inheritance-tax` on web for the same account: £1,234,280 and
   £149,712.
5. Repeat as `sarah.jones@example.com`: £553,780, chattels again absent.

### Acceptance

1. `/m`'s estate includes chattels, from the same source the web table itemises.
2. `/m` and web agree on the basis, and the label says which basis it is. If `/m` is
   deliberately individual, that is a product decision to record, not a silent difference.
3. `/m` shows an Inheritance Tax liability, or its subtitle stops promising one.
4. One source for the composition, not a second `/m` aggregation (Rule 20).
5. Rebuild `npm run build:mobile` before verifying — `/m` has no hot reload — and verify
   in a browser on both persona accounts.

---

## Built 2026-08-21 — `cycle1-surfaces`, branch `F-0016`. Fault 1 only. **Do not close.**

**Handoff:** `handoffs/W-0138/build-to-quality-2026-08-21.md`.
**Scope set by the team lead after R-18 re-measured the complete household:** the chattels
class only. **Faults 2 (individual-versus-household basis) and 3 (no Inheritance Tax
figure) are untouched and still open** — this item cannot be closed on this work.

### Prior-art check — outcome `extend`

Six sources. `EstateAssetAggregatorService` already aggregates chattels for the Inheritance
Tax path, which is why web itemises them (route: reused, untouched). The `/m` estate screen
does **not** read that service — it reads `/api/estate/net-worth` → `NetWorthAnalyzer` →
`CrossModuleAssetAggregator`, which aggregated property, investments and cash and no
chattels. So: **extend the existing single-source aggregator**, never a second `/m`
aggregation (acceptance 4). Also found: three further implementations of "this user's share
of a chattel" (`NetWorthService`, `UserProfileService`, `MobileDashboardAggregator`) — two
now consolidated into the aggregator, per Rule 20.

### What changed

`app/Services/Shared/CrossModuleAssetAggregator.php` gains `getChattelAssets()` (`:184`)
and `calculateChattelTotal()` (`:214`), wired into `getAllAssets()` (`:75`),
`getAssetTotals()` (`:239`) and `getAssetBreakdown()` (`:340`).
`NetWorthService::calculateChattelValue()` (`:123`) and
`UserProfileService::calculateAssetsSummary()` (`:667`) now read it instead of computing
their own.

**No client changed and no bundle was rebuilt.** `/m` and iOS already map
`chattel → 'Possessions'`; they were never sent the row. **Three surfaces — `/m`, web and
native iOS — are fixed from one server-side change**, because all three read
`/api/estate/net-worth`.

### Result, read-only against the persona household (no write to users 16 or 17)

| | property | investment | **chattel** | cash | assets | liabilities | net |
|---|---|---|---|---|---|---|---|
| David 16 | 755,500 | 172,500 | **132,250** | 99,750 | **1,160,000** | 182,500 | **977,500** |
| Sarah 17 | 637,500 | 132,500 | **60,750** | 31,030 | **861,780** | 122,500 | **739,280** |

Matches R-18 §1.1 and §2.8 exactly, **ownership splits included** — Manchester's 40% inside
David's property total and absent from Sarah's. (R-18 §2.8's expected net of 977,750 is a
£250 slip against its own inputs; 977,500 is right.)

**Tests:** `tests/Feature/Estate/EstateNetWorthChattelsTest.php` — 3 tests through the real
endpoint, red before the fix, green after.

**Reported, not fixed:** business interests are still absent from the same aggregation
(invisible to `peak_earners`, visible to `entrepreneur`); `NetWorthService::getJointAssets()`
misses assets where the user is the joint owner, for every class.

---

## Follow-up, 2026-08-21: business interests, the same aggregation gap

Issued by the team lead after the prior-art census above raised it. **Invisible to
`peak_earners`, which holds no business interests; visible to `entrepreneur`.**
`getBusinessAssets()` + `calculateBusinessTotal()` on `CrossModuleAssetAggregator`, built
like the chattel sibling. `/m` and iOS already map `business → 'Business interests'` — no
client edit, no rebuild. Test:
`tests/Feature/Estate/EstateNetWorthBusinessInterestsTest.php`.

Three things recorded here rather than only in the code, because each will otherwise be
"tidied up" by the next person who reads it:

### 1. `is_iht_exempt` is deliberately ABSENT from the business rows. Do not add it.

Every sibling in that collection sets `is_iht_exempt => false`. On a business that is **not
always true** — Business Property Relief depends on `bpr_eligible`, trading status and two
years' ownership. Computing it inside an aggregation fix would be building a relief model;
asserting a flat `false` would state something untrue of a qualifying trading business. **A
missing key is an absent fact; `false` is a wrong one.**

The omission is safe because **nothing reads that field on this collection** — verified:
neither `NetWorthAnalyzer` nor `AdviserExportPackService`, its only two consumers, touches
it. **Relief is already modelled** for the Inheritance Tax path in
`EstateAssetAggregatorService::gatherUserAssets()`, which is where it belongs. Ruled on by
the team lead: leave it omitted; shape-consistency is not a reason to fabricate a value.

### 2. A SECOND defect was fixed by the consolidation, and nobody raised it

`UserProfileService::calculateAssetsSummary()` summed `$user->businessInterests` — a
`user_id`-only relation — exactly as its chattel line summed `$user->chattels`. **A
co-owner's share of a business, or of a chattel, was therefore worth nothing on the
profile's assets summary and in the `net_worth` derived from it.** Two asset classes, fixed
because the share arithmetic now has one home. No run has seen it: the persona household
holds every chattel as primary owner.

### 3. The share rule is NOT uniform across asset classes

`ownership_percentage` on a business interest is a **shareholding**, so it applies even to
an **individually** held record — 60% of a company held in your own name is 60% yours. For
property, cash, investments and chattels, "individual" means **all of it** and the
percentage is ignored. `CalculatesOwnershipShare` already encodes this, detecting a business
interest by `current_valuation` **and** `business_name` together.

**A future consolidation that assumes the classes are uniform — or a refactor that
simplifies that detection away — breaks this silently**, by valuing a 60% shareholding at
100%. Pinned by the test above, but written here because a test only fails after someone
has already made the change.
