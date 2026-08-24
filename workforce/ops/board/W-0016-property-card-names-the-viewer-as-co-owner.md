---
id: W-0016
title: Property card tells the spouse the property is "Joint with" herself
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md
owner: build-lead
status: done
surfaces: [web, m, ios]
created: 2026-08-21T00:40:00Z
claimed: 2026-08-21T10:30:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21T10:30:00Z
prior_art_found: ['PropertyController::index/show already return is_primary_owner + owner_name + joint_owner_name', 'resources/js/utils/ownership.js', 'ChattelDetailInline.vue formatOwnership (second Joint with copy)']
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **Pass A** (desktop web module UI forms, local
`localhost:8000`), account **Sarah Jones (spouse)**, user id 17.

**Surface:** desktop web, `/net-worth/property`, property card.

### Expected

The Willows is owned jointly by David (primary, `user_id = 16`) and Sarah
(`joint_owner_id = 17`). Viewing it as Sarah, the co-owner line should name the other
party: **"Joint with David Jones"**.

### Actual

Viewing as Sarah, the card reads:

```
Main Residence   Joint (50.00%)
15 Chestnut Lane
Guildford, GU1 4RH
Joint with Sarah Jones          <-- she is being told it is joint with herself
Full Property Value   £850,000
Your Share (50.00%)   £425,000
Your mortgage liability £32,500
Equity                £392,500
```

Every figure on the card is correct — this is a labelling fault only.

### Root cause

`resources/js/components/NetWorth/PropertyCard.vue:23-24`:

```vue
<p v-if="isSharedOwnership && property.joint_owner_name" class="property-coowner">
  {{ ownershipLabel }} with {{ property.joint_owner_name }}
</p>
```

It always renders the record's stored `joint_owner_name`. That is correct when the
viewer is the primary owner, and wrong whenever the viewer IS the joint owner — the
card then names the viewer instead of the counterparty. The controller already
returns `is_primary_owner` on the property payload
(`app/Http/Controllers/Api/PropertyController.php` `index`, "Adds computed fields:
`user_share`, `full_value`, `is_primary_owner`, `is_shared`"), so the component has
what it needs to pick the right name.

### Why it is worth fixing properly rather than swapping the string

This persona has a third-party co-owner: the Manchester Investment Property is
tenants-in-common with **Mike Barrett** at 60% (`tests/Persona/peak_earners.md:134-135`).
Once that property can be entered (blocked by the free-tier property cap of 1), the
co-owner name is load-bearing — a household member must be able to see at a glance
that 60% of that asset belongs to someone outside the household. A card that names the
viewer, or the wrong party, on a tenants-in-common asset is materially misleading, not
cosmetic.

### Repro

1. Link two accounts as spouses.
2. As the primary owner, add a property with Ownership Type = Joint Tenancy and the
   spouse as joint owner.
3. Log in as the **spouse** and open `/net-worth/property`.
4. The card reads "Joint with <the spouse's own name>".

### Evidence

Screenshot: `tests/Persona/20-08-2026_run/pass-a-web/06-web-sarah-property-425000-correct-but-joint-with-self.jpg` — "Joint with Sarah Jones" on Sarah's own login, with the correct £425,000 share beneath it.
Report: `reports/R-02-pass-a-verification.md` RED-5.

## Acceptance

- [ ] The co-owner line names the counterparty, not the viewer, for both the primary
      owner and the joint owner.
- [ ] Verified with a non-spouse co-owner (free-text `joint_owner_name`) as well as a
      linked spouse account.
- [ ] Same check on the property detail view, chattels
      (`ChattelDetailInline.vue:472` renders a similar "Joint with …" string), savings
      and investments — anywhere a co-owner is named (Rule 20).
- [ ] `/m` and iOS property cards checked (Rule 19).
- [ ] Re-verified live in the browser by the persona run, from both accounts.

## Working notes

(append-only)

- 2026-08-20 persona-tester: raised from Pass A. The card's *figures* are correct on
  both sides (£425,000 / £425,000, £32,500 / £32,500) — this item is the label only.

- 2026-08-21 build-lead: **FIXED — verified live on the spouse's own login.**

  `coOwnerName(item, viewerId)` in `resources/js/utils/ownership.js` is the one
  home: it returns `joint_owner_name` when the viewer is the primary owner and
  `owner_name` when they are the joint owner, preferring the API's
  `is_primary_owner` flag (which `PropertyController::index`/`show` already
  returned) and falling back to a viewer id. It also reads a nested `user` /
  `joint_owner` relation, which is the shape chattels return.

  Consumers switched: `PropertyCard.vue:23`, `ChattelDetailInline.vue`
  `formatOwnership()` (the second "Joint with" copy the item named), plus a new
  "Held with" line on the investment and savings cards and on the `/m` property,
  investment and savings detail screens.

  While there: `PropertyCard` rendered the record's raw `ownership_percentage` in
  three places (both ownership badges and the share label), which is the wrong
  side of the split for the joint owner. All three now use `userSharePercent()`.

  **Live, `/net-worth/property` as Sarah Jones (17), the joint owner:**
  ```
  Main Residence   Joint (50.00%)
  15 Chestnut Lane
  Guildford, GU1 4RH
  Joint with David Jones          <-- was "Joint with Sarah Jones"
  Full Property Value   £850,000
  Your Share (50.00%)   £425,000
  Your mortgage liability £32,500
  Equity                £392,500
  ```
  Every figure unchanged and correct, as the item said they were.

  **NOT verified: a non-spouse co-owner** (free-text `joint_owner_name`, e.g. the
  Manchester property tenants-in-common with Mike Barrett at 60%). That record
  cannot be entered — blocked by the free-tier property cap of 1, as the item
  itself notes. The unit tests in `tests/frontend/utils/ownership.test.js` cover
  the free-text and nested-relation shapes; the live case needs the cap lifted.

  Frontend spec updated: `PropertyCard.spec.js` badge assertion is now
  `Joint (50.00%)` — two decimals everywhere on the card, matching the share
  label beneath it and what the decimal column returns.

  **GAP:** `/m` property card not verified live (see W-0015). iOS not checked.

- 2026-08-21 build-lead: batch handover (CLAUDE.md Rule 22) — `workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md`. Carries the dispatch verbatim, the joint-share consolidation reasoning, decisions taken, dead ends ruled out, and environment state.
