# Fyn brain rewire — stakeholder brief

**For:** Fynla leadership and investors
**About:** A planned upgrade to how Fyn — our AI advice surface — organises what it knows, what it can do, and how it decides
**Companion doc:** Full technical specification (`fynla-coala-implementation-plan.md`) for the engineering team
**Date:** 2026-05-26

---

## The one-liner

We are rebuilding the way Fyn thinks — not what Fyn says — so that Fyn becomes **regulator-auditable by design**, **cheaper to operate**, and **faster to improve** without engineering deploys. The user never sees the change. The business sees it on three lines of the P&L: compliance cost, AI infrastructure cost, and product iteration speed.

---

## Why now

```mermaid
flowchart LR
  classDef driver fill:#FCE7F3,stroke:#E83E6D,stroke-width:2px,color:#1F2A44
  classDef outcome fill:#D1FAE5,stroke:#20B486,stroke-width:1px,color:#1F2A44

  D1[Regulator pressure<br/>FCA suitability rules<br/>require traceable advice] --> O1[Audit-ready<br/>by construction]
  D2[AI costs scale with users<br/>Today: aggregate only<br/>Cannot cut what does not earn] --> O2[Cost attributable<br/>per behaviour]
  D3[Every Fyn change<br/>needs engineering PR<br/>+ production deploy] --> O3[Product ships behaviour<br/>without engineering deploys]
  D4[Knowledge lives in code<br/>FCA rules buried<br/>in PHP heredocs] --> O4[Knowledge in human-editable<br/>versioned content store]

  class D1,D2,D3,D4 driver
  class O1,O2,O3,O4 outcome
```

**The cost of doing nothing.** Each driver above is already biting today. Building this structure now is cheaper than retrofitting it under regulator pressure, under cost pressure, or under competitor pressure. None of those windows reward delay.

---

## What changes for Fyn — before and after

```mermaid
flowchart TB
  classDef today fill:#FCE7F3,stroke:#E83E6D,color:#1F2A44
  classDef future fill:#D1FAE5,stroke:#20B486,color:#1F2A44
  classDef header fill:#1F2A44,stroke:#1F2A44,color:#FFFFFF

  H1[Fyn today]:::header
  H2[Fyn after this work]:::header

  T1[Knowledge baked into code<br/>Every fact change = PR + deploy]:::today
  T2[Audit trail post-hoc<br/>Verbatim transcript only]:::today
  T3[Single AI call per turn<br/>Cost: aggregate per user-day]:::today
  T4[Cannot remember past conversations<br/>well across sessions]:::today
  T5[Sometimes confabulates<br/>when data is missing]:::today

  F1[Knowledge in versioned content store<br/>Product edits facts directly]:::future
  F2[Audit trail by construction<br/>Every fact + decision tagged]:::future
  F3[Plan / execute cycle<br/>Cost attributable per behaviour]:::future
  F4[Structured recall of past sessions<br/>Auto-resumes broken conversations]:::future
  F5[Refuses gracefully<br/>when knowledge is missing]:::future

  H1 --- T1
  T1 --- T2
  T2 --- T3
  T3 --- T4
  T4 --- T5

  H2 --- F1
  F1 --- F2
  F2 --- F3
  F3 --- F4
  F4 --- F5
```

---

## How Fyn will be organised

Borrowed from a 2023 Princeton paper (Cognitive Architectures for Language Agents, "CoALA") that has become the reference framework for production AI agents. Four kinds of memory, one decision loop, one safety boundary.

```mermaid
flowchart TB
  classDef mem fill:#FCE7F3,stroke:#E83E6D,stroke-width:2px,color:#1F2A44
  classDef desc fill:#FDFAF7,stroke:#CBD5E1,color:#1F2A44

  WM[Working memory]:::mem
  WMD[What Fyn is thinking about right now<br/>The current conversation turn]:::desc

  SM[Semantic memory]:::mem
  SMD[What Fyn knows about the world<br/>Tax rules, FCA handbook, products, our house view]:::desc

  EM[Episodic memory]:::mem
  EMD[What Fyn remembers from past conversations<br/>This client, this question, last week]:::desc

  PM[Procedural memory]:::mem
  PMD[How Fyn knows how to do things<br/>Skills, workflows, the conversation playbooks]:::desc

  WM --- WMD
  SM --- SMD
  EM --- EMD
  PM --- PMD
```

---

## How Fyn will think — a turn, simplified

The full engineering flow is detailed in the tech spec. The shape stakeholders should know:

```mermaid
flowchart LR
  classDef step fill:#FCE7F3,stroke:#E83E6D,stroke-width:1px,color:#1F2A44
  classDef fast fill:#D1FAE5,stroke:#20B486,color:#1F2A44
  classDef safe fill:#EDE9FE,stroke:#5854E6,color:#1F2A44

  Q[User asks Fyn<br/>a question]:::step
  Bypass{Is this an<br/>obvious case?}:::fast
  Plan[Look up what Fyn knows<br/>+ remember past context<br/>+ decide what to do]:::step
  Gate{Safety check:<br/>is this allowed<br/>in this mode?}:::safe
  Act[Take the action<br/>e.g. answer, ask, save data]:::step
  Record[Record verbatim<br/>for the regulator]:::safe

  Q --> Bypass
  Bypass -->|yes - skip the AI call| Act
  Bypass -->|no| Plan
  Plan --> Gate
  Gate -->|allowed| Act
  Gate -->|denied| Record
  Act --> Record
```

**Two things to notice.** First, the safety check is mechanical — Fyn cannot write to your data in advice mode by construction, not by trust. Second, the obvious-case bypass keeps cost down: Fyn does NOT pay an AI bill for routing decisions a simple rule can make.

---

## The plan in six phases

```mermaid
gantt
  title CoALA delivery roadmap (relative effort)
  dateFormat X
  axisFormat %s

  section Foundation
  Phase 1 - Build the world knowledge store    :p1, 0, 5
  section Tidy-up
  Phase 2 - Tidy past-conversation storage     :p2, after p1, 3
  section Consolidate
  Phase 3 - Consolidate current-turn state     :p3, after p2, 2
  section Externalise
  Phase 4 - Move skills out of code            :p4, after p3, 3
  section Orchestrate
  Phase 5 - Wire the decision loop + cost telemetry :p5, after p4, 4
  section Learn
  Phase 6 - Human-gated learning from experience :p6, after p5, 3
```

Each phase ships independently. We do not need to commit to the full programme up front — each phase has a definition of done that pays back on its own. Phase 1 alone resolves the FCA narrative-content risk. Phase 5 alone resolves the cost-attribution gap.

---

## Top three risks and what we are doing about them

```mermaid
flowchart TB
  classDef risk fill:#FCE7F3,stroke:#E83E6D,stroke-width:2px,color:#1F2A44
  classDef mit fill:#D1FAE5,stroke:#20B486,color:#1F2A44

  R1[Risk: planning step<br/>adds latency to every turn]:::risk
  M1[Mitigation: obvious-case bypass<br/>covers 30-50% of turns without AI<br/>+ thinking indicator covers the rest]:::mit

  R2[Risk: regression during cutover<br/>Fyn might behave differently after refactor]:::risk
  M2[Mitigation: shared loop with thin shells<br/>preserves existing safety boundary<br/>+ 75 golden-conversation regression tests]:::mit

  R3[Risk: ongoing AI cost increases<br/>during planning-stage rollout]:::risk
  M3[Mitigation: Phase 5 telemetry ships FIRST<br/>so we measure before we commit<br/>+ adaptive depth caps cost per turn]:::mit

  R1 --> M1
  R2 --> M2
  R3 --> M3
```

---

## What this work is NOT

A surprising amount of "AI strategy" conversations conflate things that should stay separate. To be explicit:

| This work is | This work is NOT |
|---|---|
| Restructuring how Fyn organises knowledge | Training our own AI model |
| Adding a decision layer above the existing AI providers | Replacing Anthropic / xAI |
| Building one cleaner decision loop | Building a multi-agent system |
| Improving Fyn's internal mechanics | Changing the user-facing chat experience |
| Making FCA audit cheaper | Replacing our regulatory framework |
| Making AI cost visible per behaviour | Cutting AI spend on its own — we still buy capability |

---

## Decisions we need stakeholder steer on

Three calls that are above the engineering team's pay grade:

```mermaid
flowchart LR
  classDef decision fill:#FCE7F3,stroke:#E83E6D,stroke-width:2px,color:#1F2A44
  classDef rec fill:#D1FAE5,stroke:#20B486,color:#1F2A44

  D1[Phase ordering<br/>Do we go foundation-first<br/>or value-first?]:::decision
  R1[Recommendation: foundation-first<br/>Phase 1 unlocks every other phase<br/>and resolves the biggest regulatory exposure]:::rec

  D2[Cutover approach<br/>Big-bang merge or incremental?]:::decision
  R2[Recommendation: incremental<br/>preserve existing safety boundary<br/>during the rollout, collapse later]:::rec

  D3[When to revisit existing<br/>two-mode safety contract]:::decision
  R3[Recommendation: after Phase 6<br/>when the new loop is proven<br/>not during the build]:::rec

  D1 --> R1
  D2 --> R2
  D3 --> R3
```

---

## What we are asking for

1. **Endorsement of the direction.** Build what is described above; the framework is right, the sequencing is right, and the risks are understood.
2. **Commitment to fund Phase 1 + Phase 5 as a paired investment.** Phase 1 unlocks the regulatory case, Phase 5 unlocks the cost-visibility case. Together they pay back the rest of the programme.
3. **Decision on the cutover approach.** Default to incremental unless there is a strategic reason to accelerate.
4. **Re-review point at end of Phase 5.** Before committing to Phase 6 (learning from experience), the team checks back in with stakeholders. By that point we will have real telemetry on cost per behaviour, real measurement of the planning-stage latency, and real numbers to recalibrate the rest of the programme.

---

**Companion documents:**

- Full technical specification: `fynla-coala-implementation-plan.md` / `.html`
- CoALA reference paper: Sumers et al., 2023, arXiv:2309.02427
- Canonical Fyn contract today: `April/April24Updates/spec/00-canonical.md`
