---
id: W-0351
title: A mixed-rate mortgage's fixed and variable rates are stored correctly and can never be displayed — the detail view gates on two fields MortgageResource does not serialise
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: build-lead
status: done
severity: medium
surfaces: [web, m, ios]
created: 2026-08-23T00:40:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0263, W-0262, W-0326]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

**Found in the browser at the moment W-0263's headline went green** — the user can
now enter a 12% fixed portion and it saves, and **they still cannot see it.**

### The defect

`PropertyDetailInline.vue:319` renders the fixed portion only when **both** hold:

```vue
v-if="mortgage.rate_type === 'mixed' && mortgage.fixed_rate_percentage"
```

`MortgageResource` serialises `fixed_interest_rate` and `variable_interest_rate`,
**and never serialises `fixed_rate_percentage` or `variable_rate_percentage` at
all.** The gate therefore reads `undefined`, which is falsy, and **the row is
structurally unreachable** — no data can satisfy it.

Verified from the live response to `GET /api/properties/9/mortgages` immediately
after a successful save:

```json
"rate_type":"mixed",
"fixed_interest_rate":"12.0000",
"variable_interest_rate":"14.7500"
```

No `fixed_rate_percentage`. No `variable_rate_percentage`. Both are stored —
`60.00` and `40.00` — and both are entered by the user on the property form.

### What the user experiences

Enter a part-fixed part-variable mortgage: 60% at 12%, 40% at 14.75%. The save
succeeds. The mortgage detail then shows:

```
Interest Rate
Rate Type:  Mixed
```

**and nothing else.** Not the 12%, not the 14.75%, not the 60/40 split. The
headline rate row is deliberately hidden for mixed (`rate_type !== 'mixed'`), so
suppressing the two portion rows leaves the section displaying a label and no
numbers at all.

### Why it is its own class

F-0025 measured six axes of rule-versus-schema disagreement, all at the **write**
boundary. **This is the same disease at the READ boundary:** a field that is
entered by a form, stored by the column, and consumed by a template, but absent
from the Resource that carries it back.

It is invisible to every sweep in F-0025 — the rules are right, the columns are
right, the Store is right, the write is right. **Only the journey home is broken.**

The dependency is also the trap: `fixed_interest_rate` IS serialised, so a reader
checking "is the rate exposed?" answers yes. The row is hidden by a **sibling**
field, which is the sort of coupling nothing warns about.

## Acceptance

1. `MortgageResource` serialises `fixed_rate_percentage` and
   `variable_rate_percentage`, conditioned like their siblings.
2. A mixed-rate mortgage displays both portions and both rates.
3. **Worth a sweep of its own:** any `v-if` gating a display on a field the
   Resource does not return. That is the read-boundary equivalent of W-0262 and
   nothing currently looks for it.

## Working notes

- 2026-08-23 build-lead (`fix-cycle4-columns`): found while browser-verifying
  W-0263's headline on mortgage 8. `app/Http/Resources/` is outside this batch's
  scope, so reported rather than fixed. The fix is two lines; the sweep is not.

---

## Closed 2026-09-01

**Acceptance 1 — done.** `app/Http/Resources/MortgageResource.php:69-88` serialises both
fields, gated on `rate_type === 'mixed'`.

**Gated on `rate_type`, not `mortgage_type`** — worth stating, because the sibling pair
directly above it (`repayment_percentage` / `interest_only_percentage`) gates on
`mortgage_type`. Those split repayment against interest-only; these split fixed against
variable. Two different questions about one mortgage, answered by two different columns,
and copying the sibling's condition would have withheld the rates from every mixed-rate
mortgage that is not also mixed-repayment.

**Acceptance 2 — done, on both surfaces.**

- Web: `PropertyDetailInline.vue:319,323` was already correct and needed no change. It
  gates each row on the split and prints it as the label; the gate read `undefined`
  because the payload never carried the key, so the rows were **structurally
  unreachable** — no data could satisfy them.
- `/m`: `MortgageDetail.vue:31-46` had **no row at all** for the split. It does now, and
  it renders the same fact the same way — split as the label, rate as the value.
  Rule 19.

### Tests — the diff only

- `tests/Unit/Resources/MortgageResourceTest.php` — 2 new: both fields served with both
  rates for a mixed-rate mortgage, and both **withheld** from one that is not.
  The tests use `resolve()` rather than `toArray()`, and the reason is at the line:
  `when()` leaves a `MissingValue` under the key and only `resolve()` strips it, so a
  `toArray()` assertion passes on a withheld field.
  **Mutation-verified:** deleting `fixed_rate_percentage` from the resource turns it red.
- `resources/mobile/views/modules/__tests__/MortgageMixedRate.spec.js` — 2 new: the rows
  render for a mixed-rate mortgage and do not for a fixed one.
- Regression: 12 tests across `tests/Unit/Resources` and the mortgage read-consumer
  parity suite.

### NOT DONE — acceptance 3

**The sweep is not done.** Acceptance 3 asks for a sweep of every `v-if` gating a display
on a field its Resource does not return — the read-boundary equivalent of W-0262 — and
nothing currently looks for it. This item fixed the one instance it was raised for; the
class remains unswept, and it is a separate piece of work that should be its own board
item rather than absorbed silently here.
