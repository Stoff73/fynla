# Fyn wiring Batch D — evidence (F19)

Branch `fix/fyn-wiring-batch-d` off dev `09cc3d477`, 9 September 2026.

**F19 — Fyn is not told which recommendation card opened a conversation.** `FynContextAssembler::surfaceActionContext` now renders `opened_from: {origin.kind}` and, when `origin.recommendation_id` names one of the user's `recommendation_tracking` rows, `opened_from_recommendation: {recommendation_text}` (wrapped as user-provided). `CreateContextualConversationRequest` already validates `origin.kind` (`surface_action` | `recommendation`) and an integer id; no web, `/m` or native client sends a recommendation origin yet, so this is the server half of the link and the client half is a follow-up.

Tests: `ContextualResourceContextTest` gained the case; `tests/Unit/Services/AI/Fyn` plus the three contextual feature files: 103 passed, 333 assertions.

## Findings still open after Batches B, C and D

F4, F5, F6, F10, F11, F14 — each needs a decision recorded in the session report before code is written.
