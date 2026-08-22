# W-0112 — build-lead (`fix-batch-I`) → quality-lead

**Branch document:** `workforce/branches/fixes/F-0009-batch-i-onboarding-spouse.md` §12–21

## Done

`users` has no `name` column — it is an appended accessor over
`first_name`/`middle_name`/`surname`, and `User::isFillable('name')` is **false**, so
`fill()` dropped every rename without an exception, a failed save or a warning, while
the rest of the same update succeeded. Probed directly rather than reasoned about.

One declared correspondence now, on `SpouseLinkingService` —
`FAMILY_MEMBER_TO_USER_COLUMNS` + `userAttributesFrom()`. `last_name → surname` is the
whole reason it needs a home: the one field whose name differs is the one a hand-written
sync gets wrong, and getting it wrong fails silently. Three callers read it — the
controller sync, spouse-user creation, and (in reverse) the reciprocal row.

**17 tests pass**, including one asserting the derived `name` the profile payload, `/m`
and native iOS all render.

## Not done, and why

- **No browser verification** — persona-tester.
- `update()` derives `$data['name']` from the parts only when a part is supplied, so a
  caller sending **only** `name` updates the legacy column while the parts go stale. No
  current client does it; left for a legacy-`name` sweep rather than patched here.
- Nothing committed, no PR, no deploy.

## What you need that isn't obvious from the artefacts

**The drift guard found two bugs nobody had reported, on its first run.** I added a test
pinning the declared map against what creation actually writes, expecting it to pass:

1. A spouse created with a **middle name** got it on their card and not on their own
   account — the creation list was hand-written and had never included it.
2. `createReciprocalFamilyMember()` built the spouse's view of their partner by
   splitting the **derived** display name on spaces — losing the middle name and
   mis-splitting double-barrelled or multi-word surnames.

Both fixed in this item. Worth knowing when you review: two of the changes in the diff
are not the reported defect, they are what the guard uncovered.

**It is not the same edge as W-0113.** The team lead asked. W-0113 is the write side of
creating a spouse in the tool layer; this is the sync side of editing one in the HTTP
layer. Neither collapses the other.

## Assumptions I made

- **That `array_key_exists` rather than `isset` is the right gate.** A key present with
  an explicit null now clears its column, on the reading that the user removed a value.
  The old code used `isset`, so nulls were ignored. If that reading is wrong, the
  symptom is a cleared field where the caller meant "leave alone".
- **That `createAndLinkNewSpouse` should compose from the map.** I did it after the
  guard proved the inline list drifts. It changes creation to set `middle_name`, which
  it never did before — additive, but it is a behaviour change on a working path.
- **That the map belongs on `SpouseLinkingService` rather than on either model.** It is
  the service that owns the user ↔ family-member relationship. A reasonable person could
  put it on `FamilyMember` instead.

## Surfaces covered / not covered

- **Web** — covered; the settings and onboarding edit paths share one modal and one
  endpoint.
- **`/m` and iOS** — no counterpart to change, and they are the reason this mattered:
  both render the spouse from `users`, so the discarded rename was invisible on the card
  and permanent on every other surface. Both now see the corrected name with no client
  change.
