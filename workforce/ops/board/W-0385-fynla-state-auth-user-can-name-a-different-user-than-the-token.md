---
id: W-0385
title: fynla-state.auth.user can name a different user than the token authenticates as, and it is our documented way of checking identity
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0027-cycle4-life-cover-reach.md
owner: null
status: done
severity: medium
surfaces: [web, m]
created: 2026-08-23T01:05:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: []
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

**A verification-method defect, and it nearly produced a false pass.**

The `/m` browser playbook says to verify which account you are on by reading
`fynla-state.auth.user` rather than by recognising a figure on screen. Measured at the
start of the W-0341 verification pass:

```
localStorage fynla-state.auth.user  ->  sarah.jones@example.com  (17)
GET /api/auth/user with m_scaffold_token -> david.jones@example.com  (16)
```

**The persisted store named one user; the token authenticated as another.** The page
rendered David's data throughout — greeting "Good morning, David", £700,000 of cover —
because the server answers to the token, not to localStorage.

**Had the playbook been followed as written, David's £700,000 screen would have been
recorded as Sarah's** — and since the whole point of that pass was that Sarah's figure
should have changed, the check would have "confirmed" a number belonging to the wrong
person.

Two things come out of this and they are separable:

1. **The method is wrong.** Identity in a browser pass must be established from the
   server: `GET /api/auth/user` with the token actually in use. The persisted store is a
   client-side cache and cannot be authoritative about who the API will answer as.
2. **The stale state is probably a real app bug.** `fynla-state.auth.user` survived an
   account switch and continued to name a user the token no longer belonged to. Anything
   rendering from the store rather than from an API response — greetings, "your" labels,
   permission-shaped UI — would show the previous user's identity until something
   overwrote it. Observed twice in one session: the `/m` login populated `fynla-state`
   for one account and left only `m_scaffold_token` for the next.

## Acceptance

1. The `/m` verification playbook is corrected to establish identity from
   `GET /api/auth/user`, and says why the store cannot be trusted for it.
2. Determine whether the stale `fynla-state.auth.user` is a login/logout path that fails
   to clear or replace the persisted user, and whether any surface renders identity from
   it. If so, that is its own defect with its own severity.

- 2026-08-31 build-lead: **RESOLVED AND TESTED — closed. Both acceptance criteria answered, and the second one has a better answer than the item expected.**

  **Acceptance 1 — the playbook is corrected.** `.claude/skills/verify-m/SKILL.md` now leads with it: establish which account you are on from `GET /api/auth/user`, **never from `localStorage`**, with the measured evidence in place — store said `sarah.jones@example.com` (17), token authenticated as `david.jones@example.com` (16), and the page rendered David's data throughout. It says WHY, which is the part that makes it stick: **the server answers to the token, not to the persisted Vuex snapshot.**

  **Acceptance 2 — investigated, and it is NOT a product defect.** `store/modules/auth.js:85-95` shows `login` commits `clearAuth` and resets **every** module — `userProfile`, `lifeStage`, `onboarding`, `netWorth`, `aiChat` — and removes the stored token, all **before** authenticating. `logout` does the same at `:115-129`. So neither path leaves a stale user behind, and no surface renders identity from a store the login flow has not just rewritten.

  **The staleness came from injecting a token directly**, without going through `login` — which is precisely what the scaffold-token bridge does, and therefore precisely the situation a `/m` verification is in. That is recorded in the skill too, so the next reader understands it is a harness artefact rather than assuming the login path is unsafe and "fixing" something that works.

  **So no code changed, and that is the finding**: the item's title was right to call this *a verification-method defect*. Had it been treated as an auth bug, the fix would have been applied to a login path that was already correct.
