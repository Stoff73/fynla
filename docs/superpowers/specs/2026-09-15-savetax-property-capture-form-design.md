# Save Tax property capture form — design

**Date:** 2026-09-15 · **Owner:** CSJ · **Status:** approved in conversation, spec for review
**Scope:** the Save Tax campaign onboarding property step (`campaign_property`) on web and `/m`. Nothing else changes. Native iOS keeps the typed prompt until CSJ has seen the form.

## Why

Free text into a parser has a long tail. On 2026-09-15 the live property step lost a two-property sentence to the model refusing and the backstop mis-reading it (prod conversation 874, fixed in #857). CSJ's decision: for onboarding, capture the data in a structure we expect, store it through the same path the app uses, and keep the model out of it. Property is the first step to get this shape. The extractor, gate and backstop stay as they are for typed text and for every other step.

## What the user sees

At the property step Fyn's message is a short lead-in ("Now your property.") followed by a small form inside the chat — the form's own boxes and Save button carry the instructions, so the message no longer repeats them (CSJ 2026-09-16):

- Three option boxes: **Home**, **Second home** and **Buy to let**. Any combination expands.
- **Home** expands to: Value *, Mortgage outstanding * (with a "No mortgage" choice), Ownership *.
- **Second home** expands to: Value *, Mortgage outstanding * (with a "No mortgage" choice), Ownership * — the same fields as Home.
- **Buy to let** expands to: Value *, Mortgage outstanding * (with "No mortgage"), Monthly rental income *, Ownership *.
- **Ownership** offers Individual, Joint, Tenants in common. Joint and Tenants in common reveal **Your share %** *, pre-filled 50. Trust is not offered here (the full app form has it). No joint-owner name is asked; the spouse joint-record memory names the co-owner as it does for typed capture.
- Required fields carry an asterisk, as the app's forms do. Save is disabled until every expanded kind's required fields are filled.
- A "Skip, I have no property" link where the step already allows a skip.
- After Save the form locks and shows the entered figures; a form in history can never be submitted again.
- Money fields take plain numbers and display through the existing currency helper. No icons. Palette tokens only.

Typing a sentence instead of using the form goes down today's typed path unchanged; the form stays available until the step advances.

## Architecture

```
web / m chat  --capture_form SSE event-->  renders FynCaptureForm from the schema
web / m chat  --POST messages {message, form}-->  AiChatController -> OnboardingChatDirector
OnboardingChatDirector (form turn)  -->  CoordinatingAgent::executeTool('create_property', ...)  -->  PropertyStore
```

One endpoint, server-side dispatch, the same write path as every Fyn capture (Rule 20).

### 1. The schema — one server-side home

`app/Services/Onboarding/CaptureForms.php` (final class) holds one schema per form-capable step, keyed by name. For this work it holds `property` only. A schema is plain data:

```
name: property
kinds:
  - key: main_residence      label: Home
    fields: current_value, mortgage, ownership
  - key: secondary_residence label: Second home
    fields: current_value, mortgage, ownership
  - key: buy_to_let          label: Buy to let
    fields: current_value, mortgage, monthly_rental_income, ownership
fields:
  current_value          money   required
  mortgage               money-or-none   required   ("No mortgage" sets has_mortgage=false)
  monthly_rental_income  money   required
  ownership              choice  required   options individual | joint | tenants_in_common
  ownership_percentage   percent required-when ownership in (joint, tenants_in_common)   default 50
```

Labels, hints and the asterisk rule are part of the schema so both renderers show the same words. Option values are the canonical enums (`individual`, `joint`, `tenants_in_common`; `main_residence`, `secondary_residence`, `buy_to_let`).

### 2. The state

`OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY` gains `'turn_type' => 'form', 'form' => 'property'`. Its `capture_focus`, `next` and `skip_if` stay. The director's `emitTurnForState` handles `form` next to `bubbles`: it yields one `capture_form` event `{type, prompt_text, form: <schema>, skip_link?}` and persists an assistant message with `metadata.form` (schema name) and `metadata.onboarding_step`, mirroring `quick_replies`, so History and a resumed conversation re-render the form.

**Client capability.** The form is sent only when the request carries `X-Fynla-Forms: 1`. The web and `/m` bundles send it on every messages call. A client without it (native today) gets the existing typed prompt for the same step, so nothing changes for iOS. The header is read once in the controller and passed to the director as a flag; the director never inspects headers.

### 3. Submission

`POST /api/ai-chat/conversations/{id}/messages` gains an optional `form` object beside `message`:

```
message: string (the plain-words summary the client composes, e.g. "Home worth £750,000, mortgage £325,000, joint 50%. Buy to let worth £450,000, no mortgage, rent £1,000 a month, individual.")
form:
  name: property
  answers:
    main_residence?:      { current_value, has_mortgage, mortgage_outstanding_balance?, ownership_type, ownership_percentage? }
    secondary_residence?: { current_value, has_mortgage, mortgage_outstanding_balance?, ownership_type, ownership_percentage? }
    buy_to_let?:          { current_value, has_mortgage, mortgage_outstanding_balance?, monthly_rental_income, ownership_type, ownership_percentage? }
```

`SendAiChatMessageRequest` validates the shape only: `form.name` must be a known schema, each answer block matches the schema's kinds and field types. Business rules stay in the store.

The user message is saved with `message` as its content (the transcript reads naturally) and the raw `form` in its metadata.

### 4. The form turn in the director

When the current state is a `form` turn and the request carries `form` for that schema:

1. For each filled kind, build the `create_property` input in the store's canonical field names: `property_type`, `current_value`, `has_mortgage`, `mortgage_outstanding_balance`, `monthly_rental_income`, `ownership_type`, `ownership_percentage`. Unstated optional fields are omitted, never sent as null (PropertyNormaliser trap, fixed in #857).
2. Run each through `CoordinatingAgent::executeTool('create_property', …)` — the same handler, gate, tier cap, spouse memory and audit trail as every Fyn write. The form answers are handed to the accuracy gate as **confirmed facts** (`CaptureAccuracyGate::inspect`'s `$confirmedFacts`, its channel for deterministic sources such as extractor parses and scripted answers), so ownership and share are satisfied by the user's structured answer and never by reading the composed sentence.
3. If every call landed: emit the `entity_created` rows, `capture_complete`, then advance exactly as the typed path does (`enterCampaignVerify` → the verify page).
4. If any call failed: emit a `capture_form_errors` event `{form, errors: {kind: {field: message}}}`; records that did land stay saved and are reported; the step stays parked and the form stays open with the values intact. A tier-cap refusal shows the plan-limit message on that kind's box.

No model call and no extractor on this path. A `form` payload on a state that is not a `form` turn is rejected with a 422.

### 5. Renderers

- **Web:** `resources/js/components/Shared/FynCaptureForm.vue`, rendered by `AiChatPanel` for a message of role `capture_form`, the way `quick_replies` is rendered. Schema-driven: it knows kinds, field types and the asterisk rule, not property. Locks after save (values shown, inputs disabled); historical forms are locked on render.
- **`/m`:** `resources/mobile/components/FynCaptureForm.vue`, rendered by the onboarding chat mixin's event router, same behaviour, built from the same schema. The two components share no code by architecture (isolated bundles); they share the schema, which is the one source of field definitions.
- Both: palette tokens, existing input classes from `app.css`, currency through the existing helper, no icons (Rule 15), British copy.
- SSE: both event routers learn `capture_form` and `capture_form_errors`. Every other consumer ignores unknown event types today; verified in the plan.

### 6. Errors and edges

| Case | Behaviour |
|---|---|
| Store validation error | Field error beside the field; form stays open with values |
| Tier cap on a kind | That kind's box shows the plan-limit message; the other kind saves |
| User types text instead | Today's typed path; form remains until the step advances |
| Refresh or reopen mid-step | Persisted `capture_form` message re-renders an empty form |
| Skip link | Existing skip behaviour for the step |
| Client without the header | Existing typed prompt; no form event |
| Form posted at a non-form state | 422 |

### 7. Testing

- **Pest:** `CaptureForms` schema (required flags, kinds, enums); request validation (shape, unknown schema, bad kind); director form turn — home only, buy to let only, both, joint with share, tenants in common, "No mortgage", a store error, the tier cap on a third property, the header fallback emitting the typed prompt, and a form on a non-form state → 422. All through the director, no model.
- **Vitest (web and `/m`):** renders kinds and fields from the schema; asterisks on required fields; Save disabled until required fields are filled; share field appears for Joint and Tenants in common with 50 pre-filled; errors render beside fields; the form locks after save and historical forms are locked.
- **Browser (csjones, CSJ's Chrome):** the Save Tax walk to the property step on web and `/m`; fill and save both kinds; the verify page shows both records with the right figures, share and rent; then the walk continues to the date-of-birth prompt.

### 8. Out of scope

Native iOS renderer; any step other than `campaign_property`; the full onboarding path outside the Save Tax campaign; Trust ownership; addresses and every other property field; changes to the extractor, gate or backstop.
