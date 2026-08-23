---
id: W-0113
title: Two Fyn tools write a spouse and only one can link — `create_family_member` has no email parameter, so it can only ever produce an unlinked household
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0009-batch-i-onboarding-spouse.md
owner: build-lead
claimed_by: fix-batch-I
status: gated
severity: medium
surfaces: [web, m, ios]
created: 2026-08-21T18:50:00Z
claimed: 2026-08-21T19:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: [CoordinatingAgent::handleCreateFamilyMember, CoordinatingAgent::handleCaptureSpouseDetails, SpouseLinkingService, OnboardingChatDirector::createSpouseFamilyMember]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found while consolidating spouse linking for W-0051. Rule 20: two mechanisms do
one job, and the model can pick the one that cannot do it.

### Actual

Three Fyn paths write a `family_members` row with `relationship = 'spouse'`.
Only one of them establishes the link the rest of the product depends on.

| Path | Takes an email? | Links the accounts? |
|---|---|---|
| `capture_spouse_details` → `handleCaptureSpouseDetails` (`CoordinatingAgent.php:1726`) | **required** — refuses without it | yes, through `SpouseLinkingService` |
| `create_family_member` → `handleCreateFamilyMember` (`CoordinatingAgent.php:4673`) | **no such parameter** | no — plain insert, `linked_user_id` NULL |
| `OnboardingChatDirector::createSpouseFamilyMember()` | no, by design (free text) | no |

`create_family_member` accepts `relationship: 'spouse'` in its own validation
(`:4681`). Nothing routes it to the linking service and nothing tells the model
that this tool cannot do the thing the household needs. Which tool the model
reaches for decides whether the user ends up with a linked household or a bare
record, and the corpus schema descriptions govern that choice.

### Impact

After W-0051 the resulting row is at least **honest** — it reports
`is_linked_account: false`, keeps its Edit and Delete, and carries a notice
saying the accounts are not linked. So this is no longer a dead end. But a user
who tells Fyn "add my wife Sarah" can still finish the conversation with an
unlinked household while a user who phrases it slightly differently gets a linked
one, and nothing in the product explains the difference.

Everything joint keys off `users.spouse_id`: Rule 6 joint ownership,
`SpousePermission`, household roll-ups, mirror wills, the whole spouse half of a
persona run.

### Evidence

- `app/Agents/CoordinatingAgent.php:4673-4703` — `handleCreateFamilyMember`
  validation, `relationship` includes `spouse`, no `email`
- `app/Agents/CoordinatingAgent.php:1726-1740` — `handleCaptureSpouseDetails`
  requires `first_name`, `date_of_birth` and `email`
- `app/Services/Onboarding/OnboardingChatDirector.php` —
  `createSpouseFamilyMember()`, free-text capture with no email
- `app/Services/Onboarding/SpouseLinkingService.php` — the single linking home,
  as of W-0051
- Memory `reference_tool_schema_description_governs_llm_defaults` — the tool
  **description** in the corpus is what steers the model here, and changing it
  means re-recording the golden master

## Acceptance

- [ ] Decide the shape: either `create_family_member` refuses
      `relationship: 'spouse'` and the schema description points the model at
      `capture_spouse_details`, or it grows an `email` parameter and routes to
      `SpouseLinkingService` for spouses. **Not both, and never a third linking
      mechanism** (Rule 20).
- [ ] The tool schema description is updated to match, and the golden master is
      re-recorded — the description is what the model actually reads.
- [ ] Fyn's acknowledgement stays honest either way — it already reads
      `FamilyMember::isLinkedAccount()` after W-0051.
- [ ] Covered on web, `/m` and iOS by construction (one endpoint, server-side
      dispatch) — state that rather than assume it.

## Working notes

Raised by `fix-batch-I` from the W-0051 ID block (W-0111–W-0120). Deliberately not
folded into W-0051: choosing between refusing the tool and extending it is a Fyn
tool-catalogue decision with a golden-master re-record attached, which is a
different unit of work from the display predicate W-0051 fixes.


---

## Working notes — fix-batch-I, 2026-08-21 (append-only)

### The finding is sharper than the item was first written

It is not "the model chooses between two tools in general". Mapping where each
tool actually exists:

| Path | `create_family_member` | `capture_spouse_details` |
|---|---|---|
| Advice mode (read-only) | stripped (`AdviceFyn::WRITE_TOOLS`) | stripped |
| **Advice → `delegate_to_capture` → `runInlineCapture`** | **offered** | **offered** |
| Onboarding grouped-extract turn | not offered (`toolsListOverride`) | offered |
| Preview mode | excluded from `getTools()` | n/a |

`OnboardingChatDirector::captureToolSet()` returns
`AdviceFyn::WRITE_TOOLS` minus `navigate_to_page`, and `HasAiChat` widens the
pool with `onboardingExtractionTools()` before filtering — so on the one path a
general "add my wife Sarah" actually lands, **both tools are on the table and
only one of them could link the household.** Which word the user happened to use
decided whether their accounts were connected.

### Not the root cause of W-0051 — stated plainly, because it changes the verdict

They share a family but sit on opposite sides of the boundary. W-0051's dead end
came from the **read** side: an unlinked row displayed as linked and stripped of
its controls. That is fixed at the one place every surface reads
(`FamilyMember::isLinkedAccount()`, appended as `is_linked_account`), which is
**entrance-independent** — any writer, including this one, now produces an honest,
editable, correctly-labelled record. W-0113 is a remaining **write**-side
divergence: a real Rule 20 defect and a real inconsistency, but no longer capable
of producing a dead end. W-0051 is fixed, not patched at one entrance.

### What changed — converged, not taught to match

`CoordinatingAgent::linkSpouseAccount()` is now THE one path from a Fyn tool to a
spouse link, and it enters `SpouseLinkingService` — the same home the settings
form and the onboarding wizard use. Both tools call it. Each keeps its own input
contract and its own response shape, because their consumers differ
(`handleCaptureSpouseDetails` answers the onboarding director with
`onboarding_capture` / `field_group`; `create_family_member` answers the
write-result pipeline with `success` / `entity_id` / `persisted_fields`). What
they no longer keep is their own idea of what linking a spouse means.

- `handleCreateFamilyMember()` routes `relationship === 'spouse'` to
  `createSpouseFamilyMember()`, which requires the email and refuses with a
  message the model can act on — one more question in the conversation, which is
  the right shape for this surface.
- `guardRecapture` is deliberately not consulted on that branch: the linking
  service already adopts an existing spouse row instead of inserting beside it
  (W-0051), so re-linking updates one record rather than duplicating it.
- Schemas bumped to **v2** on both providers
  (`fyn-memory/procedural/tool_schema/estate/create_family_member.{md,xai.md}`)
  with an `email` parameter and a description that tells the model to ask for it
  rather than send null. xAI is `strict: true`, so `email` is in `required` as a
  nullable type. `php artisan fyn:procedural:validate` passes — 101 procedures,
  `estate.tool.create_family_member v2` active on both.
- Golden masters re-recorded. **See the branch document §Collision:** the capture
  is whole-catalogue and swept in another agent's in-flight `create_pension`
  corpus edit. Reported to the team lead at the time rather than absorbed.

### Tests

`tests/Feature/AI/DirectWrite/CreateFamilyMemberTest.php` — **12 passed**.
The load-bearing one is *"produces the same household as capture_spouse_details"*:
it drives both tools and asserts the resulting households are identical by shape
(row count, link, reciprocal user, reciprocal row, both permissions) — two
entrances, one mechanism, therefore one outcome.

An existing test named *"create_family_member persists a spouse without email"*
**asserted this defect as correct behaviour**. It is now
*"refuses a spouse with no email rather than writing an unlinked row"*, with a
docblock saying so — the tool was wrong, not the assertion, and that inversion is
worth recording rather than quietly rewriting.

### Deliberately not done

`create_family_member` still accepts `partner` and maps it to `other_dependent`.
Whether a partner is linkable at all was W-0111, answered there: no.
