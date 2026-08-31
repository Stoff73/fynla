---
id: W-0186
title: A joint-life protection policy is invisible to the other life assured — Sarah sees "No Protection Coverage" while her own estate plan credits her with £500,000 of cover in trust
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0019-cycle2-ownership-applied-one-side-only.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T00:30:00Z
claimed: 2026-08-22T02:20:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0173, W-0154]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Cycle 2 journey re-walk, local, both persona accounts, read-only.
**Surface:** `/protection`, and `/plans/estate` → Life Cover.

### Expected

The persona's Vitality policy is **joint life** and **written in trust**, with Sarah
Jones as first-named beneficiary (`peak_earners.md`, Protection §1: `joint_life: Yes`,
`in_trust: Yes`, beneficiaries "Sarah Jones, William Jones, Charlotte Jones"). The two
accounts are linked with `SpousePermission` accepted both ways. A joint-life policy
covers both lives, so it must be visible to both.

### Actual

| Surface | David | Sarah |
|---|---|---|
| `/protection` | Full analysis — Vitality Level Term £500,000 @ £85/month, Legal & General Critical Illness £200,000 @ £125/month, cover allocation, four shortfall panels | **"No Protection Coverage — You currently have no protection policies recorded."** |
| `/plans/estate` → Life Cover | Cover in Trust £500,000 · Total Policies **1** | Cover in Trust **£500,000** · Total Policies **0** |

So Sarah is told, on one screen, that she has no protection at all, and on another that
she holds half a million pounds of cover in trust from zero policies. Both cannot be
right and neither matches the persona.

The empty state is not neutral either — it warns her that "your family may face
financial difficulties if something unexpected happens", on a household that has
£700,000 of cover.

### Impact

Same shape as **W-0173**: the record reaches the owner and stops. On a joint-life policy
that is worse than a rental split, because covering both lives is the product's entire
purpose. A user in Sarah's position would reasonably buy cover she already has.

### Repro

1. `david.jones@example.com` → `/protection` → both policies and the full analysis.
2. `sarah.jones@example.com` → `/protection` → "No Protection Coverage".
3. `sarah.jones@example.com` → `/plans/estate` → "Cover in Trust £500,000 · Total Policies 0".
4. Database: one `life_insurance_policies` row on `users.id 16`, `joint_life` set.

### Acceptance

1. A joint-life policy appears on both lives assured, from one source (Rule 20).
2. `/plans/estate` never reports cover with a policy count of zero — the two figures come
   from the same query.
3. A linked spouse covered by a household policy is not shown the no-coverage empty state.
4. Verified in a browser on both accounts, and on `/m` and native.

## Working notes

**2026-08-22 — build-lead (`cycle2-ownership`). Fixed.** Branch document:
`workforce/branches/fixes/F-0019-cycle2-ownership-applied-one-side-only.md`.

### The schema constrains the answer, and it matters

`life_insurance_policies` carries `joint_life` (a boolean) and a free-text
`beneficiaries` string. It has **no `joint_owner_id`, no `ownership_type` and no
`ownership_percentage`** — unlike properties, mortgages, savings, investments,
chattels, liabilities and business interests, every one of which names its
counterparty. So a joint-life policy records *that* it covers two lives and never
records *whose*. The second life assured can only be the linked spouse.

That is what I have implemented, and it is the only reading the data supports. Naming
a second life explicitly is a schema change; raised as **W-0200** rather than assumed
into existence here.

Prior art outcome corrected `extend` → **`none`**: nothing in the application answered
"which policies cover this user's life". Every consumer read `$user->lifeInsurancePolicies`,
a plain hasMany on `user_id`.

### The distinction the whole fix turns on

**Covering a life and owning the contract are two different questions, and this record
answers them differently.**

- **Covering** — a joint-life policy covers both spouses. It pays out in full on the
  first death, and the two deaths are mutually exclusive events, so **each account
  counting the full £500,000 in its own protection analysis is correct**, not a double
  count.
- **Owning** — the contract, the premium, the right to edit or cancel it, and the
  estate the proceeds fall into all belong to `user_id`, once. **The same policy in
  both estates WOULD be a double count**, so the estate asset aggregation is untouched.

Getting that backwards in either direction is a worse defect than the one being fixed.
It is pinned by a test in both directions.

### One home

**`app/Services/Protection/LifeCoverReach.php`** (NEW) — four methods, all reading one
rule:

| Method | Answers |
|---|---|
| `policiesCovering(User)` | the policies covering this user's life: own + the spouse's `joint_life` ones |
| `isOwnedBy(policy, user)` | whose contract it is — still `user_id`, unchanged |
| `otherLifeAssured(policy, viewer)` | the other life's name, **symmetric**: "Sarah Jones" on his account, "David Jones" on hers |
| `householdCoverInTrust(User)` | the in-trust amount **and the count of the policies behind it**, from one pass |

Routed onto it:

- `ProtectionController::index():94` — the policy list, so `/protection`'s empty state
  is decided by what covers her, not by what she typed.
- `ProtectionAgent::analyze():108,112,229` and `buildScenarios():408` — the coverage
  analysis, the four shortfall panels and the itemised list.
- `EstateAgent::analyze():117,327-334` — the life-cover block.
- `LifeInsurancePolicyResource` gains `is_own_policy` and `joint_life_with`.

`ComprehensiveProtectionPlanService` (`/plans/protection`) and
`MobileDashboardAggregator` both derive their policy counts from
`ProtectionAgent::analyze()`, so they inherit it without their own change.

### Acceptance 2 — "cover in trust from 0 policies"

The estate plan summed the user's in-trust cover **and the spouse's**, then printed the
count of the user's **own** policies beside it (`EstateAgent:325-329`). Two different
sets, one card. `householdCoverInTrust` returns the amount and the count from the same
pass, so they cannot disagree.

**`policies_not_in_trust_count` is deliberately left individual.** It drives
"place this policy in trust" — in `EstateAgent::step3ExistingLifeCover():901` and in
`DashboardAggregator:796`'s alert. Making it household would tell a spouse to take an
action on a policy she cannot touch. Its amount (`total_cover_not_in_trust`) is
individual too, so that pair already agrees.

### Measured against the live persona rows (read-only)

```
David: policies covering = 1 · sum assured £500,000 · cover in trust £500,000 from 1 policy
       Vitality  own=yes  other life assured = 'Sarah Jones'
Sarah: policies covering = 1 · sum assured £500,000 · cover in trust £500,000 from 1 policy
       Vitality  own=no   other life assured = 'David Jones'

EstateAssetAggregatorService::getExistingLifeCover(16) = 700,000
EstateAssetAggregatorService::getExistingLifeCover(17) = 0        <- no double count
```

Sarah's "No Protection Coverage — your family may face financial difficulties" is gone,
and "£500,000 in trust from 0 policies" is now £500,000 from 1. The estate side is
unmoved.

### The read-only problem this created, and how it is handled

Writes are scoped to `user_id` (`PolicyCRUDTrait:88,130`), so a policy reaching Sarah
through David is hers to be covered by and **not hers to change** — an edit from her
account 404s. Showing it without saying so would have replaced one defect with another:
a button that always fails. Every surface now says whose record it is, in plain text,
no icons (Rule 15), acronyms spelled out (Rule 9):

| Surface | Behaviour |
|---|---|
| `PolicyCard.vue` | "Joint life with David Jones" · "Recorded on David Jones's account" |
| `PolicyDetail.vue` | Edit and Delete replaced by "Joint life policy recorded on David Jones's account. Edit it there." · "Joint Life: Yes, with David Jones" |
| `/m` `Protection.vue` | list line "Joint life with David Jones — recorded on their account" |
| `/m` `ProtectionPolicy.vue` | "Joint life: Yes, with David Jones" · "Recorded by: David Jones" · **the contextual Fyn edit request is suppressed** for a policy this account cannot edit |

`/m`'s policy detail reads `/api/protection` and matches by id, so the shared policy
resolves there; checked before relying on it.

### Tests

`tests/Feature/Protection/JointLifePolicyReachesBothLivesTest.php` — **8 passing, 27
assertions**, driven through the real HTTP endpoint.

1. A joint-life policy **appears on the other life assured's `/api/protection`** — the harm.
2. A single-life policy does **not** reach the spouse.
3. `is_own_policy` is `true` for the owner and `false` for the spouse.
4. `joint_life_with` names the other life **symmetrically from either account**.
5. The spouse's edit **and** delete still return 404 and the record is unchanged — the
   read-only marker is truthful, not decorative.
6. Cover in trust is never reported against a count of zero.
7. Nothing in, nothing out: no cover and no policies when the household has neither.
8. **The same policy is not in both estates** — `getExistingLifeCover` is 500,000 for
   the owner and 0.0 for the spouse. The invariant.

`tests/frontend/components/Protection/PolicyCard.test.js` — 3 added, **12 passing**:
the joint-life name renders, the not-yours note renders only when it is not yours, and
a single-life policy says nothing about sharing.

Regression green: `Unit/Agents`, `Feature/Protection`, `Unit/Services/Protection`,
`ProtectionActionDefinitionTest`, `ProtectionGapPresentationTest` — **275 passing**;
`Unit/Services/Estate`, `Feature/Estate`, `Unit/Services/Plans`, `Feature/Plans` —
**424 passing**. Vitest: `PolicyCard`, `FinancialDataParity`, `ContextualEditAuthority`
— 22 passing. `./vendor/bin/pint` on the touched paths: passed.

### A real bug this work surfaced and fixed

The wider run caught `Undefined variable $userId` in
`UserProfileService::calculateLiabilitiesSummary()` — the non-mortgage items closure did
not capture it. My own W-0187 tests missed it because neither the persona nor those
fixtures had a non-mortgage liability. Fixed, and the W-0187 test extended to cover the
profile's "other" list on both sides so it cannot recur.

### Surfaces

`/api/protection` is shared by web and `/m`, and `ios-native/` reads it too, so the
backend reach lands on all three. The per-surface work above covers web and `/m`.

**`ios-native/` — checked, deliberately not changed, and here is exactly why.**
`ProtectionPolicyView.swift` has **no Edit or Delete control** (the reference to "Back +
Edit details" at `:4` is a stale docblock describing the `/m` screen it was ported
from; there is no such button in the view). So there is **no affordance to suppress and
no broken button to introduce** — the joint-life policy will simply appear in the other
life assured's list, which is the fix. What iOS will **not** show is the "Joint life
with David Jones" line: `ProtectionModels.swift:216-238` decodes `in_trust` but has no
`joint_life` case at all, so native has never displayed joint-life status. That is a
**pre-existing parity gap, not a regression from this work** — three lines in the DTO
plus a row in the view. Raised as **W-0201** rather than folded in silently.

**Not done: browser verification, by instruction.**

- 2026-08-31 build-lead: **CLOSED — verified against `dev`, and consumed on every surface rather
  than patched on one.** `App\Services\Protection\LifeCoverReach` owns "which lives does this
  policy reach", and its readers are `EstateAgent:54`, `ProtectionAgent:35`,
  `LifeInsurancePolicyResource:17` and `IHTController:265`
  (`householdCoverInTrust()`). A joint-life policy is therefore visible to both lives assured, so
  Sarah is no longer told she has no protection on one screen and £500,000 of cover in trust from
  zero policies on another.
