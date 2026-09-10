# F-06 · Tier-limit error swallowed; user told to "try again" — HIGH

**Env:** csjones staging · **Surface:** desktop web (and `/m` by omission)
**Repro:** Free-tier account → onboarding step 3 Assets → Properties →
add a second property → fill all three modal steps → Save Property.

## What happened

`POST /api/properties` returned **403** with a fully-formed, actionable payload:

```json
{"success":false,"error":"tier_limit_reached","entity_key":"property",
 "current_count":1,"hard_limit":1,"required_tier":"premium",
 "message":"Property limit reached for your current plan.",
 "action":"subscription_options",
 "destination":{"screen":"subscription","params":[],"fallback":"net_worth"}}
```

The backend supplied the cause, the limit, the required tier, a user-facing
message, an action, and an upgrade destination.

The UI rendered:

> "Failed to save property. Please try again."

## Why this is high severity

1. **The advice is wrong.** Retrying can never succeed — the limit is a hard cap.
2. **The cause is hidden.** The user is never told they hit a plan limit.
3. **Revenue leak.** The backend explicitly returned `action: subscription_options`
   and `destination.screen: subscription`. That upgrade path is never shown.
   This is the exact moment a user has demonstrated intent to exceed the free
   tier, and the product says "try again" instead of offering the upgrade.
4. **Work is lost** after completing a three-step form, with no explanation.

## Root cause

`grep -rn "tier_limit_reached" resources/js/ resources/mobile/` → **zero matches.**

The concept is emitted by `app/Http/Traits/TierLimitResponse.php` and correctly
consumed on the chat path (`CoordinatingAgent.php`, `OnboardingChatDirector.php`,
`HasAiChat.php`). Neither the web SPA nor `/m` handles it anywhere, so the
generic catch-all error message wins.

## This is the same disease as F-05 — name the pattern

| Concept | Backend emits | Fyn/chat path | Web + `/m` |
|---|---|---|---|
| `invitation_pending` (F-05) | yes | handled | **no matches** |
| `tier_limit_reached` (F-06) | yes | handled | **no matches** |

Two independent features implemented once for the conversational surface and
never for the form surfaces. This is the Rule 20 failure mode described in
CLAUDE.md — parallel mechanisms where each fix lands in only one of them. Worth
treating as a systemic audit item, not two isolated bugs: any other structured
`error_type` the backend returns is likely also unhandled by the form surfaces.

## Fix direction

1. A shared error interpreter used by web **and** `/m` (one home, Rule 20) that
   reads `error_type` and renders `message` plus the `destination` call to action.
2. For `tier_limit_reached` specifically, surface the upgrade route rather than
   a retry prompt.
3. Pre-empt where possible: disable or annotate "+ Add Property" once
   `current_count === hard_limit`, so the user is not invited to fill a form
   that cannot be saved.

## Free-tier caps for reference

`database/seeders/TierConfigurationSeeder.php:52-60` —
property 1 · investment 2 · pension_account 2 · savings_account 2 · goal 2 ·
life_event 1 · mortgage 10. Capabilities: `estate` teaser,
`expenditure_detailed` none, `what_if` none, `retirement_decumulation` none,
`joint_household_view` none.
