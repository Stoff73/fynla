---
id: W-0201
title: The native protection screens never decode joint_life, so iOS cannot show that a policy covers both lives
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: review
severity: medium
surfaces: [ios]
created: 2026-08-22T02:30:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0186]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Raised by `cycle2-ownership` while fixing **W-0186**. **Pre-existing, not a regression
from that work** — native has never shown joint-life status.

### The gap

`ios-native/Fynla/Features/Protection/ProtectionModels.swift:216-238` decodes
`in_trust`, `is_mortgage_protection`, `beneficiaries` and the rest, but has **no
`joint_life` case** at all. `ProtectionPolicyView.swift:173-176` renders "Mortgage
protection" and "Held in trust" rows and no joint-life row.

W-0186 makes `GET /api/protection` return a joint-life policy to **both** lives
assured, with two new fields — `is_own_policy` and `joint_life_with`. Native will
therefore show the policy to the other life assured (the fix lands), but will present
it as an ordinary policy with no indication that it is joint life or that the record
belongs to the spouse's account.

**No broken button is introduced:** `ProtectionPolicyView` has **no Edit or Delete
control**. The "Back + Edit details" text at `ProtectionPolicyView.swift:4` is a stale
docblock describing the `/m` screen it was ported from. Checked before concluding.

### Acceptance

1. `PolicyDTO` decodes `joint_life`, `is_own_policy` and `joint_life_with`.
2. The detail view shows "Joint life: Yes, with <name>" and, when `is_own_policy` is
   false, "Recorded by: <name>" — matching `/m`'s `ProtectionPolicy.vue` wording
   exactly (Rule 20: one wording, all surfaces).
3. If an edit affordance is ever added to that screen, it is suppressed when
   `is_own_policy` is false — the write path is scoped to `user_id` and would 404.
4. Stale docblock at `ProtectionPolicyView.swift:4` corrected while in there.

## Done 2026-08-25 — and the item was wrong about the edit affordance

### The item's claim 3 is not hypothetical, and its claim 4 is inverted

The item says:

> **No broken button is introduced:** `ProtectionPolicyView` has **no Edit or
> Delete control**. The "Back + Edit details" text at `ProtectionPolicyView.swift:4`
> is a stale docblock describing the `/m` screen it was ported from. Checked before
> concluding.

**It was not stale.** `ProtectionPolicyView.swift:66-77` renders
`MobilePageActions(onBack:editDetails:)`, and the `editDetails` closure opens
contextual Fyn against the policy. The docblock described the screen accurately.

So acceptance 3 — *"if an edit affordance is ever added, suppress it when
`is_own_policy` is false"* — was already live. **iOS was offering "Edit details" on
a spouse's joint-life policy**, which `LifeInsurancePolicyResource` states plainly
cannot work: *"the write path is scoped to `user_id`, so an edit from her account
would fail. Surfaces read `is_own_policy` to present it without an edit affordance
rather than offering one that cannot work (W-0186)."* `/m` obeys that at
`ProtectionPolicy.vue:209` by returning nil from its contextual-action builder;
native did not.

That is a live broken affordance, not a missing label, and it is why this is no
longer `severity: low`.

### Changes

**`ProtectionModels.swift`** — `ProtectionPolicy` gains `jointLife: Bool?`,
`isOwnPolicy: Bool?`, `jointLifeWith: String?` with coding keys `joint_life`,
`is_own_policy`, `joint_life_with`.

**`ProtectionPolicyView.swift`**

- `detailRows` appends "Joint life" and "Recorded by" **after** "Held in trust",
  the same position, wording and fallbacks as `/m` (Rule 20): `Yes, with <name>`
  falling back to `Yes`, and `<name>` falling back to `Your spouse`. `/m` tests JS
  truthiness, so an empty string reads there as no name; `spouseName()` reproduces
  that instead of rendering "Yes, with ".
- The edit affordance is withheld when `isOwnPolicy == false`.
  `MobilePageActions` drops the button entirely on a nil closure.
- The closure is hoisted into an explicitly typed `(() -> Void)?` binding rather
  than inlined as a ternary — Swift will not infer a type for `nil : { ... }`, and
  with no toolchain here that is a failure I could not have caught by compiling.
- Docblock corrected: not for being stale, but because the Edit pill is now
  conditional.

### Verified — as far as this machine allows

**The JSON contract, live.** Authenticated as the `peak_earners` preview persona
and called `GET /api/protection`. A returned policy object carries exactly:

    id                     20
    joint_life             false
    is_own_policy          true
    joint_life_with        NULL
    in_trust               true
    is_mortgage_protection false

All three keys present, spelled as the coding keys expect, typed bool / bool /
null-or-string against `Bool?` / `Bool?` / `String?`. **The decode contract is
proven without a compiler.**

**Structural.** Braces and parentheses balance in both files. The `CodingKeys`
enum and the stored properties are 23 and 23, one-to-one, same order — no property
without a key, which is the compile error this class of edit risks.
`ProtectionPolicy` is decode-only; nothing constructs it, so adding fields breaks
no memberwise initialiser.

### Gaps — this surface cannot self-certify

- **NOT COMPILED. NOT RUN. NOT SEEN.** There is no Swift toolchain on this Windows
  machine — no `swiftc`, `swift` or `xcodebuild`. Everything above is static
  analysis and a live check of the JSON it consumes. `08-process.md` §3 already
  says iOS cannot self-certify; this needs a build and a look on a Mac before it
  moves past `review`.
- **No seeded persona has a joint-life policy.** `life_insurance_policies` holds 6
  rows and `SUM(joint_life = 1)` is **0**. So the joint-life path cannot be
  exercised end-to-end against seeded data on any surface — web, `/m` or native —
  without first creating one. Worth its own item: W-0186's behaviour has most
  likely never been seen against seeded data either.
- **The UI test fixture has no joint-life case.**
  `Testing/FinancialDataParityUITestSupport.swift:91` stubs a single life policy
  with no `joint_life`, `is_own_policy` or `joint_life_with`. Decoding still works
  because the new fields are optional. A joint-life case asserting both rows and
  the absent Edit pill would be the real test — deliberately not added, because I
  cannot run it and an unrunnable test is worse than a named gap.
