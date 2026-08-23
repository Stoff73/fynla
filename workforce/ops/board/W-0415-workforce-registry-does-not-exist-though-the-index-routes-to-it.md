---
id: W-0415
title: workforce/registry/ does not exist, though core/index.md routes to it and makes capabilities.md the first prior-art source
mission: null
branch: null
owner: null
status: closed_invalid
severity: medium
closed: 2026-08-23T10:55:00Z
closed_reason: not-a-defect-path-resolution-error
surfaces: []
created: 2026-08-23T02:30:00Z
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: []
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Raised per `core/index.md` rule 8 — *"Never guess at doctrine. No answer in the trunk = a
trunk gap. Raise it."*

`workforce/core/index.md` has a "Where things live — `registry/`" section listing
`systems · storage · comms · tools · access · people · rhythm · meetings · capabilities`,
with **`capabilities` marked "read before building anything"**. The build-lead agent
definition makes `registry/capabilities.md` the **first** of its six prior-art sources.

`workforce/registry/` does not exist. Not the file — the whole directory.

Every prior-art check run under the current agent definitions is therefore running on five
of six sources, and no agent can tell whether the sixth would have changed the outcome.
Two agents in cycle 4 hit this independently.

## Acceptance

1. Decide whether the registry was never built, was moved, or was deleted — `git log
   --diff-filter=D -- workforce/registry` before concluding it never existed.
2. Either build it, or amend `core/index.md` and every agent definition that routes to it,
   so the trunk stops describing something that is not there. **`core/index.md`'s own
   preamble says: "if something is described here *and* elsewhere, this file is wrong" —
   describing something that is nowhere is the same failure.**
3. Until then, agents should record `prior_art_checked` with an explicit note that source
   1 was unavailable, rather than silently checking five.


## Resolution — 2026-08-23, closed INVALID

**The registry exists in full.** It is at `workforce/core/registry/`, not
`workforce/registry/`. All nine files are present, `capabilities.md` among them
(29KB, last updated 2026-08-21), plus a tenth, `sources.md`. `git log` confirms
`workforce/registry/` was **never** created, moved or deleted — it never existed,
because it was never the path.

**Why two agents concluded otherwise.** `core/index.md` headed the section
*"Where things live — `registry/`"*, and every agent definition except
`cartographer.md` cited the bare relative `registry/capabilities.md`. Read from the
repo root — which is where an agent reads from — that path resolves to nothing.
`cartographer.md` was the only file carrying the full path, which is why the
Cartographer never hit this and everyone else did.

**Fixed at source rather than per-reader (Rule 20 shape — one home, all consumers):**

- `core/index.md:21` — header now reads `workforce/core/registry/`.
- `build-lead.md`, `chief-of-staff.md`, `design-lead.md`, `growth-lead.md`,
  `product-lead.md`, `ops/FORMATS.md` — bare `` `registry/` `` made absolute.
- References **inside** `workforce/core/` are left relative: they resolve correctly
  from there, and rewriting them would be churn.

**No prior-art check was ever running on five of six sources.** Source 1 was always
there. Prior-art outcomes recorded before today do not need revisiting on this
ground.

**Correction to the 2026-08-23 session-1 handover:** its "Things that will bite you"
section states *"`workforce/registry/` does not exist although `core/index.md` routes
to it as prior-art source 1."* That is wrong on both halves and should not be
carried forward.
