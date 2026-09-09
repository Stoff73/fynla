# Fyn wiring Batch F — evidence (F4: one home for the onboarding table)

Branch `fix/fyn-wiring-batch-f` off dev `098caa12d`, 9 September 2026. CSJ (08:31): "one home for this, so long as the context is built using the bubbles and user responses".

## What changed

- `OnboardingStateMachine::inCodeStates()` lost every field the corpus workflow file owns (654 → 360 lines): 49 turn types, 30 prompt strings (the 11 callable prompt builders stay), 14 bubble lists, 44 capture fields, 15 static `next` values (the 16 closures and callable references stay), 10 extraction tools, 10 retry texts, 2 layouts, 3 value parsers, 7 `advance_on_answered_question`, 1 `clarify_single_figure`, 2 static `navigate_to` (the closure stays), 1 `skip_link`, 1 `bubble_capture`, 8 advice sections. It keeps `skip_if`, closures, callable references, and the five keys the corpus never carried (`reprompt_text`, `record_context`, `record_context_mode`, `capture_focus`).
- `transitionTable()` requires the corpus. A configured corpus without the workflow procedure (a test's temp corpus, a broken deploy) reads the shipped file `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` through the new `ProceduralCorpusLoader::parseFile`; a shipped file whose state set differs from the code throws. No silent fallback to a text-less table.
- `OnboardingWorkflowTableGoldenMasterTest` flipped from "corpus deep-equals code" to "code carries no corpus DATA" plus "every corpus DATA field lands in the merged table" (the Rule 20 guard). `CampaignVerifyFlowTest` reads turn types from `states()`.

## Proof the behaviour is unchanged

`transitionTable()` was serialised (closures as a marker) before and after the strip and compared state by state: 49 states, same order, identical.

## Live (`/m`, fresh user `f4-onboarding-test@example.com`, user 73, local)

Dashboard → Fyn sheet: "Hi Fay, I'm Fyn — welcome to Fynla … do you want to follow a life-stage journey or pick a single module focus?" with the bubbles Follow a journey / Pick a focus / Something else. Tapped Follow a journey → "Which journey fits your situation best?" with Starting Out / Building Foundations / Protecting What Matters / Planning Your Future / Enjoying Your Wealth. Prompt text, bubbles and the static transition all come from the corpus now. (User 73 is a local dev leftover; delete with user 72.)

## Tests

`tests/Unit/Services/Onboarding`, `tests/Feature/Onboarding`, `tests/Feature/AI`, `tests/Architecture`, `tests/Unit/Services/AI/Memory`: 1,657 passed, 4 skipped, 9,369 assertions.
