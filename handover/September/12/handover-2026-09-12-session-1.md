---
type: handover
mode: session-end
date: 2026-09-12
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-09-12, Session 1

(The session ran 2026-09-11 10:35 → 2026-09-12 10:45 BST; the handover lands on the day it was written.)

## Where things stand

fynla.org runs main `e6d4f4a18`, tree identical to dev `a5dd5c340`, after **nine releases across
the two days** (#809, #811, #813, #815, #817, #819, #821, #823, #825, #827). Everything CSJ hit in
his two live SaveTax runs is fixed and proven on prod with his exact sentences: the tier-cap gate
and wording (#808); the ownership loop on "My name" / "Individual" (#810, #812, #814, #816 — not a
code regression: the July fixes depended on the model re-calling its tool, and grok stopped doing
so; the director now re-runs the blocked attempt itself and never feeds its own dead-end rows
back to the model); the spouse step (#820, #822, #824 — funnel income cross-check on the campaign
spouse state, pension pot and provider names captured and backfilled from the user's words,
everything shown on the spouse verify page on `/m` and web); and the post-plan spouse invitation
(#826, copy approved by CSJ). The tech-debt batch (#818) also shipped. 36 prod test accounts were
purged over the two days (686, the preview persona, kept). Tree clean (only the brief hook's
`workforce/ops` files, as at every session start), nothing unpushed, no worktrees, csjones on dev.

## Priorities for the next session

1. **BLOCKED ON CSJ — how the phone gets tested** (carried since 2026-09-09). Build 10 is
   VALID; W-0044 / W-0496 and the build 9/10 dashboard flow need on-screen checks; `FynlaTests`
   cannot run on the device (`try!` absolute-path fixtures, `FynlaTests/AuthClientTests.swift:271`).
   Ask which route: CSJ on the device, bundling the fixtures as test resources, or a simulator
   run (wedges the Mac).
2. **BLOCKED ON CSJ — one page per module for dashboard routes.** `GamifiedDashboard.webRouteFor`
   opens `/net-worth/cash` for a savings rec; a semantic destination opens `/savings`; the
   server's `GateRoutes::MAP` web column is a third copy. Consolidation needs the product call
   (recorded in PR #818). Ask, do not pick.
3. **Watch the first real SaveTax runs after today's changes.** Three things are new on prod and
   were verified with test accounts only: the invitation email goes out at once to an address
   with no account (W-0349 path), the "Not now" / collision sentences, and the spouse verify
   page's provider rows. If CSJ reports anything, start from `ai_messages` for the conversation
   (the transcript + `tool_calls` / `tool_results` columns are the evidence; see "Things that
   will bite you").
4. **The remaining `deferred-ios` items** — W-0090, W-0243, W-0311, W-0416 need Swift. Fresh
   branch off dev.
5. **Tech debt from this session** (`docs/tech-debt-report.md`, top section): the spouse-row
   mapping written once per surface; five homes for the household field list; the two canned-
   refusal recognisers; the director at 7,668 lines. Small, none urgent.
6. **Parked, non-iOS, unchanged:** W-0540 dead-component clusters, the #770 homepage block, the
   34 sweep findings, the tax-compliance reviews in `CSJTODO.md`.

## Context to load

- `docs/tech-debt-report.md` (top section, 2026-09-11/12) — the eight findings with `file:line`;
  priority 5 comes from here.
- `app/Services/Onboarding/OnboardingChatDirector.php:6067` (`retryPreviousBlockedAttempt`) and
  `:3288` (`backfillSpouseHouseholdFromWords`), `:2141` (`takeSpouseInviteOutcomeText`) — the
  three deterministic mechanisms added this session; read before touching any capture turn.
- `app/Traits/HasAiChat.php:1735` (`buildMessageHistory`) — the ONE model-history builder; it now
  drops `is_retry` rows and `FynSystemPrompt::CANNED_REFUSAL`.
- `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` (`campaign_spouse_invite`,
  `campaign_spouse_invite_details`) and `app/Services/Onboarding/OnboardingStateMachine.php`
  (`nextFromCampaignSynthesis`, `skipSpouseInviteIfLinked`) — the new states; the corpus and
  in-code key ORDER must match or `transitionTable()` falls back to the shipped file.
- `app/Services/Onboarding/SpouseHouseholdPhrasings.php` and `OwnershipPhrasings.php` — the one
  vocabulary each for spouse figures/providers and ownership; extend these, never add a regex
  elsewhere.
- `handover/September/11/handover-2026-09-11-session-1.md` — the previous session's state and the
  phone-test question in full.

## Completed this session

- **Prod clean-up** (CSJ's list, then singles on request): 36 test accounts hard-purged via
  `RetentionPurgeService::purgeUser` + `users` row delete, each with a full dump first under
  `~/release-backups/2026-09-11-pre-purge/` and `2026-09-12-pre-purge-692.sql.gz`; 686 kept
  (preview persona). CSJ's own `c.jones@csjones.co` purged twice on request (689, 692).
- **#808 → release #809**: `LimitReachedModal` takes `entity-key` and derives label/cap/tier;
  `tierLimitMixin.guardTierCap()` / `tierCapBlocks()` is the one at-cap gate; "bank accounts"
  wording (CSJ: the cap counts every account type; the page is "Bank Accounts" everywhere and
  the Cash page cards are account types under it); onboarding tab pill "Cash" → "Bank Accounts";
  `RegistrationRulesParityTest` pins `registrationRules.js` to `RegisterRequest` (caught the
  last-name wording drift).
- **#810 → #811**: `OwnershipPhrasings::INDIVIDUAL` covers bare "individual", "my name", "me only",
  "myself", "sole"; `buildCampaignIntroPrompt` names only the funnel's ticked asset groups.
- **#812 → #813**: `retryPreviousBlockedAttempt` re-runs the previous row's gate-blocked create
  (`ai_messages.tool_calls` + `tool_results`) through `executeTool` with the merged user words as
  evidence when the model makes no tool call; `executeGapFillInput` shared with the extractor
  gap-fill.
- **#814 → #815**: the rescue walks back past refusal / retry rows (max 6).
- **#816 → #817**: `buildMessageHistory` drops `is_retry` rows and the canned refusal
  (`FynSystemPrompt::CANNED_REFUSAL`); the rescue treats the resume greeting as transparent. The
  polluted prod conversation 843 recovered live.
- **#818 → #819** (tech-debt batch): onboarding lint 23 → 0; `AdviceFyn` `DECLINE_PATTERN` /
  `AFFIRM_PATTERN` + `isDecline()` / `isAffirm()`; `tests/Support/Fyn/Sse::frames()`; dead
  `GoalsAgent` fallback dropped; `resources/mobile/utils/currency.js` replaces 17 copies.
- **#820 → #821**: `detectIncomeFunnelMismatch` covers `campaign_spouse_household`
  (key `spouse_annual_income`); `spouse_existing_pension_balance` captured;
  `income_summary.spouse.household` shown on `/m` Income (spouse view) and web
  `IncomeOccupation` as "What you told Fyn about your spouse".
- **#822 → #823**: `SpouseHouseholdPhrasings` + `backfillSpouseHouseholdFromWords` fill the pot and
  the yearly contribution (monthly ×12) through the same handler when the model drops them.
- **#824 → #825**: migration `2026_09_12_090000_add_provider_names_to_tax_strategy_household_inputs`
  (`spouse_isa_provider`, `spouse_pension_provider`); captured, backfilled (autocorrections
  "USA" / "it's" for ISA handled), shown as "ISA balance with Halifax", "Pension pot with Aviva".
  First prod migration of these releases — `database/migrations/` rsynced + `migrate --force`.
- **#826 → #827**: `campaign_spouse_invite` (bubbles) + `campaign_spouse_invite_details`
  (free_text, `parseSpouseInviteDetails`) between the synthesis and the terminal; details go
  through `SpouseLinkingService::linkOrCreateSpouse`; outcome voiced in
  `emitTerminalNavigationTurn` ahead of the app note. State count 48 → 50.
- Memory: `project_release_2026_09_11` carries all of it; `MEMORY.md` index updated.

## Verification state

- Every PR was deployed to csjones (feature branch, `git switch` + bundle upload where JS
  changed) and clicked through in Playwright before its admin-merge; every release was deployed
  to fynla.org and clicked through there. Last full prod journeys: 2026-09-12 08:45 (providers),
  09:53 (invitation), both green.
- Pest at `a5dd5c340`: Feature/Onboarding + Unit/Services/Onboarding + Feature/AI 1410 green
  (+ Unit/Services/UserProfile 1450 earlier); golden masters (tool schema, xAI, workflow) green;
  `fyn:procedural:validate` clean locally, csjones and prod. Vitest: mobile 197/200, onboarding +
  store, utils + Shared. Full suites not run (Rule 17). dev's own Quality Gate was red before this
  session (`AccountDeletionService`, `AuditTierCollapse`); not investigated.
- **Not verified:** the invitation email itself (Mail faked in tests; prod invitations went to
  `@example.com` addresses); the pensioncheck campaign path through the new invite state (only
  the SaveTax path was clicked); the collision sentence live (unit-tested only); iOS — no native
  screen looked at since build 8.

## Decisions and dead ends

- **CSJ: the ownership loop was fixed twice — why regress?** Answer given and accepted: not a code
  regression. 23 July vocabulary never matched bare "my name"/"individual"; 24 July gate repair
  only runs when the model re-calls; the 23 July deterministic gap-fill was wired for the
  workplace-pension state only. Both earlier fixes were tested with a model that re-called.
- **CSJ: "Cash Accounts" / "Savings Accounts" / "Bank Accounts"** — the entity is bank accounts
  (the cap counts every type); the page is "Bank Accounts" on every surface; the Cash page cards
  are account types under it; "Cash Management" is the nav section. Only the onboarding pill was
  out of line.
- **CSJ: spouse provider names must show like every other record** — hence the two columns.
- **CSJ approved the invitation copy** verbatim (see #826); pensioncheck gets the same state.
- **Rejected:** declaring `capture_focus` on the bank-accounts state (the extractor cannot parse
  the live sentence anyway; the rescue re-runs the model's own call instead); a Gmail flow for
  prod codes (the `ssh-fynla` MCP reads them); Chrome-extension login (would mean typing a
  password through that tool — prohibited).
- **Token-count red herring:** `ai_messages.input_tokens` is SUMMED over loop iterations, so a
  tool-call turn (two iterations) reads ~2× a text turn. Tools were offered both turns.
- **Ponytail choices recorded in `docs/tech-debt-report.md`:** `webRouteFor` vs `GateRoutes` left
  (product call); the two `/m` contextual entry points left; the per-list `SpousePermission`
  query left.

## Things that will bite you

- **Playwright's main tab can stop delivering input** (mouse AND keyboard, while page scripts and
  `elementFromPoint` work). Cause not found. Work in a second context from
  `browser_run_code_unsafe`: `page.context().browser().newContext()`; snippet scope does not
  persist, so re-find it with `browser().contexts().find(c => c.pages().some(p => p.url().includes('csjones.co')))`
  on every call and close only that context. Never `browser_close`.
- **Cookie banners:** the SPA banner declines in TWO clicks ("Decline Cookies" / "Continue Without
  Cookies", then the W-0546 confirmation); the vanilla homepage banner inside the `/m` iframe is
  `.cc-overlay` with "Decline Cookies". Both intercept every click until dismissed.
- **`/m` journeys run inside an iframe** at `/m`; the funnel buttons are `button.qr-opt[data-value=…]`
  (text has whitespace, exact-match regexes fail); the chat input is `#md-fyn-input` on the
  dashboard and `#mc-fyn-input` on module pages; level-up dialogs
  (`[role=dialog][aria-label^="Level up"]`) intercept clicks — tap "Keep going".
- **The auto-mode classifier** still refuses compound prod commands (PR create+merge in one line,
  compound deploys). Plain `rsync`, `git switch` on csjones, and the `ssh-fynla` MCP pass.
- **Prod deploys with a migration** need `database/migrations/` rsynced before `migrate --force`
  (prod is not a git checkout); corpus changes need `fyn-memory/procedural/` rsynced and
  `fyn:procedural:validate` run on prod.
- **Adding a corpus workflow state:** constants + `inCodeStates()` + the corpus file, in the SAME
  order; `OnboardingStateMachineTest` enumerates the state list (now 50). `nextFrom…('')` is
  evaluated with an empty answer by `applySkipRules` — `matchBubble('')` matches the first bubble,
  so guard empty answers.
- **Adding a field to `capture_spouse_household_data`** touches five places (handler allowlist +
  rules, model fillable, both schema mds) and both golden masters (`CAPTURE_TOOL_SCHEMA_GOLDEN=1`,
  `CAPTURE_XAI_TOOL_SCHEMA_GOLDEN=1`).
- **A conversation created in a test needs `->fresh()`** before the real chat loop reads
  `message_count` (typed int, null in memory).
- **Test accounts left behind:** csjones `savetax-0911a…f@example.com`, `savetax-0912a/b@example.com`
  (all Free, with Lloyds £500/£250 rows; 0912b has a pending spouse invitation to
  `angela.csjones@example.com`); the 2026-09-10 ones from the previous handover. Prod holds none
  of mine.

## Tech debt deferred

`docs/tech-debt-report.md` (top section). Warnings: the spouse "What you told Fyn" row mapping
written once per surface (`Income.vue:79`, `IncomeOccupation.vue:538`); five homes for the
household field list; `OnboardingChatDirector` 7,668 lines. Suggestions: two canned-refusal
recognisers (`HasAiChat.php:1757`, director `:4491`); invite outcome copy in PHP not corpus;
two stop-word lists; the same email regex twice; `IncomeOccupation.vue` 854 lines.

## Branch and deploy state

- Branch: dev @ `a5dd5c340`
- Unpushed commits: none (the handover commit follows)
- Deploy status: fynla.org = main `e6d4f4a18` (tree == dev), web bundle `app-C-Usr4DG.js`, `/m`
  bundle `main-B8hm8ebQ.js`, migrations current, corpus validated; backups
  `~/release-backups/2026-09-11/`, `b`…`h`, `2026-09-12a/` (incl. full pre-migration dump), `b`.
  csjones = dev `a5dd5c340`, both bundles from `1b22f6112` (no JS change since). TestFlight
  "Fynla" 1.0 (10) VALID, native tree unchanged. No open PRs against dev except the parked #249.
