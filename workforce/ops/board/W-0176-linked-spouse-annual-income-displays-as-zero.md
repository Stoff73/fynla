---
id: W-0176
title: A linked spouse's annual income displays as £0 on /settings/family — the row serves a stale column instead of the account behind it
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0017-cycle1-tax-income-and-allowances.md
owner: build-lead
status: handoff
surfaces: [web]
created: 2026-08-21T23:50:00Z
claimed: 2026-08-22T20:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22T20:05:00Z
prior_art_found: ["app/Services/UserProfile/UserProfileService::getFamilyMembersWithSharing() — the one method both /api/user/profile and /api/user/family-members read", "app/Models/FamilyMember::isLinkedAccount() — the one rule for whether an account sits behind a row (W-0051)", "the virtual-spouse fallback in the same method, which already read the linked account's income"]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, local `localhost:8000`, viewed as **David Jones
(`users.id` 16)**.

**Surface:** desktop web, `/settings/family` → the spouse card.

### Expected

Sarah Jones is a linked account earning £120,000. Every other surface reads her salary
correctly. Her card should show that figure, or show nothing.

### Actual

> Sarah Jones · Spouse · Account Linked · **Annual Income £0**

Confirmed in the database, read-only:

```
family_members id 24  name "Sarah Jones"  relationship spouse
                      linked_user_id 17   annual_income '0.00'
users id 17           annual_employment_income 120000.00
```

The data is right and the link is right. The surface reads neither.

**Two payload builders in one method disagreed.** `UserProfileService::getFamilyMembers
WithSharing()` serialises the stored row and overrode only `email` from the linked
account; the *virtual* spouse fallback forty lines below — used when no `family_members`
row exists at all — already read `annual_employment_income` off the account. Whichever
path a household happened to take decided which figure the user saw.

**And the £0 was rendered rather than hidden because of a type.** The card guards with
`v-if="member.annual_income"`. The column casts to `decimal:2`, so the value arriving at
the client is the **string `'0.00'`, which is truthy in JavaScript**. A genuine
`0`/`null` would have been falsy and the row would simply not have appeared.

### Impact

The household surface most likely to be checked by the *other* spouse states a linked
partner earns nothing. It is not a calculation input — the income modules read the user
record directly — so nothing downstream is wrong, which is what makes it corrosive: the
number is visibly wrong beside a badge asserting the accounts are linked, and a user who
believes it will go looking for a data-entry problem that does not exist.

### Repro

1. Log in as `david.jones@example.com`, go to `/settings/family`.
2. Read the Sarah Jones card: "Account Linked" beside "Annual Income £0".
3. `php artisan tinker` — `FamilyMember::find(24)->annual_income` is `'0.00'`;
   `User::find(17)->annual_employment_income` is `120000.00`.

### Acceptance

1. A row with a live linked account behind it takes its income from that account.
2. One definition serving both the stored-row path and the virtual-spouse fallback
   (Rule 20) — the two must not be able to disagree again.
3. An account genuinely earning nothing yields a falsy zero, so the row hides rather than
   printing £0.
4. Rows with no account behind them keep their own recorded income.
5. Verified in a browser as David, and as Sarah viewing David.

## Working notes

**2026-08-22 — build-lead (`cycle1-tax`). Fixed.**

`UserProfileService::linkedAccountAnnualIncome()` is the one definition; both paths call
it. `app/Services/UserProfile/UserProfileService.php:756-766` (the helper),
`:781-796` (stored-row path), `:836` (virtual-spouse path).

Verified against the live persona: Sarah now returns `120000.0`, the two children still
return `NULL` and stay hidden.

Tests: `tests/Unit/Services/UserProfile/LinkedSpouseAnnualIncomeTest.php` — 5 passing,
covering both paths, the income following a change on the account, the falsy-zero case,
and a non-linked child row keeping its own figure.

**Surfaces.** Web only, stated rather than assumed: `resources/mobile/` and
`ios-native/Fynla/` have no family-members screen carrying a member income — grepped for
`annual_income` across both. The fix is in a shared service, so if either surface grows
one it inherits the correct behaviour.

**This file has form, in both directions — worth knowing before your next edit in it.**
`UserProfileService` was being worked simultaneously for W-0140, where the defect is that
a plan **discards a composition the profile already computes honestly**
(`expenditurePresentation()`). This one is the opposite: a single method **builds the same
payload two ways and they disagree**. Same class, same week, under-serving in one place
and over-serving in another. Anyone treating this service as a settled source should check
which method they entered by.

Not done: browser verification, by instruction — the tester closes that loop.
