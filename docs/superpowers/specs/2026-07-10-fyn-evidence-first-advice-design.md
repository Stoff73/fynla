# Fyn Evidence-First Advice Architecture Design

**Status:** Approved by CSJ on 2026-07-10

**Programme:** Pre-launch blocker workstream in `docs/superpowers/plans/2026-07-10-online-readiness-programme.md`

**Implementation plan:** `docs/superpowers/plans/2026-07-10-fyn-evidence-first-advice.md`

## 1. Decision

Fyn will retain CoALA as its conceptual foundation, but it will not run a fully agentic CoALA planning loop on every turn. The launch architecture is a hybrid, evidence-first financial-guidance system:

> Deterministic financial workflows and engines establish the truth; typed memory provides continuity; a bounded language-model agent explains the evidence; mechanical policy gates control what it may say or do.

This is an optimisation and consolidation of the CoALA implementation already on `dev`, not a migration to another agent framework.

## 2. Launch operating perimeter

Fyn launches in `guidance` mode. It provides precise, personalised financial guidance, explains options and trade-offs, and surfaces Fynla engine output. It does not present a specific regulated investment or product action as suitable for the individual and does not claim to accept legal responsibility for a personal recommendation.

The architecture recognises three operating modes:

1. `guidance` — enabled at launch.
2. `targeted_support` — disabled unless Fynla has the required permission, approved customer grouping, conduct controls, copy and evidence.
3. `regulated_advice` — disabled unless Fynla has the required permissions, suitability process, human accountability and approved governance.

Unknown, disabled or misconfigured modes fail closed to `guidance`. An environment value cannot by itself enable a regulated mode: code configuration and an explicit deployment approval record must both permit it.

The FCA treats accumulated information about a customer's financial circumstances, objectives and risk appetite as relevant when deciding whether a later communication is a personal recommendation. The design therefore treats memory and personalisation as part of the regulatory control surface, not merely a user-experience feature:

- https://handbook.fca.org.uk/handbook/perg8/perg8s41
- https://www.fca.org.uk/firms/advice-guidance-boundary-review
- https://www.fca.org.uk/news/blogs/ai-financial-services-approach

This design is an engineering and product control contract, not legal advice. Final perimeter language and permissions remain subject to Fynla's regulated-advice counsel and FCA engagement.

## 3. Architecture

```text
User turn
  -> deterministic scope, write-intent and required-data checks
  -> operating-mode policy
  -> one immutable turn preparation
  -> one evidence snapshot
       - canonical live user data
       - current tax configuration
       - deterministic engine output
       - relevant trusted relationship memory
       - relevant historical episodes
       - versioned procedures and knowledge
  -> deterministic complexity route
       - direct workflow/reasoning for ordinary turns
       - planner for genuinely complex multi-domain turns
  -> read-only tool execution through GroundGate
  -> language-model explanation
  -> mechanical response policy gate
  -> streamed response
  -> Advice Case record and signed episodic attestation
  -> asynchronous summarisation and gated learning proposals
```

Advice writes continue through the unseen `delegate_to_capture` handoff. The new architecture must not weaken the read-only advice catalogue, `AdviceFyn::WRITE_TOOLS`, `GroundGate`, or the one endpoint shared by desktop and `/m`.

## 4. Canonical Advice Case

Every substantive advice or recommendation-mode response has one `AdviceCase` value object. It is progressively populated during the turn and persisted through the existing `ai_advice_logs` record; no third advice log is introduced.

The Advice Case contains:

- user, conversation and response-message identifiers;
- question and primary classification;
- operating mode and policy version;
- required-data/KYC result;
- complexity route (`direct` or `planned`) and planner decision when present;
- canonical facts used, including source and as-of time;
- relationship memories used, including trust state and source;
- engine outputs, calculation basis and engine/configuration versions;
- tools called and fetch provenance;
- missing information and assumptions;
- options/trade-offs surfaced;
- mechanical policy decisions and any blocked/regenerated output;
- model/provider identifiers and prompt/procedure/semantic snapshot versions;
- final status (`completed`, `blocked`, `failed`, `aborted`).

The existing signed episodic blob remains the forensic transcript. `ai_advice_logs` is the structured, queryable decision record. They reference the same assistant message and do not duplicate independent truths.

## 5. One turn preparation and one evidence snapshot

`AdviceFyn` currently classifies a turn before its deterministic bypasses, and `HasAiChat` classifies it again. The launch design computes classification, required-data status, operating policy and route metadata once in `FynTurnPreparation` and shares the immutable result through a request-scoped holder.

Memory, procedure and knowledge retrieval also run once. The planner and final reasoner receive the same `FynEvidenceSnapshot`; neither performs a separate recall with different relevance rules.

The snapshot renders the provider prompt block and retains structured provenance for the Advice Case. It is reset in `finally` after every stream, including generator abandonment and exceptions.

## 6. Complexity-gated planning

Ordinary turns do not pay for a planner call merely to select `reason`.

The deterministic router selects `direct` when any of these applies:

- factual, general, billing, navigation or out-of-remit classifications;
- one module and a known engine/tool path;
- required-data failure or deterministic deferral;
- write intent or capture continuation;
- a short follow-up answer to the current topic;
- preview-mode response that does not require cross-module planning.

The router selects `planned` only when at least one approved complexity signal applies:

- holistic/cross-module comparison;
- explicit multi-step scenario or option comparison;
- multiple modules with dependencies or ordering;
- unresolved conflicting evidence requiring a bounded retrieval decision;
- a future approved procedure whose metadata explicitly requires planning.

The existing planner remains available for `planned` turns. Initially it runs in shadow mode for eligible turns while the direct path serves the user. It becomes active only after the evaluation gate proves a material improvement without unacceptable latency, cost or policy failures.

Post-turn summarisation and learning proposal generation move to queued jobs. No learning action may add latency to the user-visible response.

## 7. Memory trust model

Fyn distinguishes information by ownership and trust:

| Layer | Source of truth | Use |
|---|---|---|
| Canonical financial state | Fynla models, tax configuration and engines | Always fetched live; never copied into durable semantic memory |
| User-confirmed relationship memory | Typed per-user SQL fact | Preferences, priorities, concerns and durable choices |
| User-stated unverified memory | Typed per-user SQL fact | Usable with an explicit unverified label; never overrides canonical data |
| Proposed inference | Review queue only | Never injected as fact before approval/confirmation |
| Historical episode | `ai_messages` index plus signed blob | What was discussed at a point in time; not current financial truth |
| Procedural/regulatory knowledge | Versioned corpus | How Fyn operates; effective-dated and reviewed |

Every relationship-memory fact has a stable fact key, category, value, display text, trust state, source type/message, effective dates, confirmation time, status and supersession link.

Precision is part of provenance. Approximate language remains approximate: “my child is eight” cannot be stored as a fabricated 1 January date of birth, and “I have an ISA” cannot become Cash or Stocks & Shares or individual/joint ownership without confirmation. If a canonical schema needs a more precise value, Fyn asks for it before the write. Neither typed memory nor an historical summary may launder an inference into a confirmed canonical fact. The user-testing evidence and capture acceptance are mapped in `docs/superpowers/specs/2026-07-11-user-testing-report-reconciliation.md` and Task 10A of the master programme.

The current per-user Markdown semantic store is migrated into the typed SQL store and retired. The agent-written Markdown episode summaries under `fyn-memory/episodic/episodes` are also retired. The existing SQL/signed-blob episodic subsystem becomes canonical.

The global procedural and source-less semantic corpora remain version-controlled files. Live values continue to be reached through pointers.

## 8. User memory control

Users can inspect and control relationship memory from Privacy & Data settings on desktop and a matching `/m` route. The interface uses plain text and the existing design system, with no new decorative icons.

Users can:

- see what Fyn remembers and why;
- distinguish confirmed statements, unverified statements and historical context;
- confirm an unverified fact;
- correct a fact, creating a superseding version rather than rewriting history invisibly;
- delete a fact;
- see when it was last used in advice.

Chat requests such as "forget that" or "that is no longer true" are write intents. They route through `delegate_to_capture` and mechanically use the same memory service as the settings interface. Advice Fyn never mutates memory directly.

Preview users cannot persist memory changes. Export, account deletion, self-service erasure and `fyn:user:erase` include the typed memory store and remove legacy files after migration verification.

The design follows ICO requirements to keep the source/status of personal data clear, distinguish opinions or inferences from facts, support rectification, and minimise collection:

- https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/data-protection-principles/a-guide-to-the-data-protection-principles/accuracy/
- https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/artificial-intelligence/guidance-on-ai-and-data-protection/how-should-we-assess-security-and-data-minimisation-in-ai/

## 9. Mechanical response policy

Regulatory and product rules are not prompt-only. The response gate receives the Advice Case and candidate response and returns one of:

- `allow`;
- `sanitise` for deterministic copy-format rules;
- `regenerate` with machine-readable violations;
- `block` with approved deterministic copy.

Launch guidance-mode checks include:

- unsupported product/provider recommendation language;
- claim of personal suitability or legal responsibility;
- directive recommendation language outside an engine-grounded action explanation;
- absent adviser signposting where the query/policy requires it;
- investment/pension risk-warning requirements;
- tax figures lacking tax-tool/configuration provenance;
- numerical claims absent from the Advice Case;
- model-visible financial-quality scores;
- fabricated write acknowledgements;
- banned icons, acronyms and repeated output already covered by the existing validator.

Product-name detection begins report-only. Blocking is enabled only after an evaluated allowlist and false-positive threshold are approved. Unsupported numerical claims, fabricated writes and disabled operating modes fail closed immediately.

## 10. Learning and retention

`FYN_LEARNING_ENABLED` remains false through initial implementation and migration. Enabling it requires all of the following:

- typed-memory migration and reconciliation green;
- user correction/deletion UI green on desktop and `/m`;
- erasure/export/retention tests green;
- memory relevance and contradiction scenarios green;
- admin proposal-review workflow green;
- CSJ launch decision recorded.

Learning can propose relationship facts or procedure amendments but never auto-apply them. It cannot write live financial values into memory and cannot alter global regulatory/procedural content without review.

## 11. Efficiency and success measures

Internal telemetry records, by route and provider:

- time to first useful content;
- total turn duration;
- model calls and token/cost attribution;
- direct versus planned route;
- planner shadow agreement and incremental benefit;
- tool calls and duplicate calls;
- evidence/memory items injected and actually referenced;
- policy violations, regenerations and blocks;
- user corrections to remembered facts;
- numerical consistency across Fyn, desktop and `/m`.

The planner may become active only when shadow evaluation shows that planned turns improve the approved task-quality rubric while remaining inside the launch latency/cost budgets recorded in the go/no-go document.

## 12. Browser test checkpoints

### Checkpoint 1 — Operating mode, Advice Case and single preparation

- [ ] Ask a normal factual question on desktop and `/m`.
- [ ] Verify one classification/KYC preparation, one completed Advice Case and one response.
- [ ] Verify `operating_mode=guidance` and the same evidence/provenance on both surfaces.
- [ ] Force an unsupported configured mode and verify it fails closed without regulated-advice copy.

### Checkpoint 2 — Direct/planned routing and evidence reuse

- [ ] Ask a single-module retirement question; verify direct route and no planner call.
- [ ] Ask a cross-module retirement/savings/tax comparison; verify planner shadow telemetry and one evidence snapshot.
- [ ] Verify the same financial figures on Fyn, desktop and `/m`.
- [ ] Verify a capture intent still uses the unseen handoff and persists exactly one record.

### Checkpoint 3 — Memory migration and user control

- [ ] Seed confirmed, unverified, superseded and historical memories.
- [ ] View the same list on desktop and `/m`.
- [ ] Confirm, correct and delete through normal UI interactions.
- [ ] Ask Fyn a relevant question and verify only the current, relevant fact is used.
- [ ] Say "forget that" in chat and verify the capture handoff removes the fact without exposing a persona change.

### Checkpoint 4 — Policy, erasure and final live evaluation

- [ ] Exercise adviser signposting, product names, investment risk, tax provenance, unsupported numbers and fabricated-save cases.
- [ ] Verify deterministic allow/sanitise/regenerate/block evidence in the Advice Case.
- [ ] Run export and erasure against a memory-bearing test user and verify no orphan SQL rows or legacy files.
- [ ] Run the approved live-provider eval and browser matrix on desktop and `/m`.
- [ ] Verify learning remains off unless its separate activation gate is explicitly approved.

## 13. Non-goals

- Replacing the language-model provider as part of this workstream.
- Adding multiple autonomous conversational module agents.
- Replacing `TaxConfigService` or deterministic financial engines with model calculations.
- Enabling targeted support or regulated advice by default.
- Autonomous changes to regulatory or procedural content.
- Dense/vector retrieval before the sparse/structured eval proves it is needed.
- Rebuilding the existing write-handoff, GroundGate, audit chain or shared web/`/m` endpoint.

## 14. Acceptance

The workstream is complete only when:

1. Launch mode is mechanically fixed to approved personalised guidance.
2. Every substantive advice turn has one structured Advice Case linked to its signed episode.
3. Classification, KYC and evidence retrieval execute once per turn and are shared by planner/reasoner.
4. Ordinary single-module turns make no planner call.
5. The two legacy per-user Markdown memory paths are migrated/reconciled and no longer serve runtime recall.
6. Users can inspect, correct and delete relationship memory on desktop and `/m`.
7. Advice memory writes route through capture; Advice Fyn remains read-only.
8. Mechanical policy tests and live-provider evals pass.
9. Export, erasure, retention and audit reconstruction include the canonical memory/advice records.
10. All automated and agent-led browser checkpoints are green on the immutable staging candidate.
