---
type: handover
mode: session-end
date: 2026-09-16
session: 2
repo: fynla
branch: dev (main checkout, clean apart from the long-standing untracked workforce/diagram files)
---

# Session Handover — 2026-09-16, Session 2

## Where things stand

Every Save Tax onboarding capture step that used to parse free text is now a structured form inside the Fyn chat on web and `/m`: property (yesterday), ISAs, bank and savings, investments, pensions, and both spouse variants (today). Each capture form is followed by a cap-aware "Do you have another … to add?" loop, re-opens without a lead-in after "Yes", focuses its first field when a kind is opened, and the income, spouse and expenditure section ends no longer visit a details page. Seven releases went to fynla.org today (main `b64ec17cc` == dev `307210164`); every one was browser-walked on csjones first. **CSJ's next intention (their words at session end): check the journey onboarding, spouse entry details, dependants and detailed expenditure "etc." through Fyn, and give them the same form shapes.** The recipe for that is in "How to add a capture form" below — read it before touching anything.

## Priorities for the next session

1. **Survey the remaining free-text capture steps and propose form shapes** — CSJ wants the form treatment extended beyond the Save Tax campaign. Candidates by state (corpus `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md`): the journey/base path `base_personal`, `base_spouse` (spouse name / date of birth / linking — grouped_extract via `capture_spouse_details`), `base_dependants` + `base_dependants_detail` (`capture_dependants`), `base_work` (income, `capture_employment`), `base_expenditure` (free_text; detailed expenditure lives in `ExpenditureProfile` with its own form on web), `campaign_charitable_giving`, and the pension check states (`campaign2_*`). For each: which tool it writes through, which fields are required, whether it is one write (spouse pattern) or one-per-kind (account pattern), and whether it needs a loop. Put the proposal to CSJ as a short table before coding; ask about field choices the same way Part F was asked yesterday, and take the spec defaults if CSJ says "go with it".
2. **Build them one PR per step using the recipe below**, csjones gate in CSJ's Chrome (web AND `/m`), then `/release`. Do NOT use subagents for these; each step was under an hour today done directly.
3. **Adjacent issues CSJ has not ruled on** (raise, do not fix unasked): `/savings` (the savings verify page) lists accounts only in preview mode, so a real user sees the Open Banking promo instead of their accounts; the level-up celebration overlay covers the Fyn panel after a save on web and `/m` until tapped; on Free two ISAs use up the investment cap so a General Investment Account is then refused; the spouse extractor attributed the ISA's provider to the pension too (typed path); the typed (native) path at a form state still gets the old questions.
4. **Parked, raise only if asked:** mapping PRs #829–#839 and the 55 mapping bugs (`CSJTODO.md`); the native iOS renderer for forms; the iPhone registration bounce; the web Retirement actions block.

## How to add a capture form (the shapes built this session)

One schema home, one mixin, two template-only renderers, one handler. Every form is added the same way; nothing below is per-surface.

**1. Schema — `app/Services/Onboarding/CaptureForms.php`.** Add a `const`, list it in `names()`, add a `case` in `schema()`, and a private static `name()` returning plain data:
- `name`, `submit_label`, `kinds[]`, `fields{}`.
- **Per-kind write (accounts, pensions):** each kind carries `key`, `label`, `fields[]`, `tool` (the create tool), `entity_type`, plus any per-kind constants the input needs (`account_type`, `scheme_type`, `isa_type`). `toolInputs()` calls `<name>Inputs($kind, $answers)` (add a `match` arm) returning the tool's own input array; `summarise()` calls `<name>Sentence($label, $input)` for the transcript line. Handler runs ONE tool call per filled kind.
- **Single write (spouse):** schema-level `tool` + `entity_type`, optional `lead_fields[]` (asked ABOVE the kind boxes under the `_lead` pseudo-kind — `CaptureForms::LEAD`), `kinds_prompt` (the line above the boxes), `allow_empty` (Save with nothing chosen; `toolInputs()` then sends zeros for every money field). Form field keys ARE the tool's field names; `spouseInputs()` folds every section into one input. Success is the tool's `onboarding_capture => true` receipt, and the recap is `buildCaptureAck($user, $stateId)` in the director — add an ack case there for the new state.
- Field types: `money` (`required` true/false), `money_or_none` (`none_label`), `choice` (`options[] {value,label}`), `percent` (`min`/`max`/`step`, default the ownership-share range), `text` (max 255). `required_when: {field, in[]}` reveals a field conditionally (the share pattern). Rules come from `fieldRules()` automatically. Labels are user-facing British copy; spell out acronyms (Rule 9); ISA is fine.
- Tests: `tests/Unit/Services/Onboarding/CaptureFormsTest.php` — kinds/labels in order, `toolInputs()` exact array per kind, `summarise()` exact sentence, `names()`.

**2. Corpus — `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md`.** On the state add `form: <name>` and `form_prompt_text: '<short lead-in>'` (e.g. "Now your pensions."). Keep `prompt_text` (typed clients: native). `turn_type` may stay `delegated` or `grouped_extract` — the director emits the form whenever a state carries `form` and the client sends `X-Fynla-Forms: 1` (`emitTurnForState`, `($turnType === 'form' || isset($state['form']))`), and matches a posted form on `state.form === form.name` only. Run `php artisan fyn:procedural:validate`. The golden-master test enforces corpus DATA vs PHP-only keys: `turn_type`, `prompt_text`, `form_prompt_text`, `form`, `bubbles`, static `next` live in the corpus; `capture_focus`, `skip_if`, `record_context`, callable `next` live in `OnboardingStateMachine`.

**3. Loop question (only for multi-record steps).** Corpus: set the capture state's `next: <step>_more` and add `campaign_<step>_more` with `turn_type: bubbles`, `prompt_text: 'Do you have another X to add?'`, bubbles `yes` "Yes, add another" / `no` "No, that's everything" / `continue` "Continue to the next section", `next: { branch: nextFrom<Step>More }`. PHP: a constant, a states-table entry (`'next' => self::class.'::nextFrom<Step>More'`, plus the SAME `skip_if` as the capture state — a skipped capture resolves through its static `next`), the branch method (`saidYes()` → capture state; else the old destination), and add the constant to the list in `OnboardingStateMachineTest`. Director: add the `_more` state to `capReachedAtLoop()` (entity key, count, plural noun — at the cap the prompt becomes the limit statement and only the `continue` bubble shows) and to the map in `reenteredFromLoopQuestion()` (no lead-in on "Yes"). Tests to mirror: `tests/Feature/Onboarding/AccountCaptureFormTurnTest.php` (emit with/without header via the dataset, save, loop yes/no, Free cap wording).

**4. Section end without a page visit.** `OnboardingStateMachine::sectionSkipsVerifyPage()` lists the Save Tax sections that skip announce → navigate → confirm (income, spouse, expenditure). Add a section there if CSJ says no page visit; everything else keeps `enterCampaignVerify`.

**5. Clients — nothing to do unless a new field TYPE is needed.** Renderers are schema-driven: `resources/mobile/utils/captureFormState.js` (the ONE mixin: state, validation, payload, focus, lead fields, blocks order) and the two template-only SFCs `resources/js/components/Fyn/FynCaptureForm.vue` and `resources/mobile/components/FynCaptureForm.vue`. A new field type = one mixin branch in `isValid()` + one `v-else-if` in BOTH templates + a `fieldRules()` arm + a case in `tests/frontend/components/Fyn/FynCaptureForm.test.js` copied into `resources/mobile/components/__tests__/FynCaptureForm.spec.js`. Any template or mixin change means both bundles must be rebuilt and uploaded (csjones: `./deploy/csjones-fynla/build.sh`; prod: `./deploy/fynla-org/build.sh`). PHP/corpus-only changes need no build.

**6. Gate, every time.** csjones on the feature branch (`git fetch && git checkout <branch> && git reset --hard origin/<branch>`, cache clears ending `config:cache`, `fyn:procedural:validate`); provision test user 399 by tinker (park `onboarding_fyn_step`, set `household_calculation_mode` / `employment_status` / `funnel_answers.assets` as the step's `skip_if` needs); mint a Sanctum token in tinker and set `sessionStorage.auth_token` (web, `https://csjones.co/fynla/dashboard`) or `localStorage.m_scaffold_token` (`https://csjones.co/fynla/m/app/dashboard`); the Fyn panel resumes with "Welcome back … Continue"; walk the form in Playwright (fill, Save, read the tail, check the DB row by tinker). Then merge with `--admin`, open the release PR `dev → main`, merge, csjones back to dev, prod deploy (`php artisan down` via the `ssh-fynla` MCP, `rsync -azRc` of `app/ fyn-memory/` and the bundles if built, `composer dump-autoload -o`, cache clears, `config:cache`, `up`, `fyn:procedural:validate`, home/`/m`/login 200, log tail). Check whether `c.jones@csjones.co` is mid-walk before taking prod down; purge it only when CSJ asks or its walk is finished, and purge with `withTrashed()->forceDelete()` (a plain delete leaves a trashed row that registration offers to restore).

## Context to load

- `handover/September/16/sdd-account-forms/progress.md` — Rulings 23–41 with reasons (why two field keys for the bank rate, why zeros for "nothing chosen", why the ISA form states individual, the cap-loop design, the no-lead-in rule and its scoping bug, the /m empty-reply sweep). Extend, never re-litigate.
- `app/Services/Onboarding/CaptureForms.php` — the one schema home; `pension()` and `spouseHousehold()`/`spouseAssets()` are the two shapes to copy (per-kind write vs single write).
- `app/Services/Onboarding/OnboardingChatDirector.php` — `emitTurnForState` form branch (~1047–1110 incl. `capReachedAtLoop`), `handleFormTurn` (~3778), `reenteredFromLoopQuestion`, `buildCaptureAck` (~6080, the recap acks).
- `app/Services/Onboarding/OnboardingStateMachine.php` — `enterCampaignVerify` / `sectionSkipsVerifyPage` (~1081), `nextFromPropertyMore` … `nextFromPensionMore` (the loop branches), `sectionLabel`.
- `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` — the states; compare a form state (`campaign_bank_accounts`) with an untouched one (`base_spouse`) to see exactly what changes.
- `resources/mobile/utils/captureFormState.js` — the mixin; read before adding any field type.
- `docs/superpowers/specs/2026-09-16-savetax-account-capture-forms-design.md` — the spec the account forms were built from (its Part F defaults were all taken; pensions/spouse were out of its scope and were added on CSJ's instruction today).
- `docs/tech-debt-report.md` — today's audit (0 critical, 4 warnings).

## Completed this session (all merged to dev and released to fynla.org)

- #868 generalisation (tool per kind, `text` + bounded `percent`, `fieldRules`), #870 ISA/bank/investment forms + loops (Cash and Stocks and Shares ISA only; joint bank 50/50 no share; Other investment optional type), #871 income/spouse ends skip the details page + spouse repeat-back acks, #873/#875 cap-aware loop question (limit statement, single Continue bubble, cap-only refusal advances), #877 no lead-in on "Yes, add another" (+ /m empty-reply sweep fix), #880 open-a-kind focuses its first field, #882 pension form + `campaign_pension_more` + property verify label, #884 spouse forms (lead fields, kinds prompt, single write, allow_empty) + no expenditure page visit. Releases #872, #874, #876, #878, #881, #883, #885. Memory `project_release_2026_09_15.md` records each with backups.
- Process lesson recorded in memory `feedback_clear_cjones_prod_account_after_every_fix.md`: soft-delete trap on purge.

## Verification state

- Focused Pest per PR (CaptureForms, state machine, golden master, section/verify flows, pension-check, tool coverage, Account/Property form turns, request): green at each merge; last run 170 passed at `1f2bf3cee`, flow files 71 passed at `ec1f106ec`. Vitest: both renderer specs + mixin spec + store spec 48 passed at `1f2bf3cee`. No full suite today (CSJ: focused files only).
- Browser (Playwright, csjones, CSJ's Chrome session): web — ISA save/lock/loop/cap refusal, bank save, investment refusal, cap-loop wording, re-open without lead-in + kind focus, spouse household form (income above, two sections, recap, on to expenditure); `/m` — ISA and bank save/loop, investment render+refusal, re-open without lead-in + kind focus, spouse assets form (Save with nothing chosen enabled, savings saved, recap). API on csjones — income end (no announce), spouse ack, cap-loop continue, pension form emit/save/loop/pot question.
- NOT verified in a browser: the pension form (API-proven only — same renderer, no new field types); a successful investment save on web/`/m` (Free cap blocked it; API + Pest proven); a fresh registration through the Save Tax funnel on prod (CSJ walked it as c.jones and found the pension omission and the cap loop, both fixed).

## Decisions and dead ends

- CSJ rulings today (all in the ledger): forms for every capture step; Part F defaults accepted; Lifetime/Innovative Finance ISAs off the form; Other investment optional type; at the cap no "add another" — limit statement, upgrade after onboarding, one Continue bubble, no trailing question; no lead-in on re-open; income, spouse and expenditure ends do not visit a page; opening a kind scrolls and focuses; spouse = income box then "choose more than one" boxes, one save, recap.
- Dead ends: a `reenteredFromLoopQuestion` that matched ANY loop state stripped the bank form's lead-in after "No" on ISAs — it must match the form's own loop state. The `/m` `send()` empty-reply sweep removed a text-less form row — a row with a form is not empty. The non-working spouse tool rejects an empty write — "nothing chosen" is sent as zeros. Testing the branch on a local worktree port was wrong; CSJ tests on csjones, always gate there.
- Process: CSJ said the first hour was lost to narrating and to a worktree/local server detour; do the change, gate on csjones, ship. Answer "why is this slow" with the mechanism. Never re-mint a Sanctum token while a browser tab uses it (revoke-all); mint once per surface.

## Things that will bite you

- Level-up celebration overlays (`div.celebrate[role=dialog]`) block Playwright clicks after a save; dismiss with a JS click on the dialog first.
- The web Fyn panel persists its open state; clicking "Chat with Fyn" on load can collapse it. Check `getComputedStyle(aside).display` before clicking.
- csjones test user 399 `formwalk-accounts-0916@example.com` (Free, married, dual earner) is the parked-walk fixture; conversation 228 carries `source: fyn_onboarding` (required for the resume path). `TaxStrategyHouseholdInput`, `SavingsAccount`, `InvestmentAccount`, `DCPension` rows for 399 must be cleared between walks (Free caps are 2).
- `c.jones@csjones.co` on prod was purged at 16:05 at CSJ's request; CSJ was about to re-register.
- 28 csjones screenshots (`csjones-web-*.png`, `csjones-m-*.png`) were moved out of the repo root into the vault day folder.
- The formatter hook still strips a just-added `use` import if it runs before the usage exists.

## Tech debt deferred

From `docs/tech-debt-report.md` (0 critical): "Free plan" hardcoded in the cap statement (`OnboardingChatDirector.php:1105`); `pounds()` duplicated between the director acks and `CaptureForms`; `CaptureForms.php` at 796 lines (split per form); `handleFormTurn` at 142 lines; `sections()`/`blocks()` overlap in the mixin; `nextFromCampaignOccupationalScheme` now only reached via `nextFromPensionMore`; spouse schemas use tool field names as form keys (deliberate, undocumented).

## Branch and deploy state

- Branch: dev at `307210164` (== origin/dev); main at `b64ec17cc` (== dev).
- Unpushed commits: none.
- Deploy status: prod (fynla.org) at main `b64ec17cc` content (PHP, corpus, both bundles); csjones on dev `307210164` with dev bundles.
