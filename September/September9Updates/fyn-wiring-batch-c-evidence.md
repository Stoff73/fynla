# Fyn wiring Batch C — evidence (F1, F7, F8, F9, F13, F18 + Batch B residue)

Branch `fix/fyn-wiring-batch-c` off dev `01edb44da`, 9 September 2026, verified locally (main checkout server on `127.0.0.1:8000`; `/m` rebuilt with `npm run build:mobile`). Persona: young family, user 63.

## Batch B residue

- **Empty "LIFE EVENT IMPACTS BY MODULE:" line / `months_until` warning.** `AdvicePromptBuilder` read a `months_until` key that `LifeEventIntegrationService::formatEventForModule` never emits; it now derives months from `expected_date`. Tinker for user 63: `- savings: 3 upcoming events, net impact -£23,000 (next: Replace Family Car in 11 months)`.
- **`/m` 403 on `POST /api/ai-chat/onboarding/start` for preview personas.** The endpoint's 403 `preview_mode` is by design; the mixin's two entry points (`startOnboarding`, `resumeOnboardingInDock`) now greet instead of calling it, through one `skipOnboardingForPreview()`. Reloaded `/m/app/dashboard` and `/m/app/savings` as the persona on the rebuilt bundle: greeting shown, no console error file written (Playwright only writes one when there is an error).
- **`BelongsTo` null-offset deprecation** inside `orchestrateAnalysis` is Laravel 10's `BelongsTo::match` under PHP 8.5.2 when `users.spouse_id` is null and `spouse` is eager-loaded (`UserProfileService::getCompleteProfile`). Vendor code; not changed. It goes away with the framework upgrade.

## Findings

| Finding | Fix | Evidence |
|---|---|---|
| F1 pension phrasing → general | Two `RETIREMENT_CONTRIBUTION` patterns in `app/Constants/QuerySchemas.php` | Classifier run: all four phrasings → `retirement_contribution`; data-entry and general cases unchanged. Live `/m` turn "Should I be putting more into my pension?" as the persona → `ai_messages` 441: `<required_tools>` names the four pension tools, KYC PASSED, reply cites the £10,857 shortfall and the 5 % / 5 % contributions against the 10 % match |
| F7 `employed` bubble skips the workplace-pension question | `OnboardingStateMachine::WORKPLACE_PENSION_STATUSES`, read by both predicates | `CampaignStateMachineBranchTest` now includes `employed` |
| F8 classifier runs twice | Classification threaded AdviceFyn → `FynLoop::run` → `reason` → `stream` → `chatWithPromptOverride` → `setChatOverrides(classificationOverride:)`; `HasAiChat::chat` reuses it | AdviceFyn, loop and `ConsentRuntimeCheckTest` families green (61) |
| F9 config key without a file | `config/ai_chat.php` | — |
| F13 action endpoint without consent; `ai_chat` withdrawable; version pin | Consent guard on `action()`; `ai_chat` removed from the GDPR writable types; version guarded by test | `AiChatActionConsentGateTest` (3) |
| F18 status filter never matches | Aggregator merges `recommendation_tracking` status; `/m` reads it; engine ids pass through the raw blocks | `RecommendationsStatusFilterTest` |

## Tests

Consolidated pass at the end of the branch (Unit/Services/AI, QuerySchemas, onboarding branch tests, GDPR API, Feature/AI, recommendations API, mobile, coordination, aggregator, Fyn context, AdviceFyn parity): 1,530 passed, 3 skipped, 5,245 assertions; the one failure was `ContextualConversationDispatchTest` hitting the action endpoint without consent, which F13 now requires, and it grants consent since. Mobile Vitest specs for the onboarding mixin, Dashboard and ModuleDetail: 47 passed.
