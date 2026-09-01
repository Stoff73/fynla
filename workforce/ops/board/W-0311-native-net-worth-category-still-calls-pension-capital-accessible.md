---
id: W-0311
title: The native Pensions category still calls the figure "Accessible pension capital" and carries no exclusion note, so a Defined Benefit holder sees a bare £0
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: deferred-ios
severity: medium
surfaces: [ios]
created: 2026-08-22T21:45:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0241, W-0243]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Parity gap left open by **W-0241**, filed rather than skipped (Rule 19: surfaces are
named individually) and rather than blocking that batch on Xcode. Same handling as
**W-0243**, and the two are natural companions for one native visit.

### What W-0241 already fixed for native, with no Swift change

`NetWorthService::getAssetsSummaryWithDetails()` no longer capitalises Defined
Benefit schemes at 20× the accrued pension plus the lump sum — the option CSJ
rejected. Native reads that endpoint (`NetWorthClient.swift:38-44`) and has **no
client-side capitalisation of its own**, so it now receives `value: 0` and
`annual_pension: 35000` for an NHS scheme.

**`NetWorthCategoryView.swift:269-270` already renders the income:**

```swift
if let annual = item.annualPension, annual > 0 {
    values.append("\(MoneyFormatter.gbpWhole(annual)) a year")
}
```

So the native figures are correct today, unreleased, with no code change. The
summary page's disclosure also already works — `NetWorthView.swift:63` renders the
exclusion note from `overview.hasDBPensions`.

### The gap

**Two things on the category screen, both Swift.**

1. **`NetWorthModels.swift:324` still reads `case .pensions: "Accessible pension
   capital"`.** That is the exact sentence W-0241's ruling required retitling on the
   other two surfaces, because it is what makes a £0 line read as a lost record
   rather than a statement. A user taps Pensions, reads "Accessible pension
   capital", and sees **£0** with their NHS scheme listed beneath it.
2. **The category screen carries no exclusion note.** The backend now sends
   `disclosure` and `subtitle` with the pensions section, and
   `NetWorthAssetSection` (`NetWorthModels.swift:56-66`) decodes only `count`,
   `total_value` and `items`, so both are dropped. `Codable` ignores unlisted keys,
   so **nothing breaks** and no coordinated release is needed — the fields are
   simply unread.

## The change

**One home, already built: `App\Constants\PensionDisclosure`.** The wording is
served by the backend with the figure it qualifies; do **not** re-type either string
into Swift, for the same reason web and `/m` no longer hold their own copies.

1. Decode the two new fields on `NetWorthAssetSection`:

```swift
struct NetWorthAssetSection: Decodable, Sendable, Equatable {
    let count: Int
    let totalValue: Decimal
    let items: [NetWorthAssetItem]
    let disclosure: String?
    let subtitle: String?

    private enum CodingKeys: String, CodingKey {
        case count
        case items
        case disclosure
        case subtitle
        case totalValue = "total_value"
    }
}
```

2. **`NetWorthCategoryView.swift:70`** — prefer the server subtitle, falling back to
   the local one for a payload that predates it:

```swift
MobileDetailHeader(
    title: category.title,
    subtitle: section?.subtitle ?? category.subtitle
)
```

3. Render `section?.disclosure` beneath the category total, when non-nil, matching
   the styling already used at `NetWorthView.swift:63-68`
   (`.font(.system(size: 13))`, `FynlaColor.Token.neutral600`). Give it room to
   wrap — **do not clamp or truncate it. A clipped disclosure is not a disclosure**,
   and W-0241's acceptance is that no surface presents the total as complete.
4. **`NetWorthModels.swift:324`** — replace `"Accessible pension capital"` with a
   local fallback consistent with the server's
   `PensionDisclosure::PENSION_CAPITAL_SUBTITLE`. It is only reached if the server
   omits the field.

## Acceptance

1. A Defined-Benefit-only household on native sees **£0 capital, the scheme's real
   annual income, and the exclusion note** on the Pensions category screen.
2. The phrase "Accessible pension capital" appears nowhere in `ios-native/`.
3. Neither disclosure string is duplicated into Swift — both come from the payload.
4. A household with no Defined Benefit scheme sees **no** note (`disclosure` is null
   for them — assert both directions).
5. Verified on the simulator, not by reading the diff.

## Notes

- **Not urgent and not shipping-blocking.** Native reads the staging database and
  the figures are already right; this is wording and a missing note.
- **Worth doing in the same visit as W-0243** (the native retirement card's
  guaranteed income), which is now unblocked. Both are small, both are in
  `Features/`, and both exist because a backend fix reached native for free while
  its Swift copy of the presentation rule did not.
- Do not develop against `ios/` (the dormant Capacitor target).

---

## Deferred 2026-09-01 — iOS is out of scope for the board loop

CSJ ruled on 2026-08-31 that the board loop covers web and `/m` only, and every iOS
item defers rather than being worked. This item's `surfaces` is `[ios]` alone, so all
of it defers. No Swift was changed and nothing was verified on a simulator.

The backend and `/m` halves named in the item are unaffected and remain available to
whoever picks the native work up.
