---
id: W-0276
title: Emergency runway counts cash the user cannot actually reach, and LiquidityAnalyzer already knows which is which
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: product-lead
status: queued
severity: low
surfaces: [web, m, ios]
created: 2026-08-22T22:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0271]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Recorded as the **known cost** of W-0271 rather than left implicit.

Both the savings module and (now) the risk engine compute runway from **all** of a
user's cash. A five-year fixed-rate bond counts exactly like a current account, so a
household can be told it has nine months of runway when most of it cannot be reached
without breaking a term.

This is **not a regression** — the savings module has always counted it this way, and
W-0271 made the risk page agree with it rather than inventing a third rule. But
`app/Services/Savings/LiquidityAnalyzer.php` already categorises accounts by access
and builds a liquidity ladder, so the information to do better is present and unused
for this figure.

**It is a product call, not a bug fix**, because narrowing "available" would move the
dashboard's headline months for every existing user.

## Acceptance

A decision, recorded: either runway uses accessible cash on **every** surface at once,
or the current definition is deliberate and the copy says "cash savings" rather than
implying immediacy.
