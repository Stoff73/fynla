---
id: W-0343
title: IHTController carries a third, dead copy of the household life-cover question
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0027-cycle4-life-cover-reach.md
owner: build-lead
status: queued
severity: low
surfaces: [web, m, ios]
created: 2026-08-22T23:35:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0186, W-0341]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

`app/Http/Controllers/Api/Estate/IHTController.php:211`:

```php
private function getExistingLifeCover(User $user, ?User $spouse): array
```

It sums each side's `in_trust` policies and returns `user` / `spouse` / `total` — the
same question `LifeCoverReach::householdCoverInTrust()` now owns (W-0186).

**Nothing calls it.** The name appears exactly once in the file, at the declaration.
No user impact today: the figure it computes never reaches a response.

It matters because it is a **live Rule 20 trap**. The next person needing a household
cover figure in that controller finds a private method that looks purpose-built, uses
it, and the application is back to two mechanisms — one of which does not pass through
the live/reciprocal spouse gate added in W-0278 and would disclose a deleted partner's
in-trust cover.

## Acceptance

1. Deleted, or routed to `LifeCoverReach::householdCoverInTrust()` if a caller is wanted.
2. Not left dead. A private method nothing calls is either an omission or a leftover;
   determine which before deleting, since the omission case means a figure the estate
   response was supposed to carry never shipped.

## Working notes

(append-only)

- 2026-08-22 — Found by `fix-cycle4-lifecover` while enumerating consumers for W-0341.
  Reported rather than fixed: `IHTController.php` is outside that agent's exclusive
  scope and the estate module had two other agents live at the time.
