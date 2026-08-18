---
type: handover
mode: session-end
date: 2026-08-18
session: 1
repo: fynla
branch: fyn/crud-contract-followups
---

# Session Handover — 2026-08-18, Session 1

## Where things stand

Three tester-reported dead ends were diagnosed to root cause, fixed, tested and
deployed to csjones today: the TestFlight consent lockout, the budgeting refusal
loop, and the spouse-link failure. All three are verified by database and
server-side evidence; **none has been watched working in a browser by a human**,
because I hold no password for any affected account. Four pull requests merged to
`dev` (#700, #697, #698, #701) and csjones is at `b26e571cb` with the migration
run and the frontend bundle rebuilt.

The branch `fyn/crud-contract-followups` is fully merged into `dev` and has no
unpushed commits. One commit landed on `dev` that CSJ should look at
deliberately: `fbbc3a1f3`, which deletes four PreToolUse guard hooks.

## Priorities for the next session

1. **The guard removal is now on `dev`** — BLOCKED ON CSJ. `fbbc3a1f3` deletes
   the `prod-guard.sh` hook on the production SSH tool, the `.env` edit guard,
   and both oversight guards from `.claude/settings.json`, leaving only the
   dangerous-command check on `Bash`. CSJ asked for it to be committed; it then
   rode into `dev` with PR #701 because it sat on the same branch. Commit
   `5d469637f` (17 Aug) restored one of these after an accidental removal, so
   confirm this one is intended. The hook scripts are all still on disk — a
   one-line revert either way.

2. **Live browser verification of today's three fixes** — partly BLOCKED ON CSJ.
   Needs either a password for one of the eight consent-backfilled csjones
   accounts, or the two testers retrying: **Az** (user 80,
   `azlan.raj@phailanx.co.uk`, parked at the budgeting question in conversation
   67) and **Brett** (user 49, `isenbret@yahoo.co.uk`, parked at the spouse step
   in conversation 197). Both users' onboarding state is preserved, so their next
   message exercises the fix directly. I verified server-side that Az's next turn
   now carries `set_expenditure` and the "Budgeting" label.

3. **Full Pest suite has not been run end to end today.** Targeted families only,
   per the lean cadence rule — 1,453 passed / 3 skipped / 0 failed across
   onboarding, AI, agents and unit at `cd20187b9`. Architecture, Integration and
   Browser suites were not run against the final state. Worth one full pass
   before anything goes `dev → main`.

4. **The advice-side handoff carries no `capture_*` tools.**
   `OnboardingChatDirector::captureToolSet()` has every `create_*`/`update_*`
   write tool but none of the five grouped-extract capture tools
   (`capture_personal_details`, `capture_spouse_details`, `capture_dependants`,
   `capture_work_details`, `capture_pension_history`). So "my wife is Meg, born
   1975" in advice mode routes through `create_family_member`/`update_profile`
   instead. Possibly correct, possibly the same class of gap as today's bugs —
   not investigated, needs a decision.

5. **The `lint` job now runs its design-policy checks for the first time.** The
   runner had no ripgrep, so `policy-lint.sh` died on "rg: command not found" for
   every pull request touching `resources/` — the guard was not failing, it was
   not running. Fixed in `8cfcfa071`. Expect the next frontend pull request to
   surface palette or emoji violations that were never being caught.

6. **Golden-master consolidation** — `August/August17Updates/NOTE-golden-master-consolidation.md`,
   still untouched. Three suites, three capture flags, two fixture directories
   holding three identically-named files with different content. A schema edit
   needs two re-records and nothing tells you so.

7. **Carried from this morning's update doc** (`UPDATE-2026-08-18.md` §What to do
   next): field maps for the renamed property/mortgage payloads; C5 — whether the
   no-fabricated-success guard becomes mechanical (CSJ deferred 17 Aug); and
   `update_record` still has no recapture guard.

8. **iOS**: TestFlight build 7 is current and nothing today needs a build 8 — all
   fixes are server-side. The `test-and-build` CI job is red on every pull
   request (missing in-app purchase products in App Store Connect, CSJ's action,
   not code — see `reference_native_iap_products_missing_in_asc`).

## Context to load

- `August/August18Updates/REPORT-2026-08-18-capture-tool-coverage.md` — today's
  three bugs with full evidence trails, the fixes, the tests, and the gaps I
  explicitly did not claim. Read this first.
- `August/August18Updates/UPDATE-2026-08-18.md` — the morning's CRUD handler
  contract work (recapture guard, post-save link) and its own "what to do next".
- `August/bugs/fynBug18Aug.md` — the five live capture defects fixed overnight;
  useful for telling today's bugs apart from those.
- `app/Services/Onboarding/OnboardingPromptBuilder.php:101` — `toolsForFocus()`,
  now the one focus → tools map, and `focusLabel()` below it, now the one label
  map. Both were the site of today's main bug.
- `tests/Feature/Onboarding/CaptureStateToolCoverageTest.php` — the enumeration
  that walks all four capture mechanisms. If a new focus or delegated state is
  added, this is what fails.
- `August/August17Updates/NOTE-golden-master-consolidation.md` — for priority 6.

## Completed this session

- **`bf04bae49`** — `ai_chat` consent had one grant path (registration since
  2026-05-05), so spouse-created and legacy accounts were permanently locked out
  of Fyn on every surface with no way to grant it. Both spouse creators now
  record it; a backfill migration grants it to accounts holding no row and leaves
  withdrawn rows withdrawn. Eight csjones accounts unlocked, including the
  TestFlight tester (user 49).
- **`c45dff8df`** — the onboarding front door: `path_choice` now has a third
  "Something else" bubble and a free-text escape into advice Fyn.
- **`cd20187b9`** — `budgeting` was aliased to `savings` in every focus map, so
  Fyn asked for monthly spending and ran a Cash & Savings capture with no
  `set_expenditure`; the model's only exit was the prompt-injection refusal, then
  "Sorry, I didn't catch that", forever. Fixed, plus two silent siblings (`estate`
  missing `create_business_interest`, `goals` missing `create_life_event`), plus
  the extractor that would have written spending figures as savings accounts.
  Also: `SpouseLinkingService` now looks up `withTrashed()` — it was INSERTing
  into a guaranteed 1062 duplicate-key violation on soft-deleted emails — and a
  failed spouse write reports what happened instead of re-asking for details the
  user already gave.
- **`6a6e7c6ab`, `8cfcfa071`** — stamping tests corrected; CI lint gains ripgrep.
- Merged **#700, #697, #698, #701** to `dev`; deployed csjones twice (code,
  migration, SPA bundle, caches).

## Verification state

- 1,453 passed / 3 skipped / 0 failed at `cd20187b9` — onboarding, AI, agents,
  unit. Pint clean.
- csjones after the consent deploy: `users still missing ai_chat: 0`, user 49
  `YES`.
- csjones after the budgeting deploy, for the stuck user: focus `budgeting`,
  label `Budgeting`, tools `set_expenditure, update_profile, update_record`,
  block no longer says "Cash & Savings".
- **Not verified:** anything in a browser, on any surface, by anyone. Also not
  run: the full Pest suite, Architecture/Integration/Browser suites against the
  final state, and `/m` (nothing this round touched `resources/mobile/`, but that
  is reasoning, not evidence).

## Decisions and dead ends

- **Consent for legacy accounts was granted, not prompted for.** The alternative
  was a first-use consent prompt on three surfaces. I went with the backfill
  because it extends the position `AuthController` already states in its own
  comment — the journey is chat-driven, so consent is given at account creation
  and withdrawal still flows through `PUT /api/user/consents`. Flagged to CSJ in
  PR #700 as a compliance call they can reverse.
- **Rejected a `User::created` observer** for the consent grant. It would have
  been one place, but it fires for every factory-made user in ~5,000 tests, and
  `UserConsent` is `Auditable`, so it would have added an audit row per test user
  and broken the tests that construct a consent-less user deliberately.
- **`NetWorthControllerTest` failing on #700's first CI run was a flake** — it
  passed locally ten times and passed on the re-run. Do not go hunting for it.
- **`AssetCaptureEntityExtractorTest` asserted the bug**: it required
  `toolNameForFocus('budgeting') === 'create_savings_account'`. Corrected to
  `null`. This is the one case today where changing an assertion was right — the
  assertion encoded the alias, not the behaviour.
- **The morning's "all five defects fixed" claim did not cover today's bugs.**
  They are a different root cause and were never touched. Worth remembering
  before repeating any "fixed everywhere" claim: the only reason today's
  enumeration found the estate and goals gaps is that I stopped assuming and
  walked every focus.

## Things that will bite you

- **The state machine's transition table is private**; both new enumeration tests
  reach it via `ReflectionMethod`. If it gains a `delegated` or `grouped_extract`
  state, `CaptureStateToolCoverageTest` fails by design — add the state to its
  table rather than deleting the assertion.
- **Episodic blobs are the fastest debugging tool for any Fyn turn.**
  `storage/app/private/episodic/YYYY/MM/DD/<conv>/<msg_id>.md` on the server holds
  the exact prompt and tool list the model received. Today's root cause came
  straight out of one.
- **`vitest` needs node 20** — `export PATH="$HOME/.nvm/versions/node/v20.19.5/bin:$PATH"`.
- **`git commit -F`** for messages mentioning protected paths; the commit-message
  guard false-positives.
- The working tree has three untracked/modified `workforce/ops/` files
  (`log/2026-08.jsonl`, `log/myrtle-brief.log`, `reports/brief-2026-08-18.md`)
  generated by the workforce tooling, not by this session. Left alone
  deliberately.

## Branch and deploy state

- Branch: `fyn/crud-contract-followups` — fully merged into `dev`, no unpushed
  commits.
- `dev` at `b26e571cb`. `main` untouched today and still far behind.
- Deployed: csjones (`https://csjones.co/fynla`) at `b26e571cb` — code pulled,
  migration run, `public/build/` rebuilt and uploaded, caches cleared, config
  re-cached. Production (`fynla.org`) had nothing deployed today.
- TestFlight: build 7, unchanged. No build 8 needed for any of today's fixes.
