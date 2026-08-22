---
id: W-0201
title: The native protection screens never decode joint_life, so iOS cannot show that a policy covers both lives
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: low
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
