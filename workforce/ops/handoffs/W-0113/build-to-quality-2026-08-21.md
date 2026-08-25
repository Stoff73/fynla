# W-0113 — build-lead (`fix-batch-I`) → quality-lead

**Branch document:** `workforce/branches/fixes/F-0009-batch-i-onboarding-spouse.md` §12–21
**Sibling items handed over together:** W-0111, W-0112, W-0114

## Done

**The finding, sharpened.** Not "the model picks between two tools" in general —
`capture_spouse_details` is not in `getTools()` at all. It lives in the `onboarding`
group and is reachable only through `toolsListOverride` (grouped extract) or by name
through `allowedToolsOverride`, which `HasAiChat` widens with
`onboardingExtractionTools()` before filtering. That widening is the crux: on the
**advice → `delegate_to_capture` → `runInlineCapture`** path —  the one path a general
"add my wife Sarah" actually lands on — `OnboardingChatDirector::captureToolSet()`
returns `AdviceFyn::WRITE_TOOLS`, so **both tools are offered and only one could link
the household.** Which word the user happened to use decided whether their accounts
were connected.

**Converged, not taught to match.** `CoordinatingAgent::linkSpouseAccount()` is now THE
one path from a Fyn tool to a spouse link, and it enters `SpouseLinkingService` — the
same home the settings form and the onboarding wizard use. Both tools call it. Each
keeps its own input contract and response shape because their consumers differ; neither
keeps its own idea of what linking a spouse means.

- `handleCreateFamilyMember()` routes `relationship === 'spouse'` to
  `createSpouseFamilyMember()`, which requires the email and refuses with a message the
  model can act on.
- Tool schemas bumped to **v2** on both providers with an `email` parameter and a
  description telling the model to ask rather than send null. xAI is `strict: true`, so
  `email` is in `required` as a nullable type.
- `php artisan fyn:procedural:validate` — 101 procedures, v2 active on both.

**12 tests pass.** The load-bearing one drives both tools and asserts the resulting
households are identical by shape — row count, link, reciprocal user, reciprocal row,
both permissions. Two entrances, one mechanism, one outcome.

## Not done, and why

- **No browser verification** — a persona-tester closes Rule 14's loop.
- `create_family_member` still accepts `partner` and maps it to `other_dependent`.
  Whether a partner is linkable at all was W-0111, answered there: no.
- Nothing committed, no PR, no deploy.

## What you need that isn't obvious from the artefacts

- **An existing test asserted the defect as correct behaviour.** It was called
  `create_family_member persists a spouse without email`. It is now
  `refuses a spouse with no email rather than writing an unlinked row`, with a docblock
  saying so. The tool was wrong, not the assertion — but you should see that inversion
  rather than find a renamed test in a diff.
- **The golden-master re-record swept in another agent's `create_pension` corpus edit.**
  Whole-catalogue capture; there is no way to record one tool. Reported to the team lead
  at the time. The fixtures match the corpus on disk and the gate is green, but three
  fixture files carry a change that is not mine.
- **W-0113 is not the root cause of W-0051.** The team lead asked directly. They sit on
  opposite sides of the read/write boundary; the reasoning is in W-0113's working notes,
  and it matters because it decides whether W-0051 counts as fixed. It does.

## Assumptions I made

*Stated as assumptions, never as facts.*

- **That requiring the email on the tool path is right for a conversational surface.**
  Fyn now answers "add my wife Sarah" with a question rather than a silent half-write.
  I believe that is better than an unlinked household, but it does turn one turn into
  two, and that is a product feel CSJ may want to weigh.
- **That the two tools should keep separate response shapes.** Their consumers differ —
  the onboarding director reads `onboarding_capture`/`field_group`, the write pipeline
  reads `success`/`entity_id`. Collapsing them was not asked for and would have widened
  the change considerably.
- **That `guardRecapture` should not run on the spouse branch.** The linking service
  already adopts an existing row rather than inserting beside it, so the duplicate guard
  has nothing to guard. If that reasoning is wrong the symptom would be a re-link
  silently updating a record a user expected to be refused.

## Surfaces covered / not covered

- **Web, `/m` and iOS — covered by construction and stated, not assumed.** All three
  send to the one endpoint `POST /api/ai-chat/conversations/{id}/messages`; read/write
  dispatch and tool selection are entirely server-side. There is no per-surface tool
  catalogue to keep in step.
- **All paths:** advice → `delegate_to_capture` → inline capture (where both tools are
  live), the onboarding grouped-extract turn, and the preview-mode strip.
