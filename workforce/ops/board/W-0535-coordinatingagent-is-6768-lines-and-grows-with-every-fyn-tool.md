---
id: W-0535
title: CoordinatingAgent is 6,768 lines and 115 methods, and grows with every Fyn tool added — wants a plan, not an opportunistic extraction
mission: board-verification-31-august
owner: null
reviewers: [quality-lead]
status: queued
severity: low
surfaces: [web, m, ios]
created: 2026-09-04
source: tech-debt audit, 2026-09-01 (docs/tech-debt-report.md, sole warning)
prior_art_checked: 2026-09-04
prior_art_found: []
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

`app/Agents/CoordinatingAgent.php` is **6,768 lines** across **115 methods**,
verified 2026-09-04. It was the only warning in the 2026-09-01 tech-debt audit.

Every Fyn capture handler lives in it, so it is not merely large, it is **on the
growth path**: each new tool in the catalogue adds another handler here. Rule 20
makes that the correct place for them today — one Fyn change, one place — which is
exactly why the file cannot be split casually. A split that puts handlers in two
places would be a Rule 20 violation dressed as a refactor.

## Why this is a board item and not a refactor someone does in passing

The failure mode to avoid is an opportunistic extraction taken while fixing
something else: a handler moved because it was convenient, leaving the reader with
two homes and no rule for which. The file needs a **decided seam** first —
per-module handler classes, a registry, a trait per surface — chosen against the
Fyn architecture contract, not against line counts.

## Acceptance

1. A written plan naming the seam, checked against the `fyn-architecture` skill's
   one-prompt/one-endpoint contract, before any code moves.
2. The plan states how Rule 20 stays true afterwards: one place a Fyn behaviour is
   changed, still reachable from every surface and every dispatch path.
3. No behaviour change in the extraction itself, evidenced by the Fyn tool-schema
   golden masters staying byte-identical (they are, deliberately — see
   `ToolSchemaGoldenMasterTest`).
4. CSJ approves the seam before implementation. This is architecture, and the
   handover named it as wanting a plan rather than a fix.
