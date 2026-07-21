# 04 — Handoff round-trips

5 scenarios — full advice → inline-capture → advice round-trip per the canonical contract.

Each scenario asserts (per INV-2.4.1 / INV-2.4.2):

- Advice Fyn emits `delegate_to_capture` when a missing fact is required.
- Onboarding Fyn's `handleInlineCapture` persists the fact via the same direct-write handlers in `CoordinatingAgent`.
- Control returns to Advice Fyn and the original query is answered.
- **Zero `persona_state_change` SSE events** reach the frontend.
- **Zero capturing-pill renders.** Input placeholder text invariant.
- The user sees a single seamless conversation thread — no preamble, no bubble shift.

Source: `fyn-rubrics.md §B` coverage table — "Handoff round-trips (advice → capture → advice)".
