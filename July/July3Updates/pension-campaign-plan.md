# Pension Campaign (/pensioncheck) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship campaign #2 — the pension planning campaign at `/pensioncheck` — per the approved map (`July/July3Updates/pension-campaign-map.md`): homepage CTA → anonymous funnel → projected-pot plan page → registration → pension-lean Fyn walk; existing users (completed SaveTax onboarding) re-enter via a minimal dispatch extension and answer only the missing-data questions.

**Architecture:** Four PR-shaped slices, each merged to `dev` + deployed to csjones + live-verified before the next stacks (the WP-5c deploy-gate cadence). Slice A generalises the campaign seams and builds the re-entry substrate (no user-visible change; SaveTax behaviour byte-identical). Slice B ships the public surfaces (funnel, plan page, homepage CTA). Slice C ships the walk (sections, states, corpus, tools, synthesis, terminal). Slice D is the E2E verification gate on csjones.

**Tech Stack:** Laravel 10 (PHP 8.2, `declare(strict_types=1)`), Vue 3 + Vuex (web SPA + `resources/mobile/` /m SPA), public server-rendered PHP pages, Pest, xAI-backed Fyn with corpus-file tool schemas + golden masters.

## Global Constraints

- **Branching:** feature branches off `dev`, built in a git worktree (NEVER switch the main dir's branch — memory `feedback_never_switch_branches`). If the worktree symlinks `vendor/`, `cp -R` the real vendor + `composer dump-autoload` (memory `reference_worktree_symlinked_vendor_break`). All PRs target `dev`; CSJ merges; deploy each slice to csjones and verify before the next (memory `feedback_deploy_gate_csjones_before_admin_merge`).
- **Rule 2:** every tax value via `TaxConfigService` — zero hardcoded tax numbers, including in public PHP pages.
- **Rule 9:** no acronyms in user-facing text except ISA ("Annual Allowance", "Defined Benefit" spelled out, "National Insurance" not "NI").
- **Rule 12/15:** no scores; no icons/emoji anywhere in Fyn text, prompts, corpus files, commit messages.
- **Rule 19:** every surface change verified on web AND /m.
- **F1–F15** (playbook §2): bold question lines, one SSE multi-turn responses, point-form recaps, deterministic read-backs, action-logged notices, milestone acks, confirm-back for ambiguous input, retry copy with examples, record cards, verify-navigate nudges, time estimate, level-up frames, house rules, corpus/in-code lockstep.
- **British spelling** in user-facing text; PSR-12; `./vendor/bin/pint` before commit. The Pint PostToolUse hook strips just-added unused imports — add imports together with the code that uses them.
- **SaveTax regression guard:** Slices A and C touch shared campaign machinery. `OnboardingWorkflowTableGoldenMasterTest` and the full Fyn/onboarding suites must stay green with **zero savetax golden-master diffs** unless a task explicitly re-records.
- **Canonical contract:** the dispatch change in Task A3 MUST be accompanied by the `00-canonical.md` amendment (Task A8) in the same PR.
- Campaign token everywhere: **`pensioncheck`** (URL `/pensioncheck`, campaign_map key, `funnel_answers.campaign`, `users.active_campaign` value).

---

## Slice A — Re-entry substrate + seam generalisations (PR 1; no user-visible change)

### Task A1: Generalise `campaign_map` values to arrays (G1)

**Files:**
- Modify: `config/onboarding.php:75-77` (campaign_map)
- Modify: `app/Http/Controllers/Api/AiChatController.php:665-691` (startOnboarding campaign match)
- Test: `tests/Feature/AI/OnboardingStartCampaignMapTest.php` (create; mirror the existing start-endpoint test file's setup — find it via `grep -rl "onboarding/start" tests/Feature/`)

**Interfaces:**
- Produces: `config('onboarding.campaign_map')` shape `['<from>' => ['selection' => string, 'entry' => string, 'reentry' => bool]]`. Consumed by Tasks A4, C1.

- [ ] **Step 1: Write the failing test** — assert that `POST /api/ai-chat/onboarding/start` with `from=savetax` (fresh user, `onboarding_completed=false`) still sets `onboarding_fyn_path='campaign'`, `onboarding_fyn_selection='savetax'`, `onboarding_fyn_step='base_work'` after the config shape change. Also assert each configured `entry` value is a real state id (exists in `OnboardingStateMachine::inCodeStates()`).
- [ ] **Step 2: Run — confirm it passes against current code** (this is a characterisation test; it must keep passing after the refactor). `./vendor/bin/pest tests/Feature/AI/OnboardingStartCampaignMapTest.php`
- [ ] **Step 3: Change the config:**

```php
'campaign_map' => [
    // value shape: selection id, entry state id (literal string — must match an
    // OnboardingStateMachine::STATE_* constant; asserted by OnboardingStartCampaignMapTest),
    // and whether completed users may re-enter this campaign (Task A4).
    'savetax' => ['selection' => 'savetax', 'entry' => 'base_work', 'reentry' => false],
],
```

- [ ] **Step 4: Update the controller match block** (`AiChatController.php:668,682-691`): resolve `$campaignEntry = $campaignMap[$from] ?? null` as an array; `$matchedCampaign = $campaignEntry['selection'] ?? null`; replace the hardwired `STATE_BASE_WORK` assignment with `$user->onboarding_fyn_step = $campaignEntry['entry']`. Keep the funnel-answers fallback (`:676-680`) working by resolving `$campaignEntry = $campaignMap['savetax']` there (Task A6 generalises which key).
- [ ] **Step 5: Run the test + the existing onboarding-start suites.** Expected: PASS, no other diffs.
- [ ] **Step 6: Commit** `refactor(onboarding): campaign_map values carry selection/entry/reentry (G1)`

### Task A2: `users.active_campaign` column

**Files:**
- Create: `database/migrations/2026_07_03_000001_add_active_campaign_to_users.php`
- Test: covered by Task A3's dispatch test (column exercised there)

- [ ] **Step 1: Migration:**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Campaign re-entry marker for users who already completed
            // onboarding (map §4). Null = no campaign in flight. Read by the
            // sendMessage dispatch guard every message — a column, not a JSON
            // context key, deliberately.
            $table->string('active_campaign', 32)->nullable()->after('onboarding_fyn_context');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('active_campaign');
        });
    }
};
```

- [ ] **Step 2:** `php artisan migrate` locally, then **`php artisan db:seed`** (CLAUDE.md law: reseed after any migration). NEVER `migrate:fresh`.
- [ ] **Step 3: Commit** `feat(campaign): users.active_campaign re-entry marker`

### Task A3: Dispatch guard OR-branch

**Files:**
- Modify: `app/Http/Controllers/Api/AiChatController.php:236-238`
- Test: `tests/Feature/AI/CampaignReentryDispatchTest.php` (create)

**Interfaces:**
- Produces: a user with `onboarding_completed=true`, `active_campaign='pensioncheck'`, `onboarding_fyn_step` set routes to `OnboardingChatDirector::handleUserMessage`. Everyone else unchanged.

- [ ] **Step 1: Write the failing tests** (use the scripted AI clients bound by the Pest global hook; mirror an existing sendMessage dispatch test for setup):
  - completed user, `active_campaign=null` → advice path (assert AdviceFyn handled it — existing pattern).
  - completed user, `active_campaign='pensioncheck'`, `onboarding_fyn_step='campaign2_existing_recap'` → director path.
  - completed user, `active_campaign='pensioncheck'`, `onboarding_fyn_step=null` (paused mid-campaign) → advice path (fall-back safety).
  - `fyn_flow_enabled=false` → advice path even with campaign set.
- [ ] **Step 2: Run — expect the director-path test to FAIL** (routes to advice today).
- [ ] **Step 3: Implement:**

```php
$inOnboarding = ($user->onboarding_completed === false || $user->active_campaign !== null)
    && $user->onboarding_fyn_step !== null
    && (bool) config('onboarding.fyn_flow_enabled', true);
```

**AMENDMENT (execution finding, 2026-07-03):** the predicate is duplicated at THREE controller sites — `sendMessage`, `streamQueuedMessage` (~:418, queued turns), and `action` (~:799, the walk's Continue/Edit pills). All three must route re-entry identically or the campaign walk breaks on queued turns and button presses. Implemented as one private helper `routesToOnboardingDirector(User $user)` used at all three sites, with dispatch tests for the queued and action seams. A8's canonical amendment must name the helper.

Extend the comment block above it (`:226-235`) with two lines: campaign re-entry (map §4, canonical amendment ref) routes completed users with an active campaign back through the director — the one write state; `onboarding_completed` is never modified by re-entry.
- [ ] **Step 4: Run the new test file + the whole `tests/Feature/AI/` + `tests/Feature/Fyn/` suites.** Expected: all PASS.
- [ ] **Step 5: Commit** `feat(campaign): dispatch re-entry branch — active_campaign routes to the director`

### Task A4: `onboarding/start` re-entry branch (replaces the 409 for re-entry campaigns)

**Files:**
- Modify: `app/Http/Controllers/Api/AiChatController.php:587-592` (early 409) and `:665-700` (match block)
- Test: `tests/Feature/AI/CampaignReentryStartTest.php` (create)

**Interfaces:**
- Produces: completed user + `from=pensioncheck` → 200 SSE stream, `active_campaign='pensioncheck'`, `onboarding_fyn_step=<entry>`, new conversation with `metadata.source='fyn_onboarding'` + `metadata.campaign='pensioncheck'`. Completed user + `from=savetax` (reentry=false) or no `from` → 409 `already_completed` exactly as today.

- [ ] **Step 1: Failing tests:** the three behaviours above, plus resume: completed user with `active_campaign` + step already set calling start again → `resume` SSE event (existing `:613-636` machinery), not a second conversation.
- [ ] **Step 2: Implement.** Replace the flat 409 at `:587` with:

```php
$from = $request->input('from');
$campaignMap = (array) config('onboarding.campaign_map', []);
$reentryCampaign = is_string($from)
    && isset($campaignMap[$from])
    && ($campaignMap[$from]['reentry'] ?? false)
    ? $campaignMap[$from] : null;

if ($user->onboarding_completed === true && $reentryCampaign === null) {
    return response()->json([
        'success' => false,
        'reason' => 'already_completed',
    ], 409);
}
```

Then, for the completed+re-entry case, after the existing resume check (`:613`, which now also covers re-entry users mid-campaign): stamp `$user->active_campaign = $reentryCampaign['selection']; $user->onboarding_fyn_path = 'campaign'; $user->onboarding_fyn_selection = $reentryCampaign['selection']; $user->onboarding_fyn_step = $reentryCampaign['entry'];` — reusing the fresh-start conversation-create + stream path. Never touch `onboarding_completed`. Move the `$from`/`$campaignMap` reads up so they're not duplicated at `:665`.
- [ ] **Step 3: Run the test file + existing start tests.** PASS.
- [ ] **Step 4: Commit** `feat(campaign): onboarding/start re-entry for reentry-enabled campaigns`

### Task A5: Exit paths null `active_campaign`

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` — `handleSomethingElseAction` (`:453` area) and the campaign-terminal completion handler (locate via `grep -n "campaign_terminal\|onboarding_completed = true\|markCompleted" app/Services/Onboarding/OnboardingChatDirector.php`)
- Test: extend `tests/Feature/AI/CampaignReentryDispatchTest.php`

- [ ] **Step 1: Failing tests:** (a) re-entry user hits terminal → `active_campaign=null`, `onboarding_fyn_step=null`, `onboarding_completed` still `true`, and completion-only side effects (the onboarding-completed award/milestone) did NOT double-fire — assert no new `point_awards` row for the completion award id; (b) re-entry user triggers "Something else" → both nulled → next message routes to advice.
- [ ] **Step 2: Implement:** wherever terminal completion sets `onboarding_completed = true`, guard the completion side effects with `if (! $user->onboarding_completed) { ... }` and unconditionally `$user->active_campaign = null`. In `handleSomethingElseAction`, add `$user->active_campaign = null;` beside the step-nulling (do NOT stamp `paused_at_step` resume expectations for re-entry — re-entry resume is via the campaign CTA, keep it simple).
- [ ] **Step 3: Run + PASS. Step 4: Commit** `feat(campaign): terminal + pause exits clear active_campaign; completion side effects idempotent`

### Task A6: Stamp `funnel_answers.campaign` (G2)

**Files:**
- Modify: `public/pages/js/savetax.js` and `public/pages/js/savetax-v2.js` (add `campaign: 'savetax'` to the answers object persisted to localStorage and posted as `funnel_answers` on register)
- Modify: `app/Http/Controllers/Api/AiChatController.php:676-680` (fallback reads `funnel_answers['campaign']` first, defaults `'savetax'` for legacy rows)
- Test: extend `tests/Feature/AI/OnboardingStartCampaignMapTest.php`

- [ ] **Step 1: Failing test:** fresh user, no `from`, `funnel_answers = ['campaign' => 'pensioncheck', ...]` (config already containing a pensioncheck entry via a test-time `config()->set`) → start lands on the pensioncheck entry state; `funnel_answers` without a `campaign` key still lands on savetax (legacy fallback).
- [ ] **Step 2: Implement** the controller fallback:

```php
if ($matchedCampaign === null && $matchedJourney === null && ! empty($user->funnel_answers)) {
    $funnelCampaign = $user->funnel_answers['campaign'] ?? 'savetax'; // legacy rows predate the stamp
    $campaignEntry = $campaignMap[$funnelCampaign] ?? null;
    $matchedCampaign = $campaignEntry['selection'] ?? null;
}
```

and add the one-line `campaign: 'savetax'` stamp in both funnel JS files where the answers object is built.
- [ ] **Step 3: Run + PASS. Step 4: Commit** `feat(campaign): funnel_answers.campaign stamp + generalised fallback (G2)`

### Task A7: /m passes `from` through to start; G6 affinity parameterised

**Files:**
- Modify: `resources/mobile/mixins/onboardingChat.js:69-96` — `startOnboarding` accepts an optional `from` and sends `{ from }` when present; the auto-open call site (`:44-46`) forwards `this.$route.query.from`.
- Modify: `app/Services/Actions/NextActionsService.php:227` (`applyCampaignAffinity`) — module keyed off the user's campaign: `['savetax' => 'tax', 'pensioncheck' => 'retirement']` from a new `private const CAMPAIGN_AFFINITY` map, resolved via `$user->onboarding_fyn_selection ?? ($user->funnel_answers['campaign'] ?? null)`; unknown/null → current behaviour.
- Test: `tests/Unit/Services/Actions/CampaignAffinityTest.php` (create) — savetax user → tax first (characterisation, unchanged), pensioncheck user → retirement first, no-campaign user → unchanged ordering.

- [ ] **Step 1: Failing test → Step 2: implement → Step 3: PASS → Step 4:** rebuild nothing (mixin ships with the slice-C /m bundle; note it in the PR body). **Commit** `feat(campaign): /m forwards from=; campaign-affinity module map (G6)`

### Task A8: Amend the canonical contract + regression sweep

**Files:**
- Modify: `April/April24Updates/spec/00-canonical.md` — the 3-part dispatch predicate section gains the re-entry branch: *"…every other case routes to the read-only advice state, EXCEPT campaign re-entry: a user with `onboarding_completed = true`, non-null `users.active_campaign`, and a non-null `onboarding_fyn_step` routes to the onboarding write state for the duration of the campaign walk; terminal and pause both null `active_campaign`. Re-entry never modifies `onboarding_completed`."*
- Modify: `CLAUDE.md` Fyn contract paragraph — one sentence noting the re-entry branch and pointing at 00-canonical.

- [ ] **Step 1:** Make both edits. **Step 2:** Full regression: `./vendor/bin/pest` — entire suite green; zero savetax golden-master diffs. **Step 3: Commit** `docs(canonical): campaign re-entry amendment to the dispatch predicate`
- [ ] **Step 4: Open PR 1 → dev.** Deploy to csjones after CSJ merge; smoke: savetax funnel walk unaffected (one fresh-user /m walk), completed user chat still advice-mode.

---

## Slice B — Public surfaces: funnel, plan page, homepage CTA (PR 2)

### Task B1: `PensionEstimateService`

**Files:**
- Create: `app/Services/Marketing/PensionEstimateService.php` (sibling of `SaveTaxEstimateService.php` — read that file first and mirror its structure: answers-array in, display-ready array out, all tax values via `TaxConfigService`)
- Test: `tests/Unit/Services/Marketing/PensionEstimateServiceTest.php` (create)

**Interfaces:**
- Produces: `estimate(array $answers): array` returning at minimum `['projected_pot' => float, 'retirement_age' => int, 'years_to_retirement' => int, 'monthly_contribution_assumed' => float, 'tax_relief_note' => string]`. Consumed by Tasks B2 (plan page) and B4 (homepage figure).

- [ ] **Step 1: Failing tests** (seed `TaxConfigurationSeeder`): known-band inputs → deterministic pot (compute the expected value in the test from the same constants — no magic asserted literals without derivation comments); `retired` employment → graceful "already retired" shape; missing bands → defaults documented in the service.
- [ ] **Step 2: Implement.** Deterministic compound projection off band midpoints:

```php
// Band midpoints (age, pot, income) → assumed monthly contribution
// (auto-enrolment minimum 8% of band-midpoint salary as the conservative
// default), compound to age 67 (State Pension age default from
// TaxConfigService pension config where available) at REAL_GROWTH_RATE.
private const REAL_GROWTH_RATE = 0.025; // real-terms, conservative; marketing estimate only
```

Tax-relief note text uses `TaxConfigService` band thresholds (never literals). Keep it one class, no interfaces, no options objects.
- [ ] **Step 3: PASS. Step 4: Commit** `feat(pensioncheck): PensionEstimateService — banded projected-pot estimate`

### Task B2: Funnel page + plan page + JS

**Files:**
- Create: `public/pages/pensioncheck.php`, `public/pages/pensioncheck-plan.php`, `public/pages/js/pensioncheck.js`, `public/pages/js/pensioncheck-plan.js`
- Reference (read first, copy structure): `public/pages/savetax.php`, `savetax-plan.php` (server-side estimate inject at `:27` + `window.SAVETAX_ESTIMATE` at `:208`), `js/savetax.js`, `js/savetax-plan-v4.js`

- [ ] **Step 1: Funnel page** — copy `savetax.php`, reskin copy, replace the 5 questions with the map §6 six: `employment` (same values), `income` (same bands), `age` (`under_30|30s|40s|50s|60_plus`), `pensions` (multi: `workplace|personal_sipp|final_salary|none`), `pot` (banded: `none|under_25k|25k_100k|100k_250k|over_250k`), `spouse` (`yes|no`). Answers → `localStorage('pensioncheck_answers')` + query params, **including `campaign: 'pensioncheck'`**.
- [ ] **Step 2: Plan page** — copy `savetax-plan.php`: server-side `app(PensionEstimateService::class)->estimate($answers)` → `window.PENSIONCHECK_ESTIMATE`; hero shows "On track for a pension pot of roughly £X by age Y" (D2); compact register card posts `POST /api/auth/register` with `funnel_answers` (same shape/mechanism as savetax — `PendingRegistration` carries it; zero backend change needed). Include an **"Already with Fynla? Log in"** link on the register card → `/login?redirect=/dashboard?openFyn=journey%26from=pensioncheck` (existing-user path from the map §3) — mirror however savetax-plan links to login, keep the from param.
- [ ] **Step 3:** No inline hardcoded tax values — the page reads allowance figures from the injected estimate payload only. All copy British-spelled, no icons. **Flag the page copy in the PR body for CSJ review (headline, sub, button labels) — do not invent beyond draft status.**
- [ ] **Step 4: Commit** `feat(pensioncheck): funnel + plan pages`

### Task B3: Routes

**Files:**
- Modify: `routes/web.php` — mirror the `/savetax` block (`:618-686`), declared BEFORE `/savetax` alphabetical position is irrelevant but MUST be before the SPA catch-all:

```php
// /pensioncheck       = pension campaign questionnaire funnel (entry gate)
// /pensioncheck/plan  = projected-pot estimate + register card
Route::get('/pensioncheck/plan', function () {
    return response()->file(/* mirror the savetax include pattern exactly */ public_path('pages/pensioncheck-plan.php'));
});
```

  (Copy the actual include/ob pattern from the savetax block verbatim — it uses `include public_path(...)` inside the closure; keep identical headers/no-cache handling.)
- Test: `tests/Feature/PublicPages/PensioncheckRoutesTest.php` (create): GET `/pensioncheck` and `/pensioncheck/plan` → 200, body contains the funnel's first question / the register card marker; GET `/pensioncheck` does NOT hit the SPA catch-all (assert no SPA root div).

- [ ] Steps: failing test → routes → PASS → check `PreviewWriteInterceptor::EXCLUDED_ROUTES` (no new auth POSTs added — registration already excluded; assert nothing needed, note in PR). **Commit** `feat(pensioncheck): public routes`

### Task B4: Homepage CTA block

**Files:**
- Modify: `public/pages/index.php` — add a `feature-pensioncheck` block as a sibling of `feature-savetax` (`:228-243`), same card pattern: headline, server-computed representative figure (call `PensionEstimateService::estimate()` with the same representative-persona-defaults approach the savetax counter uses at `index.php:6-7` — read that block first and mirror), sub-line, CTA link:

```html
<!-- Pension-check highlight — representative projected pot + CTA into the funnel. -->
<div class="feature-pensioncheck">
  <p class="feature-pensioncheck__headline">Where is your pension heading?</p>
  <span class="feature-pensioncheck__figure" id="pensioncheck-figure"><?= $pensioncheckFigure ?></span>
  <p class="feature-pensioncheck__sub">Answer six quick questions — no account needed — and see the pot you're on course for.</p>
  <a href="/pensioncheck" class="feature-pensioncheck__cta">Check my pension</a>
</div>
```

  Styling: reuse the `feature-savetax` CSS classes' definitions as the pattern (palette tokens only; no new colours). **All three copy strings are DRAFT — flag for CSJ in the PR body.**
- [ ] Steps: implement → view `http://localhost:8000` and confirm both cards render (this is a server-rendered page — remember `route:clear` not `route:cache` if routes act stale) → **Commit** `feat(pensioncheck): homepage CTA block`
- [ ] **Open PR 2 → dev.** After CSJ merge + csjones deploy: browser-verify (Playwright, click-fill-submit law): homepage CTA → funnel → answer all six → plan page shows a pot figure → register a throwaway user → verify `pending_registrations.funnel_answers` contains `campaign: 'pensioncheck'` via csjones tinker. The Fyn walk does not exist yet — the user lands on the generic onboarding (path_choice fallback is WRONG for pensioncheck arrivals until Slice C; acceptable only because /pensioncheck is not yet linked in production marketing. Note it in the PR body).

---

## Slice C — The walk: sections, states, corpus, tools, synthesis, terminal (PR 3)

### Task C1: Per-campaign section machinery (G3)

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php:138-189`
- Test: `tests/Unit/Services/Onboarding/PensioncheckSectionsTest.php` (create)

**Interfaces:**
- Produces: `sectionOrderFor(string $selection): array`, `campaignSections(string $selection): array`, `campaignVerifyConfig(string $selection): array`. All existing savetax callers pass `'savetax'` and get today's arrays byte-identically (grep every call site: `grep -rn "CAMPAIGN_SECTION_ORDER\|campaignSections()\|campaignVerifyConfig()" app/ tests/`).

- [ ] **Step 1: Failing tests:** savetax arrays identical to the current constants (characterisation); pensioncheck order is `['income','pensions','state_pension','retirement_goals','spouse','expenditure']`; every pensioncheck entry state exists in `inCodeStates()`.
- [ ] **Step 2: Implement:**

```php
public const CAMPAIGN_SECTION_ORDERS = [
    'savetax' => ['income', 'savings', 'investments', 'pensions', 'giving', 'spouse', 'expenditure'],
    'pensioncheck' => ['income', 'pensions', 'state_pension', 'retirement_goals', 'spouse', 'expenditure'],
];

public static function sectionOrderFor(string $selection): array
{
    return self::CAMPAIGN_SECTION_ORDERS[$selection] ?? self::CAMPAIGN_SECTION_ORDERS['savetax'];
}
```

`campaignSections($selection)` / `campaignVerifyConfig($selection)`: keep the savetax arrays verbatim; pensioncheck adds:

```php
// pensioncheck sections (map §5). Entry states defined in Task C3;
// data-presence skips in Task C2.
'income' => ['entry' => self::STATE_BASE_EMPLOYMENT, 'skip' => [self::class, 'skipSectionIfIncomeKnown']],
'pensions' => ['entry' => self::STATE_CAMPAIGN_DOB, 'skip' => null],
'state_pension' => ['entry' => self::STATE_CAMPAIGN2_STATE_PENSION, 'skip' => [self::class, 'skipSectionIfStatePensionKnown']],
'retirement_goals' => ['entry' => self::STATE_CAMPAIGN2_RETIREMENT_GOALS, 'skip' => [self::class, 'skipSectionIfGoalsKnown']],
'spouse' => ['entry' => self::STATE_CAMPAIGN_SPOUSE_WORK, 'skip' => [self::class, 'skipIfNotMarried']],
'expenditure' => ['entry' => self::STATE_BASE_EXPENDITURE, 'skip' => [self::class, 'skipSectionIfExpenditureKnown']],
```

verify config for the new sections: `state_pension` + `retirement_goals` → `['route' => '/retirement', 'entry' => <their entry state>]`; pensions → `/retirement` (as savetax). Update `nextCampaignSection` (the resolver) and every caller to resolve the order via `sectionOrderFor($user->onboarding_fyn_selection)`.
- [ ] **Step 3: PASS + savetax suites green. Step 4: Commit** `feat(pensioncheck): per-campaign section orders + verify config (G3)`

### Task C2: Data-presence skip predicates

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php` (beside `skipSectionIfNoCash` etc. at `:1502-1571` — read those first and mirror their signature exactly)
- Test: extend `tests/Unit/Services/Onboarding/PensioncheckSectionsTest.php`

- [ ] **Step 1: Failing tests:** user with a `job_employment` row skips income; user with a `state_pensions` row skips state_pension; user with `retirement_profiles.target_retirement_age` AND `target_retirement_income` both set skips retirement_goals; user with `users.monthly_expenditure` set skips expenditure; fresh user skips none of them.
- [ ] **Step 2: Implement** the four predicates (same static-callable shape as the existing ones; queries via the user's relations, no new services). **Step 3: PASS. Step 4: Commit** `feat(pensioncheck): data-presence section skips — existing users answer only the gaps`

### Task C3: New states — in-code + corpus in lockstep (F15)

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php` — `inCodeStates()` + new `STATE_CAMPAIGN2_*` constants
- Modify: `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` — matching entries
- Test: `OnboardingWorkflowTableGoldenMasterTest` (existing — re-record per its documented flag after both sides match), plus `tests/Unit/Services/Onboarding/PensioncheckStatesTest.php` (create) asserting transitions

**Read first:** one full existing campaign state definition in `inCodeStates()` (e.g. `campaign_pension_contribs` at `:472-517` area) AND its corpus twin — copy the exact key set (`prompt_text`, `retry_text`, `turn_type`, `bubbles`, `advance_on_answered_question`, capture-tool wiring, per-state skip keys).

New states with their prompt content (F1 bold questions, F9 retry examples, Rule 9 no acronyms, no icons):

| State id | turn_type | prompt_text | retry/notes |
|---|---|---|---|
| `campaign2_existing_recap` | bubbles | "Welcome back, {first_name}. Let's take a proper look at your pension. Here's what I already have from you:" + point-form recap (income line, each pension with what's known, spouse status — built by Task C6's builder) + "**Is that all still right?**" | bubbles: "Yes, that's right" / "Something's changed" → changed routes to the existing `campaign_verify_edit` machinery |
| `campaign2_pension_pots` | delegated | "**What's the current value of your {scheme_name} pension?** A rough figure from your latest annual statement or provider app is fine." | tool: `update_record` (existing, `data/update_record.md`) targeting the `dc_pensions` row missing `current_fund_value`; loops per missing-value pension; retry names the format ("for example £45,000 or 45k") |
| `campaign2_pension_db` | delegated | "**Do you have any final salary or career average pensions — the kind that pay a guaranteed income rather than building a pot?** If so, tell me the scheme name and the yearly pension you've built up so far." | tool: `create_pension` (existing — Defined Benefit fields); `advance_on_answered_question: true` so "no" advances |
| `campaign2_flexible_access` | grouped_extract | "**Have you taken any money out of a pension — a lump sum or a regular income?** It matters because it can cap what you're allowed to pay in from now on." | only reached when age ≥ 55 (per-state skip); tool: `update_record` setting `has_flexibly_accessed` |
| `campaign2_state_pension` | grouped_extract | "**Do you know your State Pension forecast?** You can check it in a couple of minutes on the government's Check your State Pension service. If you have it, tell me the yearly amount and how many qualifying years you've built up." | tool: `capture_state_pension` (Task C4); "not sure" advances with the gap noted (the engine's no-forecast advice then fires) |
| `campaign2_retirement_goals` | grouped_extract | "**When would you like to retire, and what yearly income would feel comfortable?** Rough numbers are fine — for example 65 and £30,000." | tool: `capture_retirement_goals` (Task C4); retry: "give me an age and a yearly amount — for example 67 and £28,000" |
| `campaign2_spouse_pensions` | delegated | "**Does your spouse have pensions of their own?** Tell me the type and a rough value for each — workplace, personal, or final salary." | tool: `create_pension` for the spouse (mirror how `campaign_spouse_household` writes spouse-owned records — read it first); skip if not married |
| `campaign2_terminal` | terminal | "We've built your pension picture, {first_name}." + navigation SSE → `/retirement` | mirror `campaign_terminal`'s shape; navigate_to `/retirement` (D4) |

Pensions-section internal order for pensioncheck: `campaign_dob` → `campaign_occupational_scheme` (reused; per-state skip extended: skip if a workplace `dc_pensions` row exists AND has `current_fund_value`) → `campaign2_pension_pots` → `campaign_pension_contribs` (reused) → `campaign2_pension_db` → `campaign_pension_history` (reused; Task C5 gate) → `campaign2_flexible_access`.

- [ ] **Step 1:** Write `PensioncheckStatesTest` transitions failing → **Step 2:** add constants + in-code entries → **Step 3:** add corpus entries (identical text) → **Step 4:** golden master: run, diff shows ONLY the new states, re-record per the test's documented mechanism → **Step 5:** full onboarding suite PASS → **Step 6: Commit** `feat(pensioncheck): campaign2 states — in-code + corpus lockstep`

### Task C4: New capture tools

**Files:**
- Create: `fyn-memory/procedural/tool_schema/campaign/capture_retirement_goals.md` + `.xai.md`
- Create: `fyn-memory/procedural/tool_schema/campaign/capture_state_pension.md` + `.xai.md`
- Modify: the capture-tool handler registry (locate via `grep -rn "capture_pension_history" app/Services/ --include=*.php` — wire the two new handlers exactly where that one is wired)
- Test: `tests/Feature/AI/ToolSchemaGoldenMasterTest.php` (re-record: `CAPTURE_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php`), plus handler unit tests beside the existing capture-handler tests

**Schema content (descriptions govern model behaviour — memory `reference_tool_schema_description_governs_llm_defaults`; write them as the contract):**

- `capture_retirement_goals` — description: "Record the user's retirement goals. Call when the user states a target retirement age and/or a desired yearly retirement income. Ages are whole years between 55 and 75; income is a gross yearly figure in pounds. Never guess a value the user did not state — omit the parameter instead." Params: `target_retirement_age` (integer, optional), `target_retirement_income` (number, optional; yearly gross £). Handler: `RetirementProfile::updateOrCreate(['user_id' => $user->id], array_filter([...]))`.
- `capture_state_pension` — description: "Record the user's State Pension forecast. Call when the user gives a forecast amount and/or National Insurance qualifying years. The forecast is a yearly figure in pounds (convert a weekly figure by multiplying by 52 and say so in your reply). Omit anything the user did not state." Params: `forecast_annual` (number, optional), `ni_years_completed` (integer, optional), `state_pension_age` (integer, optional). Handler: `StatePension::updateOrCreate(['user_id' => $user->id], array_filter([...]))`.

- [ ] **Steps:** failing handler tests (given tool-call args → DB row asserted, including update-not-duplicate on second call) → schemas (copy `capture_pension_history.md` frontmatter/format exactly; both `.md` and `.xai.md` since the app runs xAI — memory `reference_dual_provider_tool_catalogue`) → handlers → golden-master re-record → PASS → **Commit** `feat(pensioncheck): capture_retirement_goals + capture_state_pension tools`

### Task C5: Carry-forward gate + advice + synthesis

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php` — per-state skip on `campaign_pension_history` when the walk is pensioncheck: skip unless gross annual income exceeds the higher-rate threshold from `TaxConfigService` (`$taxConfig->get('income_tax.higher_rate_threshold')` — confirm exact key by reading `TaxConfigService`; NEVER a literal)
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` — `SECTION_STRATEGY_TYPES` gains the pensioncheck sections → retirement strategy types (read `app/Services/Coordination/PlanSources/RetirementStrategySource.php:19-52` + the `RetirementActionDefinition` type ids first; map pensions → contribution/salary-sacrifice/consolidation types, state_pension → National Insurance types, retirement_goals → income-gap types)
- Modify: `buildSynthesisAdvice` (`:1025-1061`) — generalise the composed-plan source by campaign: savetax → the existing tax plan (byte-identical, guarded by `CampaignSynthesisTurnTest`), pensioncheck → `ComposedModulePlanService::forSource()` retirement output, same F4 bullet format
- Test: extend director tests; `CampaignSynthesisTurnTest` must stay green unmodified

- [ ] **Steps:** failing tests (higher-rate user asked history / basic-rate skipped; pensioncheck synthesis mirrors retirement composed items) → implement → PASS → **Commit** `feat(pensioncheck): carry-forward gate, retirement section advice, campaign-aware synthesis`

### Task C6: Existing-user recap builder + re-entry entry state (G5)

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (or wherever `buildCampaignIntroPrompt` lives — locate first) — a pensioncheck intro branch: fresh users get a time-estimated greeting (F12, reuse the greeting builder); re-entry users get the `campaign2_existing_recap` point-form recap built from live DB reads (income line from `job_employment`, one line per pension with known fields, spouse status) — F4 bullets, deterministic values (F5 spirit: DB reads, never model paraphrase)
- Modify: `config/onboarding.php` — add the campaign entry: `'pensioncheck' => ['selection' => 'pensioncheck', 'entry' => 'base_work', 'reentry' => true]` (`base_work`, not `base_employment` — the funnel already captured employment status, mirroring savetax's start at `AiChatController:685-690`; `base_employment` remains the income SECTION entry for verify loop-backs, as savetax does), and the re-entry entry state override: re-entry users enter at `campaign2_existing_recap` (add `'reentry_entry' => 'campaign2_existing_recap'` to the map value; Task A4's code reads `reentry_entry` when present for completed users, `entry` otherwise — one-line addition there, covered by a test here)
- Test: extend `CampaignReentryStartTest` — re-entry start lands on `campaign2_existing_recap`; fresh pensioncheck funnel user lands on `base_work`

- [ ] **Steps:** failing tests → implement → PASS → full suite → **Commit** `feat(pensioncheck): intro/recap builders + config entry live (G5)`
- [ ] **Open PR 3 → dev.** This PR turns the campaign ON (config entry). After CSJ merge: deploy csjones (code + corpus reindex if the corpus loader caches — mirror whatever the CoALA/corpus deploy steps in `deploy/DEPLOY.md` say) + rebuild the /m bundle.

---

## Slice D — E2E verification gate (csjones; no code — Browser Testing Law applies)

- [ ] **D1 New-user walk:** phone-sized Playwright on csjones: homepage → pension CTA → funnel (fill all six) → plan page (pot figure renders, no hardcoded tax text) → register fresh user (MFA code via SSH tinker per `reference_m_verification_path`) → /m auto-opens Fyn → complete the FULL walk, every section, every verify screen (Continue/Edit pills), section advice after each confirm → synthesis bullets → terminal navigates `/retirement` → screen shows the captured pensions/State Pension/targets. Verify DB rows via tinker: `dc_pensions.current_fund_value`, `db_pensions`, `state_pensions`, `retirement_profiles`, `users.funnel_answers.campaign='pensioncheck'`.
- [ ] **D2 Existing-user delta walk:** log in as `julycsj3@example.com` (id 168, Password1!) on csjones `/m/app/login` → deep-link `/m?to=/dashboard?openFyn=journey%26from=pensioncheck` → recap state greets with held data → confirm → asked ONLY the gap questions (pot values, Defined Benefit, State Pension, goals, spouse pots — count them; income/DOB/expenditure NOT re-asked) → terminal → `/retirement` → tinker: `active_campaign` is null post-terminal, `onboarding_completed` still true, no duplicate completion `point_awards`.
- [ ] **D3 Gamification + milestones:** on both walk users: `point_awards` rows for the walk states + captures; `pension_pot` / `retirement_on_track` milestones minted where thresholds crossed (WP-5c catalogue); activity feed labels sane; level wheel updated. Milestone Fyn ack (F7) observed on the pot-crossing capture.
- [ ] **D4 Regression:** one savetax fresh-user /m walk end-to-end on csjones — unchanged behaviour (the shared-machinery regression, live).
- [ ] **D5:** anything RED → CLAUDE.md Rule 14 loop (systematic-debugging → fix → redeploy → re-verify from the top of the affected walk). Reports only after GREEN.

---

## Self-review notes (spec coverage)

- Map §4 re-entry → A2–A5, A8. §5 walk/states/skips → C1–C3, C5, C6. §5a gates → C2 (presence), C3 (55+ skip), C5 (higher-rate gate). §6 funnel/plan/stamp → B1–B3, A6. Homepage CTA (CSJ 2026-07-03) → B4. §7 landing `/retirement` → C3 terminal. §8 G1–G6 → A1 (G1), A6 (G2), C1 (G3), C3 terminal (G4), C6 (G5), A7 (G6). §10 items 1–11 all mapped; item 10 milestones → D3; E2E → D1–D4.
- Deliberately NOT built (map): fees/beneficiaries/member-number questions, `/retirement-plan` landing, desktop achievements parity, milestone email loop.
- Copy strings in B2/B4 are DRAFT pending CSJ review — flagged in both PR bodies.
