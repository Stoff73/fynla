---
id: W-0328
title: Product question — should Fynla support capped and offset mortgage rate types? They were in a validation rule but the column has never been able to store them
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: product-lead
status: handoff
severity: low
surfaces: [web, m, ios]
created: 2026-08-23T00:10:00Z
claimed: 2026-08-25T14:00:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-23
prior_art_found: [W-0326]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

**Raised deliberately so that removing two values is not mistaken for deciding
against them.** W-0326 aligned `MortgageStore`'s `rate_type` list to the column
enum, which removed `capped` and `offset`.

**That removal was not a product decision and must not be read as one.**
`mortgages.rate_type` is `enum('fixed','variable','tracker','discount','mixed')`
and has never contained `capped` or `offset`, so a rule accepting them could only
ever produce a write that fails. They were unreachable whatever anyone wants —
removing them changed nothing a user could do.

**The genuine question is separate: should the application support them?**

Capped-rate and offset mortgages are real UK products:

- **Capped rate** — variable, but with a ceiling it cannot pass.
- **Offset** — savings balances net off the mortgage balance for interest.

Neither can be recorded today. A user with an offset mortgage must currently
choose `variable` or `tracker` and lose the distinction, which matters because an
offset changes the interest actually paid and a cap changes the rate-shock
exposure the alerts key off.

## Acceptance

1. [x] A decision from CSJ: supported, or explicitly not. — **SUPPORTED** (CSJ,
   2026-08-25). Scope then reduced by Brett from the full Option D; see the note.
2. [x] If supported, the work is a migration widening the enum, the option in the
   property form's rate-type select, the value added to all three form requests
   AND `MortgageStore`, and whatever the interest calculation should do
   differently for an offset. **Five layers, per W-0329 — not one.** — **Nine, not
   five.** The interest calculation does nothing differently, and that is the
   finding, not an omission.
3. [n/a] If not supported, record it here so the next sweep does not re-raise it.

## Working notes

- 2026-08-23 build-lead (`fix-cycle4-columns`): raised on team-lead's explicit
  instruction not to conflate "currently unstorable" with "unwanted". The
  distinction is the point of the item.

---

## DECISION BRIEF FOR CSJ — prepared 2026-08-25 (Brett)

> [!WARNING]
> **SUPERSEDED. Three of the arguments below are wrong.** Kept unedited because it is
> the record of what CSJ actually read when he chose D — but do not cite it. The
> corrections are in the 2026-08-25 working note at the foot of this item:
>
> 1. **"Offset as a label would be stored and ignored by the arithmetic"** — no.
>    `monthly_payment` is user-entered and `calculateMonthlyPayment()` never touches
>    it, so the borrower's own figure already carries the offset. There is no
>    arithmetic being skipped.
> 2. **"Fynla already advises the product it cannot record"** — the
>    `offset_mortgage_better` recommendation **can never fire**; its seeded condition
>    matches no dispatcher arm. Raised as **W-0484**.
> 3. **"A new value falls through every `MortgageResource` gate"** — no.
>    `interest_rate` is serialised ungated above the gated block, so capped and offset
>    render correctly with no change.
>
> The eight-site count below is also short by one: `MortgageMapper:34` makes nine.

**No code written.** The acceptance asks for a decision from CSJ and this is that
decision made cheap, not made for him. Everything below is measured, not assumed.

### The question is really two questions, and they have different answers

The item asks one question about two values. They are not the same size of job, and
the reason is that **`rate_type` drives no arithmetic anywhere in the application
today.** Every consumer was checked: `MortgageResource` uses it to decide which rate
fields to serialise, `PropertyDetailInline` prints it, `PropertyService` and
`CoordinatingAgent` pass it through. **Nothing calculates from it.**

So adding either value *as a label* is consistent with what the field already is.
What differs is what each needs in order to be more than a label.

| | Capped | Offset |
|---|---|---|
| To store it | enum + 8 sites | enum + 8 sites |
| To **mean** anything | one ceiling column | **a savings↔mortgage relationship that does not exist** |
| Blocked on a product decision beyond "yes"? | no | **yes** |

`Mortgage` has `property()`, `user()`, `jointOwner()` and `snapshots()`. There is no
link to a savings account, and an offset's defining behaviour is that savings
balances net off the mortgage balance for interest. **Recorded as a bare label,
`offset` would be a mortgage the app calls offset while computing interest on the
full balance** — the same shape as W-0221's write-only column and W-0008's fee that
was displayed and never charged.

### Fynla already advises the product it cannot record

`database/seeders/SavingsActionDefinitionSeeder.php:518` seeds a live, enabled
recommendation:

> **"Consider an Offset Mortgage Arrangement"** — *"Your mortgage rate of
> {mortgage_rate}% exceeds your after-tax savings rate — consider an offset mortgage
> arrangement."* → *"Speak to your mortgage provider about offset options."*

**So the app tells a user to get an offset mortgage and then has nowhere to put it if
they do.** They must choose `variable` or `tracker` and lose the distinction. Neither
this item nor W-0326 noticed this, and it is the strongest single argument on the
"support it" side.

### Correction to this item — one of its arguments rests on a feature that does not exist

The Intent says *"a cap changes the rate-shock exposure the alerts key off"*. **There
is no rate-shock or rate-expiry alert.** The mortgage-related action definitions are
protection-side (`mortgage_no_decreasing_term`, `no_ci_with_mortgage`); nothing keys
off `rate_fix_end_date`. Supporting `capped` would not feed an alert, because there is
no alert to feed. That does not make the case worse — it makes it *simpler*, and the
argument should not be relied on as written.

### It is eight sites, not five — and two of the extra ones decide whether `/m` can use it

The acceptance says five layers. Measured, adding a value touches:

1. `mortgages.rate_type` enum — migration
2. `StorePropertyRequest:98`
3. `StoreMortgageRequest:53`
4. `UpdateMortgageRequest:53`
5. `MortgageStore:331`
6. **`CoordinatingAgent:3570` and `:3710`** — Fyn's own two copies of the list
7. **`AIExtractionService:604`** — the document-extraction prompt's allowed values
8. `PropertyForm.vue:510` — the select

**Items 6 and 7 are the ones that would be missed.** Per `app/Http/CLAUDE.md`, Fyn is
`/m`'s only write path — `resources/mobile/api.js` has no post/put/patch helper at
all. Add the value to the four request/store layers and not to `CoordinatingAgent`,
and **web can record a capped mortgage while `/m` and native cannot**, which is a
Rule 19 break shipped by omission. This is exactly the shape W-0329 records.

### One consequence nobody has flagged, whichever way this goes

`MortgageResource:57-68` gates rate fields on the type: `rate_fix_end_date` only when
`fixed`, `fixed_interest_rate` when `fixed|mixed`, `variable_interest_rate` when
`variable|mixed`. **A new `capped` or `offset` value falls through every one of those
gates**, so the detail view would print a rate type and no rate figures at all. Any
"yes" must include that Resource, or it ships the W-0351 defect — a value that is
stored, correct, and cannot reach the user.

### What a decision looks like

- **A — Not supported.** Record it here, close the item. Cost: nothing. Leaves the app
  recommending offset mortgages it cannot record, which is a real if small
  inconsistency someone will eventually re-raise.
- **B — Capped only.** Contained and coherent: 8 sites + the Resource gate + a ceiling
  column so the value means something. No new relationships. Does not resolve the
  recommendation gap above.
- **C — Both, as labels.** Cheapest "yes", and **not recommended**: `offset` would be
  stored and ignored by the arithmetic, which is the defect class this board keeps
  finding.
- **D — Both, properly.** B, plus a savings↔mortgage link and offset-aware interest.
  Resolves the recommendation gap. This is a feature, not a fix, and wants a spec.

**Recommendation from the investigation, offered not decided: B or D, not C.** The
choice between them is a product call about whether Fynla wants to model offset
arithmetic at all — precisely the question this item was raised to put to CSJ.
Brett explicitly declined to make the call on CSJ's behalf and asked for it to be
prepared instead, which is why this is a brief rather than a fix.

**Not done:** no code, no migration, no tests. Nothing in the application changed.

---

## Working notes

- 2026-08-25 (Brett): **CSJ decided D — both, properly.** Then, on reading the brief
  back, Brett judged D over-complicated and reduced the scope. **Both new rate types
  ship; the offset arithmetic does not.** Recorded here in full because that reverses
  part of a founder decision and CSJ should see the reasoning when he reviews.

  **Why the reduction is right, and why my brief was wrong.**

  The brief argued against option C — both as bare labels — on the grounds that
  `offset` would be "stored and ignored by the arithmetic, the same shape as W-0221 and
  W-0008". **That argument does not hold**, for a reason I had not checked:

  **Every input an offset would affect is already user-entered, and already correct.**
  `monthly_payment` is supplied by the user (`StoreMortgageRequest:63`), stored as given
  (`MortgageService:62`) and read back as stored (`:202`).
  `MortgageService::calculateMonthlyPayment()` **never touches it** — it backs a
  standalone what-if endpoint (`POST /api/mortgages/calculate-payment`) that takes its
  inputs from the request body. I had assumed it was the storage path. It is not.

  So an offset borrower enters the payment their lender actually charges, which already
  has the offset in it — as do the balance and a linked savings account's rate.
  **Deriving the offset ourselves would put a second mechanism against a figure the
  user has already stated**, which is precisely the disease CSJ's W-0228 ruling was made
  to end. The over-built design would have introduced the defect it was justified by.

  And the W-0221/W-0008 comparison was wrong in kind. W-0221 was a column **nothing
  read**. W-0008 was a fee **displayed as charged** while the projection ignored it —
  the app claimed something it did not do. `rate_type` is a **descriptive label**, and
  all five existing values are equally descriptive: none drives arithmetic. Two more
  descriptive values claim nothing.

  **A second argument in the brief was also void, and worse.** The brief called "Fynla
  already advises the product it cannot record" the strongest reason to support offset,
  citing the seeded `offset_mortgage_better` recommendation. **That recommendation can
  never fire.** Its seeded condition string matches no dispatcher arm, so it falls to
  `default => []` in silence — and so does its sibling. Raised as **W-0484**. The brief
  was written from a seeder definition rather than from the execution path.

  **Nine sites, not five.** The item's acceptance names five. Measured:

  | # | Site | In the item's list? |
  |---|---|---|
  | 1 | `mortgages.rate_type` enum — migration | yes |
  | 2 | `StorePropertyRequest:98` | yes |
  | 3 | `StoreMortgageRequest:53` | yes |
  | 4 | `UpdateMortgageRequest:53` | yes |
  | 5 | `MortgageStore:331` | yes |
  | 6 | `CoordinatingAgent:3570` **and** `:3710` — Fyn's own two copies | **no** |
  | 7 | `AIExtractionService:604` — the extraction prompt's allowed values | **no** |
  | 8 | `MortgageMapper:34` — the document field mapper | **no, and I missed it too** |
  | 9 | `PropertyForm.vue:510` — the select | yes |

  **Site 8 was found by the verification grep, not by planning**, and it is the one
  with teeth: `parseEnum()` coerces anything it does not recognise to its default, so a
  capped mortgage read off a statement was silently stored as `variable`. Silent
  rewriting of a user's stated product — the W-0162 coercion shape.

  Sites 6 and 7 decide whether `/m` and native can record the value at all:
  `resources/mobile/api.js` has no post, put or patch helper, so **Fyn is `/m`'s only
  write path**. Web-only support would have been a Rule 19 break shipped by omission.

  **`MortgageResource` needed no change, and my brief was wrong about that too.** I
  claimed a new value "falls through every gate, so the detail view would print a rate
  type and no rate figures at all — the W-0351 shape". It does not: `interest_rate` is
  serialised **ungated** at `:32`, above the gated block, and `PropertyDetailInline:311`
  renders it for any non-`mixed` type. The `fixed_interest_rate` /
  `variable_interest_rate` gates are for the **mixed** split only. Capped and offset
  render their rate and their type correctly with no change. Verified against both
  files rather than asserted a second time.

  **Tests.** `tests/Feature/Property/CappedAndOffsetRateTypesTest.php` — 16 cases. Both
  new values store; the five existing ones still work; a genuine non-value is still
  refused; both are accepted through the real nested create endpoint; **both of Fyn's
  copies carry them**; the document mapper no longer rewrites them; and two cases pin
  the **non-goals** — no `offset_mortgage_id`, no `offset_benefit`, and the user's
  stated `monthly_payment` is left exactly as entered — so a later reader does not
  "finish" the job by adding the arithmetic this note explains away.

  **Mutation-verified:** reverting Fyn's two copies and the document mapper turns
  exactly those four cases red, while the web-endpoint case correctly stays green.

  **One diagnosis worth recording for the next person.** The endpoint test first
  returned a 404 reading "Endpoint not found", which looks exactly like a missing
  route. The route was registered and there was no route cache. The real cause, found
  with `withoutExceptionHandling()`, was
  `ModelNotFoundException: No query results for model [App\Models\TierConfiguration]` —
  the mortgage write path runs through `DbTierGate` and the test had not seeded the
  tier rows. **A missing seed renders as a missing route.** Seed
  `TierConfigurationSeeder` in any test that writes a mortgage.

  **Verification.** Mortgage + Property 391 tests / 2,533 assertions; Architecture
  177 / 4,296; `Unit/Database` schema-conformance 30 / 63. Pint and ESLint clean.
  Migration rollback narrows the enum and will refuse if a row holds a new value —
  correct, because a rollback must not silently rewrite a user's stated product.

  **Not done, deliberately:** no `capped_rate_ceiling` column. The scope is "record the
  type"; a ceiling is additional data nothing yet reads, and adding it would be the
  write-only-column shape W-0221 was raised to remove. Trivially addable if wanted.
