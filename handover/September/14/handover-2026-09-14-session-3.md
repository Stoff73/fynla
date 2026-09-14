---
type: handover
mode: session-end
date: 2026-09-14
session: 3
repo: fynla
branch: dev
---

# Session Handover — 2026-09-14, Session 3

## Where things stand

The application-mapping programme now has six maps on `dev` (overview, 17 emails, 01 auth, 02a onboarding, 02b campaigns) and 55 mapping bugs raised, none fixed, none decided. Section 02b (Save Tax and Pension Check campaigns) landed this session at `86ef97b41`, driven live end to end on web and `/m`. CSJ has set the focus for the next session: **fix the bugs found in the mappings.** Nothing was deployed today; no application code has changed since the 2026-09-12 release.

## Priorities for the next session

The focus is fixing, not mapping. Sections 03 onwards stay in the index as "not started" and wait.

1. **BLOCKED ON CSJ — the decisions in the register at the top of `September/September14Updates/mappingBugs2026-09-14.md`.** Ask at the start, in this order, because the answers change which fixes are even valid:
   - MB-23 (paused user's next message gets no reply): dispatch by the user's step, or open a new conversation on pause.
   - MB-44 (Save Tax has no way back after "No thanks"; the promised Actions tile can never show): allow Save Tax re-entry, or drop the tile and the comment.
   - MB-37 (web savings verify page shows no accounts): open `/net-worth/cash` instead, or make `/savings` list accounts.
   - MB-45 (Tax Strategy: dead next-step routes on web, key drift on `/m` and iOS, two list sources): which list is canonical, the composed plan Fyn voices or the raw calculator list.
   - MB-25 (paused journey/focus walk can never resume): offer "Continue setting up", where and on which surfaces.
   - MB-38 (Pension Check hardcoded bands, no income context or cross-check): extend to Pension Check or accept the drift.
   - MB-54 (Save Tax drops the workplace pension pot and provider): add the pot loop to Save Tax, or extend the prompt.
   - MB-53 (Pension Check re-entry re-asks the pensions section): data-presence skips, or accept the repeat.
   - MB-49 (a natural "X, not Y" correction produced no write): accept as model behaviour, or add a deterministic parser.
   - Dead code and copies: MB-01 + MB-09 + MB-29/30/31 (delete or restore), MB-39 (four non-campaign "campaign" pages), MB-41 and MB-42 (two uncalled endpoints), MB-43 (charitable-giving remnants), MB-08, MB-10, MB-11, MB-06.
   - Data and copy: MB-32 (`life_stage` vocabularies), MB-34 (46 hardcoded tax figures in wizard copy), MB-40 (Save Tax headline counts hidden lines), MB-14/15, MB-21.
2. **Fix the bugs that need no decision, one MB per branch and PR to dev, verified on web and `/m` (Rule 19), functional before cosmetic.** Suggested order by user impact:
   - MB-47 — web pages under the chat are not refetched after a Fyn write (investments after an edit; expenditure verify shows £0). Extends MB-27; fix them together. Evidence in `docs/app-map/02b-campaigns.md` § 2.5.
   - MB-48 — spouse verify page empty for a non-working spouse: `UserProfileService::spouseIncomeSources()` `:488-501` returns null before reading the household row. Web and `/m` read the same payload.
   - MB-24 — `/m` "Something else" at the front door answers "This step cannot be skipped." (02a § 2.6).
   - MB-18, MB-19, MB-20 — `/m` login has no two-factor step and no restore branch; web restore modal never shows the MFA field (01).
   - MB-50 — `/m` expenditure verify screen shows a derived total, never the entered figure.
   - MB-26, MB-28, MB-33, MB-35 (02a), MB-17, MB-22, MB-16.
   - MB-46 (corpus prompt hardcodes "£40,000"), MB-51 (four copy strings and the `assets` vs `pensions` key lookup), MB-52 (`/m` collapses multi-paragraph advice; check `resources/mobile/utils/fynText.js`), MB-55 (Save Tax sign-in link, subject to MB-44).
   - Unskip `tests/Feature/Onboarding/PausedUserMessageRoutesToAdviceTest.php` with the MB-23 fix once decided.
3. **Do not resume mapping (section 03 dashboard onwards) until CSJ says so.** The index rows are ready when it restarts.
4. **Carried, unchanged:** the iOS items and everything else in `CSJTODO.md`.

## Context to load

- `September/September14Updates/mappingBugs2026-09-14.md` — every MB with evidence, and the decisions register at the top. This is the work list. Append, never renumber.
- `docs/app-map/02b-campaigns.md` — the campaign map; § 2.5 and § 2.8 hold the walk-by-walk evidence for MB-37 to MB-55, § 4 lists what was not read or driven.
- `docs/app-map/02-onboarding.md` — the onboarding map behind MB-23 to MB-36; § 2.2 (dispatch) and § 2.6 (pause and exits) for MB-23/24/25.
- `docs/app-map/01-auth-registration-sessions.md` — behind MB-18 to MB-22.
- `.claude/skills/fyn-architecture/SKILL.md` — Rule 20 applies to every Fyn fix: enumerate every mechanism before changing one.
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_savetax_verify_sequence_canonical.md` — the verify sequence CSJ fixed; MB-37/47/48/50 fixes must keep it.

## Completed this session

- `docs/app-map/02b-campaigns.md` (520 lines): funnels, estimate pages, compact registration and hand-off, campaign selection at Fyn start, both section walks state by state, advice and synthesis, spouse invitation, terminals, Pension Check re-entry, Tax Strategy page on all three clients, tests, findings, coverage.
- Driven live: web as user 90 (Save Tax, married, spouse with no income, all four asset types) from the advert link through every verify page, the verify edit, the short-date confirm, the invitation decline, the terminal, the Tax Strategy page and mark-done; `/m` at 390 px as user 91 (Pension Check framed in the `/m` host, self-employed, in their 50s) from the funnel through the hand-off into `/m/app`, every verify screen, the terminal, the retirement page, then re-entry to the recap and one gap-walk step.
- Five Excalidraw diagrams `docs/diagrams/map-campaigns-{funnel-to-fyn,start-and-reentry,savetax-walk,pensioncheck-walk,tax-strategy-terminal}.excalidraw`, mirrored to `fynlaBrain/Diagrams/` and indexed.
- 36 screenshots in `docs/app-map/screenshots/02b-campaigns/`.
- MB-37 to MB-55 appended with register rows; the bugs file mirrored to the vault; INDEX row 02b set to mapped.
- Commit `86ef97b41` on dev.

## Verification state

- Scoped Pest run (all campaign, re-entry, hand-off, funnel, estimate, state-machine, tax-strategy and direct-write capture files): 658 passed, 95,560 assertions, 318 s, at `6b365dddc` (HEAD moved only by docs since).
- Playwright, local: see `docs/app-map/02b-campaigns.md` § 4 for the exact list of flows driven and not driven.
- Not verified: the Pension Check funnel at desktop width; the Save Tax walk on `/m`; the dual-earner spouse path; "Yes, invite them" (sends a real email); "No thanks" at the consent gate; "Something's changed" at the re-entry recap; the `/m` Tax Strategy screen; iOS anything; production. BS-26/27/28 and the two E2E specs were not run.

## Decisions and dead ends

- **02b's scope stops at the Tax Strategy page.** The thirteen strategy classes and `TaxStrategyMath` are section 13's; the Marketing Pipeline "campaigns" table and the lifecycle email campaigns are different concepts (16 and 17). The four Vue pages `/biggerpension`, `/paymortgage`, `/managedebt`, `/wealth` are not campaigns (MB-39).
- **The mapping run reports and does not fix.** Every defect this session went into the bugs file. The next session reverses that: fix, do not map.
- **Test accounts were created through the real funnel forms**, not tinker, so the hand-off and mapper were exercised. Do not "clean up" users 90 and 91 before reading § 4 if you want to reproduce MB-47/48/50/53; nothing else depends on them.
- **Dead end: the level-up celebration dialog on `/m` intercepts every click** — dismiss with "Keep going" first (bit twice today).
- **Dead end: `browser_wait_for` with `textGone` adds a 5-second timeout after the sleep** — use plain `time` waits and re-snapshot; a delegated capture turn with the xAI provider took up to 6 minutes 36 seconds today.
- **Dead end: Playwright snapshot files must be written under `.playwright-mcp/`**, not the scratchpad; that folder is gitignored.
- **The savings verify route is `/savings`**, which on web is the Savings & Emergency Fund page with an Open Banking placeholder, not the Bank Accounts page (`/net-worth/cash`). That is MB-37; do not assume the config is wrong until CSJ picks the page.
- **The verify edit's honesty gate is right**; the failure on natural phrasing (MB-49) is upstream of it. The rephrase "Change the current value from £25,000 to £27,000" landed.
- **`sessionStorage['fynla.signup_source']` is first-touch by design** — user 91 is attributed to linkedin although the Pension Check funnel was opened with `utm_source=instagram` in the same browser profile. Not a bug.

## Things that will bite you

- Test accounts, password `MapTest2!`: user 90 `map-camp-w-2026-09-14@example.com` (completed Save Tax; married, `single_earner_couple`; savings 237-238, investment 123 at £27,000, dc pension 71, household row 1, `tax_isa_topup_vs_psa` marked done; Sanctum token `map-camp`); user 91 `map-camp-m-2026-09-14@example.com` (completed Pension Check, then re-entered and left at `campaign_pension_contribs` with `active_campaign = pensioncheck`; dc pension 72, db pension 32, state pension 70, retirement profile, three `pension_input_history` rows, two conversations; tokens `map-m` and the `/m` login token). Users 85 to 89 from sessions 1 and 2 are still there too.
- The local `.env` still sends real email; both registrations sent code emails to example.com addresses. Codes come from `pending_registrations.verification_code` before verification and `email_verification_codes` after.
- The Playwright tab is on `/m/app/dashboard` signed in as user 91 (`m_scaffold_token` in localStorage); the web SPA session is signed out.
- Delegated capture turns with `AI_PROVIDER=xai` took between 30 seconds and 6.5 minutes each on web today, with nothing in `laravel.log`; budget for it when reproducing MB-47/49.
- The workforce ops files (`workforce/ops/log/*`, `workforce/ops/reports/brief-*.md`) are still uncommitted and belong to the daily brief agent; left alone all three sessions.
- Dev servers were left running (8000 and Vite 5173; `public/hot` is from 10:09).

## Tech debt deferred

No application code changed this session; only documentation, diagrams and screenshots. The `tech-debt-session` pass is not applicable. Everything found is in the bugs file.

## Branch and deploy state

- Branch: dev
- Unpushed commits: `86ef97b41` plus this handover commit, pushed together.
- Deploy status: nothing deployed today; prod main and dev are as the 2026-09-12 handover left them.
