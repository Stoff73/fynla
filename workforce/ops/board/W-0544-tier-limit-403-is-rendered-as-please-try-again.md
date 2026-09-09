---
id: W-0544
title: A tier-limit 403 carrying a message, the required tier and an upgrade destination is rendered as "Failed to save property. Please try again."
mission: new-user-run-2026-09-07
branch: null
owner: null
reviewers: [build-lead, growth-lead]
status: queued
severity: high
surfaces: [web, m]
created: 2026-09-09
source: new-user run, csjones 2026-09-07
prior_art_checked: 2026-09-09
prior_art_found: [W-0011, W-0018]
prior_art_outcome: extends — W-0011 was one gate behaving wrongly; this is the gate's response never being read at all
constitution_refs: [07-quality-bar, 06-commercials]
---

## Intent

Free-tier account, adding a second property. `POST /api/properties` returns
**403** with a fully-formed, actionable payload:

```json
{"error":"tier_limit_reached","entity_key":"property","current_count":1,
 "hard_limit":1,"required_tier":"premium",
 "message":"Property limit reached for your current plan.",
 "action":"subscription_options",
 "destination":{"screen":"subscription","params":[],"fallback":"net_worth"}}
```

The UI rendered:

> "Failed to save property. Please try again."

Four separate problems:

1. **The advice is wrong** — retrying can never succeed against a hard cap.
2. **The cause is hidden** — the user is never told they hit a plan limit.
3. **Revenue leak** — the backend returned `action: subscription_options` and
   `destination.screen: subscription`. `router/index.js` already maps
   `subscription -> /settings/subscription`. The upgrade path exists and is
   never shown, at the exact moment the user has demonstrated intent to exceed
   the free tier.
4. **Work is lost** after a three-step modal, unexplained.

`grep -rn "tier_limit_reached" resources/js/ resources/mobile/` -> **zero
matches**. Emitted by `app/Http/Traits/TierLimitResponse.php`, consumed
correctly on the chat path. See W-0549.

Free caps for reference (`TierConfigurationSeeder.php:52-60`): property 1,
investment 2, pension_account 2, savings_account 2, goal 2, life_event 1.

## Acceptance

- [ ] A tier-limit response renders its own `message`, not a generic failure.
- [ ] The upgrade destination the backend returns is offered as a call to action.
- [ ] "+ Add" is disabled or annotated once `current_count === hard_limit`, so the
      user is not invited to fill a form that cannot save.
- [ ] Same behaviour on `/m` from the same helper (Rule 20).
- [ ] Also correct for investment, pension, savings, goal and life_event.
- [ ] The premium benefits list mentions properties — currently it promises
      unlimited investments, savings and pensions and never mentions the limit
      most households hit first.
