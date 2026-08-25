---
id: BUG-03
raised: 2026-08-17
surface: all three clients (the link), plus a spec-vs-code conflict on KYC
severity: blocker
status: diagnosed — one confirmed gap, one spec conflict needing CSJ's ruling
---

# BUG-03 — the post-save link is built on no surface; KYC is bypassed for data entry

CSJ: *"a user asks to enter a pension, Fyn responds with either a KYC gate as every
turn needs … if there is enough info, saves the pension and reports as such with a
link for the user to go to the page to see the entry, like every entry, specced,
planned and reported as complete. Why is this not working?"*

Two separate answers. One is a real gap on every surface. The other is a
documented design decision that contradicts the expectation, which CSJ has to rule
on rather than me.

## Corrections to what I said while investigating

Both were wrong and are recorded so they do not get reused:

1. **"The unified prompt discards the KYC result."** Wrong. `HasAiChat:1403-1425`
   builds `FynTurnContext` with `kycResult:` and passes it to
   `FynContextAssembler::build()`, which renders it at `:234-242`. The layer is
   intact.
2. **"`entity_created` is not emitted."** Wrong. It fires correctly. My repro
   filtered SSE frames against a fixed list that omitted it. Measured properly, the
   capture turn emits:

```
title
tool_use          create_pension running
entity_created    {"entity_type":"dc_pension","entity_id":961,"name":"Aviva Pension"}
tool_use          create_pension complete
capture_complete  {"summary":"Saved to your records","records_created":[{"type":"dc_pension","id":961,...}]}
done
```

## Finding 1 — CONFIRMED: no client turns the confirmation into a link

The backend does its part. `handleCreatePension` returns `created => true`,
`entity_type`, `entity_id`, `name`; `HasAiChat:878-885` emits `entity_created`
carrying all four; `capture_complete` additionally carries `records_created` with
each id. The canonical spec (`00-canonical.md:21`) lists `entity_created` as a
public confirmation event "consumed on desktop and `/m`".

All three clients consume it. **None of them builds a link** — every one uses only
`name` and throws away the `entity_type` and `entity_id` a link needs:

| Surface | Code | What it renders |
|---|---|---|
| web | `AiMessageContent.vue:14-22` | text card: `{{ entityLabel }} created: {{ message.content }}` |
| `/m` | `onboardingChat.js:454-457` | pushes `ev.name` into `createdEntityNames` |
| native | `FynEventReducer.swift:63-72` | appends the name to a `.pending(names:)` capture chip |

Native is worse than "not implemented": `FynEvent.swift:21` declares
`case entityCreated(name: String?)`, so the id and type are **discarded at decode
time**. It structurally cannot build a link without a decoder change.

So the specced behaviour — save, confirm, and offer a link to view the entry — has
its data flowing correctly end to end and is then dropped by every consumer. This
is the Rule 20 shape again: one backend event, three consumers, none implementing
the contract.

**Nothing to un-regress here — as far as the code shows, the link was never built.**
Worth checking against whatever reported it complete, because the backend half
plainly was.

## Finding 2 — SPEC CONFLICT: KYC is deliberately bypassed for data entry

Measured for the exact message:

```
message                : "I have an aviva pension with a balance of 45000"
classification primary : data_entry
isBypassType           : true
KYC gate would run     : false
```

`HasAiChat:293-298` only calls `KycGateChecker` when the classification is neither
a bypass type nor `GENERAL`. And `QuerySchemas.php:158-164`:

```php
/**
 * Types that bypass the FCA process entirely — data entry and navigation.
 */
public const BYPASS_TYPES = [
    self::DATA_ENTRY,
    self::NAVIGATION,
];
```

So a pension-capture turn is **explicitly exempted** from the KYC gate, with a
documented rationale: recording a fact is not advice, so it does not enter the FCA
process. The gate machinery is healthy — it simply is not asked on this turn type.

**This needs CSJ's ruling, not my guess.** I could not find a spec statement
requiring a KYC gate on `data_entry` turns; what I found is code deliberately
excluding them. Either:

- the exemption is correct and the expectation applies to advice turns, or
- the exemption is the deviation, and `data_entry` should gate — in which case
  removing `DATA_ENTRY` from `BYPASS_TYPES` is a one-line change with a wide blast
  radius (every capture turn would gate on missing personal details, income, etc.),
  and needs the eval bank re-baselined.

If CSJ can point at the spec section, I will implement to it.

## Still open from BUG-02

On the follow-up turn Fyn narrates "recorded as a Self-Invested Personal Pension"
while making **no tool call**, so the row stays `pension_type=occupational`. The
allowlist now permits the correction (BUG-02 fix) but the model does not take it.
That is a claim of success without a write — the one failure mode Rule 14 names
explicitly — and it deserves a mechanical guard rather than prompt tuning: if a turn
narrates a save and no write landed, the turn should not be allowed to claim it.

## Suggested order

1. **Build the link.** Backend already sends everything. Native needs
   `FynEvent.entityCreated` widened to carry `entity_type` and `entity_id` first.
   Route resolution should come from ONE shared source, not three per-surface maps —
   see the existing `WebHandoffDestination` and `SemanticDestination` precedents.
2. **CSJ ruling on Finding 2**, then implement.
3. **A mechanical no-fabricated-success guard** for narrated-but-unwritten saves.
