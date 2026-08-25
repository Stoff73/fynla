---
id: W-0258
title: The card captions an arithmetic "expected return" beside a median projection that is lower by volatility drag, and the two cannot be reconciled by the reader
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T21:50:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [RiskPreferenceService, InvestmentProjectionChart]
prior_art_outcome: none
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

Surfaced by W-0251's acceptance criterion — *every horizon must imply an annual rate
consistent with the caption on the card.* After the fix they do, but only once volatility
drag is understood, and nothing on the card discloses it.

David's portfolio, life events removed so the comparison is clean: a stated
**7.07% expected return** produces a **median** implying **5.36% – 6.09% a year**. The gap
is σ²/2 = 0.1688²/2 = **1.42%** — the difference between the arithmetic mean return the
caption quotes and the geometric outcome a median path actually compounds at.

The figure is correct and the caption is correct. **They are simply not the same quantity,
and a user checking one against the other will conclude the projection is wrong.**

This is a disclosure question, not a calculation one. No code was changed on it.

## Acceptance

CSJ to decide one of:
1. Caption the geometric/median rate rather than the arithmetic expected return;
2. Keep the caption and add a line explaining why the projection compounds lower;
3. Leave as is, recorded as a deliberate choice.

## Working notes

- Measured after the W-0251 fix, `MonteCarloEngine` seeded, 1,000 iterations.
- Applies identically to the retirement pension projection, which shares the caption
  pattern and the band extractor.
