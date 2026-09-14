---
type: handover
mode: session-end
date: 2026-09-14
session: 2
repo: fynla
branch: dev
---

# Session Handover — 2026-09-14, Session 2

## Where things stand

The application-mapping programme is five maps in: overview, 17 emails, 01 auth (session 1), and now 02a onboarding (this session), committed on `dev` at `ce88f3d63`. INDEX row 02 was split: 02a onboarding is mapped; 02b Save Tax and Pension Check campaigns is not started. Fourteen new mapping bugs (MB-23 to MB-36) were raised on top of the morning's twenty-two; none of the thirty-six has been fixed, and none of the decisions has been taken. Nothing was deployed.

## Priorities for the next session

1. **BLOCKED ON CSJ — decisions on the mapping bugs.** Ask at the start, do not start fixing unasked. The register at the top of `September/September14Updates/mappingBugs2026-09-14.md` lists every pending call. In priority order, newest first because two are live user-facing breaks:
   - MB-23 (Broken, both surfaces): a user who taps "Something else" or types at Fyn's front door and then asks anything gets no reply, because `ConversationModeResolver` routes by conversation source before the step. Decision: dispatch by the user's step (the canonical three-part predicate), or open a new conversation on pause.
   - MB-25 (Dead end): a paused journey/focus walk can never be resumed on any surface; only the campaign path resumes. Decision: offer "Continue setting up" to paused users, where and on which surfaces, or drop the parked-step promise.
   - MB-30, MB-31, MB-29 (Dead): seven onboarding and journey routes without callers, the permanently empty Journeys page, the wizard's journey mode that renders eight non-existent components, five dead step branches with an unimported class. Decision: delete with MB-01, or restore.
   - MB-32 (Does not make sense): `users.life_stage` is written with three vocabularies and overrides every wizard route.
   - MB-34 (Rule 2): 46 hardcoded pound figures in the wizard's learning copy.
   - Still pending from session 1: MB-14/MB-15, MB-21, MB-08, MB-06, MB-01+MB-09, MB-10, MB-11.
2. **Map section 02b, the Save Tax and Pension Check campaigns**, with `/app-map campaign`. Load `fyn-architecture` first. Scope: the public funnels (`routes/web.php:625-720`, `public/pages/savetax*.php`, `pensioncheck*.php`), `config/onboarding.php:79-86` campaign map, the `campaign_*` and `campaign2_*` states (`OnboardingStateMachine.php:110-184, 208-362, 1680-2388, 2545-2625`), the director's campaign handlers (`OnboardingChatDirector.php:1106-1510, 3288-3560, 4173-5540, 5800-6535`), section advice, synthesis, spouse invitation, the Tax Strategy terminal (`routes/api.php:380`), campaign re-entry in `AiChatController::startOnboarding`, `SaveTaxEstimateService`, `FunnelIncomeBand`, the campaign tool schemas under `fyn-memory/procedural/tool_schema/campaign/`, and `tests/E2E/journeys/user-reported-campaign-regressions.spec.js`. The 02a map's § 2.3 table and § 4 coverage list say exactly which lines were not read. Do not re-map the base states.
3. **Then sections 03 onwards in index order** (`docs/app-map/INDEX.md`), one skill run each.
4. **Fix the mapping bugs CSJ approves**, one MB per branch and PR to dev, verified on web and `/m`. Of the new ones, MB-24, MB-26, MB-27, MB-28, MB-33, MB-35 need no decision. MB-24 (`/m` "Something else" answers "This step cannot be skipped") and MB-23 are the most user-facing. `tests/Feature/Onboarding/PausedUserMessageRoutesToAdviceTest.php` is the skipped contract test to unskip with the MB-23 fix.
5. **Carried, unchanged:** the iOS items and everything else in `CSJTODO.md`.

## Context to load

- `.claude/skills/app-map/SKILL.md` and `report-template.md` — the process every section run follows; this was the fourth use and the template held.
- `docs/app-map/INDEX.md` — the manifest; row 02a is done, 02b is next.
- `docs/app-map/02-onboarding.md` — the map just written; § 2.3 lists the state table, § 4 lists exactly which director and state-machine line ranges were not read (those are 02b's), § 4 also lists the test accounts.
- `September/September14Updates/mappingBugs2026-09-14.md` — MB-01 to MB-36 with the decisions register; append, never renumber.
- `docs/app-map/01-auth-registration-sessions.md` — depth and evidence style to match (02a follows it).
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_savetax_verify_sequence_canonical.md` — the campaign verify sequence CSJ fixed; 02b must describe what the code does and note where it differs from this.

## Completed this session

- `docs/app-map/02-onboarding.md`: the Fyn onboarding flow (dispatch, corpus state table with every journey/focus state, the base facts walk, verify loop, pause and resume, completion), the legacy wizard's five modes, the journeys and life-stage APIs, data columns, background machinery, tests, findings, coverage. Driven live on web (user 86: full journey walk to done) and `/m` (user 87: full focus walk to done; user 88: front-door pause check; user 89: web returning-user check).
- Four Excalidraw diagrams `docs/diagrams/map-onboarding-{request-flow,fyn-base-walk,start-resume-pause,wizard-modes}.excalidraw`, mirrored to `fynlaBrain/Diagrams/` and indexed.
- 29 screenshots in `docs/app-map/screenshots/02-onboarding/`.
- MB-23 to MB-36 appended with register rows; bugs file header now names both runs; vault copy refreshed.
- One skipped contract test for MB-23.
- INDEX row 02 split into 02a (mapped, `e4ddc4e3f`) and 02b (not started).
- Commit `ce88f3d63` on dev (pushed with this handover).

## Verification state

- `./vendor/bin/pest tests/Feature/Onboarding tests/Unit/Services/Onboarding` plus the campaign, seam, funnel, gamification and milestone files listed in the map § 2.11: 996 passed, 3618 assertions, at `e4ddc4e3f`.
- `npx vitest run resources/mobile/mixins/__tests__/onboardingChat.spec.js tests/frontend/mobile/onboardingChatEvents.test.js`: 31 passed.
- `tests/Feature/Onboarding/PausedUserMessageRoutesToAdviceTest.php`: 1 skipped (by design, MB-23).
- Playwright, local build: web login, returning-user chat open, front door, Something else then a question, journey walk (Protecting What Matters, single, full-time, income verify, expenditure verify, protection capture and verify, add more, done), reload resume, `/onboarding` picker and one saved wizard step, `/onboarding/full`, `/onboarding/estate`, `/onboarding/journey/budgeting` with and without a stored life stage, `/planning/journeys`; `/m` login with code, dashboard auto-start, focus walk (Savings, married with spouse skipped, retired 2020, expenditure), expenditure and savings verify pills, dock resume, savings capture, add more, done; `/m` front-door Something else and a typed question.
- Not verified: iOS anything (read only); the verify "No, change something" edit turn; a spouse actually captured (would send a real email); the dependants detail step; web free-text front-door exit; `/m` message after a pause (MB-24 blocks the tap); the wizard beyond one step; the `stage=` registration branch; the whole campaign path; production.

## Decisions and dead ends

- **Section 02 was split into 02a and 02b** because the onboarding services are 17,000 lines and the campaign states, handlers and funnels are a deliverable of their own. The skill allows this ("split the section into two maps and say the second is not started"). CSJ has not commented; it is a reversible index change.
- **Test accounts were created by tinker, not through registration**, with consents inserted at `version = v1.0` (`UserConsent::CURRENT_VERSIONS`; a `1.0` version fails `hasConsent` and the chat returns 403 `consent_required`). Registration itself was mapped in section 01, so this shortcut lost nothing. The reference route into onboarding was reproduced by navigating to `/dashboard?openFyn=journey`, which is exactly what `Register.vue:510-513` does.
- **`/m` was driven two ways:** the real `/m/app/login` with the emailed code (user 87), and a Sanctum token injected into `localStorage['m_scaffold_token']` on any `/m/app/*` page (user 88). Both worked; the token path saves four turns.
- **User 86's `life_stage` was cleared by me** after the picker set it, to prove that the wizard's mode is decided by that column (MB-32). It is a test fixture I created, not a real user's row.
- **A failing-then-skipped contract test was written rather than a red test or nothing.** The section's gap (dispatch after pause) is a defect, so a green test would lock the bug and a red one would break the suite; the skipped test carries the MB id and the behaviour a fix must produce.
- **Dead end: Playwright refs go stale after every streamed turn**; every click needs a fresh snapshot, but the chat textbox ref survives, so "click bubble, wait for text, type into the textbox" can be chained in one response.
- **Dead end: the `/m` level-up celebration dialog intercepts clicks** (Level 2 fired after the family review); dismiss with "Keep going" before the next bubble.
- **Dead end: `browser_take_screenshot` fails silently-ish if the folder does not exist** — create `docs/app-map/screenshots/<section>/` first. One screenshot had to be retaken with a fourth account.
- **Not done on purpose:** no fixes, no deletions, no deploy, no iOS build, no reading of the campaign code beyond its method index.

## Things that will bite you

- Test accounts left behind, all password `MapTest2!`: user 86 `map-onb-2026-09-14@example.com` (completed, journey protection, one Aviva life policy, `life_stage` null after my reset); user 87 `map-onb-m-2026-09-14@example.com` (completed, focus savings, one Halifax account, retired); user 88 `map-onb-m2-2026-09-14@example.com` (at `path_choice`, one conversation); user 89 `map-onb-w2-2026-09-14@example.com` (never started, one plain advice conversation). Sanctum tokens `m-map`, `m-map2` exist. Delete any of them freely; nothing depends on them. User 85 from session 1 is still there too.
- The local `.env` still sends real email through the production mail host; every test login sent a code email to an example.com address. Fetch codes from `email_verification_codes` (`EmailVerificationCode::where('user_id', $id)->latest()->first()->code`).
- The Playwright tab is left signed in as user 89 on the web dashboard; the earlier `/m` session (user 88's token) is in the same browser profile's localStorage.
- The web onboarding chat renders nothing for an error `content` event that arrives without `done` (that is why MB-23 shows an empty reply); do not mistake a blank reply for a slow model.
- `PathChoiceHasAWayOutTest` and `CampaignReentryExitTest` assert the pause nulls the step and stop there; they will stay green with MB-23 unfixed.
- The workforce ops files (`workforce/ops/log/*`, `workforce/ops/reports/brief-*.md`) are still uncommitted; they belong to the daily brief agent, not to the mapping work. Left alone both sessions today.
- Dev servers are still running (`artisan serve` on 8000, Vite on 5173).

## Tech debt deferred

No application code changed this session; the only code file added is the skipped Pest test, which follows `tests/CLAUDE.md` (`declare(strict_types=1)`, `it()`, `RefreshDatabase`). Everything found is in the bugs file.

## Branch and deploy state

- Branch: dev
- Unpushed commits: `ce88f3d63` plus this handover commit, pushed together.
- Deploy status: nothing deployed today; prod main and dev are as the 2026-09-12 handover left them.
