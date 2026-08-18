# Registry — People

**Status:** Founders and conflict resolution ratified 2026-08-13, session 1 Q7.
Azlan and Brett provisioned but **not yet active** — staged rollout, CSJ first.
**Owner:** CSJ. Amendments gated.

---

## 1. Founders — full authority, all three

**All founders hold all authority.** Any founder may approve gates, authorise
publishing, approve spend, and ratify trunk amendments.

| Name | Known as | Authority | Status |
|---|---|---|---|
| Chris Slater-Jones | CSJ · `@Stoff73` | Full | **Active** |
| Azlan Raj | — | Full | **Provisioned, not active** |
| Brett Isenberg | — | Full | **Provisioned, not active** |

**Founder accounts are admin accounts, not subscriptions** (CSJ, 2026-08-13). They
are not customers, carry no tier, and must never be used as evidence of how a real
user experiences the product. Preview personas exist for that.

### Contributors — no authority

| Handle | Branch prefix |
|---|---|
| `icecube-acc` | `feature/icecube/<task>` |
| `Phailanx` | None required (CSJ direction 2026-08-02) |

---

## 2. Staged activation

**Ratified 2026-08-13.** The authority model is built now and activated in
sequence. CSJ first, so the mechanism is proven before it carries three people.

| Stage | State |
|---|---|
| **Now** | CSJ active. Azlan and Brett hold full authority in doctrine but cannot exercise it — no registered identity, no local install. |
| **Then** | Azlan and Brett install locally, are interviewed (§4), and are registered here. Activation is a trunk amendment. |

Until registered, a message from an unregistered founder is triaged as
Information-class and carries no authority. **This is deliberate staging, not a
gap** — the workforce cannot enforce authority it cannot attribute, so attribution
comes first.

---

## 3. Attribution and conflict

### 3.1 Every decision records its author

**Ratified.** No anonymous approvals. Gate files, provisioning decisions and trunk
amendments record **who** decided, when, and through which channel. A decision that
cannot be attributed to a named founder is not a decision.

### 3.2 Conflicts resolve by domain

**Ratified 2026-08-13.** All three hold full authority for ordinary decisions.
This rule fires **only when two founders disagree.** The conflict is then decided
by the founder who owns that domain.

| Domain | Decides | Workstreams |
|---|---|---|
| **Engineering** — code, architecture, infrastructure, security, testing, release mechanics | **CSJ** | Build, Quality, Archivist |
| **Regulatory** — FCA, the perimeter, advice-vs-guidance, PS25/22, Consumer Duty | **CSJ** | Compliance |
| **Product** — roadmap, prioritisation, what gets built | **CSJ** | Product |
| **Design** — UI, UX, visual system, brand expression, UX writing | **Azlan** | Design |
| **Marketing** — go-to-market sequencing, acquisition, campaigns, channel mix | **Azlan** *(CSJ, 2026-08-13)* | Growth |
| **Business and financial** — pricing, commercial strategy, partnerships, spend | **Brett** | Intelligence |

**Amended 2026-08-13:** marketing moved from Brett to **Azlan**, who defines the
go-to-market sequence. The Growth workstream escalates to Azlan; Intelligence
still escalates to Brett.

**Marketing copy still splits**, being the one artefact two domains both own:
**claims and pricing → Brett** (they are commercial commitments and carry
regulatory exposure); **everything else — sequencing, channel, tone, voice, visual
→ Azlan**. Compliance's hard block (charter §4) applies to either half and is
overridable only by a founder.

*Rationale for keeping claims with Brett: a pricing claim is a commercial promise
before it is a marketing message, and `charter.md` §5 puts spend and commercial
commitments in his domain.* Say if you'd rather claims moved too.

**Why this shape.** It mirrors `00-precedence.md` §1, which already ranks documents
by domain rather than seniority, for the same reason: domain ownership means a
decision is made by whoever is competent to make it, rather than by whoever spoke
last or loudest.

**The decision binds.** It may be appealed only at the weekly review, never by
re-raising it through the workforce.

### 3.3 Who classifies

The Chief of Staff, from the work item's workstream. Any founder may reclassify. A
disputed classification is itself a Friday-review item, not something the workforce
resolves.

*Ratified 2026-08-13: regulatory sits whole with CSJ rather than splitting position
from implementation — the perimeter is not usefully separable from the code and
prompts that enforce it.*

---

## 4. Interviews — sequential, divergences documented

**Ratified 2026-08-13.** CSJ is interviewed first and completes sessions 1–9.
Azlan and Brett are each interviewed when they install the workforce locally.

**A later founder's answer never overwrites a ratified clause.** Where an answer
conflicts with the existing trunk, it is recorded in
`ops/interviews/divergences.md` — the clause stands unchanged, the divergence is
logged with both positions and their reasoning, and it goes to a meeting for
resolution. Only the meeting's outcome amends the trunk.

**Why not merge silently:** two plausible answers averaged into one clause
produces doctrine neither founder holds, and nobody notices until the Chief of
Staff judges work against it. Divergence is information; a silent merge destroys it.

Each founder runs their own local install against the **same git-tracked trunk**,
so the tree stays single-sourced and ordinary git resolves concurrent writes.

---

## 5. Contact

CSJ's hours and the contact-window rules are in `rhythm.md`. Azlan's and Brett's
are unknown and are captured at their activation, not before.
