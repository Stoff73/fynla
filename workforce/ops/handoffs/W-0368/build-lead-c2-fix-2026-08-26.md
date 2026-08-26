# W-0368 C2 — fixed, back for re-gate

**From:** build-lead · **To:** tax-compliance-reviewer · **Date:** 2026-08-26
**Prior:** `tax-compliance-reviewer-2026-08-25.md`, `tax-compliance-reviewer-recheck-2026-08-25.md`
**PR:** #719 → `dev` · **Head:** `c8e71bdd5`

---

## What you are being asked to re-gate

**C2 only.** C1 and C3 you discharged by measurement on 2026-08-25 and nothing in
these commits touches them. C6 is corrected and is offered for your check. C4, C5
and C7 are untouched and open.

**I am not claiming C2 is discharged.** That is your call, and this item has come
back blocking twice. What follows is what changed and what I measured, so you can
re-check rather than re-derive.

## Commits since the re-gate

| Commit | What |
|---|---|
| `7476ac5b8` | C2 — all three routes |
| `5a34bf535` | C6 — citations, plus the session tech-debt pass |
| `243922925` | `User::spouseIdRegardlessOfAccountState()` — names the relationship question and guards it |
| `4fdf274ff` | W-0500 raised (`/m` and native cannot answer the question) |
| `c8e71bdd5` | W-0501 raised (your fifth valuation site, re-scoped after measurement) |

## The three routes

### (a) The answer never reached the form, and every save wiped it

You were right, and about the shape of the error as well as the fact of it: I wrote a
reader for a field I never populated. `populateForm()` now copies it, at
`PropertyForm.vue:1538`, with `??` and not `||` for the reason you gave — `||` maps a
stored `false` to `null` and silently disables the feature.

### (b) The answer outlived the co-owner — **and I am correcting you here**

Implemented as you prescribed: `PropertyStore::update()` forgets the answer when
`joint_owner_id` or `joint_owner_name` changes and the write does not itself supply a
new one.

**But your reachability finding does not hold as written, and I would rather say so
than accept a discharge on a false premise.** You wrote that "every Fyn write leaves
the old answer standing — and Fyn is the only write path on `/m` and on native".
Measured:

- There is **no `update_property` tool anywhere in the application**. `grep -rn
  "update_property" app resources` returns nothing. Fyn creates properties only —
  `XaiToolDefinitions:91` lists `create_property` and `create_mortgage`.
- `PropertyStore::update()` has **exactly one caller**: `PropertyController::update()`,
  behind the single `PUT /api/properties/{id}` (`routes/api.php:419`).
- `PropertyStore::updateOrCreate()` has **no callers at all**.
- `/m` issues **no PUT requests** at all, and native has no property update.
- The web form **always** sends the field — it is a key in `form`, and `handleSubmit()`
  spreads the whole object.

So no current user route reaches a stale answer. **(b) is a guard at the boundary, not
the closure of a live hole.** The docblock at `PropertyStore.php:170` and the test
comments both say exactly that. It is still worth having there and worth having in that
one place: the next writer is the `update_property` tool `/m` and native need, and
`PropertyNormaliser::fromFyn()` whitelists `joint_owner_name` without the answer — so
that writer arrives holding this defect ready-made.

If you disagree with this reading, that is the thing to push on. I would rather be
corrected a third time than have a fix pass a gate for a reason that is not true.

### (c) A deleted spouse account switched the discount on

`applies()` compared against `liveSpouseId()`, which answers *"may I show this person's
data"* and goes null on account deletion while `spouse_id` is retained. It now asks the
relationship question. Deleting an account is not a divorce; the link ends on a genuine
unlink, which nulls the column on both sides (`FamilyMembersController:437-440`).

`243922925` then gives that question a name and a guard, because there is a trap:
`hasReciprocalSpouseLink()` looks like the obvious consolidation target and would
**silently reinstate this defect** — its existence check runs under `User`'s
`SoftDeletes` global scope (`User.php:29`), so it returns false once the account goes.
A test in `DeletedSpouseVisibilityTest` now reddens if anyone does it.

## Verification

**Browser, end to end**, on a shape the form can actually produce — a spouse with no
linked account, property created through the form rather than by hand:

| Step | Result |
|---|---|
| Save with "Ruth Alderton (Spouse)" | `is_spouse=true`, no discount, £180,000 |
| Reopen edit | accessibility tree reads `combobox "Ruth Alderton (Spouse)" (selected)` — **not "Other"**; free-text box hidden |
| Save without touching anything | `is_spouse=true` survives — previously wiped to `NULL` |
| Switch to "Other" → "Marcus Webb" → save | `is_spouse=false`, discount applies, **£162,000 against an undiscounted £180,000** |

My first attempt at this used a hand-built row — unlinked name plus a *linked* spouse —
which the form cannot produce. Discarded and rebuilt through the form. Flagging it
because the artificial fixture initially looked like a bug in the fix and was not.

**Route (c) is measured by test, not browser** — soft-deleting a seeded account on the
local database was not worth the blast radius. The Pest test measures what your probe
measured.

**Suites:** 831 passed / 2,630 assertions across Estate, Stores, UserProfile, Property
and NetWorth (your baseline was 702 for the narrower scope, plus six new tests here).
Architecture 177. Frontend 1,249 across 123 files. Spouse-link and household-goals 24.
Pint and ESLint clean.

## C6 — offered for check

Two sites carried the old wording and one overreach was mine:

1. `EstateAssetAggregatorService.php:86` — the load-bearing one. Now leads with s160 as
   the authority, demotes IHTM15071 / SVM113040 to guidance on it, and says s161
   **substitutes**.
2. `UndividedShareDiscountTest.php:145` — describe block renamed to match its own
   docblock.
3. `UndividedShareDiscount.php:24-37` — my overreach. "Turns entirely on whether the
   co-owner is a spouse" is gone. It now states that the class asks something narrower
   than s161: s161(2)(b) also relates charity-held property, and s161(1) substitutes
   only where the related-property basis is higher — a floor, not an unconditional
   rule. The class tests neither, both simplifications withhold rather than grant a
   discount, and it says so.

`grep -rn "s160\|s161"` across `app`, `resources`, `tests`, `database` and `ios-native`
now returns no "denies" anywhere. I verified that rather than asserting it, since
"throughout" not being throughout is what you caught last time.

## Carried forward — unchanged, and now on the board

- **C4** — `undiscounted_share` / `undivided_share_discount` still read by nothing, and
  `PersonalizedGiftingStrategyService:328` still quotes a sale price that is the
  discounted share. Open. Cross-referenced from W-0501.
- **C5 / your fifth site** — raised as **W-0501**, and **re-scoped after measurement**.
  You called it non-blocking because it overstates. It is wrong in **both** directions:
  `estimateEstateValue()` drops the non-primary side of every joint asset entirely, so
  a joint owner's share is reported as **£0** and the recommendation never fires. A
  suppressed Inheritance Tax warning, not a conservative estimate. Filed **high**. It
  is also not property-specific — savings, investments, cash, assets, DC pensions and
  life policies share the shape. Please sanity-check that re-scope.
- **C7 nits** — `config(['dummy' => null]);` at `UndividedShareDiscountTest:74`,
  `rate()` still unbounded, and the `db:seed --class=TaxConfigurationSeeder --force`
  deploy note still needed. Open.

## The decision you flagged, now taken

You asked whether shipping a correct-but-dormant rule was right. CSJ ruled on
2026-08-26: **option B — the answer must come from a structured question the user
answers, never from an LLM's reading of conversation.** Raised as **W-0500**, with the
implementation route found and grounded: `quick_replies` are suppressed on the
post-onboarding capture path by design (INV-2.4.1 / INV-2.4.2), so it follows the
`CaptureAccuracyGate` + deterministic-extractor pattern that `ownership_type` already
uses. Not in this branch.

## Note on context

The fynlaBrain vault is not present on this machine (`/Users/CSJ/Desktop/fynlaBrain`),
so this handoff carries the repo-sourced context in its place: your two prior handoff
docs, the W-0368 board item, and CLAUDE.md Rules 2, 19 and 20.
