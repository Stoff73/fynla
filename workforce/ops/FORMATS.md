# Ops Formats

Every artefact the workforce produces. **Copy these; do not improvise.** A format
nobody follows is a format that cannot be swept, indexed, or read by the control
centre.

---

## Work item — `board/W-NNNN-slug.md`

```yaml
---
id: W-0142
title: Write the PS25/22 targeted-support position paper
mission: 2026-08-13-fca-targeted-support
branch: branches/research/ps2522-position/
owner: compliance-lead
status: queued            # queued|claimed|in_progress|handoff|review|gated|blocked|done
surfaces: [web, m, ios]   # Rule 19 — explicit, never "the app"
created: 2026-08-13T09:14:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: null   # REQUIRED before claimed
prior_art_found: []
prior_art_outcome: null   # none|route|extend
constitution_refs: [05-perimeter, 03-hard-nos]
---

## Intent
## Acceptance
## Working notes
(append-only)
```

**An item cannot reach `claimed` without `prior_art_checked`.** `charter.md` §11.

**Claiming** is setting `owner` + `claimed` and committing. Git rejects the second
writer, so two agents cannot hold one item.

---

## Handoff note — `handoffs/W-NNNN/<from>-to-<to>-<date>.md`

**An item cannot move to `handoff` with an empty note.** Unstated assumptions are
how agent chains silently degrade.

```markdown
# W-0142 — compliance-lead → product-lead

## Done
## Not done, and why
## What you need that isn't obvious from the artefacts
## Assumptions I made
(stated as assumptions, never as facts)
## Surfaces covered / not covered
```

---

## Gap record — `gaps/G-NNNN-slug.md`

```yaml
---
id: G-0009
class: knowledge   # access|tool|credential|context|knowledge|capability|contradiction
agent: growth-lead
severity: blocking # blocking|degrading|info
opened: 2026-08-13
action: interview  # fix|interview|escalate
blocking: [W-0144]
status: open
---
## The gap
## Evidence
## Resolution
```

---

## Gate request — `gates/GATE-NNNN-slug.md`

```yaml
---
id: GATE-0031
workstream: quality
item: W-0131
action: Merge dev → main and deploy to fynla.org
raised: 2026-08-13T15:50:00Z
decided_by: null    # MUST name a founder
decided_at: null
decision: null      # approve|hold
---
## What is being asked
## Evidence
## What happens if held
## Decision and reasoning
```

**Every decision records its author.** A decision that cannot be attributed to a
named founder is not a decision (`registry/people.md` §3.1).

**Timeout:** 48h → escalate once → park the item and move on. Agents never idle
waiting on a human. Outside CSJ's contact window the clock pauses.

---

## Provisioning request — `provisioning/PR-NNNN-slug.md`

```yaml
---
id: PR-0001
needs: Slack workspace authorisation
kind: connector    # connector|skill|hook|agent|access|credential|subscription
requested_by: chief-of-staff
blocking: [phase-3]
spend: none        # none | £X — anything non-zero is also a spend gate
status: open
---
## What it unlocks
## Cost of not having it
(the workaround in use, and what that workaround costs — "we need this" is not a case)
## Setup
## Decision
```

**One thing per request, never bundled** — so a founder can approve some and
decline others. Declined requests keep their reason; the workaround becomes
permanent rather than being re-requested monthly.

---

## Event log — `log/YYYY-MM.jsonl`

Append-only, one line per state change. **The control centre reads only this.**

```json
{"ts":"2026-08-13T11:41:19Z","agent":"compliance-lead","item":"W-0142","event":"handoff","to":"product-lead","note":"handoffs/W-0142/compliance-to-product-2026-08-13.md"}
```

Events: `claimed` · `started` · `progress` · `handoff` · `review` · `judged` ·
`gated` · `blocked` · `done` · `gap_opened` · `probe` · `triage` · `deploy`

**`progress` matters most.** Liveness is derived from the log, not from reports —
a working agent emits events, a dead one does not. **If a status cannot be derived
from the log, the missing event is the bug**, and agents are never asked to
compensate by narrating.

---

## Branch document — `branches/<type>/<slug>/`

```yaml
---
id: F-0042
type: feature      # feature|fix|maintenance|research|meeting
parent: core/constitution/07-quality-bar.md   # MUST resolve
applies: [core/constitution/05-perimeter.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-13T18:00:00Z
status: active     # active|superseded|archived
---
```

**A branch with no resolving parent is invalid and blocks until linked.** Branches
apply rules; they never author them (`charter.md` §11 / Archivist).

---

## Checkpoint report — `reports/`

Written by an agent **only** on completion, handoff, hard block, or context
handover. **Never on a clock.** A mid-work agent reports nothing; the Chief of Staff
observes it from the log.

```markdown
## Done
## Not done, and why        (never omitted)
## Assumptions
## Needs                    (gate | answer | access | provisioning)
## Noticed                  (outside my remit — routed to whoever owns it)
```

---

## Daily brief — `reports/brief-YYYY-MM-DD.md`

Generated by the Chief of Staff at 17:30. **≤300 words.** If a day needs more, it
is a meeting.

**Shipped** · **Moving** (right now, observed) · **Needs you** (ranked by what they
block) · **Watch** · **Read** (one line of judgement).

Ends with what is still in flight. **Never a summary implying completion.**
