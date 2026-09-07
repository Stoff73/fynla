---
id: W-0496
title: The native joint-life rows and the suppressed edit affordance have never been looked at on a screen
mission: M-0001-state-truth
owner: build-lead
status: deferred-ios
severity: low
surfaces: [ios]
created: 2026-08-26T00:00:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-26
prior_art_found: [W-0201, W-0186]
prior_art_outcome: extends
constitution_refs: [07-quality-bar]
source: carried out of W-0201 by CSJ direction 2026-08-26 so it does not block PR #718
---

## Intent

W-0201 changed two native Swift files — `ProtectionModels.swift` decodes
`joint_life`, `is_own_policy` and `joint_life_with`, and `ProtectionPolicyView.swift`
renders two new detail rows and suppresses the "Edit details" action when the policy
is not the viewer's own. **Nobody has looked at the result on a screen.**

CSJ ruled on 2026-08-26 that this does not block the merge, because the parts that
can be verified mechanically have been. This item carries the part that cannot.

## What IS verified, so nobody re-does it

- **It compiles on macOS.** `.github/workflows/ios-native.yml` runs on `macos-26`,
  triggers on `ios-native/**`, and ran on `fynla-bug-fixes` at `1b529d4d9`. The
  `Run unsigned unit tests` step reported `** TEST SUCCEEDED **`.
- **`FynlaTests` passes** against fixtures on that runner.
- **The JSON contract is real.** `LifeInsurancePolicyResource` already emits
  `joint_life` (`:56`), `is_own_policy` (`:28`) and `joint_life_with` (`:29`) on
  `dev` — this branch does not change it. Staging serves the fields today; **no
  backend deploy is needed** for this check.

The claim in the original handoff that the change was "not compiled, not run" was
based on not knowing the iOS workflow exists. It does, and it runs on every PR
touching `ios-native/**`.

## What is NOT verified

1. That "Joint life: Yes, with &lt;name&gt;" and "Recorded by: &lt;name&gt;" actually
   **render**, in the right position (after "Held in trust"), with `/m`'s exact
   wording — Rule 20 says one wording on all surfaces, and wording is the kind of
   thing a unit test on a decoder cannot see.
2. That the fallbacks behave: `Yes, with <name>` degrading to `Yes`, and `<name>`
   degrading to `Your spouse`, including the empty-string case `spouseName()` exists
   to reproduce from `/m`'s JS truthiness.
3. **That the "Edit details" affordance is actually gone** when `is_own_policy` is
   false. This is the one that matters, and the item's own history says why: W-0201
   originally recorded the `ProtectionPolicyView.swift:4` docblock as stale and the
   screen as having no edit control. **It was not stale.** `:66-77` renders
   `MobilePageActions(onBack:editDetails:)`, and iOS was offering "Edit details" on a
   spouse's joint-life policy against a write path scoped to `user_id` that would 404.
   That was found by reading the file, after a written claim to the contrary. Looking
   at the screen is what would have caught it first, and it is the check still owed.

## What it needs

A Mac **with Xcode installed** — Command Line Tools alone is not enough
(`xcodebuild` and `simctl` are absent, and `/Library/Developer/CommandLineTools/SDKs/`
carries macOS SDKs only, no iPhoneOS SDK). That is what stopped this being cleared
on 2026-08-26.

Then, per `.claude/skills/ios-simulator`:

1. Adopt the booted simulator; do not boot a rival.
2. Run the **`Fynla-Staging`** scheme — it reads `https://csjones.co/fynla`.
   `Fynla-Production` cannot log in; there are no native endpoints on prod.
3. Sign in as a **csjones** account — testers register there, not on fynla.org.
4. Open a **joint-life** policy and read the two rows.
5. Open a joint-life policy **recorded by the spouse** (`is_own_policy` false) and
   confirm there is no "Edit details" action.

## Acceptance

Both screens seen and described — or a screenshot attached — covering the two rows,
the fallback wording, and the absent edit affordance on a policy the viewer does not
own. A green unit suite does not close this item; that is what is already true.

## Related

- **W-0201** — the change itself. Merged with this carved out.
- **W-0186** — where `is_own_policy` and the no-edit-affordance rule came from.
- **PR #718** — the branch carrying it.

## 2026-09-01 — DEFERRED, iOS. Not closed.

`surfaces: [ios]`, and CSJ ruled on 2026-08-31 that the board loop is web and `/m` only.
`ios-native/` is untouched.

The **code** was read at its current lines rather than assumed, so the next person does
not repeat that part:

- `ProtectionModels.swift:222-224, 247-249` — `jointLife`, `isOwnPolicy` and
  `jointLifeWith` are decoded, with the coding keys mapped to the JSON contract.
- `ProtectionPolicyView.swift:199` — `rows.append(("Joint life", name.map { "Yes, with \($0)" } ?? "Yes"))`, the fallback the item asks about.
- `:201-202` — `rows.append(("Recorded by", spouseName(policy) ?? "Your spouse"))`, gated on `isOwnPolicy == false`.
- `:72` — `let editAction: (() -> Void)? = policy.isOwnPolicy == false ? nil : { ... }`, and `:88-90` passes it to `MobilePageActions`, which drops the button when it is nil.

**That is not what this item asks for, and reading it does not close it.** The acceptance
is *"both screens seen and described — or a screenshot attached"*, and it says explicitly
that a green unit suite does not close it. The three unverified things — that the rows
render in the right position with `/m`'s exact wording, that the fallbacks behave
including the empty-string case, and that the edit affordance is actually gone — are all
things only a screen shows. The item's own history is the argument: the docblock was
once recorded as stale and was not, and iOS was offering "Edit details" against a write
path that would 404.

It still needs a Mac with Xcode, the `Fynla-Staging` scheme, and a csjones account.
Unchanged and owed.
