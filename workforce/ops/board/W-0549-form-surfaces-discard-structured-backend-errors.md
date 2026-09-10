---
id: W-0549
title: The form surfaces discard every structured error the backend returns — the concepts are implemented once, for the chat path only
mission: new-user-run-2026-09-07
branch: null
owner: null
reviewers: [quality-lead, build-lead]
status: queued
severity: high
surfaces: [web, m]
created: 2026-09-09
source: pattern behind W-0543 and W-0544, identified during the new-user run
prior_art_checked: 2026-09-09
prior_art_found: [W-0543, W-0544, W-0541]
prior_art_outcome: extends — this is the mechanism behind all three; they are symptoms
constitution_refs: [07-quality-bar]
---

## Intent

Three independent defects in one run turned out to be one illness: **the backend
returns rich, correct, structured information and the frontend throws it away.**

| Concept | Backend emits | Chat path | web + `/m` |
|---|---|---|---|
| `invitation_pending` (W-0543) | yes | handled (`CoordinatingAgent.php:1816`) | **zero matches** |
| `tier_limit_reached` (W-0544) | yes (`TierLimitResponse.php`) | handled | **zero matches** |
| conversation bootstrap (W-0541) | n/a | correct on `/m` | never called on web |

`grep -rn "invitation_pending\|tier_limit_reached" resources/js/ resources/mobile/`
returns **nothing**. Both concepts were implemented for the conversational
surface and never for the form surfaces, so each generic catch-all error handler
wins and the user is told something false.

This is the Rule 20 failure mode named in CLAUDE.md — parallel mechanisms where
each fix lands in only one of them. Fixing the three symptoms individually
leaves the mechanism in place, and **any other `error_type` the backend returns
is likely also unhandled**.

## Acceptance

- [ ] Enumerate every structured `error_type` / state flag the API returns.
- [ ] One shared error interpreter, used by web **and** `/m` from one home, that
      reads `error_type`, renders `message`, and offers `destination` as a call
      to action where one is given.
- [ ] Every generic "something went wrong" handler audited against that list.
- [ ] W-0543 and W-0544 fixed through it rather than individually.
- [ ] A test pins that an unrecognised `error_type` still shows the server's
      `message` rather than a generic string.


## Working notes

**2026-09-09 — narrowed after testing on production.**

The original claim ("the form surfaces discard every structured error") is too
strong and W-0544 now carries the correction. The Net Worth surfaces **do**
handle tier limits, through a shared `LimitReachedModal` consumed by six
components. Tier limits are detected there from the subscription capability
payload rather than by reading `error_type` off a 403, which is why the
`tier_limit_reached` grep returned nothing and misled the first reading.

What survives, and is still worth the audit:

- **Onboarding** discards errors wholesale — 17 bare `catch {}` blocks with no
  error binding (W-0544).
- **`invitation_pending` is genuinely unhandled everywhere on web and `/m`**
  (W-0543) — that one is unchanged, and the list endpoint does not even carry
  the field.

So the mechanism is real but smaller than first stated: it is a missing shared
error interpreter for *server-sent* messages, not a blanket failure to handle
tier state. Scope the item to that.
