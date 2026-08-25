# W-0272 — build-lead → quality-lead

## Done

Built `app/Services/Shared/DependantsReach.php` — the one home for "who depends on
this user" — and routed `AutoRiskCalculator::calculateDependantsFactor()` to it.
Sarah (17) now reads **2, Lower-Med**; she read **0, Upper-Med** with *"No dependants
means you can afford to take more investment risk"*.

5 new feature tests covering reach, link removal, a deleted partner, the
self-dependant case and the duplicate-child case.

## Not done, and why

**Nothing in this item.** Browser-verified on Sarah's own login: `/risk-profile`
reads Dependants **2 · Lower-Med** and the detail page names William and Charlotte.

**The other eight consumers** of the same question still run the `user_id`-only query
— protection plans, Fyn's memory facts, savings actions, intestacy, the advice prompt.
Raised as **W-0275**. Until that lands, `/risk-profile` and Fyn can still describe the
same household differently, and Fyn's is the one that will say it out loud.

## What you need that isn't obvious from the artefacts

1. **Three rules make this more than a spouse union**, and each has a test that fails
   without it: the viewer is not their own dependant (a dependent spouse reached from
   the other side would count the reader), a child both parents entered is one child,
   and the link must be **live** (`liveSpouseId()`, because `spouse_id` outlives the
   partner's account).
2. **No spouse-permission gate, and that is deliberate.** Reasoning is in the class
   docblock: `hasAcceptedSpousePermission()` governs a partner's *financial* data, and
   `ProfileCompletenessChecker` already reads spouse children ungated to judge the
   user's own profile. If you disagree, it is a one-line change — but change it
   everywhere, not here alone.
3. **The count is de-duplicated on name + date of birth** where there is no
   `linked_user_id`. Two genuinely different children with the same name and the same
   birthday would merge. I judged that less harmful than double-counting one child,
   and a row with no usable identity keeps its own id so it never merges.
4. **This suite passes the mutation test in both directions** — restoring the
   `user_id`-only reach turns 3 of 5 red, removing the de-duplication turns the
   duplicate-child case red. Under the *first* mutation the duplicate-child case still
   passed, which is why the two properties are separate cases.

## Assumptions I made

- **That a dependant recorded on a linked spouse's account is a dependant of both
  parents.** The schema supports no other reading. A one-sided dependant — a child
  only one partner supports — cannot be expressed today, before or after this change.
- **That the persona's two children are the intended count**, i.e. that the household
  has two dependants and not "two on his side, zero on hers".

## Surfaces covered / not covered

- **Web** — code complete, service-level measured, **browser-verified on both accounts** (Sarah through the MFA gate, code taken from the database).
- **`/m`** — no dependants surface exists; nothing to change. `/m` was still driven
  end to end for W-0271 on the same login, so the session is proven.
- **iOS** — same backend; no native risk screen. **Not verified** — say so.
