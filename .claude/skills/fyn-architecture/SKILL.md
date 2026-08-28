---
name: fyn-architecture
description: The canonical Fyn AI contract — one prompt, two write states, one endpoint across web, /m and native. Covers the dispatch predicate, why Advice Fyn is read-only, how write intents reach the database via delegate_to_capture, and how to satisfy Rule 20 (one change, one place, all surfaces). Use before touching anything under app/Services/AI/, app/Services/Onboarding/, AiChatController, the Fyn prompts or context assembler, Fyn chat rendering on any surface, the SSE event contract, or any tool catalogue. Also use when a Fyn behaviour is wrong on one surface but right on another.
---

# Fyn architecture — the canonical contract

Source of truth: `April/April24Updates/spec/00-canonical.md`. This skill is the working summary; the spec wins on any conflict.

## One prompt, two write states, one endpoint

Fyn has two states behind one chat surface. **The user never sees or feels the switch.**

The system prompt is **unified**: both states send the identical `FynSystemPrompt::text()` plus the per-turn `FynContextAssembler::build()` (`app/Services/AI/Fyn/`). Gated by `FYN_PROMPT_ARCH` / `config('fyn.prompt_architecture')`, **default `unified`** since the 2026-05-17 cutover. `legacy` is the emergency rollback path only — it silently breaks the advice→capture write journey, so never leave it on.

The two write states are enforced at **dispatch and tool-gating**, never by prompt content.

| State | Class | Writes? |
|---|---|---|
| Onboarding Fyn | `app/Services/Onboarding/OnboardingChatDirector` | **Yes — the only state that enters or edits information.** Runs the bubble-driven onboarding flow and the post-onboarding `handleInlineCapture` entry point. |
| Advice Fyn | `app/Services/AI/AdviceFyn` | **No — read-only.** Answers questions using the recommendation engine, risk module and every other engine. |

**Advice Fyn exposes zero write tools.** Every `create_*` / `update_*` / `delete_*` / `set_expenditure` / `capture_*` tool is listed in `AdviceFyn::WRITE_TOOLS` and stripped from the catalogue. That includes `create_what_if_scenario`, which persists a `WhatIfScenario` row — it is a write tool despite the name reading like a calculation.

## How a write intent gets written in advice mode

```
LLM calls delegate_to_capture
  -> AdviceFyn::wrapStream
  -> OnboardingChatDirector::handleInlineCapture
  -> the same direct-write handlers in CoordinatingAgent
```

The synthetic `handoff` SSE event is consumed internally and **never reaches the frontend** (INV-2.4.1).

## The dispatch predicate — three parts, not one

One guard in `AiChatController::sendMessage`. The onboarding write state requires **all three**:

1. `users.onboarding_completed === false`
2. `users.onboarding_fyn_step !== null`
3. `config('onboarding.fyn_flow_enabled', true)`

Every other case routes to the read-only advice state — **including a paused onboarding user whose `onboarding_fyn_step` was nulled**. It is **not** keyed purely on `onboarding_completed`. See `00-canonical.md:11`.

**Campaign re-entry (2026-07-03) extends this:** a completed user with a non-null `users.active_campaign` and a non-null step routes to the write state, via the shared `routesToOnboardingDirector()` helper.

## Invariants that must not be broken

- **No frontend persona signals.** No `persona_state_change` SSE event, no "capturing" pill, input placeholder invariant. Any UI that distinguishes the two states violates the contract.
- **No `FynPersonaOrchestrator`**, no invoker, no registry, no `DataCapturePromptBuilder`. If you find yourself adding one, stop.
- **One endpoint for every surface.** Web, `/m` and native all post to `POST /api/ai-chat/conversations/{id}/messages`. Read/write dispatch is server-side and surface-agnostic. A client must never bake in an onboarding-vs-advice split — send to the one endpoint and render the stream.

## Direction of travel

The contract above is the current state: two write states, write-safety by catalogue-strip. CoALA adds a shared loop with mechanical write-safety at the dispatch boundary (`GroundGate` rejects write tools on the read-only surface, audited `status='stripped'`) — the substrate for collapsing to **one Fyn**. The final single-loop step is a deferred design call, but the direction is settled. **Build new work against a single Fyn surface.**

## Rule 20 in practice — one change, one place, all surfaces

Root `CLAUDE.md` Rule 20 is the law; this is how to obey it.

**Before fixing any Fyn behaviour, enumerate every mechanism that implements it.** If more than one exists, **consolidating them into one source is part of the fix.** Editing the copies in lockstep is a violation, not a fix.

- **Prompts:** one prompt source per behaviour (`FynSystemPrompt` plus per-turn assembler layers). Never a second prompt carrying its own copy of a fact or rule.
- **Vocabularies and regexes** (ownership phrasings and similar): one canonical constant or class; every consumer composes from it.
- **Frontend:** shared helpers across web and `/m` (e.g. `renderFynText`). Every SSE consumer handles the full event contract. Any route the backend emits must resolve on every surface.

**The disease this was written against** (2026-07-23, after a full day of "regressions" that were all one illness): two ownership-phrasing vocabularies, two answer paths, three SSE consumers with one missing `navigation`, per-surface markdown renderers, `/m`-only routes.

**A change is not done until proven on all surfaces AND all paths from its one home** — fresh and resumed conversations, first and repeat turns, every dispatch branch.
