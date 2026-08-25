# W-0111 — build-lead (`fix-batch-I`) → quality-lead

**Branch document:** `workforce/branches/fixes/F-0009-batch-i-onboarding-spouse.md` §12–21
**Read W-0114 first** — investigating this found a worse defect underneath it.

## Done

**A partner does not get an account.** The Email field and its promise ("Used to create
or link their account") are removed for every relationship except spouse, and the
request now answers `prohibited_unless:relationship,spouse` with a message that explains
why. Nothing accepts a field it intends to discard, which was the acceptance criterion.

**10 tests pass**, including that the refusal creates neither a `family_members` row nor
a `users` row — the point being that nothing is half-done.

## Not done, and why

- **No browser verification** — persona-tester.
- The Fyn tool path ignores an `email` sent with a non-spouse relationship rather than
  refusing it. The v2 schema now says "Null for every other relationship", and a stray
  field from a model is not a user being shown a promise. Noted rather than hidden.
- Nothing committed, no PR, no deploy.

## What you need that isn't obvious from the artefacts

**This item as written was the smaller half.** Adding a partner did not merely lose the
email — it returned **HTTP 500**, because `family_members.relationship` is an enum of
four values and the form offers six. Raised as **W-0114** (high) and fixed there. Review
them together; W-0111's fix is meaningless on its own.

**The decision was mine, taken under an explicit constraint.** The team lead's
instruction was that the promise and the behaviour must agree, either way. I chose to
remove the promise. The reasoning is on the item; the short version is that the modal
already warns a partner is not legally recognised for UK tax purposes, the schema has no
`partner` value at all, and removing a promise is reversible while committing to a
spouse-permission and data-sharing surface for partners is not.

## Assumptions I made

- **That CSJ does not want partners linkable.** This is the assumption the whole item
  rests on. I recommended it and the lead's constraint pointed the same way, but nobody
  has said it in those words. If it is wrong, the mechanism is already there —
  `SpouseLinkingService` — and the work is the schema and the permission model.
- **That `prohibited_unless` will not break an unseen client.** The web modal is the
  only caller I found; `/m` has no family form. A client sending `email: null` still
  passes, since Laravel treats null as absent for prohibited rules.

## Surfaces covered / not covered

- **Web** — covered; the modal serves both settings and onboarding step 2.
- **`/m` and iOS** — no family-member form on either, so no counterpart. The server-side
  refusal covers them if one is ever built.
