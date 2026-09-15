# Onboarding — application map

| | |
|---|---|
| Scope | How a new account is set up: the Fyn-driven onboarding conversation (path choice, life-stage journeys, module focus, the base facts walk, verify loops, asset capture, completion, pause and resume), the legacy web wizard (`/onboarding/*` routes, life-stage, module and journey modes), the journeys and life-stage APIs, the onboarding progress trail, and how each of the three clients enters and renders the flow. Out of scope, mapped next as section 02b: the Save Tax and Pension Check campaigns (the public funnels, `campaign_*` states, section advice, synthesis, spouse invitation, the Tax Strategy terminal, campaign re-entry). Also out of scope: spouse linking itself (section 04), the recommendation engines the verify screens call (their modules), the AI chat plumbing beyond dispatch (section 14). |
| Commit | `e4ddc4e3f` on `dev` |
| Mapped on | 2026-09-14 |
| Mapped by | Claude Code session, `app-map` skill |
| Supersedes | none (INDEX row 02 split into 02a this file and 02b not started) |
| Issues raised | MB-23 to MB-36 (in `September/September14Updates/mappingBugs2026-09-14.md`) |

Every statement cites a file and line read this run, a command run this run, or a browser interaction performed this run. Where a claim could not be checked it says "I COULD NOT VERIFY".

## 1. Overview

### In plain English

When someone new arrives, Fyn, the assistant, sets them up by asking questions in the chat rather than through forms. Fyn speaks first: follow a life-stage journey, pick one money topic, or do something else. It then asks for date of birth and marital status, partner details if married, children, employment and income, and monthly spending, one question at a time, mostly with tap-to-answer buttons. After each block of answers Fyn takes the person to the page that now shows what they said, asks "does it look right?", and only then moves on. The chosen topic (savings, protection, and so on) is captured in free text, saved, shown, confirmed, and the person can add another topic or finish. Finishing marks the account as set up and lands them on the relevant page. A returning person is greeted with "Welcome back" and offered Continue or Something else.

An older form-based wizard still exists at `/onboarding`. It is reached only by people who register without following a "start with Fyn" link, and it has five internal modes that share one screen. One of those modes renders empty steps, several of its server routes have nothing that calls them, and the column that decides which mode you see is written by three unrelated features.

Fourteen problems were found. The two that matter most: a person who taps "Something else" or types at the front door, then asks Fyn anything, gets no reply at all, because their message is sent back to the onboarding machine which no longer has a step; and on the mobile web version the "Something else" button itself answers "This step cannot be skipped." Paused onboarding can never be resumed except on the campaign path. On the web, the expenditure check page showed £0 the moment after Fyn had saved £2,500.

### How it fits together

All three clients drive one backend state machine through two endpoints: `POST /api/ai-chat/onboarding/start` (Fyn's first turn) and `POST /api/ai-chat/conversations/{id}/messages` (every answer), plus `POST .../{id}/action` for Continue, Something else, Skip and Restart. A resolver decides per message whether the writing onboarding director or the read-only advice Fyn handles it. The director reads a table of states whose text and buttons live in a corpus file and whose branching lives in PHP; it calls the language model only for the "grouped extract" questions (date of birth and marital status, partner, dependants, work) and the free-text asset capture. Answers are written to the `users` row, family members, an expenditure profile, and the module tables; every completed state is logged to `onboarding_progress`. The web wizard uses a separate controller and service that write the same `users` columns through `POST /api/onboarding/step`, with a life-stage API for the picker and a journeys API that nothing calls.

### Flow diagrams

- `docs/diagrams/map-onboarding-request-flow.excalidraw` — the four surfaces, their routes, the dispatch resolver, the director and advice Fyn, the helper services and the tables written.
- `docs/diagrams/map-onboarding-fyn-base-walk.excalidraw` — every state on the journey and focus paths from the front door to done, including the verify loops and the pause exits.
- `docs/diagrams/map-onboarding-start-resume-pause.excalidraw` — how each surface starts onboarding, the `/start` gates, the welcome-back resume, and the two pause dead ends.
- `docs/diagrams/map-onboarding-wizard-modes.excalidraw` — the legacy wizard's five modes behind one component, the column that overrides the route, and the journey mode that renders blank.

### What it looks like

All screenshots were taken this run on the local build with four brand-new accounts: `map-onb-2026-09-14@example.com` (user 86, web), `map-onb-m-2026-09-14@example.com` (user 87, `/m`), `map-onb-m2-2026-09-14@example.com` (user 88, `/m` pause check), `map-onb-w2-2026-09-14@example.com` (user 89, web returning-user check).

![Fyn front door](screenshots/02-onboarding/web-fyn-path-choice.png)
*Web: Fyn's first turn in the 712-pixel docked chat. Three buttons; the dashboard is blurred behind.*

![Journey selection](screenshots/02-onboarding/web-fyn-journey-selection.png)
*Web: the five life-stage journeys after "Follow a journey".*

![Profile review pause](screenshots/02-onboarding/web-fyn-profile-review-pause.png)
*Web: the chat narrows to 356 pixels and the Settings page is pushed behind it for "Does your family and personal information look right?".*

![Income verify announce](screenshots/02-onboarding/web-fyn-income-verify-announce.png)
*Web: after the income block Fyn announces the page it will open and waits for Okay.*

![Income verify navigate](screenshots/02-onboarding/web-fyn-income-verify-navigate.png)
*Web: the Income page shows the £75,000 just captured; the chat asks "does it look right?".*

![Expenditure verify shows zero](screenshots/02-onboarding/web-fyn-expenditure-verify-navigate.png)
*Web: the Expenditure page shows £0 while Fyn has just said "Recorded monthly spending of £2,500" (MB-27).*

![Welcome back](screenshots/02-onboarding/web-fyn-welcome-back-resume.png)
*Web: a reload mid-walk gives the resume greeting with Continue and Something else.*

![Asset capture](screenshots/02-onboarding/web-fyn-asset-capture-protection.png)
*Web: the protection capture turn, the saved-record card and the verify announcement.*

![Add more](screenshots/02-onboarding/web-fyn-add-more.png)
*Web: after the protection page is confirmed, the remaining topics and "I'm done".*

![Done](screenshots/02-onboarding/web-fyn-done.png)
*Web: "All set, Map. Your protection module is ready to explore." with the navigation bubble.*

![Something else then a question](screenshots/02-onboarding/web-fyn-something-else-then-question-no-reply.png)
*Web: after "Something else", the typed question "What is an ISA?" gets no reply (MB-23).*

![Returning user gets advice chat](screenshots/02-onboarding/web-returning-not-started-user-gets-advice-chat.png)
*Web: a user who registered but never started onboarding (user 89) signs in and gets the advice greeting, not onboarding; the network log shows a plain conversation created and no call to the onboarding start route (MB-26).*

![Wizard welcome](screenshots/02-onboarding/web-wizard-welcome-life-stage-picker.png)
*Web wizard: the life-stage picker at `/onboarding`.*

![Wizard journey map](screenshots/02-onboarding/web-wizard-stage-journey-map.png)
*Web wizard: the nine-step "Protecting What Matters" walk, step one, with the sidebar and "Did you know".*

![Wizard estate module](screenshots/02-onboarding/web-wizard-module-estate-no-life-stage.png)
*Web wizard: module mode at `/onboarding/estate` with no life stage stored: Will then Trusts.*

![Wizard journey mode blank](screenshots/02-onboarding/web-wizard-journey-budgeting-no-life-stage.png)
*Web wizard: journey mode at `/onboarding/journey/budgeting` renders four step labels and no form (MB-31).*

![Journeys page](screenshots/02-onboarding/web-planning-journeys.png)
*Web: the Journeys page is permanently empty (MB-30).*

![Mobile auto-start](screenshots/02-onboarding/m-dashboard-onboarding-autostart.png)
*`/m`: a not-started user lands on the dashboard with Fyn open at the front door.*

![Mobile focus selection](screenshots/02-onboarding/m-fyn-focus-selection.png)
*`/m`: the eight module focuses.*

![Mobile spouse skip link](screenshots/02-onboarding/m-fyn-spouse-step-skip-link.png)
*`/m`: the partner question with its "Skip this for now" link.*

![Mobile level up](screenshots/02-onboarding/m-level-up-celebration-mid-onboarding.png)
*`/m`: the Level 2 celebration fired after the family review answer; it blocks the chat until dismissed.*

![Mobile expenditure verify](screenshots/02-onboarding/m-expenditure-verify-pills.png)
*`/m`: the Expenditure screen with the on-page Continue and Edit pills; the £1,800 just captured is shown.*

![Mobile dock resume](screenshots/02-onboarding/m-dock-resume-after-verify-continue.png)
*`/m`: tapping Continue reopens the dock with the whole transcript and the savings capture prompt.*

![Mobile savings verify](screenshots/02-onboarding/m-savings-verify-pills.png)
*`/m`: the Bank Accounts screen with the Halifax account and the verify pills (and the contradictory emergency-fund copy, MB-36).*

![Mobile done](screenshots/02-onboarding/m-fyn-done.png)
*`/m`: after "I'm done" the app lands on Cash and savings.*

![Mobile something else](screenshots/02-onboarding/m-fyn-something-else-then-question.png)
*`/m`: "Something else" answers "This step cannot be skipped." (MB-24); the typed question is then answered inline and the front door re-asked.*

### Surfaces

| Feature | Web | `/m` | iOS | Notes |
|---|---|---|---|---|
| Auto-start for a brand-new user | Working (only via `/dashboard?openFyn=journey` from registration) | Working (dashboard opens Fyn) | Unverified (code: `FynConversationModel.swift:159-193` calls status then `/start`) | Web driven this run for user 86; `/m` for users 87 and 88 |
| Returning not-started user offered onboarding | Dead end (MB-26) | Working | Unverified (same code path as above would start it) | Web users 86 and 89; `/m` users 87 and 88 |
| Front door: journey / focus | Working | Working | Unverified | |
| Front door: Something else | Working (pauses) | Broken (MB-24) | Unverified | Web verified user 86; `/m` user 88 |
| Free text or question at the front door | Unverified on web (pauses per code `OnboardingChatDirector.php:457-476`) | Working: question answered inline, front door re-asked | Unverified | `/m` user 88 |
| Base facts (personal, spouse, dependants, review, employment, work, retirement date, expenditure) | Working | Working | Unverified | Web: single, full-time; `/m`: married with spouse skipped, retired |
| Profile-review pause layout | Does not make sense (MB-28) | Working (bubble only, no navigation) | Unverified | |
| Verify loop (announce, navigate, confirm) | Working for income and protection; Broken for expenditure (MB-27) | Working for expenditure and savings | Unverified | |
| Asset capture (LLM turn, record card) | Working (life policy) | Working (savings account) | Unverified | |
| Add more, done, navigation to the module | Working | Working | Unverified | |
| Welcome-back resume, Continue | Working | Working (dock reloads the transcript; the greeting fires on the dashboard per `onboardingChat.js:157-204`) | Unverified (`fireResume` at `FynConversationModel.swift:167-170`) | |
| Message after pause | Broken (MB-23) | I COULD NOT VERIFY (MB-24 blocks the tap) | Unverified | Backend is shared |
| Resume a paused journey/focus walk | Dead end (MB-25) | Dead end (MB-25) | Dead end by code (`/start` branch is server-side) | |
| Legacy wizard (life-stage, module modes) | Working | Not present | Not present | Wizard is web only |
| Legacy wizard journey mode | Broken (MB-31) | Not present | Not present | |
| Journeys page | Dead end (MB-30) | Not present | Not present | |

### Depends on / depended on by

| Direction | Module or service | What crosses the boundary | Evidence |
|---|---|---|---|
| Consumes | Auth and registration (section 01) | the `from=` query and consents; `/start` and every message require `ai_chat` consent | `Register.vue:504-517`; `AiChatController.php:204-209, 625-630` |
| Consumes | Fyn chat plumbing (section 14) | conversations, SSE stream, `FynLoop`, tool catalogue, queue lock | `AiChatController.php:232-274`; `OnboardingChatDirector.php:2756-2766` |
| Consumes | Spouse linking (section 04) | `SpouseLinkingService`, `HouseholdProvisioner` when a spouse is captured | `OnboardingChatDirector.php:125, 2300-2323`; `OnboardingService.php:344` |
| Consumes | Stores and normalisers | savings, investment, mortgage, liability, property stores on the wizard assets branch | `OnboardingService.php:18-26, 565-703` |
| Consumes | Tax configuration | tax year for ISA subscription fields; estate estimates | `OnboardingService.php:658, 696`; `EstateOnboardingFlow.php:259` |
| Consumes | Prerequisite gates | "does this section have data" before a verify | `OnboardingStateMachine.php:1157-1182` |
| Consumes | Gamification | points per completed state; level-up frame after actions | `OnboardingChatDirector.php:6657-6663`; `AiChatController.php:961-965` |
| Consumed by | Mobile dashboard | cache cleared on every step change | `app/Observers/UserOnboardingStepObserver.php:24-29` |
| Consumed by | Every module screen used as a verify destination | `/income`, `/expenditure`, `/savings`, `/investment`, `/retirement`, `/protection`, `/estate`, `/goals` | `OnboardingStateMachine.php:280-312`; web redirects `router/index.js:678-679, 893-905` |
| Consumed by | Advice Fyn | reads `onboarding_completed`, `onboarding_fyn_step`, `active_campaign` on every message | `ConversationModeResolver.php:12-29` |

## 2. Detailed sections

### 2.1 Entry: how each client starts onboarding

**In plain English.** Registration with a "start with Fyn" link lands on the dashboard with Fyn open and speaking. On the mobile web version the dashboard opens Fyn for anyone who has not finished setting up. On the iPhone the dock asks the server whether onboarding is in progress and starts it if there is no conversation yet. Plain registration on the web goes to the form wizard instead.

**Status:** Working on web (via the registration query) and `/m`; Dead end on web for a returning not-started user (MB-26); Unverified on iOS — **Evidence:** web user 86 at `/dashboard?openFyn=journey` received the front door; `/m` users 87 and 88 received it on the dashboard; iOS code read at `FynConversationModel.swift:144-199`, not executed.

**How it works**

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Register | `resources/js/views/Register.vue:504-517` | `from=<id>` → `/dashboard?openFyn=journey&from=<id>`; `stage=` → `/onboarding?stage=`; neither → `/onboarding` |
| 2 | Web dashboard | `resources/js/views/Dashboard.vue:1377-1406` | reads `openFyn=journey`, strips the query, dispatches `aiChat/startOnboardingConversation({from})`, blurs the dashboard until the first interaction |
| 3 | Web store | `resources/js/store/modules/aiChat.js:1376-1442` | `GET /ai-chat/onboarding/status`; in progress → `postAction('resume')`; else `POST /onboarding/start` (503, 409, 403 fall back to a normal chat) |
| 4 | `/m` dashboard | `resources/mobile/views/Dashboard.vue:919-944, 983-988` | `initFyn()` calls `startOnboarding(from)` when `onboardingActive`, `onboardingNeedsStart`, or `?from=` |
| 5 | `/m` mixin | `resources/mobile/mixins/onboardingChat.js:53-65, 164-204` | active = not completed (or campaign) and step set; needs-start = not completed, step null, not paused; streams `/start` |
| 6 | iOS | `ios-native/Fynla/Features/Fyn/FynConversationModel.swift:159-193` | status → resume greeting once per session and load; else first conversation; else `/start`; a 409 maps to `busy` and creates a plain conversation (`FynClient.swift:268-269`) |
| 7 | Controller | `app/Http/Controllers/Api/AiChatController.php:618-885` | consent gate; 409 `already_completed` (unless a re-entry campaign); 503 flag off; 403 preview; `resume` event if a step exists; else picks `path_choice`, `base_personal` (journey map) or a campaign entry, saves `onboarding_started_at`, creates the conversation with `metadata.source = fyn_onboarding`, streams `emitFirstTurn` |
| 8 | Config | `config/onboarding.php:34, 57-62, 79-86` | `fyn_flow_enabled` (env), `journey_map` (budgeting, goals, protection, retirement), `campaign_map` (02b) |

**Diagram:** `docs/diagrams/map-onboarding-start-resume-pause.excalidraw`

**CRUD**

| Operation | Method and endpoint | Who may call it | Writes | Returns | Evidence |
|---|---|---|---|---|---|
| Start | `POST /api/ai-chat/onboarding/start` | signed-in, `ai_chat` consent, not preview, flag on | `users.onboarding_fyn_step/path/selection/started_at`, one `ai_conversations` row | SSE: `conversation_created` then the first turn; or `resume`; or JSON 409/503/403 | `AiChatController.php:618-885`; `PreviewWriteInterceptor.php:78` |
| Status | `GET /api/ai-chat/onboarding/status` | signed-in | none | `{in_progress, current_step, path, selection, conversation_id}` | `AiChatController.php:894-900`; `OnboardingChatDirector.php:905-933` |

**Tests**

| Test file | Covers | Run this session | Result |
|---|---|---|---|
| `tests/Feature/Onboarding/StartOnboardingEndpointTest.php` | `/start` gates and first turn | yes | part of the 996 passed below |
| `tests/Feature/Onboarding/EntrySourceJourneyMapTest.php` | `from=` journey mapping | yes | passed |
| `tests/Feature/Onboarding/OnboardingResumeTest.php`, `ResumeAfterDisconnectTest.php` | resume greeting and continue | yes | passed |

### 2.2 Dispatch: which Fyn answers

**In plain English.** Every message a person sends goes to one place, which decides whether the onboarding machine (the only part of Fyn allowed to write) or the read-only advice Fyn should answer. The person never sees the switch.

**Status:** Working for the walk; Broken for a paused user (MB-23) — **Evidence:** `ConversationModeResolver.php:12-29`; web conversation 187 rows 492-494 this run.

**How it works**

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Controller | `AiChatController.php:198-274` | consent gate; per-conversation lock (queue on collision); `routesToOnboarding()` |
| 2 | Resolver | `app/Services/AI/ContextualConversation/ConversationModeResolver.php:12-29` | flag off → advice; `source = surface_action` → advice; `source = fyn_onboarding` → onboarding (before reading the step); else (not completed or campaign) and step set |
| 3 | Director | `OnboardingChatDirector.php:158-594` | saves the user message, parks facts, handles pending challenges, routes by `turn_type` (`delegated`, `grouped_extract`, bubbles/free text), persists, advances |
| 4 | Advice | `app/Services/AI/AdviceFyn` | read-only tools (section 14) |

The canonical contract (`fyn-architecture` skill) says the write state requires not-completed, step set, and the flag. The resolver adds the conversation-source shortcut at step 2, which is what breaks the pause exits (MB-23). Tests: `tests/Feature/AI/ContextualConversationDispatchTest.php:145-165` asserts the shortcut for a completed user's onboarding conversation.

### 2.3 The state machine and its corpus table

**In plain English.** The whole conversation is a list of named steps. Each step knows what Fyn says, what buttons to show, which column to write, and where to go next. The wording and buttons are kept in a text file that can be edited without touching code; the branching rules stay in code.

**Status:** Working — **Evidence:** `tests/Unit/Services/Onboarding/OnboardingStateMachineTest.php`, `OnboardingWorkflowTableTest.php`, `OnboardingWorkflowTableGoldenMasterTest.php` passed this run; both walks this run followed the table.

**How it works**

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Corpus | `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` (412 lines) | one YAML block: 50 states with `turn_type`, `prompt_text`, `bubbles`, `capture_field`, `value_parser`, `extraction_tool`, `retry_text`, `next` |
| 2 | Loader | `app/Services/Onboarding/OnboardingWorkflowTable.php:28-60` | parses the block; returns null on any fault so the in-code table wins |
| 3 | Merge | `OnboardingStateMachine.php:764-848` | corpus data over in-code PHP fields (`next` callables, `prompt_text` builders, `skip_if`); the two state lists must match or the shipped file is read; mismatch throws |
| 4 | Transitions | `OnboardingStateMachine.php:861-950` | static next, `Class::method` next, or closure; `applySkipRules` follows `skip_if` transitively (depth 20) |
| 5 | Bubble matching | `OnboardingStateMachine.php:2415-2450` | label, id, then substring |
| 6 | Interpolation | `OnboardingStateMachine.php:2455-2492` | `{first_name}`, `{selection}`, `{path}` |

**Turn types** (`OnboardingStateMachine.php:35-51`): `bubbles`, `free_text`, `grouped_extract` (one narrow LLM tool), `delegated` (LLM with a tool set), `advice` (auto-advancing, campaign), `terminal`.

**States on the journey and focus paths** (corpus lines 20-135, 380-412; in-code overrides `OnboardingStateMachine.php:378-445, 723-739`):

| State | Turn type | Writes | Next |
|---|---|---|---|
| `path_choice` | bubbles (journey, focus, skip) | `onboarding_fyn_path` (the bubble id, so `skip` too — MB-32) | `nextFromPathChoice` (`:954-966`) |
| `journey_selection` | bubbles (5 journeys) | `onboarding_fyn_selection`, `visited_focuses` | `base_personal` |
| `focus_selection` | bubbles (8 modules) | same | `base_personal` |
| `base_personal` | grouped_extract `capture_personal_details` | `date_of_birth`, `marital_status` | `nextFromPersonal` (`:976-992`): stays until both set; married → spouse; else dependants. Skipped when both already set (`:1659-1662`) |
| `base_spouse` | grouped_extract `capture_spouse_details`, skip link | spouse user and link (section 04) | `base_dependants` |
| `base_dependants` | bubbles Yes/No | `onboarding_fyn_context.has_dependants` | detail or review (`:994-1003`) |
| `base_dependants_detail` | grouped_extract `capture_dependants` | `family_members` | `profile_review_family` |
| `profile_review_family` | bubbles (Looks correct), layout `standard` | nothing | `base_employment` |
| `base_employment` | bubbles (5) | `employment_status` (skipped if set) | work / retirement date / expenditure (`:1281-1297`) |
| `base_work` | grouped_extract `capture_work_details` | employer, occupation, income | `base_employment_more` |
| `base_employment_more` | bubbles | nothing | Yes → `base_employment`; No → verify income if any, else expenditure (`:1249-1268`) |
| `base_retirement_date` | free text `parseRetirementDate` | `retirement_date` | expenditure (`:1320-1327`) |
| `base_expenditure` | free text `parseExpenditureAmount` | `monthly_expenditure`, `expenditure_entry_mode = simple`, `expenditure_profiles.total_monthly_expenditure` (`OnboardingChatDirector.php:2265-2285`) | verify expenditure if data, else asset capture (`:429-445`) |
| `campaign_verify_announce` | bubbles (Okay) | `onboarding_fyn_context.verify_section/origin` | `campaign_verify_navigate` |
| `campaign_verify_navigate` | bubbles (Yes / No) plus a `navigation` event | nothing | Yes → continue by origin (`:1073-1148`); No → `campaign_verify_edit` |
| `campaign_verify_edit` | delegated update turn | the section's records | back to navigate |
| `asset_capture` | delegated, tools by focus (`OnboardingPromptBuilder.php:103-169`) | module records | verify the module if it has data, else `add_more` (`:1127-1134`) |
| `add_more` | bubbles (unvisited focuses + I'm done) | `onboarding_fyn_selection`, `visited_focuses` | `asset_capture` or `done` (`:1613-1627`) |
| `free_chat` | terminal | step nulled, progress `paused` | none |
| `done` | terminal | `onboarding_completed`, `completed_at`, all `onboarding_fyn_*` cleared, `active_campaign` null | none; navigates by selection (`OnboardingChatDirector.php:6620-6634`) |

The verify states are named `campaign_*` but serve the journey path too (`OnboardingStateMachine.php:289-295, 1101-1148`, CSJ 2026-07-24 note in code).

### 2.4 The base facts walk (personal, family, work, spending)

**In plain English.** Fyn collects who you are, who depends on you, how you earn and what you spend. Where you have already volunteered a fact earlier in the conversation, Fyn does not ask again.

**Status:** Working on web and `/m` — **Evidence:** web user 86: single, no dependants, full-time at ACME Ltd £75,000, £2,500 a month; `/m` user 87: married (spouse skipped), retired 2020, £1,800 a month. Database after each walk: user 86 `dob=1985-01-12 marital=single emp=full_time employer=ACME Ltd occ=engineer income=75000.00 monthly=2500.00`; user 87 `marital=married emp=retired retirement_date=2020-01-01 monthly=1800.00`. Progress rows 13-25 (user 86) and the equivalent for user 87 record every state.

**How it works**

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Fact parking | `app/Services/Onboarding/OnboardingFactExtractor.php:109-130, 164-357` | regex on every user message parks personal, spouse, dependants, employment and expenditure facts into `ai_conversations.onboarding_parked_facts` (never writes users.*) |
| 2 | Hydration | `OnboardingChatDirector.php:399-406, 2488-2693` | if parking already has what the grouped tool would collect, the fields are applied without a model call — both walks this run recorded `base_personal {"hydrated_from_parking":true}` |
| 3 | Grouped extract | `OnboardingChatDirector.php:2693-2975` | one extraction tool from `fyn-memory/procedural/tool_schema/onboarding/*.md`, provider-shaped (`AiToolDefinitions::onboardingExtractionTools`); model prose is swallowed; no capture → interruption dispatch or retry; partial → ask for the missing field; then income challenge and short-DOB confirm checks, then advance |
| 4 | Bubble and free text | `OnboardingChatDirector.php:412-594` | `interpretAnswer` (`:1557-1628`) via `matchBubble` or `OnboardingValueInterpreter` parsers (`OnboardingValueInterpreter.php:46-391`); unparsed → interruption (question answered inline or deferred; volunteered record offered a store) or retry; `persistCapture` (`:2174-2288`); ack; `onboarding_advance`; next turn |
| 5 | Spouse | `OnboardingChatDirector.php:2300-2323` | a `family_members` spouse row with a household id; the linked account is section 04 |
| 6 | Dependants | `OnboardingChatDirector.php:2329-2369` | only explicit dates of birth become rows; relationship by age |
| 7 | Questions mid-walk | `OnboardingChatDirector.php:1635-1700, 1862-1901` | simple question → answered inline by advice Fyn's read-only tools, then the step is re-asked (seen live on `/m`: "What is an ISA?"); holistic question → promise now, raised at completion (`:5590-5662`) |
| 8 | Profile review | `AppLayout.vue:318-345`; corpus `profile_review_family.layout: standard` | web narrows the chat and pushes `/profile` (MB-28); `/m` shows the bubble only |

**Fyn touchpoints**

| Tool or intent | Prompt or catalogue file | What it reads or writes | Evidence |
|---|---|---|---|
| `capture_personal_details` | `fyn-memory/procedural/tool_schema/onboarding/capture_personal_details.md` | `users.date_of_birth`, `marital_status`; partial payloads allowed | corpus `base_personal`, `campaign_dob` |
| `capture_spouse_details` | `.../capture_spouse_details.md` | creates and links the spouse account | corpus `base_spouse` |
| `capture_dependants` | `.../capture_dependants.md` | `family_members` | corpus `base_dependants_detail` |
| `capture_work_details` | `.../capture_work_details.md` | employer, occupation, income | corpus `base_work` |
| grouped-extract system prompt | `OnboardingChatDirector.php:3560-3620` | narrow instruction per state | read |
| asset-capture prompt | `app/Services/Onboarding/OnboardingPromptBuilder.php:49-93, 180-186` | identity, compliance, `FynCaptureTurnInstructions`, known facts | read |

**Tests**

| Test file | Covers | Run this session | Result |
|---|---|---|---|
| `tests/Feature/Onboarding/StateMachineWalkthroughTest.php` | the base walk end to end | yes | passed |
| `tests/Feature/Onboarding/FactParkingTest.php`, `ParkedFactsFlushTest.php` | parking and flushing | yes | passed |
| `tests/Feature/Onboarding/OnboardingInterruptionTest.php`, `ClarificationAnswerRescueTest.php` | questions and volunteered records mid-walk | yes | passed |
| `tests/Feature/Onboarding/SpouseSkipTest.php`, `ChildrenDOBFallbackTest.php`, `ShortDobConfirmFlowTest.php`, `MultiJobCaptureTest.php`, `ProfileReviewPauseTest.php`, `RetractionTest.php` | individual steps | yes | passed |
| `tests/Unit/Services/Onboarding/OnboardingValueInterpreterTest.php`, `OnboardingFactExtractorTest.php`, `CaptureAckTest.php`, `AckDedupeTest.php` | parsers, extractor, acks | yes | passed |

### 2.5 The verify loop

**In plain English.** After each block of answers Fyn says it will open the page that shows them, waits for Okay, opens it, and asks whether it looks right. Yes moves on; No opens an edit conversation. Every data entry verifies, on both paths.

**Status:** Working on web (income, protection) and `/m` (expenditure, savings); Broken on web for expenditure (MB-27) — **Evidence:** screenshots listed in § 1; `/m` `MobileChrome.vue:50-56, 370-395` renders Continue and Edit pills that send the same answers.

**How it works**

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Enter | `OnboardingStateMachine.php:1025-1042` | stamps `verify_section` and `verify_origin` into `onboarding_fyn_context`; goes to announce |
| 2 | Announce | corpus `campaign_verify_announce`; `OnboardingStateMachine.php:1197-1207` | "I've saved your {section}. Next I'll take you to your {section} page … tap Okay" |
| 3 | Navigate | `OnboardingChatDirector.php:1027-1048`; routes `OnboardingStateMachine.php:280-312` | emits `navigation {route_path, section}`; web `aiChat.js:579-599` sets a pending route; `/m` `onboardingChat.js:377-408, 711-742` closes the dock and routes, dropping the confirm bubble so the destination re-shows it |
| 4 | Confirm | `OnboardingStateMachine.php:1073-1099` | journey path continues by origin (`:1137-1148`); No → edit |
| 5 | Edit | `OnboardingChatDirector.php:4173-4392` (read: entry and routing only) | delegated update-only turn; I COULD NOT VERIFY the edit branch live |

### 2.6 Pause, resume and the front-door exits

**In plain English.** Coming back later gives "Welcome back … Continue or Something else". Something else parks the walk. At the front door, Something else or any typed sentence that Fyn cannot place also parks it. The person is meant to be able to ask Fyn anything afterwards and to come back to the walk; neither works.

**Status:** Resume Working; message after pause Broken (MB-23); `/m` front-door Something else Broken (MB-24); re-entry Dead end (MB-25) — **Evidence:** § 3.

**How it works**

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Resume | `OnboardingChatDirector.php:667-726` | prunes earlier greetings, saves one with Continue / Something else as action bubbles, `describeStep` (`:856-898`) |
| 2 | Continue | `:628-645` | re-emits the saved state with `reprompt_text` when one exists (`:977-980`) |
| 3 | Something else (action) | `:779-805` | `paused_at_step` into context, step null, `active_campaign` null, "Of course — what can I help you with?" |
| 4 | Something else (front door bubble) | `nextFromPathChoice` → `free_chat`; `emitFreeChatTurn` (`:6536-6558`) | "No problem. What would you like help with?", step null, progress `paused` |
| 5 | Free text at the front door | `:457-476` | step null, message handed to advice Fyn inline once |
| 6 | Next message | `ConversationModeResolver.php:23-25` → `OnboardingChatDirector.php:191-198` | back to the director, "Onboarding state lost", no `done`; web renders nothing |
| 7 | Restart | `:732-769` | deletes the conversation's messages, resets to `path_choice` (or null for a completed user) |
| 8 | Skip | `:812-850` | only `base_spouse` → `base_dependants` |

### 2.7 Completion

**In plain English.** "I'm done" closes the setup. Any question Fyn promised to come back to is raised first. The account is marked complete, the scratch columns are cleared, and the person is taken to the page for the topic they chose.

**Status:** Working on web and `/m` — **Evidence:** user 86 `completed=true at 13:41:39`, progress row 30 `done {"next_route":"/protection"}`; user 87 landed on `/m/app/net-worth/cash`; `tests/Feature/Onboarding/WizardCompletionTest.php` and `tests/Feature/AI/CampaignReentryExitTest.php` passed.

**How it works**

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Done turn | `OnboardingChatDirector.php:6560-6618` | deferred questions raised; celebration; `navigation` to `routeForSelection` (`:6620-6634`: savings → `/net-worth/cash`, investment → `/net-worth/investments`, retirement → `/net-worth/retirement`, protection → `/protection`, estate → `/estate`, family → `/valuable-info?section=letter`, business → `/net-worth/business`, goals → `/goals`, budgeting → `/dashboard`; more than one focus → `/dashboard`); `onboarding_complete`; user finalised; summariser job |
| 2 | Web | `aiChat.js:758-767` | clears the onboarding flag, sets pending navigation, refreshes dashboard data |
| 3 | `/m` | `onboardingChat.js:410-423, 711-742` | mirrors completion into the store user; routes if the screen exists |
| 4 | Wizard completion | `OnboardingService.php:1004-1048` | `finaliseOnboardingState` sets completed and clears every Fyn routing column in one transaction |

### 2.8 The legacy web wizard

**In plain English.** A form-based set-up that predates Fyn. People reach it only if they register without a "start with Fyn" link. It first asks which life stage you are at, then walks a fixed set of forms for that stage. The same screen also has a module mode (a couple of forms for one topic), a journey mode (which renders empty), a "full" mode and a "quick" mode that cannot be reached.

**Status:** Life-stage mode Working; module mode Working; journey mode Broken and Dead (MB-31); quick mode Dead (MB-30); several routes Dead (MB-30); the deciding column is overloaded (MB-32) — **Evidence:** driven this run at `/onboarding`, `/onboarding/full`, `/onboarding/estate`, `/onboarding/journey/budgeting` with and without a stored life stage; `POST /api/onboarding/step` returned 200 and wrote gender, city and postcode for user 86.

**What it looks like** — see § 1 (wizard screenshots).

**How it works**

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Routes | `resources/js/router/index.js:455-527` | `/onboarding`, `/onboarding/welcome`, `/onboarding/journey/:journey`, `/onboarding/full`, six `/onboarding/<module>` routes; all `hideNavbar`, so the wizard is chrome-less by route meta (no `AppLayout`) |
| 2 | Views | `resources/js/views/Onboarding/OnboardingView.vue`, `OnboardingFullView.vue`, `OnboardingModuleView.vue:8-28` | thin wrappers passing `mode` and, for modules, a fixed step list (protection: policies; estate: will, trusts; investments/pensions/savings: assets; family: family) |
| 3 | Wizard | `resources/js/components/Onboarding/OnboardingWizard.vue:550-555` | life-stage mode whenever `lifeStage/currentStage` is set, regardless of route mode |
| 4 | Life-stage mode | `OnboardingWizard.vue:428-443, 805-983`; `resources/js/constants/lifeStageConfig.js` (steps at 82-90, 226-231, 387-396, 567-575, 746-753) | 5 stages × 5 to 9 steps; each step's component is one of the shared step forms; saves go to module services (`:896-983`) or, inside the step components, to `POST /api/onboarding/step`; finishing dispatches `onboarding/completeOnboarding` and refreshes net worth (`:837-851`) |
| 5 | Welcome | `resources/js/components/Onboarding/FocusAreaSelection.vue:469-502` | picking a stage calls `POST /api/life-stage/set` immediately; "Start" emits `stage-selected`; `focus-selected`/`selected` are declared but never emitted |
| 6 | Module mode | `OnboardingWizard.vue:1141-1146, 1336-1365` | steps from the route props; "Continue" past the last step goes to the dashboard without a completion call |
| 7 | Journey mode | `OnboardingWizard.vue:1267-1299, 1494-1506` | `GET /api/journeys/{j}/steps`, `POST .../start`; components resolved by name, eight of which do not exist |
| 8 | Controller | `app/Http/Controllers/Api/OnboardingController.php` | 11 routes at `routes/api.php:276-288`; `setFocusArea` accepts only `estate` (`:47`) |
| 9 | Service | `app/Services/Onboarding/OnboardingService.php` | `saveStepProgress` (`:115-147`) requires `life_stage`, writes the step's data to real tables (`:152-195`) and an `onboarding_progress` row; `skipStep` (`:908-943`); `skipToDashboard` (`:948-996`); `completeOnboarding`, `completeQuickOnboarding` (`:1004-1021`); `restartOnboarding` (`:1067-1088`) |
| 10 | Estate flow | `app/Services/Onboarding/EstateOnboardingFlow.php:19-196` | the only focus area with a defined step list (11 steps, ordered); trust step conditional on a rough estate estimate above the residence nil-rate band taper (`:236-241`, `EstateDefaults`) |
| 11 | Life-stage API | `app/Http/Controllers/Api/LifeStageController.php`; `app/Services/LifeStage/LifeStageService.php:35-70` | `set` writes `users.life_stage`; `progress` returns completed steps and a suggested transition; `complete-step` appends to `life_stage_completed_steps` |

**Form fields** — steps that post to `POST /api/onboarding/step` and what the service writes. Validation column: the endpoint validates only `step_name` and `data` (MB-33); the per-field rules live in each Vue form and were not verified server-side.

| Label shown | Field | Input type | Validation (Form Request line) | Column and type | Default | Surfaces |
|---|---|---|---|---|---|---|
| Middle Name | `middle_name` | text | none server-side | not written by `processPersonalInfo` (`OnboardingService.php:205-216`) | | web wizard |
| Date of Birth | `date_of_birth` | date | none | `users.date_of_birth` | null | web wizard |
| Gender | `gender` | select | none | `users.gender` | null | web wizard |
| Marital Status | `marital_status` | select (Single, Married, Divorced, Widowed; no civil partnership — MB-35) | none | `users.marital_status` enum | null | web wizard |
| Address Line 1 / 2, City, County, Postcode, Phone Number | `address_line_1`, `address_line_2`, `city`, `county`, `postcode`, `phone` | text | none | matching `users.*` | null | web wizard |
| Are you in good health? / Do you smoke? / Highest Education Level | `health_status`, `smoking_status`, `education_level` | select | none | matching `users.*`, only when non-empty (`:219-227`) | | web wizard |
| Employment status, occupation, employer, industry, registered blind | `employment_status`, `occupation`, `employer`, `industry`, `is_registered_blind` | select/text/checkbox | none | `users.*` (`:393-406`); zero defaults for the five income columns when absent | 0 | web wizard (`IncomeStep`) |
| Expenditure categories or a monthly total | 23 category keys, `monthly_expenditure`, `annual_expenditure`, `expenditure_entry_mode`, or `userData`/`spouseData` | numbers | none | `users.*` through `SharedExpenditure::shareOf` (`:499-545`), mirrored to a reciprocal spouse | 0 | web wizard (`ExpenditureStep`) |
| Country of birth, UK arrival date | `country_of_birth`, `uk_arrival_date`, `domicile_status`, `years_uk_resident`, `deemed_domicile_date` | text/date | none | `users.*` (`:240-252`) | null | web wizard (`DomicileInformationStep`) |
| Do you currently have a valid will? plus date and executors | `has_will`, `will_last_updated`, `executor_name` | radio/date/text | none | `wills` via `updateOrCreate` (`:355-383`) | `has_will` false | web wizard (`WillInfoStep`) |
| Trusts | `has_trusts` and details | radio | none | no `processStepData` branch; progress row only | | web wizard (`TrustInfoStep`) |
| Goal | `goal_type`, `name`, `target_date` and amounts | select/text/date | none | goal created by `goalsService` in the step; progress row only | | web wizard (`GoalSetupStep`) |

The Fyn flow's fields are single facts captured by tool, listed in § 2.3.

**CRUD**

| Operation | Method and endpoint | Who may call it | Writes | Returns | Evidence |
|---|---|---|---|---|---|
| Status | `GET /api/onboarding/status` | signed-in | none | completed, focus area (= `life_stage`), current step, skipped steps, progress percentage (estate only), mode, asset flags | `OnboardingController.php:27-39`; `OnboardingService.php:46-77` |
| Set focus area | `POST /api/onboarding/focus-area` | signed-in; `in:estate` | `life_stage`, `onboarding_focus_area`, `started_at`, `current_step` | focus, step | `:44-68`; **no caller** (MB-30) |
| Steps | `GET /api/onboarding/steps` | signed-in; 400 if no `life_stage` | none | estate steps, or `[]` for any other value | `:262-289`; `OnboardingService.php:1128-1143` |
| Step data | `GET /api/onboarding/step/{step}` | signed-in | none | saved step data, or a fallback from `users.*` for expenditure | `:243-257`; `OnboardingService.php:1220-1343` |
| Save step | `POST /api/onboarding/step` | signed-in; `step_name` string, `data` array; preview allowed (`PreviewWriteInterceptor.php:73`) | tables per branch; `onboarding_progress`; `onboarding_current_step` | progress, percentage, next step; tier-limit response on store caps | `:73-116`; `OnboardingService.php:115-195` |
| Skip step | `POST /api/onboarding/skip-step` | signed-in | `onboarding_progress` skipped, `onboarding_skipped_steps` | progress, next step | `:121-151` |
| Skip reason | `GET /api/onboarding/skip-reason/{step}` | signed-in; 400 if no `life_stage` | none | estate skip text or null | `:294-320` |
| Skip to dashboard | `POST /api/onboarding/skip-to-dashboard` | signed-in | marks remaining estate steps skipped; finalises | completed, skipped steps | `:156-173`; `OnboardingService.php:948-996` |
| Complete | `POST /api/onboarding/complete` | signed-in | finalises (completed, Fyn columns cleared) | completed, at | `:201-217`; `:1004-1009` |
| Complete quick | `POST /api/onboarding/complete-quick` | signed-in | mode `quick`, finalises | completed, mode, flags | `:178-196`; **unreachable** (MB-30) |
| Restart | `POST /api/onboarding/restart` | signed-in | deletes progress rows, resets wizard columns (not the Fyn columns) | completed, focus | `:222-238`; `:1067-1088`; **no caller** (MB-30) |
| Life stage | `POST /api/life-stage/set`, `GET /progress`, `GET /completeness`, `POST /complete-step` | signed-in; stage `in:` five ids | `users.life_stage`, `life_stage_completed_steps` | progress, module completeness | `LifeStageController.php:27-118` |

**Tests**

| Test file | Covers | Run this session | Result |
|---|---|---|---|
| `tests/Feature/Onboarding/WizardCompletionTest.php` | completion clears the Fyn columns | yes | passed |
| `tests/Feature/Onboarding/JourneyApiTest.php`, `JourneyFlowTest.php` | journey routes | yes | passed |
| `tests/Unit/Services/Onboarding/JourneyStateServiceTest.php`, `JourneyFieldResolverTest.php`, `DashboardPromptServiceTest.php` | journey services | yes | passed |

I COULD NOT FIND a Pest test for `OnboardingController`'s step, skip, focus-area or restart endpoints (`grep -rl "onboarding/step" tests` returned only the browser scenario files); the step endpoint was exercised live this run instead.

### 2.9 Journeys API and the Journeys page

**In plain English.** A server-side feature for choosing several "journeys", previewing what each asks for, showing "continue your journey" prompts on the dashboard, and a Journeys page. No client calls the selection, preview or prompt endpoints, so the page is always empty.

**Status:** Dead (routes) and Dead end (page) — MB-30 — **Evidence:** `JourneyController.php:26-176`; `JourneyStateService.php:14-42, 103-135`; `DashboardPromptService.php:14-140`; `JourneyFieldResolver.php:17-89, 357-502`; `/planning/journeys` this run showed "No Journeys Selected" for a user who had just completed a Fyn journey; `resources/js/views/Planning/PlanningJourneys.vue`, `resources/js/components/Dashboard/JourneyCard.vue`.

Live parts: `GET /api/journeys/{journey}/steps`, `POST .../start`, `POST .../complete` are called by the wizard's journey mode (`OnboardingWizard.vue:1367-1379, 1494-1506`), which itself is Broken and Dead (MB-31). `JourneyFieldResolver::BASE_DATA_STEPS` and `getFynPrompt` (`:322-355`) are read by the state machine for prompt copy.

### 2.10 Data

**In plain English.** Set-up state lives on the person's own row: whether they have finished, which step they are on, which path and topic they chose, a scratch-pad of context, plus the wizard's older columns. Every completed step is also written to a log table.

**Status:** Working — **Evidence:** live column list read from the database this run (`Schema::getColumns('users')`) and the migrations below.

| Table and column | Type (live) | Written by | Read by | Migration |
|---|---|---|---|---|
| `users.onboarding_completed` | tinyint not null default 0 | done turns, wizard finalise | dispatch, both clients, `/start` 409 | pre-existing |
| `users.onboarding_fyn_step` | varchar(50) null | `/start`, every director advance, pause, done | dispatch; `UserOnboardingStepObserver` clears the mobile dashboard cache on change (`:24-29`) | `2026_04_15_090000` |
| `users.onboarding_fyn_path` | varchar(20) null | `/start` (journey/campaign) and the `path_choice` capture (`journey`, `focus`, `skip` — MB-32) | verify and campaign branching | same |
| `users.onboarding_fyn_selection` | varchar(50) null | selection states, `add_more` | asset capture tools, done route, progress `focus_area` | same |
| `users.onboarding_fyn_context` | json null | verify section/origin, `has_dependants`, `visited_focuses`, `paused_at_step`, pending challenges | state machine, `/start` | same |
| `users.active_campaign` | varchar(32) null | campaign re-entry (02b); cleared on pause and done | dispatch | `2026_07_03_000001` |
| `users.onboarding_started_at`, `onboarding_completed_at` | timestamp null | `/start`, finalise | status | pre-existing |
| `users.onboarding_mode`, `onboarding_asset_flags` | enum(quick, full) null; json null | quick wizard only (unreachable) | status | `2026_03_07_100806` |
| `users.onboarding_focus_area`, `onboarding_current_step`, `onboarding_skipped_steps` | varchar(50) null; varchar(255) null; json null | wizard service | wizard status | `2026_03_07_200002` widened the enum; `2026_03_21_214000` made it varchar |
| `users.life_stage`, `life_stage_completed_steps` | varchar(20) null; json null | `LifeStageService`, `JourneyStateService::startJourney`, `OnboardingService::setFocusArea` (MB-32) | wizard mode, module completeness | pre-existing |
| `users.journey_states`, `journey_selections`, `dismissed_prompts` | json null | journeys API (dead) | journeys API | `2026_03_07_200001` |
| `ai_conversations.metadata.source = fyn_onboarding` | json | `/start` | dispatch shortcut, status lookup (`scopeOnboarding`, `AiConversation.php:95-98`) | |
| `ai_conversations.onboarding_parked_facts` | json null | `OnboardingFactExtractor` | hydration, prompts | `2026_04_22_000003` |
| `ai_messages.metadata.bubbles / skip_link / action_bubbles / onboarding_step / turn_intent` | json | every director turn | `/m` and iOS transcript renders, web resume | |
| `onboarding_progress` | `id, user_id, focus_area, step_name, step_data, completed, skipped, skip_reason_shown, completed_at` | director `recordProgress` (`focus_area` = selection or `__setup__`) and wizard | admin `UserOnboardingProgress.vue` (mounted in `UserManagement.vue:146`); wizard progress percentage | `2026_03_07_200002`, `2026_03_21_214000` |

**Background machinery**

| Kind | Name | Trigger | Effect | Evidence |
|---|---|---|---|---|
| Observer | `UserOnboardingStepObserver` | `users.onboarding_fyn_step` changes | clears the mobile dashboard cache | `app/Observers/UserOnboardingStepObserver.php`; registered `AppServiceProvider.php:222` |
| Job | `ConversationSummariserJob` | done turn | summarises the onboarding conversation | `OnboardingChatDirector.php:6617` |
| Points | `PointsService::award` | every `recordProgress` | one award per state id | `OnboardingChatDirector.php:6657-6663`; the Level 2 celebration seen on `/m` |
| Lock | `Cache::lock('fyn:inflight:{conversation}')` | every message | queues a second message while one streams | `AiChatController.php:232-249` |

### 2.11 Tests for this section

Command run this session:

```
./vendor/bin/pest tests/Feature/Onboarding tests/Unit/Services/Onboarding \
  tests/Feature/AI/OnboardingStartCampaignMapTest.php tests/Feature/AI/CampaignReentryDispatchTest.php \
  tests/Feature/AI/CampaignReentryExitTest.php tests/Feature/AI/CampaignReentryStartTest.php \
  tests/Feature/AI/CampaignAuditFixesTest.php tests/Feature/Fyn/UnifiedPromptOnboardingSeamTest.php \
  tests/Feature/Auth/FunnelAnswersCaptureTest.php tests/Feature/Auth/CampaignRegistrationHandoffTest.php \
  tests/Feature/Gamification/OnboardingAnswerAwardTest.php tests/Unit/Services/Mobile/MilestoneJourneyTest.php
Tests:    996 passed (3618 assertions)   Duration: 256.62s
```

```
npx vitest run resources/mobile/mixins/__tests__/onboardingChat.spec.js tests/frontend/mobile/onboardingChatEvents.test.js
Test Files  2 passed (2)   Tests  31 passed (31)
```

Browser scenario contracts read, not run: `tests/Browser/scenarios/BS-01-onboarding-path-choice-to-done.php` (the journey walk; matched by this run's web walk), `BS-05-journey-map-by-entry-source.php`, `BS-07-dispatch-flips-after-onboarding.php`. `tests/E2E/journeys/user-reported-campaign-regressions.spec.js` is campaign (02b).

Tests written in this run: `tests/Feature/Onboarding/PausedUserMessageRoutesToAdviceTest.php` — one contract test for MB-23, marked skipped with the MB id so it does not go red before the fix; it ran as "1 skipped".

## 3. Findings

| Id | Status | Where | What is wrong | Evidence |
|---|---|---|---|---|
| MB-23 | Broken | `ConversationModeResolver.php:23-25`; `OnboardingChatDirector.php:191-198` | a paused user's next message routes to the director by conversation source, which has no step; web shows nothing | conversation 187 rows 492-494; screenshot; resolver probe |
| MB-24 | Broken (`/m`) | `resources/mobile/mixins/onboardingChat.js:575` | front-door "Something else" (id `skip`) is sent as the skip action → "This step cannot be skipped." | user 88 live; screenshot |
| MB-25 | Dead end | `AiChatController.php:648-658`; `AiChatPanel.vue:1049-1053`; `onboardingChat.js:61-65` | paused journey/focus walks cannot be resumed on any surface | code trace |
| MB-26 | Dead end (web) | `AiChatPanel.vue:1046-1061`; `Dashboard.vue:1377` | web never offers onboarding to a returning not-started user; `/m` does | user 86 live; screenshot |
| MB-27 | Broken (web) | `aiChat.js:579-599`; `ExpenditureForm.vue:2248` | expenditure verify page shows £0 from stale store data | screenshot; database read |
| MB-28 | Does not make sense (web) | `AppLayout.vue:330-345`; `router/index.js:680-690` | profile-review pause pushes `/profile` (→ Settings) and never returns | URL stayed `/settings/personal` |
| MB-29 | Dead (latent Broken) | `OnboardingService.php:565, 152-195` | unimported `PropertyNormaliser`; five step branches with no client | `class_exists` probe; step components' save calls |
| MB-30 | Dead | `routes/api.php:278,286,287,292-296`; `FocusAreaSelection.vue:481` | seven routes without callers; quick mode unreachable; Journeys page always empty | grep; live page |
| MB-31 | Broken + Dead | `OnboardingWizard.vue:1273-1299, 1320` | journey mode names eight components that do not exist | live blank steps; file list |
| MB-32 | Does not make sense | `LifeStageService.php:41-43`; `JourneyStateService.php:73-76`; `OnboardingService.php:86-88`; `OnboardingWizard.vue:550-555` | `life_stage` written with three vocabularies and overriding every wizard route; `onboarding_fyn_path` gets `skip` | live route comparison; user 86 row |
| MB-33 | Does not make sense | `OnboardingController.php:75-78` | step endpoint writes user columns unvalidated | code |
| MB-34 | Does not make sense | `lifeStageConfig.js` (46 matches) | hardcoded tax figures in wizard copy | grep; live "£1 million" copy |
| MB-35 | Does not make sense | `FamilyInfoStep.vue:3`; Personal Information select | "Dependents"; civil partnership missing | live snapshot |
| MB-36 | Does not make sense (adjacent, Savings) | `/m/app/savings` | contradictory expenditure copy on one screen | screenshot |

None found in this run: Duplicate (the web and `/m` chat clients share the one SSE contract and the backend; the `/m` mixin is the single mobile client for both the dashboard and the dock).

Observations not raised (noted for the reader): the wizard routes are chrome-less by `hideNavbar` meta rather than wrapped in `AppLayout`; the web chat leaves the front-door bubbles clickable after a content-only reply while later turns disable them; the iOS client maps a 409 from `/start` to "busy" and silently opens a plain conversation (`FynClient.swift:268-269`, `FynConversationModel.swift:187-192`).

## 4. Coverage and gaps

| Area | Checked | I COULD NOT VERIFY |
|---|---|---|
| Files in perimeter | 86 listed (`git ls-files` filtered to onboarding, journeys, life stage, the chat entry points, the four tool schemas, the corpus, three iOS files). Read in full: the controllers, resolver, config, migrations, `OnboardingService`, `EstateOnboardingFlow`, `JourneyStateService`, `JourneyFieldResolver`, `DashboardPromptService`, `LifeStageService` (to line 220), `OnboardingPromptBuilder`, `HouseholdProvisioner`, `OnboardingWorkflowTable`, the corpus, the four tool schemas, `OnboardingStateMachine.php:1-1360, 1360-1680, 1836-1865, 2388-2545`, `OnboardingChatDirector.php:91-1110, 1511-1700, 1862-1905, 2174-2372, 2693-3060, 3621-3800, 5539-5800, 6536-6840`, `OnboardingWizard.vue`, the three wizard views, `FocusAreaSelection.vue` script, `FynOnboardingChat.vue`, `onboardingService.js`, `journeyService.js`, `onboarding.js`, `journeys.js`, `lifeStage.js`, `onboardingChat.js`; targeted sections of `AiChatController.php`, `AiChatPanel.vue`, `AppLayout.vue`, `aiChat.js`, `Dashboard.vue` (web and `/m`), `MobileChrome.vue`, `Register.vue`, the router, `FynClient.swift`, `FynEvent.swift`, `FynConversationModel.swift` | Not read: the campaign parts of the director (`:1106-1510, 3288-3560, 4173-5540, 5800-6535`) and state machine (`:1680-1836, 1865-2388, 2545-2625`) — section 02b; `AssetCaptureEntityExtractor` (1,483 lines), `CaptureAccuracyGate` (858), `SpouseLinkingService` (652), `FunnelIncomeBand`, `OwnershipPhrasings`, `SpouseHouseholdPhrasings`, `OnboardingValueInterpreter` bodies — indexed by method only; the step form components other than their save calls and `v-model` lists; `lifeStageConfig.js` beyond the step lists; the four dead components (MB-01); `AiChatPanelShell.vue`, `useLifeStageFields.js`, `lifeStageService.js`, `onboardingLinks.js` |
| Tests | 996 Pest tests across the listed paths; 31 Vitest tests; 1 contract test written (skipped) | Browser scenarios BS-01/05/07 not re-run; E2E suite not run; campaign tests counted in the 996 but their subject is 02b |
| Playwright | Web: login, returning-user chat open, front door, Something else then a question, full journey walk (Protecting What Matters, single, full-time, income verify, expenditure verify, protection capture, verify, add more, done), reload resume, `/onboarding` picker, one wizard step save, `/onboarding/full`, `/onboarding/estate`, `/onboarding/journey/budgeting` with and without a stored life stage, `/planning/journeys`. `/m`: login with emailed code, dashboard auto-start, focus path (Savings, married with spouse skipped, retired, retirement year, expenditure), expenditure verify pills, dock resume, savings capture, savings verify pills, add more, done; second user for the front-door Something else and a typed question | Verify "No, change something" (edit turn); the campaign path (02b); a married walk with the spouse actually captured (sends a real email from this machine); the dependants detail step; the web free-text front-door exit; `/m` message after a pause (blocked by MB-24); the wizard beyond one saved step; the `stage=` registration branch |
| Surfaces | Web and `/m` driven; iOS read | iOS not run (no simulator in this session); production not touched |

Test data left behind: users 86, 87, 88, 89 (emails above, password `MapTest2!`), their conversations and records, one life insurance policy (user 86), one savings account (user 87), three Sanctum tokens named `m-map`, `m-map2`. Nothing depends on them. User 86's `life_stage` was set by the picker and then cleared by me to test the module and journey routes; every other row is as the app left it. The local `.env` sends real email through the production mail host, so each test login sent a code email to an example.com address.

## 5. Glossary

| Term | Meaning in this section |
|---|---|
| Director | `OnboardingChatDirector`, the service that runs the onboarding conversation and is the only Fyn state allowed to write |
| State machine | `OnboardingStateMachine`, the table of steps and transitions |
| Corpus | the `fyn-memory/procedural` folder of editable text files: the workflow table and the tool schemas |
| Turn type | how a step is answered: buttons (`bubbles`), typed text, a narrow model extraction (`grouped_extract`), a free model turn with tools (`delegated`), an automatic message (`advice`), or an ending (`terminal`) |
| Grouped extract | one model call with one tool that pulls several facts from a sentence |
| Parking | facts regex-extracted from any message and stored on the conversation until a step needs them |
| Verify loop | announce the page, open it, ask "does it look right?", continue or edit |
| Path | `journey` (life stage), `focus` (one module), `campaign` (02b) |
| Selection | the journey or module chosen; drives the capture tools and the final page |
| Pause | step set to null with the walk parked; the user is meant to talk to advice Fyn |
| Wizard | the older form-based onboarding at `/onboarding/*` |
| Life stage | one of five stages (`university`, `early_career`, `mid_career`, `peak`, `retirement`) that picks the wizard's steps |
| Journey (wizard) | a server-defined step list per topic, called through `/api/journeys` |
| SSE | server-sent events, the streaming format the chat uses |
| Action | a non-message command to the conversation: resume, continue, restart, skip, something_else |
