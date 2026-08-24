# W-0176 — build-lead (`cycle1-tax`) → quality-lead

## Done

`UserProfileService::linkedAccountAnnualIncome()`
(`app/Services/UserProfile/UserProfileService.php:756-766`) is the one definition of the
income shown for a family-member row with a live account behind it. Both paths in
`getFamilyMembersWithSharing()` call it: the stored-row path (`:781-796`) and the
virtual-spouse fallback (`:836`), which previously disagreed.

Verified against the live persona: Sarah returns `120000.0` on David's `/settings/family`;
the two children still return `NULL` and their rows stay hidden.

Tests: `tests/Unit/Services/UserProfile/LinkedSpouseAnnualIncomeTest.php` — 5 passing.

## Not done, and why

- **No browser verification.** By instruction.
- **The stale `family_members.annual_income` column is left as it is.** Nothing writes it
  once accounts are linked, and I did not add a backfill or a write-through — the read is
  now correct and a data migration is a separate decision. **Nothing was written to users
  16 or 17 or to their family rows.**
- **No commit, no PR, no deploy.** By instruction.

## What you need that isn't obvious from the artefacts

**The £0 was a type problem as much as a data problem, and that is worth carrying
forward.** `family_members.annual_income` casts to `decimal:2`, so a zero crosses the API
as the **string `'0.00'`**, which is truthy in JavaScript. The card guards with
`v-if="member.annual_income"`, so it rendered "£0" where a real `0` or `null` would have
been falsy and the row would simply not have appeared.

The helper returns a **float**, so a linked account genuinely earning nothing now yields
`0.0` and the row hides. There is a test for exactly that, because it is the case most
likely to regress if someone later "simplifies" the helper back to passing the column
through.

**Any other `decimal:` cast reaching a `v-if` truthiness check has the same latent bug.**
I did not sweep for them — out of scope — but it is a cheap grep if you want the class
closed rather than the instance.

**Which income figure this shows.** `annual_employment_income`, matching what the virtual
spouse path already used. It is therefore employment income, not total income — a spouse
with rental or dividend income shows less here than their full income. That was the
pre-existing definition and I kept it rather than changing what the card means; if the
card should show total income, that is a product decision and a different change.

## Assumptions I made

- **That showing a linked spouse's income to their partner needs no additional permission
  check.** The item states spouse permissions are accepted both ways, the row already
  publishes the linked account's email through the same path, and `liveLinkedUser()` is
  the established gate (W-0051). I did not add a permission check and did not find one to
  compose with. **If household data sharing is meant to be permission-gated per field,
  this is the place to say so** — it would apply to the email too.
- **That `liveLinkedUser()` remains the single predicate for "an account is behind this
  row".** I composed with it rather than around it.

## Surfaces covered / not covered

- **web** — covered, the only surface with this screen.
- **`/m`** — **no counterpart.** Grepped `resources/mobile/` for `annual_income`: the only
  hits are retirement products and a test fixture. No family-members screen.
- **iOS** — **no counterpart.** Same grep across `ios-native/Fynla/`: retirement and
  protection models only.

Stated rather than assumed, per Rule 19. The fix is in a shared service, so either surface
inherits correct behaviour if it grows the screen.
