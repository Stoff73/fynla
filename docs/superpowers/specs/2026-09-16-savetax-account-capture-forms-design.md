# Save Tax account capture forms — ISAs, bank and savings accounts, investments

**Date:** 2026-09-16 · **Owner:** CSJ · **Status:** spec for a fresh instance to implement · **Predecessor:** `docs/superpowers/specs/2026-09-15-savetax-property-capture-form-design.md` (property, shipped 2026-09-16 in PRs #859, #861, #863, #865)

## Goal

Do for the three account steps of the Save Tax onboarding what was done for property: replace free-text capture with a structured form inside the Fyn chat, ask afterwards whether there is another to add, and cut the prompt to a short lead-in because the form carries the instructions. Web and `/m`; native iOS keeps the typed path untouched (it never sends `X-Fynla-Forms: 1`).

The three steps, in walk order:

| State | Today | Tool it delegates to | After this work |
|---|---|---|---|
| `campaign_isa_holdings` | `turn_type: delegated`, typed sentence | `create_savings_account` (cash ISA) / `create_investment_account` (stocks and shares, lifetime, innovative finance) | `form: isa` + `campaign_isa_more` |
| `campaign_bank_accounts` | `delegated`, prompt built by `buildCampaignBankAccountsPrompt` | `create_savings_account` | `form: savings` + `campaign_bank_accounts_more` |
| `campaign_investment_accounts` | `delegated` | `create_investment_account` | `form: investment` + `campaign_investment_accounts_more` |

Everything else in the walk (income, property, pensions, spouse, verify pages, advice) is out of scope. The extractor, gate and backstop stay for typed text.

## Read these first — the property implementation is the template

Every mechanism already exists; this work adds three schemas and generalises two property-specific spots. Read in this order:

1. The predecessor spec above (sections 1–6) — the architecture, the header contract, the event contract.
2. `app/Services/Onboarding/CaptureForms.php` — the one schema home. `property()` (~line 163) is the shape to copy: `name`, `submit_label`, `kinds[]` (`key`, `label`, `fields[]`), `fields{}` (`type`, `label`, `required`, `hint`, `none_label`, `options`, `default`, `required_when`). `names()`, `schema()`, `rules()`, `kindLabel()`, `toolInputs()` (~line 91), `summarise()` (~line 132).
3. `app/Services/Onboarding/OnboardingChatDirector.php`:
   - `emitTurnForState()` form branch (~line 1047): `if ($turnType === 'form' && $this->clientSupportsForms)` — reads `$state['form']`, resolves `form_prompt_text ?? prompt_text`, yields `capture_form`, persists `metadata.capture_form`. Generic already.
   - the dispatch predicate (~line 416): `in_array($state['turn_type'], ['delegated', 'form'])` — a typed sentence at a form state still goes down the typed path. Generic already.
   - `handleFormTurn()` (~line 3676–3790): yields `form_received`, calls `CaptureForms::toolInputs()`, loops the kinds, **hardcodes `'create_property'`** at ~3735, ~3741, ~3746 and `entity_type => 'property'` at ~3749, builds the partial-failure line with `kindLabel()`, yields `capture_form_errors`, parks or advances via `advanceAfterCapture()` (~line 4400). The fact extractor is skipped for form turns at ~line 187–213.
   - `handleUserMessage()` signature: sixth parameter `?array $form`.
4. `app/Http/Controllers/Api/AiChatController.php` — `clientSupportsForms()` (bottom of file), `setClientSupportsForms()` calls in `sendMessage`, `streamQueuedMessage`, `startOnboarding`, `action`. Generic; no change.
5. `app/Http/Requests/AI/SendAiChatMessageRequest.php` — `form.name` must be in `CaptureForms::names()`; rules from `CaptureForms::rules()` filtered to the kinds present in `form.answers`. Generic; no change unless a new field type needs a rule (see "New field types").
6. `app/Services/Onboarding/OnboardingStateMachine.php` — `STATE_CAMPAIGN_PROPERTY_MORE` (~line 127), its states-table entry (`'next' => self::class.'::nextFromPropertyMore'`), `nextFromPropertyMore()` (yes → the capture state, otherwise `enterCampaignVerify($user, 'property')`), and `base_employment_more` / `nextFromEmploymentMore()` (~line 1274) — the loop pattern. The three target states' entries are at ~lines 477–504: note `skip_if` on ISA and bank, `record_context`/`record_context_mode` on bank, `prompt_text` builder on bank, and `next` closures that call `enterCampaignVerify($user, 'savings'|'investments')`.
7. `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` — `campaign_property` / `campaign_property_more` (~line 165–185) are the corpus shape to copy; the three target states are at ~lines 150–163. Corpus DATA keys (`turn_type`, `prompt_text`, `form_prompt_text`, `form`, `bubbles`) live only here; PHP keeps `capture_focus`, `next`, `skip_if`, `record_context`. `tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php` enforces that split.
8. Clients (schema-driven, read once, change only for new field types):
   - `resources/mobile/utils/captureFormState.js` — the ONE mixin (`captureFormMixin`) holding the form's state, validation and payload logic for both renderers.
   - `resources/js/components/Fyn/FynCaptureForm.vue` and `resources/mobile/components/FynCaptureForm.vue` — template-only renderers (Tailwind vs `md-fyn__form*` classes in `resources/mobile/views/dashboard.css` ~line 1921+). Field branches: `money`, `money_or_none`, `choice`, `percent`.
   - `resources/js/store/modules/aiChat.js` — `pushCaptureFormTurn()`, `SET_CAPTURE_FORM_ERRORS`, the `form_received` / `capture_form` / `capture_form_errors` cases in all four SSE switches, history normalisation in `loadConversation`, `sendMessage({ form })`.
   - `resources/js/components/Shared/AiChatPanel.vue` — `isCaptureFormOpen`, `captureFormValues`, `handleCaptureFormSubmit`.
   - `resources/mobile/mixins/onboardingChat.js` — `handleFynEvent` branches, `send(text, form)`, `submitCaptureForm`, `loadTranscript` mapping; `resources/mobile/api.js` sends the header.
9. Tests that are the pattern: `tests/Unit/Services/Onboarding/CaptureFormsTest.php`, `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php` (director form turn: emit, save, tier cap, refusal, partial failure, punctuation, the `_more` loop), `tests/Feature/AI/SendAiChatMessageFormRequestTest.php` (HTTP: header on/off, send, queued round trip, action continue), `tests/frontend/store/aiChatCaptureForm.test.js`, `tests/frontend/components/Fyn/FynCaptureForm.test.js` + its `/m` copy `resources/mobile/components/__tests__/FynCaptureForm.spec.js`, `resources/mobile/mixins/__tests__/onboardingChat.spec.js`, `resources/js/components/__tests__/Shared/AiChatPanel.conversation.spec.js`.
10. Ledger of every ruling taken on the property work, with reasons: `handover/September/16/sdd-property-form/progress.md` (Rulings 1–22). Do not re-litigate them; extend them.

## Part A — generalise the two property-specific spots (do this first, one PR)

**A1. The tool per kind.** Each kind in a schema gains `'tool' => 'create_property' | 'create_savings_account' | 'create_investment_account'` and `'entity_type' => 'property' | 'savings_account' | 'investment_account'`. `handleFormTurn()` replaces the four hardcoded `'create_property'` / `'property'` occurrences with `$kind['tool']` / `$kind['entity_type']` (look the kind up from the schema by key; `CaptureForms::schema($form['name'])['kinds']`). The ISA form needs this because a cash ISA is a savings row and a stocks and shares ISA is an investment row. Property tests must stay green unchanged.

**A2. Per-schema input mapping and summary.** `CaptureForms::toolInputs()` and `summarise()` are property-shaped today (mortgage, rent, share). Split them by schema name: `toolInputs()` dispatches to `propertyInputs()`, `isaInputs()`, `savingsInputs()`, `investmentInputs()`; `summarise()` likewise. Keep the public signatures. The property branches are the existing bodies moved verbatim.

**A3. New field types (mixin + both templates + request rules).**
- `text` — a short free-text input (provider or bank name). Mixin: value is a trimmed string, required means non-empty. Templates: `<input type="text">` with the same `name`/`id` pattern. Request rule: `string|max:255`.
- `percent` gains optional `min`, `max`, `step` on the field definition (default today is 0.01–99.99 step 0.01 for the ownership share); the interest-rate field uses `min 0, max 20, step 0.01`. Templates bind the attributes from the field instead of literals.
- `money` gains optional `required: false` handling for optional amounts (already supported by `isValid()` — verify with a test).

Add cases to `tests/frontend/components/Fyn/FynCaptureForm.test.js`, copy the file to the `/m` spec unchanged (the two renderers share one contract), and to `CaptureFormsTest` for the rules.

**A4. The `_more` loop as a corpus pattern.** Nothing to generalise in PHP: each new `_more` state is a `bubbles` state with its own `nextFrom…More` branch, exactly as `campaign_property_more`. Three small methods.

## Part B — the three schemas

Canonical enums come from the tools, never invented: savings `account_type` from `CoordinatingAgent::SAVINGS_ACCOUNT_TYPES` (~line 136: `easy_access, notice, fixed, fixed_term, regular_saver, cash_isa, junior_isa, current_account`); investment `account_type` from the `Rule::in` at ~line 3163 (`stocks_shares_isa, lifetime_isa, innovative_finance_isa, personal_investment_account, …, isa, gia`) and `isa_type` (`stocks_and_shares, lifetime, innovative_finance`); ownership `individual, joint, tenants_in_common, trust` (forms offer individual and joint only; ISAs are individual by law — the investment tool rejects a joint ISA at ~line 3211, so the ISA form has no ownership field at all).

Tool input requirements to satisfy (from the validators): savings needs `account_name` (required) and `current_balance` (required), optional `institution`, `interest_rate` (0–20), `is_isa`, `isa_subscription_amount`, `ownership_type`, `ownership_percentage`; investment needs `account_name` (required) and `current_value` (required), optional `account_type`, `isa_type`, `provider`, `isa_subscription_current_year` (max `TaxDefaults::ISA_ALLOWANCE`), `ownership_type`, `ownership_percentage`. `account_name` is composed in `toolInputs()` as `"<provider> <kind label>"` (e.g. "Barclays current account") so the user never types a name.

### B1. `isa` form — state `campaign_isa_holdings`

Kinds (any combination): **Cash ISA** (`cash_isa`, tool `create_savings_account`, inputs `account_type: cash_isa, is_isa: true`), **Stocks and Shares ISA** (`stocks_shares_isa`, tool `create_investment_account`, inputs `account_type: stocks_shares_isa, isa_type: stocks_and_shares`), **Lifetime ISA** (`lifetime_isa`, investment tool, `isa_type: lifetime`), **Innovative Finance ISA** (`innovative_finance_isa`, investment tool, `isa_type: innovative_finance`).

Fields per kind: `provider` (text, required, "Who is it with"), `current_value` (money, required, "Current balance"), `paid_in_this_year` (money, optional, "Paid in this tax year", hint "Leave blank if none") mapped to `isa_subscription_amount` (savings) or `isa_subscription_current_year` (investment), and for Cash ISA only `interest_rate` (percent 0–20, optional). No ownership field. Summary line: "Cash ISA with Nationwide, balance £12,000, £4,000 paid in this year."

`form_prompt_text: "Now your ISAs."`. `skip_if` stays (`skipIfNoIsa`). `next: campaign_isa_more`; `campaign_isa_more` bubbles "Do you have another ISA to add?" — yes → `campaign_isa_holdings`, no → `campaign_bank_accounts` (today's `next`).

### B2. `savings` form — state `campaign_bank_accounts`

Kinds: **Current account** (`current_account`), **Easy access savings** (`easy_access`), **Fixed rate savings** (`fixed`), **Notice account** (`notice`). Regular saver and fixed-term are not offered (the typed path still accepts them). All kinds use `create_savings_account` with `account_type` = the kind key.

Fields per kind: `provider` (text, required), `current_value` (money, required, "Balance") mapped to `current_balance`, `interest_rate` (percent 0–20, required for savings kinds, optional for the current account), `ownership_type` (choice: Individual, Joint). Joint bank accounts are always 50/50 (CSJ ruling in memory `feedback_joint_ownership_defaults_fifty_fifty`): no share field; `toolInputs()` sends `ownership_percentage: 50` when joint. Summary: "Barclays current account, balance £3,200, joint."

`form_prompt_text: "Now your bank and savings accounts."` (the typed `prompt_text` builder `buildCampaignBankAccountsPrompt` stays for native). `skip_if` stays. `record_context`/`record_context_mode` stay for the typed path; the form only creates — existing rows are edited on the verify page. `next: campaign_bank_accounts_more`; bubbles "Do you have another account to add?" — yes → `campaign_bank_accounts`, no → `enterCampaignVerify($user, 'savings')` (today's closure).

### B3. `investment` form — state `campaign_investment_accounts`

Kinds: **General Investment Account** (`gia`, tool `create_investment_account`, `account_type: personal_investment_account`), **Other investment** (`other`, `account_type: other`). Bonds, VCT/EIS and share schemes stay on the typed path and the app forms.

Fields per kind: `provider` (text, required), `current_value` (money, required), `ownership_type` (choice: Individual, Joint), `ownership_percentage` (percent, default 50, `required_when` joint — the property pattern). Summary: "General Investment Account with Vanguard worth £45,000, individual."

`form_prompt_text: "Now your investments."`. `next: campaign_investment_accounts_more`; bubbles "Do you have another investment account to add?" — yes → `campaign_investment_accounts`, no → `enterCampaignVerify($user, 'investments')`.

## Part C — corpus and PHP changes, exactly

Corpus (`fyn-onboarding.v1.md`): on each of the three states set `turn_type: form`, add `form: <name>` and `form_prompt_text: "<lead-in>"`, keep `prompt_text` (typed clients), change `next` to the new `_more` state; add the three `_more` states with `turn_type: bubbles`, `prompt_text`, `bubbles` (`yes` "Yes, add another" / `no` "No, that's everything"), `capture_field: null`, `next: { branch: nextFrom<Step>More }`.

PHP (`OnboardingStateMachine.php`): three constants, three states-table entries (`'next' => self::class.'::nextFrom…More'`), three branch methods (copy `nextFromPropertyMore`). Remove the `next` closures from the three capture states (their `next` is corpus data now, as `campaign_property`'s became — see the comment left there). `capture_focus`, `skip_if`, `record_context`, `prompt_text` builder stay.

`tests/Feature/Onboarding/CaptureStateToolCoverageTest.php` keeps the three states in `DELEGATED_STATE_TOOLS` (the typed path still delegates); check its `turn_type` enumeration accepts `form` (it did for property). `tests/Unit/Services/Onboarding/OnboardingStateMachineTest.php` `only uses known turn_types` already lists `form`. `PensioncheckStatesTest` and any walkthrough test that asserts the old `next` (`campaign_bank_accounts → campaign_verify_announce`, `campaign_isa_holdings → campaign_bank_accounts`) must be updated to the `_more` states.

## Part D — tests to write (mirror the property files)

- `CaptureFormsTest`: each schema's kinds and labels in order; `rules()` per kind; `toolInputs()` for one answer block per kind (assert the exact tool input array including `account_name`, enums, `ownership_percentage: 50` for joint bank, `is_isa`, `isa_type`); `summarise()` exact sentences; `names()` lists `property, isa, savings, investment`.
- A `<Step>CaptureFormTurnTest` per step (copy `PropertyCaptureFormTurnTest`): form turn emitted with the short prompt for a forms client and the typed prompt without; save one kind → row created through the right store, `entity_created` event, advance to the `_more` state; the `_more` yes/no; Free cap refusal (`count_caps`: `savings_account: 2`, `investment: 2` in `TierConfigurationSeeder`) shows on the refused box with the plan-limit message and the form stays open; a joint ISA never reaches the tool (the form has no ownership field — assert the schema); no fact parked from a form turn.
- `SendAiChatMessageFormRequestTest`: one 200 per new form name; a `text` field over 255 characters is a 422; an unknown kind is a 422.
- Frontend: extend `FynCaptureForm.test.js` (+ the `/m` copy) for `text` and the percent bounds; `aiChatCaptureForm.test.js` needs nothing new unless the event shape changes (it must not).

## Part E — verification on csjones (the release gate)

1. Deploy the branch to csjones (`./deploy/csjones-fynla/build.sh`, rsync `public/build/` and `public/m-build/`, `git checkout <branch>` on the server, cache clears ending `config:cache`, `php artisan fyn:procedural:validate`).
2. A fresh Free user parked at each state (`onboarding_fyn_path campaign`, `onboarding_fyn_selection savetax`, `funnel_answers.assets` including `isa`, `bank`, `savings`, `investment` so the `skip_if` predicates pass; consent recorded). Mint a Sanctum token in tinker and inject it (web: `sessionStorage.auth_token`; `/m`: `localStorage.m_scaffold_token`) — registration and password entry are prohibited for the Chrome tool. A stale token in an open tab triggers a revoke-all on load: close old tabs before minting.
3. Per step, in CSJ's Chrome: the form renders with the short lead-in and the right boxes; required asterisks; Save gated; save one of each kind; the transcript line; the locked form; the `_more` question; Yes renders a fresh form; No reaches the verify page and the rows show the right provider, balance, rate and ownership on `/savings` and `/investment`; the Free cap refusal on the third account; a refused kind re-opens with its error. Then the same on `/m` (dashboard chat and the docked bar).
4. API checks that need no browser: `curl` the action endpoint with `continue` and no header → typed prompt, no `capture_form`; with the header → `capture_form` with the short `prompt_text`.
5. Pest: the new files plus `tests/Feature/Onboarding`, `tests/Unit/Services/Onboarding`, `tests/Feature/AI` once; Vitest once. Do not run the full suite per edit.

## Part F — decisions for CSJ before starting

1. The kinds offered per form (B1–B3 above are the proposal; the typed path keeps the full enum).
2. Bank joint accounts fixed at 50/50 with no share field (per the existing ruling) — confirm.
3. Interest rate required on savings kinds, optional on current accounts — confirm.
4. Whether "Paid in this tax year" appears on the ISA form (it feeds the ISA allowance figures on the Save Tax plan) — recommended yes.
5. Whether bonds, VCT/EIS and share schemes ever get a form (proposal: no, typed path and app forms).

## Rollout

Branch off `dev`; Part A as its own PR (property stays green); Parts B–D as one PR per step or one PR for all three (CSJ's call, one is faster); csjones gate per Part E; merge with `--admin` after the gate; release `dev → main` via `/release`; prod deploy = PHP + corpus + both bundles (the mixin changed), no migration; `fyn:procedural:validate` after; purge `c.jones@csjones.co` before reporting. Keep the ledger in `handover/<Month>/<DD>/sdd-account-forms/progress.md` with every ruling.
