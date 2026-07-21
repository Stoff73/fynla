# Fyn System Prompt — Unified Architecture

> Archived per-state docs (pre-2026-05-16 two-prompt architecture): prompts/archive/

Fyn presents as one chat surface with **one static system prompt**. There are two
*write states* — onboarding and advice — selected by
`AiChatController::sendMessage` on `users.onboarding_completed` and enforced
purely by the turn's tool list (tool gating + dispatch), **not** by prompt
content. Both states send the identical `FynSystemPrompt::text()`; only the
dynamic per-turn context block and the tool list differ.

Gated behind `FYN_PROMPT_ARCH` (`config('fyn.prompt_architecture')`, values
`legacy` | `unified`, default `legacy`). Canonical contract:
`April/April24Updates/spec/00-canonical.md`.

## Static system prompt — `FynSystemPrompt::text()`

`app/Services/AI/Fyn/FynSystemPrompt.php`. One immutable string, assembled once
from the existing prompt text (deduplicated, generalised only at the two named
interpolation sites — `firstName`, `taxYear`), in this fixed order:

```
<identity>          generalised — "you help the user" (no {firstName})
<security>          verbatim from Prompts/CoreIdentity (9 non-negotiable rules)
<scope>             generalised (no {firstName})
<personality>       verbatim
<response_format>   verbatim; informal-address line generalised to
                    "you may occasionally use the user's first name (given in your context)"
<instructions>      verbatim from Prompts/ComplianceRules (British, no-acronyms,
                    currency, IDs, routes, joint ownership, jargon, irrelevant concepts)
<regulatory>        verbatim from Prompts/ComplianceRules; rule 5's "{taxYear}"
                    generalised to "the tax year given in your context"
<tool_use>          consolidated from Prompts/FcaProcessInstructions:
                    FCA 6-step, read-vs-write tool error handling,
                    update-vs-create rule, handoff (delegate_to_capture) rules,
                    billing response shape, FCA-signposting final-line rule
```

Carried in the provider `system` field with `cache_control` — the entire prefix
is a cache hit on turns 2..N and across users.

## Dynamic user turn — `FynContextAssembler::build()`

`app/Services/AI/Fyn/FynContextAssembler.php`. Everything per-user / per-turn /
per-classification is relocated here from the system prompt:

```
<context>
  Current tax year: {taxYear}
  You are speaking with: {firstName}
  Situation: advice  |  onboarding — focus: {focusLabel}
  {profile}                                ← always (IDENTITY)
  {current page}                           ← always (IDENTITY)
  {financial snapshot}                     ← POSITION
  {existing records}                       ← POSITION
  {ranked recommendations}                 ← POSITION
  {known facts}                            ← always when MemoryRetrieverService returns a non-empty block
  {data completeness · KYC · review-due}   ← READINESS
  {preview-mode notice}                    ← if isPreview
  {capture-turn instructions}              ← if onboarding capture turn
</context>

<user_message>
  {message, wrapped via UserContentSanitiser}
</user_message>
```

### 4-bucket selector — `FynContextSelector`

`app/Services/AI/Fyn/FynContextSelector.php`. Reuses the existing
`QueryClassifier` factual signal.

| Bucket | Content | Included when |
|--------|---------|---------------|
| `IDENTITY` | profile narrative, current-page context | **always** |
| `POSITION` | financial snapshot, existing records, ranked recommendations | message references the user's money/records, or asks for advice |
| `READINESS` | data completeness, KYC gate result, review-due | advice-type query or a module that may be `BLOCKED` |
| `CAPTURE` | onboarding focus header, capture-turn instruction block | `OnboardingChatDirector` is mid-flow (`mode = onboarding`) |

- `mode = onboarding` ⇒ always `{IDENTITY, CAPTURE}` (never POSITION/READINESS — preserves the deliberate onboarding context-starvation).
- `mode = advice` ⇒ `IDENTITY` + selector-chosen `POSITION`/`READINESS`.
- Factual queries (BILLING / NAVIGATION / DATA_ENTRY / OUT_OF_REMIT / INCOME / GENERAL) ⇒ `IDENTITY` only.
- Known-facts gap-fill (`MemoryRetrieverService::renderKnownFactsBlock()`) is **mode-independent** — emitted whenever the block is non-empty, not gated by a bucket.

The strict capture-turn rules (one `tool_use` per entity; ≤15-word or empty
acknowledgement; no questions; ignore out-of-scope volunteered info; retraction
→ `update_profile`/`update_record`; tool list for the focus) live verbatim in
`app/Services/AI/Fyn/FynCaptureTurnInstructions.php`, emitted in the `CAPTURE`
block.

## Canonical rendered text

The canonical rendered system prompt is snapshotted at
`docs/superpowers/specs/fyn-system-prompt.snapshot.txt` (byte-stability test in
the Fyn prompt suite). Design spec:
`docs/superpowers/specs/2026-05-16-fyn-prompt-rework-design.md`. Parity record:
`May/May16Updates/fyn-prompt-rework-parity.md`. Pre-cutover reference tag:
`fyn-two-prompt-pre-unify`.
