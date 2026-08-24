---
id: W-0015
title: The same joint account's share is computed three different ways — investments page says £95,000, wealth summary says £47,500
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md
owner: build-lead
status: gated
surfaces: [web, m, ios]
created: 2026-08-21T00:20:00Z
claimed: 2026-08-21T10:30:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21T10:30:00Z
prior_art_found: ['App\\Traits\\CalculatesOwnershipShare', 'resources/js/utils/ownership.js (existed, zero consumers)', 'InvestmentController/PropertyController/MortgageController raw user_share in joint audit logs', 'registry/capabilities.md "Ownership share calculation"']
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **Pass A** (desktop web module UI forms, local
`localhost:8000`), account **David Jones (primary)**, user id 16.

**Surfaces:** desktop web, `/net-worth/investments` vs `/net-worth/wealth-summary`.

Severity: **high**. Two screens in the same session, same user, same account, show
figures £47,500 apart. This is the Rule 20 disease in its clearest form: one concept,
three implementations.

### Expected

Persona file `tests/Persona/peak_earners.md:306-316` — Joint General Investment
Account, AJ Bell, £95,000, **Ownership: Joint**.

Per Rule 6 the single record must render **£47,500 to David and £47,500 to Sarah**,
and every surface that displays that share must agree: the investments module page,
the household wealth summary, and `/m`.

### Actual

The same account, same session, same user, read minutes apart:

| Screen | Renders |
|---|---|
| `/net-worth/investments` (David) | **Full Value £95,000 · Your Share (100.00%) £95,000** |
| `/net-worth/investments` (Sarah, the JOINT owner) | **Full Value £95,000 · Your Share (100.00%) £95,000** |
| `/net-worth/wealth-summary` | Investments — David **£47,500**, Sarah £132,500, Total £180,000 |
| `/m/app/investment` | **Total portfolio value £95,000**, no share, no joint badge |
| `/m/app/net-worth` | assets £1,020,000 — investments counted at **£47,500** |

Two independent faults are visible here:

1. **Cross-surface disagreement** — £47,500 apart between two web screens, and `/m`
   disagrees with itself the same way.
2. **Double-counting** — both spouses are told they own 100% of the same single
   record: £190,000 of claimed ownership against a £95,000 asset. This is exactly what
   Rule 6 exists to prevent.

DB row behind all of it: `investment_accounts.id 14`, `ownership_type joint`,
`joint_owner_id 17`, `ownership_percentage 100.00` (the stored value is itself wrong —
W-0014).

### Evidence

Screenshot: `tests/Persona/20-08-2026_run/pass-a-web/05-web-sarah-investments-your-share-100pct-95000.jpg`
Screenshot: `tests/Persona/20-08-2026_run/pass-a-web/07-web-sarah-wealth-summary-household-split.jpg`
Screenshot: `tests/Persona/20-08-2026_run/pass-a-web/09-m-sarah-investments-full-95000-no-share.jpg`
Report: `reports/R-02-pass-a-verification.md` RED-2, RED-3, RED-4.

### The contradiction

Joint General Investment Account, AJ Bell, £95,000, `joint_owner_id = 17`,
`ownership_percentage = 100` (stored value is itself wrong — see W-0014).

| Screen | Renders |
|---|---|
| `/net-worth/investments` | **Full Value £95,000 · Your Share (100.00%) £95,000** |
| `/net-worth/wealth-summary` | Investments — David **£47,500**, Sarah £132,500, Total £180,000 |

Both were read in the same browser session, minutes apart, with no data change
between them.

### Three mechanisms for one number

**1. `app/Traits/CalculatesOwnershipShare.php:73`** — the shared trait, used by the
API (`InvestmentController.php:104`) and by
`CrossModuleAssetAggregator::calculateInvestmentTotal` (`app/Services/Shared/CrossModuleAssetAggregator.php:202-207`)
which feeds the wealth summary:

```php
// Joint or tenants_in_common ownership - use ownership_percentage (default 50)
$jointPercentage = $percentage !== 100.0 ? $percentage : 50.0;
```

It **silently rewrites a stored 100 to 50** for any joint asset. Verified live:
`calculateInvestmentTotal(16)` returns 47500 while the row says
`ownership_percentage = 100.00`.

**2. `resources/js/components/NetWorth/InvestmentList.vue:86-87`** — the investments
list does its own client-side arithmetic on the raw percentage, ignoring the
`user_share` the API already computed and returned:

```vue
<span class="detail-label">Your Share ({{ account.ownership_percentage || 50 }}%)</span>
<span class="detail-value">{{ formatCurrency(getDisplayValue(account) * ((account.ownership_percentage || 50) / 100)) }}</span>
```

100 → £95,000.

**3. `app/Http/Controllers/Api/InvestmentController.php:972`** — the store/update
response computes it raw as well, bypassing the trait that the same controller uses
at :104, :467 and :611:

```php
'user_share' => $validated['current_value'] * (($account->ownership_percentage ?? 100) / 100),
```

### Two problems, not one

**a) Parity.** `InvestmentList.vue` must consume the `user_share` the API supplies
rather than recomputing it. One home for the calculation, every consumer reads it
(Rule 20).

**b) The trait's fallback is masking, not fixing.** `CalculatesOwnershipShare.php:73`
coercing `100 → 50` is a defensive patch over the bad data W-0014 writes. It has two
bad consequences of its own:

- It makes a legitimate 100/0 joint arrangement inexpressible — a user who genuinely
  owns 100% of a jointly-titled asset silently becomes 50%.
- It hides W-0014 on every surface that uses the trait, which is why the storage bug
  survived: the wealth summary looked right.

Fix the storage (W-0014) and this fallback should be removed, not kept.

There is a third symptom from the same stored value: `InvestmentList.vue:57` only
renders the joint badge when `ownership_percentage < 100`, so an account stored at
100 does not even display as joint.

### Repro

1. Create a joint investment account with a linked spouse (see W-0014 for the route).
2. `/net-worth/investments` → the card reads "Your Share (100.00%) £95,000".
3. `/net-worth/wealth-summary` → Investments row reads £47,500 for the same user.
4. `php artisan tinker` → the row says `ownership_percentage = 100.00`, and
   `app(CrossModuleAssetAggregator::class)->calculateInvestmentTotal(16)` returns
   47500.

### What was verified as CORRECT, for contrast

The wealth summary's arithmetic is otherwise exact — every line recomputed by hand
against the persona and the DB:

```
                 David       Sarah      Total
Pensions       £500,000   DB only    £500,000     (180,000 + 320,000)
Property       £425,000   £425,000   £850,000     (850,000 joint 50/50)
Investments     £47,500   £132,500   £180,000     (see above; 85,000 + 95,000)
Cash            £47,500    £28,780    £76,280     (25,000+22,500 / 6,280+22,500)
Valuables            £0    £18,000     £18,000
Total Assets £1,020,000   £604,280 £1,624,280
Mortgages       £32,500    £32,500     £65,000     (65,000 joint 50/50)
Net Worth      £987,500   £571,780 £1,559,280
```

Property and mortgage joint splits render correctly on both sides. The investments
row is the only one in dispute, and only because of this item plus W-0014.

## Acceptance

- [ ] `InvestmentList.vue` renders the API's `user_share` instead of recomputing —
      no client-side ownership arithmetic.
- [ ] `InvestmentController.php:972` uses `calculateUserShare` like its siblings at
      :104/:467/:611.
- [ ] After W-0014 lands, the `100 → 50` fallback at
      `CalculatesOwnershipShare.php:73` is REMOVED, and a deliberate 100/0 joint split
      is preserved rather than rewritten.
- [ ] `/net-worth/investments` and `/net-worth/wealth-summary` agree to the penny for
      the same account, from both spouses' accounts.
- [ ] The joint badge (`InvestmentList.vue:57`) shows for any account with a
      `joint_owner_id`, not only when the percentage is under 100.
- [ ] Same audit run across savings, property and chattels — anywhere a share is
      computed outside the trait (Rule 20).
- [ ] `/m` and iOS checked for their own copies of this arithmetic (Rule 19).
- [ ] Re-verified live in the browser by the persona run, both accounts, both screens.

## Working notes

(append-only)

- 2026-08-20 persona-tester: raised from Pass A. Fix alongside W-0014 and W-0013 —
  all three are the same concept implemented differently per module.

- 2026-08-20 persona-tester: `/m` evidence added. The same contradiction exists
  inside the mobile pathway on its own:
  - `/m/app/net-worth` — "Total net worth £987,500 · £1,020,000 in assets" (assets
    include investments at £47,500, matching the web wealth summary).
  - `/m/app/investment` — "Total portfolio value £95,000 · Across 1 account."
  So `/m` disagrees with itself by £47,500 across two screens, and `/m`'s investments
  screen shows no ownership share and no joint indicator at all — the account's joint
  nature is invisible there. Whatever single source the fix lands on must feed the
  `/m` investment screen too (Rule 19 + Rule 20).
  Net worth totals themselves are in perfect parity: web wealth summary £987,500 =
  `/m` net worth £987,500 = `/m` dashboard "£12,500 away" from the £1,000,000
  milestone.

- 2026-08-20 persona-tester: **spouse-side evidence — this is double-counting, not
  just disagreement.** Logged in as Sarah Jones (user 17), who is the *joint owner*
  of the AJ Bell GIA (`joint_owner_id = 17`, she is NOT `user_id`).
  `/net-worth/investments` renders for her:

  ```
  General Investment Account | AJ Bell
  Full Value            £95,000
  Your Share (100.00%)  £95,000
  Current Portfolio     £180,000        (= 85,000 + the FULL 95,000)
  ```

  David sees exactly the same "Your Share (100.00%) £95,000" on his account. So the
  same single record is presented as 100%-owned to BOTH spouses — £190,000 of claimed
  ownership against a £95,000 asset. This is the failure mode Rule 6 exists to
  prevent.

  Cause is the same line: `InvestmentList.vue:86-87` multiplies by the record's raw
  `ownership_percentage` with no regard for whether the viewer is the primary owner or
  the joint owner. `CalculatesOwnershipShare` handles that correctly
  (`app/Traits/CalculatesOwnershipShare.php:75-82` gives the joint owner the
  complementary share) — the component just doesn't use it.

  For contrast, the property card gets this right for the same household: Sarah's
  `/net-worth/property` shows "Full Property Value £850,000 · Your Share (50.00%)
  £425,000 · Your mortgage liability £32,500", correctly complementary to David's.

- 2026-08-21 build-lead: **FIXED — one home, backend and frontend, web and `/m`.**

  **The consolidation (this is the item's point, not a side effect).** Eight
  mechanisms implemented "the primary owner's share of a shared asset". They now
  read two homes, one per side of the wire:

  - `app/Support/SharedOwnership.php` (NEW) — the write rule. Consumed by all four
    Store normalisers (`InvestmentAccountNormaliser:100`,
    `MortgageNormaliser:89`, `PropertyNormaliser:60`,
    `SavingsAccountNormaliser:33`), both savings FormRequests, and
    `MortgageService::createFromPropertyData`. `PropertyController` and
    `InvestmentController` inline copies DELETED.
  - `resources/js/utils/ownership.js` — the display rule. It existed with **zero
    consumers**; it is now viewer-aware, prefers the API's `user_share`, and is
    imported by `InvestmentList.vue`, `PortfolioOverview.vue`, `PropertyCard.vue`,
    `SavingsModuleOverview.vue`, `AccountGroupList.vue`,
    `ChattelDetailInline.vue`, the `investment` store's `totalPortfolioValue`
    getter, and **five `/m` views** by relative import (`resources/mobile/views/modules/`
    `Investment.vue`, `InvestmentAccountDetail.vue`, `SavingsAccount.vue`,
    `PropertyDetail.vue`, `NetWorthCategory.vue`). `/m` has its own bundle but no
    longer its own ownership arithmetic — precedent for the cross-import is the
    web SPA already importing `resources/mobile/utils/fynText.js`.

  **Per acceptance bullet:**
  - `InvestmentList.vue:88` renders `userShareOf(account)` → the API's
    `user_share`. No client-side ownership arithmetic remains in the component.
  - `InvestmentController.php` joint audit log now computes the post-edit share
    through `calculateUserShare` on a clone, like its siblings. Same fix applied
    to the identical raw computations in `PropertyController` and
    `MortgageController` (three copies, one disease).
  - The `100 → 50` fallback at `CalculatesOwnershipShare.php:73` is **REMOVED**.
    `database/migrations/2026_08_21_000000_normalise_shared_ownership_percentage.php`
    repairs the rows it was masking (properties, savings_accounts,
    investment_accounts, mortgages, chattels; business_interests deliberately
    excluded — there the percentage is a shareholding). The migration changes no
    displayed figure on trait-consuming surfaces: those rows were already being
    treated as 50/50.
  - Joint badge (`InvestmentList.vue:58`) now uses `isSharedRecord()` — any record
    with a second party, not only `percentage < 100`.
  - Savings, property and chattels audited and routed through the same helper.

  **DEVIATION, flagged for the Chief of Staff.** The bullet "a deliberate 100/0
  joint split is preserved rather than rewritten" is **NOT implemented, and
  cannot be alongside W-0014's own acceptance.** W-0014 requires matching
  `PropertyController.php:154-158`, which rewrites a submitted 100 to 50
  unconditionally; `StoreSavingsAccountRequest` independently *rejects* any
  shared share outside (0,100). No form in the app exposes a share input for
  joint ownership, so a "deliberate" 100 is unreachable by a user. I implemented
  the board's own proven-correct reference (rewrite at the input boundary) and
  removed the read-side rewrite, which is what makes every surface agree. If CSJ
  wants 100/0 expressible, that needs a share input and a decision on the savings
  validator — a separate item.

  **Live browser verification, both accounts, localhost:8000:**

  | Screen | David (16) | Sarah (17) |
  |---|---|---|
  | `/net-worth/investments` card | Joint · Full £95,000 · **Your Share (50.00%) £47,500** · Held with Sarah Jones | Joint · Full £95,000 · **Your Share (50.00%) £47,500** · Held with David Jones |
  | `/net-worth/investments` Current Portfolio | — | **£132,500** (was £180,000) |
  | `/net-worth/wealth-summary` Investments | **£47,500** | £132,500 |

  The two screens now agree to the penny, from both accounts. £47,500 + £47,500 =
  £95,000 — the £190,000 double-count is gone. `GET /api/investment` confirmed
  returning `user_share: 47500`, `is_primary_owner`, `owner_name`,
  `joint_owner_name` for both sides.

  **GAP — I COULD NOT TEST `/m` LIVE.** The `/m` code changes are made and
  `vite.mobile.config.js` builds clean (126 modules, to a scratch outDir), but
  rendering `/m` locally needs `public/m-build/` rebuilt, and CSJ has a Vite dev
  server running on :5173 with `public/hot` live. Per `verify-m`, `/m` is
  verified on csjones. Routed to Quality. The API those screens read is verified
  correct above, and `/m` reads the same endpoints as web.

  **iOS not checked** (`ios-native/`) — outside this dispatch.

- 2026-08-21 build-lead: batch handover (CLAUDE.md Rule 22) — `workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md`. Carries the dispatch verbatim, the joint-share consolidation reasoning, decisions taken, dead ends ruled out, and environment state.
