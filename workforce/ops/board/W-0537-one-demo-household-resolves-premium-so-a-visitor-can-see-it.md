---
id: W-0537
title: Every demo persona resolves free, so a visitor can never see what premium looks like — David & Sarah Mitchell now resolve premium
mission: board-verification-31-august
owner: build-lead
reviewers: [product-lead, compliance-lead]
status: done
severity: medium
surfaces: [web, m]
created: 2026-09-04
source: found trying to browser-verify W-0045 on csjones, 2026-09-04; decided by CSJ the same day
prior_art_checked: 2026-09-04
prior_art_found: [W-0018]
prior_art_outcome: extends — W-0018 established that users.tier grants nothing; this reads it only where no grant is possible
constitution_refs: [06-commercials]
---

## Intent

`PremiumEntitlementResolver::resolve():32` returned `free()` for **every**
`is_preview_user`, unconditionally. No subscription row and no tier column could
change it, because the branch short-circuits before the provider resolution runs.

So every demo persona was free, and the six landing-page demos — the first thing
a prospective customer sees — could only ever show the free product. `/trusts`,
the Will Builder, Bequests and Lasting Power of Attorney all answered
"Full Estate Planning is part of Premium" (`EnsureFullEstateAccess:38`).

Found while trying to browser-verify W-0045's trust palette: `peak_earners` is
the only persona holding a trust, and it could not open the page.

**CSJ, 2026-09-04:** "Lets set David & Sarah Mitchell to premium always, so a
user can see what premium looks like, the rest we leave."

## The constraint this had to respect

W-0018 settled that `users.tier` grants nothing — it is a query cache, and
entitlement comes from a Subscription or a PremiumEntitlement. The resolver's
docblock says so in terms and warns against reversing it.

The column is read **only inside the `is_preview_user` branch**, where the
account is a seeded fixture that can never reach a provider, so no real user's
entitlement can be affected. A test pins that a real user carrying
`tier = 'premium'` with no subscription still resolves free.

## Outcome — done, 2026-09-04

- `app/Services/Billing/PremiumEntitlementResolver.php` — the preview branch
  resolves `previewPremium()` when the persona's tier says so, else `free()`.
  The premium fixture carries `provider: null`, `status: 'preview'`,
  `renews: false`, so nothing downstream is told a real subscription exists.
- `database/seeders/PreviewUserSeeder.php` — `PREMIUM_DEMO_PERSONA = 'peak_earners'`,
  applied to both the primary and the spouse: the household is premium, not the
  account, so Sarah sees what David sees.

Tests: `tests/Feature/Tiers/OneDemoHouseholdShowsPremiumTest.php` — the demo
household resolves premium, another persona resolves free, and a real user's
column still grants nothing.

## Adjacent, not fixed

`EnsureFullEstateAccess:38` calls `TeaserGate::isFull()` while its own docblock
says it applies "the same canonical TeaserGate decision" as everywhere else.
`TeaserGate::allows()` — the canonical one — bypasses for admins and preview
users; `isFull()` does not. So preview personas bypass every other capability
gate and are blocked by this one. Left alone deliberately: closing it would
make ALL personas premium for estate, which is the opposite of the decision
above. Worth its own item if the inconsistency ever bites.
