# Estate / Inheritance-Tax Campaign — Implementation Plan

*2026-07-06. The execution plan for `estate-campaign-spec.md` — read the spec FIRST; this plan assumes its decisions verbatim. Grounded against dev `9c9e7d2`. Four slices, one PR each (A→B→C sequential, D is the live gate). Work flows feature branch → dev; each slice branch stacks on the previous (retarget stacked PRs to dev BEFORE deleting base branches — the #608/#609 lesson). Rule 14 (LOOP UNTIL CORRECT) governs Slice D: the gate walks are the contract; no partial success.*

**Binding references (open them while coding):**
- `July/July3Updates/campaign-playbook.md` §2 — the F1–F15 formatting standard. EVERY prompt/ack/advice line complies.
- `July/July6Updates/pensionCampaign.md` — the current-state map of the template you are cloning (state table shapes, verify sub-flow, SSE contract).
- `July/July4Updates/pensioncheck-patch-notes-technical.md` — what the last clone got wrong first time.
- CLAUDE.md Rules 2 (TaxConfigService only), 8/11 (palette), 9 (no acronyms — write "Inheritance Tax", "Lasting Power of Attorney"; ISA is the only allowed abbreviation), 12 (no scores), 14, 15 (no icons), 19 (/m parity).

**Campaign id `inheritancecheck`, states `campaign4_*`, entry `base_personal`, terminal → `/estate`.**

---

## Slice A — substrate wiring (small PR, config + one-line seams)

**Branch `inheritancecheck-a` off dev.**

| # | File | Change |
|---|---|---|
| A1 | `config/onboarding.php` `campaign_map` | Add `'inheritancecheck' => ['selection' => 'inheritancecheck', 'entry' => 'base_personal', 'reentry' => true, 'reentry_entry' => 'campaign4_existing_recap']`. NOTE: `OnboardingStartCampaignMapTest` asserts every `entry`/`reentry_entry` is a real state id — this PR will be red on `campaign4_existing_recap` until Slice C lands the state. **Ship A with `reentry => false` and no `reentry_entry`; Slice C flips it.** (The pensioncheck build hit exactly this ordering.) |
| A2 | `app/Http/Middleware/RedirectPhoneToMobile.php` `CAMPAIGN_PREFIXES` | Add `'inheritancecheck'` (audit P1 lesson). |
| A3 | `app/Services/Mobile/NextActionsService.php` `CAMPAIGN_AFFINITY` | Add `'inheritancecheck' => 'estate'`. |
| A4 | `app/Http/Requests/RegisterRequest.php` | Add `funnel_answers.home`, `.other_assets`, `.will`, `.gifts`, `.children` — all `['nullable','string','max:20']`. (`campaign` sanitisation derives from campaign_map — nothing to do.) |
| A5 | `resources/mobile/mixins/onboardingChat.js` `ONBOARDING_NAV_ROUTES` | Add `'/estate'`, `'/net-worth'`. Keep the comment's "Keep in step with campaignVerifyConfig()" instruction true. |
| A6 | Tests | Extend `tests/Feature/Mobile/MobileScaffoldTest.php`: `/inheritancecheck` + `/inheritancecheck/plan` phone-redirect + `isFramableTo` + query-string preservation (copy the pensioncheck block added in #612). New `tests/Unit/Services/Mobile/CampaignAffinityTest` case: `inheritancecheck` funnel stamp boosts `module==='estate'` items. RegisterRequest: unknown-key rejection unchanged; new keys accepted (extend `CampaignAuditFixesTest`-style register tests or the existing register suite). |

**Slice A done =** suite green; `curl` phone-UA GET `/inheritancecheck` on local returns the `/m?to=` redirect (the route itself 404s until Slice B — the middleware redirect still fires; assert via the test, not curl, if local routing complains).

## Slice B — public surfaces (funnel, plan page, estimate service, homepage card)

**Branch `inheritancecheck-b` off `inheritancecheck-a`.**

| # | File | Change |
|---|---|---|
| B1 | `app/Services/Marketing/EstateEstimateService.php` (NEW) | Clone `PensionEstimateService`'s class shape exactly (final class, constructor-injected `TaxConfigService` only, `estimate(array $answers): array`, private banded consts labelled "marketing midpoints, not tax values"). Midpoints + allowance logic + zero-case per spec §7.2. Tax keys ONLY: `getInheritanceTax()['nil_rate_band']`, `['residence_nil_rate_band']`, `['standard_rate']` (in-code fallbacks 325000 / 175000 / 0.40). Docblock: RNRB taper deliberately ignored (bands max below £2m); marriage doubling states the transferable-allowance assumption. |
| B2 | `public/pages/inheritancecheck.php` + `public/pages/js/inheritancecheck.js` (NEW) | Clone `pensioncheck.php`/`pensioncheck.js` structurally: same chrome (qr-header, progress, `savetax.css` reuse), the 6 questions from spec §7.1, answers object with `campaign: 'inheritancecheck'` baked in at line 1, auto-advance 220ms singles, localStorage `inheritancecheck_answers` (try/catch), `persistAndGoToPlan()` → `/inheritancecheck/plan?from=inheritancecheck&spouse=…&children=…&home=…&other_assets=…&will=…&gifts=…`, **utm capture block** (S6 pattern — same key `fynla.signup_source`, same 6-platform allowlist, first-touch). |
| B3 | `public/pages/inheritancecheck-plan.php` + `public/pages/js/inheritancecheck-plan.js` (NEW) | Clone the pensioncheck plan pair: server preamble reads the 6 `$_GET` keys, direct-visit representative fallback (`spouse=yes, children=yes, home=300k_500k, other_assets=50k_250k, will=no, gifts=no`), try/catch → null; inject `window.INHERITANCECHECK_ESTIMATE` with the full HEX flag set; JS guards `EST` null → static defaults; **`esc()` before every `innerHTML`**; hero = "Your estate could face an inheritance tax bill of roughly £X" with the `estimated_iht === 0` alternate hero (spec §7.2); register card (`signup_source: storedSignupSource() || undefined` in the POST body); "Already with Fynla? Log in" → `/login?redirect=` + encoded `/dashboard?openFyn=journey&from=inheritancecheck`; verification hand-off via `sessionStorage['fynla_pending_verify']` → `/register?from=inheritancecheck`. Cache-buster `?v=1` on both new JS files' script tags. |
| B4 | `routes/web.php` | Clone the pensioncheck block (:617-642): `/inheritancecheck/plan` BEFORE `/inheritancecheck`, both inside `Route::middleware('redirect.authed')`, ob_start include, `Cache-Control: public, max-age=300, stale-while-revalidate=60`. Declare before the SPA catch-all. `RebasePublicPageUrls` is global — nothing to do. |
| B5 | `public/pages/index.php` | `feature-inheritancecheck` card beside the pensioncheck card — same try/catch failure tolerance (service throw → card without figure), representative-persona figure, copy DRAFT per spec §8, CTA `/inheritancecheck`. Same visual pattern as the sibling cards — NO new gradients/colours (Rule 16 — a "distinguishing" gradient was reverted on the pensioncheck card in review). |
| B6 | Tests | `tests/Unit/Services/Marketing/EstateEstimateServiceTest.php`: married+children+home → doubled NRB + RNRB included; single renter → NRB only; zero-case; unknown bands default safely; all values change when the seeded config changes (no hardcoding — assert against `TaxConfigService`, seed `TaxConfigurationSeeder`). `tests/Feature/PublicPages/InheritancecheckRoutesTest.php`: clone `PensioncheckRoutesTest` — plan-before-funnel ordering, not served by the SPA catch-all (no `id="app"`), redirect.authed bounce, no query echo in HTML (XSS assertion: request with `home=<script>` → response contains neither). |

**Slice B done =** funnel + plan browsable locally end-to-end (fill all 6 questions → personalised £ figure renders → register card posts `funnel_answers` incl. campaign + signup_source); suite green.

## Slice C — the walk (states, corpus, tool, recap, advice, synthesis, terminal)

**Branch `inheritancecheck-c` off `inheritancecheck-b`.** The big slice — clone the campaign2 shapes 1:1.

### C1. The one new tool: `capture_will_status`

The only new tool (spec §5 will-state rationale: `create_will` requires `executor_name` so it cannot record "no will"; a `wills` row with `has_will=false` IS the recorded answer).

| File | Change |
|---|---|
| `fyn-memory/procedural/tool_schema/estate/capture_will_status.md` + `.xai.md` (NEW — BOTH providers, `.xai.md` is LIVE) | `procedure_id: 'estate.tool.capture_will_status'`, `module: estate`. Description (governs model behaviour — write it like `capture_state_pension`'s): *"Record whether the user has a will. Call when the user answers the will question — including a clear 'no'. has_will is required. If they have one, pass executor_name and residuary_beneficiary when stated — never guess a name the user did not give. If the user is unsure whether their old will still counts, pass has_will=true and note nothing else."* Params: `has_will` (boolean, REQUIRED), `executor_name` (string), `residuary_beneficiary` (string), `will_last_updated` (string, year ok). `strict: true`. |
| `app/Services/AI/AiToolDefinitions.php` + `XaiToolDefinitions.php` | Register in `ORDER['estate']` (follow how `campaign` tools were added for pensioncheck — both provider tables). |
| `app/Agents/CoordinatingAgent.php` | Dispatch entry + `handleCaptureWillStatus`: validate `has_will` present (missing → error result, partial-retry protocol `details.missing => ['has_will']`); write `Will::updateOrCreate(['user_id' => $user->id], array_filter([...]))` — **never overwrite an existing `executor_name` with null**; return the standard onboarding-capture receipt (`onboarding_capture => true, field_group => 'campaign_will'`, summary naming the stored fact: "Noted — no will in place." / "Will recorded — executor {name}."). |
| `app/Services/AI/AdviceFyn.php` `WRITE_TOOLS` | Add `capture_will_status`. |
| `app/Services/Onboarding/OnboardingChatDirector.php` `captureToolSet` | Add `capture_will_status` (the P8 lesson — post-campaign "actually I made a will last week" must delegate to the real handler). |
| Golden masters | `CAPTURE_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php` AND the Xai variant (`XaiToolSchemaGoldenMasterTest`) — re-record; commit the fixtures. Corpus validators must exit 0. |

### C2. State machine (`app/Services/Onboarding/OnboardingStateMachine.php`)

1. Constants: `STATE_CAMPAIGN4_FAMILY/_PROPERTY/_OTHER_ASSETS/_WILL/_LPA/_GIFTS/_EXISTING_RECAP/_ADVICE_PROPERTY/_ADVICE_ASSETS/_ADVICE_WILL/_ADVICE_LPA/_ADVICE_GIFTS/_TERMINAL` (13 — follow the campaign2 block :135-154).
2. `CAMPAIGN_SECTION_ORDERS['inheritancecheck'] = ['essentials', 'property', 'assets', 'will', 'lpa', 'gifts']`.
3. `campaignSections('inheritancecheck')` — entries + the six data-presence skips from spec §5 (new predicates `skipSectionIfEssentialsKnown`, `skipSectionIfPropertyKnown`, `skipSectionIfEstateAssetsKnown`, `skipSectionIfWillKnown` (**row-existence, not has_will=true**), `skipSectionIfLpaKnown`, `skipSectionIfGiftsKnown`). Model reads via existing models/stores; there is NO EstateStore — direct model reads in the state machine follow the existing `skipSectionIfStatePensionKnown` precedent (`$user->statePension()->exists()` style; use relations/models exactly as `EstateDataReadinessService` does).
4. `campaignVerifyConfig('inheritancecheck')` — spec §5 table (essentials route null → inline confirm; property/assets → `/net-worth`; will/lpa/gifts → `/estate`).
5. State definitions in `inCodeStates()` — clone the campaign2 shapes; every prompt from spec §5 verbatim; `record_context: 'property'` on campaign4_property (and add the `'property'` arm to the Director's `captureRecordContextAppendix` — see C3.6); `advance_on_answered_question: true` where specced; advice states `next` = **callable-string** `self::class.'::nextFromCampaign4<Section>Advice'`-style or the `nextCampaignSection` closure pattern the campaign2 advice states use (closures ARE allowed on plain advice states — the callable-string law is for `campaign_synthesis.next` specifically; copy the campaign2 advice-state shape exactly).
6. `campaign4_existing_recap` — bubbles identical ids (`yes`/`changed`) so `matchBubble` + the GENERIC `nextFromExistingRecap` work unchanged (it reads selection via `firstCampaignSection`; verify it has no pensioncheck-hardcoding — at 9c9e7d2 it doesn't).
7. `buildEstateRecapPrompt` per spec §5 (deterministic reads; completed-vs-fresh leads; `**Is that all still right?**`).
8. `buildInheritancecheckFunnelRecapPrompt` + `campaignWelcomeFor('inheritancecheck')` = `"welcome to Fynla — let's build your estate plan."`; wire the recap from `buildPersonalPrompt`'s campaign branch keyed on selection (the pensioncheck seam in `buildWorkPrompt` is the model — this campaign's entry is `base_personal`, whose campaign branch already exists at :1427-1433).
9. `campaign4_terminal` — clone `campaign2_terminal` (:748-754) with prompt/navigate_to from spec.
10. `nextFromCampaignSynthesis` — add the `inheritancecheck` → `STATE_CAMPAIGN4_TERMINAL` arm.
11. `sectionLabel` — the five entries from spec §8 (the "I've saved your details" cosmetic lesson).
12. **Corpus lockstep**: add every DATA-carrying state to `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` in the SAME ORDER as `inCodeStates()` (the golden master asserts id set + order). Callable prompts/next use the `{ branch: … }` marker form. Run `tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php` — must be green WITHOUT touching its fixtures.

### C3. Director (`app/Services/Onboarding/OnboardingChatDirector.php`)

1. `ESTATECHECK_SECTION_STRATEGY_TYPES` const per spec §6.
2. `buildEstateSectionAdvice` — clone `buildRetirementSectionAdvice` (:1042) against `EstateStrategySource`; route it in `buildSectionAdvice`'s selection switch (non-mapped sections → null; NO fallthrough to tax builders).
3. `buildSynthesisAdvice` — `inheritancecheck` arm (lead-in + degrade line per spec §6; estate items have `estimatedAnnualTaxSaved` null → the £-suffix and combined-savings lines naturally stay silent — do NOT special-case).
4. `terminalNavigationBubble` — `'/estate'` arm.
5. `describeStep` — one label per campaign4 state ("capturing your home", "capturing your savings and investments", "capturing your will details", "capturing your Lasting Power of Attorney details", "capturing your gifts", "reviewing what we already have", "pulling your estate plan together").
6. `captureRecordContextAppendix` — add the `'property'` arm (list `properties` rows with `entity_type: property, entity_id: N` — the update-vs-create steer for campaign4_property re-entrants).
7. `verifyEditRecordContext` / `verifyEditSnapshot` / `verifyEditFocus` — extend for the estate sections: `'property'`/`'assets'` contexts (properties + savings/investments with ids), will/lpa/gifts → profile-style notes ("use capture_will_status / update_record"); `verifyEditFocus`: `'property','assets','will','lpa','gifts' => 'inheritancecheck'`. **The `'recap'` sentinel is selection-blind** — extend `verifyEditRecordContext('recap')` to branch on `onboarding_fyn_selection` (pensioncheck keeps income+pensions+spouse; inheritancecheck renders property+assets+will+gifts) — smallest change: a selection check inside the existing `'recap'` case.
8. **Gift hallucination guard** (spec §5): in `handleCreateEstateGift`, reject the write when the user's turn carried no date signal — mirror `handleCaptureStatePension`'s guard shape (:4633-4707): validation errors first, then the guard, no-write result lets `advance_on_answered_question` move on. Definition of "no date signal": the tool call's `gift_date` maps to today/this-month with no date-like token in the user message — implement conservatively and pin with a unit test (model invents `gift_date=now` from "I gave my son some money once" → NO row).
9. `toolsForFocus` (`OnboardingPromptBuilder`) — the `'inheritancecheck'` arm from spec §8 (the #610 root-cause lesson: without the arm, delegated turns fall to the savings default and the model security-refuses).

### C4. Flip re-entry on

`config/onboarding.php` — set `'reentry' => true, 'reentry_entry' => 'campaign4_existing_recap'` (deferred from A1).

### C5. Tests (clone the pensioncheck suites 1:1)

- `tests/Feature/AI/OnboardingStartCampaignMapTest` — auto-covers the new entry (asserts valid state ids).
- `tests/Unit/Services/Onboarding/Inheritancecheck*` — section order, each skip predicate (row present vs absent; will-row-with-has_will-false skips), verify config routes, recap builder (property/will/gifts lines; both leads), funnel recap builder.
- `tests/Feature/AI/CampaignReentry{Start,Dispatch,Exit}Test` — parameterise or clone the pensioncheck cases for `inheritancecheck` (409 bypass, active_campaign stamp, pause/terminal clears, completed_at guard). The #612 pause-resume + mid-walk-resume cases too.
- `tests/Feature/Onboarding/InheritancecheckSynthesisTurnTest` — synthesis mirrors the composed estate plan; degrade on empty plan; NO £-suffix lines.
- `capture_will_status`: handler unit tests (yes-with-executor, bare-no writes has_will=false row, missing has_will → partial retry, never null-overwrites executor) + both golden-master re-records.
- Gift-guard unit test (C3.8).
- Characterisation pins: savetax AND pensioncheck section orders/verify configs byte-identical (extend the existing pins).

**Slice C done =** full suite green (expect ~5,530+); corpus validators exit 0; goldens re-recorded deliberately (the ONLY fixture diffs are the new tool).

## Slice D — the live E2E gate (csjones, real xAI, Playwright — Rule 14 loop)

Deploy the stacked branch to csjones (checkout branch + `./deploy/csjones-fynla/build.sh` + upload `public/build` AND `public/m-build` + cache clears — never `optimize`/`route:cache`; reseed if migrations touched anything — none planned). Verify the served m-bundle contains the change (`grep` a new symbol in `public/m-build/assets/main-*.js`) before testing — the stale-bundle lesson.

| Walk | Definition of GREEN |
|---|---|
| **D1 fresh funnel walk** | Phone-shaped `/inheritancecheck` deep-link → funnel 6/6 → plan page personalised £ (assert the maths vs seeded config by hand) → register (+MFA via tinker) → dashboard, Fyn opens with the funnel recap → full walk (property→assets→will→lpa→gifts) with every verify announce/navigate/pill/confirm → synthesis bullets mirror `/estate`'s composed plan → terminal CTA lands `/estate` → DB: properties/savings/wills/LPA/gifts rows exact, `funnel_answers` keys, `signup_source` if utm used, point awards present, `will_in_place`/`lpa_in_place` milestones minted when earned |
| **D2 existing-user delta** | julycsj3 (or the then-current standing user) via `?from=inheritancecheck` → estate recap (income NOT re-asked, DOB NOT re-asked) → only the gap questions → synthesis → terminal → advice mode normal after |
| **D3 integrity** | Two re-entries: `onboarding_completed_at` byte-identical, terminal award count 1, `active_campaign` cleared, recap-edit ("Something's changed") → edit → confirm → **continues the gap walk** (the P2 pin, now selection-aware) |
| **D4 regression** | One savetax walk AND one pensioncheck walk (fresh users) — zero bleed: no estate advice in either, section orders unchanged, syntheses correct |
| **D5 targeted** | "No will" answer → `wills` row `has_will=false`, honest ack, no phantom executor; "no gifts" → NO gift row; gift with no date → NO row + honest line; pause mid-walk → bare reopen resumes at parked step; Tier teaser renders on /estate for the free walk user (accepted default — screenshot for CSJ) |

Bugs found live route through fix rounds on the same branch (the #610 pattern), each re-verified. Exit ONLY per Rule 14: every walk GREEN as defined, or a genuine CSJ decision surfaced.

## The trap table (all 30 — check EVERY one before opening each PR)

| # | Trap | Guard |
|---|---|---|
| 1 | No `toolsForFocus` arm → model security-refuses every delegated turn | C3.9; live-verify one capture per section |
| 2 | `funnelHasAnyAsset` reads `funnel_answers['assets']` — this campaign never produces it | NO gate/state may call it (grep before PR) |
| 3 | Income-section skip keyed on `employment_status` (mapper seeds it at registration) | N/A here (no income section) — but `skipSectionIfEssentialsKnown` must NOT key on mapper-seeded `marital_status` alone for the FAMILY sub-question |
| 4 | `CAMPAIGN_PREFIXES` missing → phone ad links land on plain /m | A2 + scaffold tests |
| 5 | `ONBOARDING_NAV_ROUTES` missing → verify navigation silently no-ops | A5 (`/estate`, `/net-worth`) |
| 6 | RegisterRequest strips unknown campaign keys → funnel answers silently vanish | A4 adds the 5 keys; register feature test posts the full funnel object |
| 7 | New user-facing columns absent from `UserResource` | None added — if ANY new users column appears, expose it (the P3 lesson) |
| 8 | Corpus/in-code drift → golden master red or silent fallback | C2.12; run the golden master locally before pushing |
| 9 | `.xai.md` descriptions govern model behaviour | C1 writes descriptions first-class; goldens re-recorded |
| 10 | File cache driver: tag invalidation silently no-ops | Estate paths read live (EstateController) — but IF any cached analysis is touched, explicit `Cache::forget` |
| 11 | Model hallucinates required params (the state-pension age incident) | C3.8 gift-date guard; `capture_will_status` requires explicit has_will |
| 12 | Duplicate creates when records exist | `record_context 'property'` arm (C3.6); Will uses updateOrCreate |
| 13 | Advice-state recursion (PR #504, 17,509 messages) | synthesis `next` = callable-string; copy campaign2 advice shapes; MAX_ADVICE_CHAIN is the backstop, not the plan |
| 14 | `describeStep` default "mid-onboarding" | C3.5 |
| 15 | `sectionLabel` default "details" | C2.11 |
| 16 | Synthesis silent on empty plan | degrade line (C3.3) + test |
| 17 | Terminal route 404s on web | `/estate` is native (router:917) — assert in a routes test anyway |
| 18 | Store mirror staleness on /m (pills gated off login-time user) | Generic since #612 (`startOnboarding` mirrors `from`) — no work, but D-walk asserts pills render mid-re-entry |
| 19 | Verify pill answers are literal strings ("Yes, that's right") | Don't rename bubbles |
| 20 | Web verify-bubbles render literal `**` | Pre-existing cosmetic — do NOT fix in this campaign (scope) |
| 21 | PreviewWriteInterceptor | No new auth-adjacent POSTs — nothing to do |
| 22 | Plan-page XSS | HEX-flag injection, `esc()`, never echo query values; B6 asserts |
| 23 | Funnel pages cached by CDN | `redirect.authed` + the standard Cache-Control; nothing new |
| 24 | Homepage card breaks the homepage on service throw | try/catch → figureless card (B5 + test) |
| 25 | Tier-2 gate on /estate | Accepted default (spec D4) — D5 screenshots it; NO gate carve-out unprompted |
| 26 | Cache-busters | New JS ships `?v=1`; BUMP on any later edit |
| 27 | Campaign key > 32 chars | `inheritancecheck` = 16 — fine; re-check if CSJ renames |
| 28 | Stacked-PR merge order | Retarget to dev before deleting base branches |
| 29 | Queue-dependent side effects | None for estate (risk observers irrelevant); milestones mint on dashboard read + capture path — D1 asserts |
| 30 | Corpus validators | Exit 0 before every deploy |

## Testing process & gates (the quality ladder — every slice climbs it in order)

**Gate 0 — pre-code (per slice).** Re-read the spec section the slice implements + playbook §2 (F1–F15) + this plan's trap table. Sweep every trap row against the slice's file list. Confirm the branch stacks correctly (`git log --oneline -3` shows the previous slice's tip).

**Gate 1 — tests with the code (per slice).** Write the slice's listed tests alongside the implementation, never after the PR. Run targeted:
```bash
./vendor/bin/pest tests/Unit/Services/Marketing/EstateEstimateServiceTest.php     # B
./vendor/bin/pest tests/Feature/PublicPages/InheritancecheckRoutesTest.php        # B
./vendor/bin/pest tests/Unit/Services/Onboarding/ --filter=Inheritancecheck       # C
./vendor/bin/pest tests/Feature/AI/CampaignReentryStartTest.php tests/Feature/AI/CampaignReentryDispatchTest.php tests/Feature/AI/CampaignReentryExitTest.php  # C
./vendor/bin/pest tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php  # C — corpus lockstep
```
Pint every touched PHP file. Slice C additionally: corpus validators exit 0; both tool-schema golden masters re-recorded ONCE for `capture_will_status` (`CAPTURE_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php` + the Xai variant) — the fixture diff must contain ONLY the new tool; any other diff is a bug.

**Gate 2 — review passes (per slice PR, before merge).** The canonical review path (CLAUDE.md Working Style):
- `/code-review` on every slice PR — report ALL findings with confidence + severity; fix or explicitly catalogue every one in the PR body before merge (the pensioncheck discipline: 11 task-scoped reviews, every Critical fixed in-slice).
- `pr-review-toolkit:pr-test-analyzer` on every slice (coverage honesty — every new branch has a pinning test).
- `pr-review-toolkit:silent-failure-hunter` on Slice C specifically (capture handlers, the gift-date guard, `capture_will_status` partial-retry, SSE paths — this campaign's riskiest surface for swallowed errors).
- `security-reviewer` MANDATORY on Slice B (public pages: XSS injection shape, query-echo, register payload validation, open-redirect surface) and on Slice A's middleware/allowlist changes.
- `tax-compliance-reviewer` MANDATORY on Slice B1 (`EstateEstimateService` — every NRB/RNRB/rate via `getInheritanceTax()`, no hardcoded £325k/£175k/40%, marriage-doubling assumption stated) and on any prompt interpolating tax figures (`campaign4_family`'s RNRB line).

**Gate 3 — full suite (per slice merge).** `./vendor/bin/pest` — 0 failures, expected skips only. Record the passing count in the PR body (baseline at plan time: 5,506 + ~30 skips). The Architecture suite must stay green (StoreBoundary — no new direct model queries outside the sanctioned state-machine precedents).

**Gate 4 — deploy verification (before any D walk).** On csjones: `git fetch && git checkout <branch>`; build locally via `./deploy/csjones-fynla/build.sh` ONLY (never raw vite/npm — the guard blocks it anyway); upload `public/build` + `public/m-build` with the preserve-old-chunks pattern; cache clears `cache:clear && route:clear && config:clear && view:clear && config:cache` (NEVER `optimize`/`route:cache`); **bundle-contains-change grep** (`grep -l <new symbol> public/m-build/assets/main-*.js` — the stale-bundle lesson cost a full session once); `php artisan db:seed` if any seeder/migration changed (none planned).

**Gate 5 — the browser gate (Slice D — Rule 14 loop, NON-NEGOTIABLE).**
- The law: "browser tested" = clicked, filled, submitted, and verified the result in Playwright. Every `[x]` in the D table maps to an interaction. No completion report before ALL walks run. Anything untestable is reported as "I COULD NOT TEST THIS" — never "verified".
- **Runbook** (per `verify-m` + `reference_m_verification_path` — the desktop→/m bridge does NOT fire on cold automated navs):
  - Fresh-user walks: drive the REAL funnel (`/m?to=%2Finheritancecheck` for the framed phone shape), register a disposable user (`<campaign>.e2e.<date>@example.com`), fetch the verification code via SSH tinker: `PendingRegistration::where('email',…)->first()?->verification_code`.
  - Existing-user walks: log in at `/m/app/login`, MFA code via tinker (`EmailVerificationCode::where('user_id',$u->id)->latest()->first()->code`). Dismiss level-up dialogs before asserting.
  - Backend assertions per step via tinker one-liners — for THIS campaign: `Will::where('user_id',$id)->first()?->has_will`, `Gift::where('user_id',$id)->count()`, `LastingPowerOfAttorney::where('user_id',$id)->count()`, `Property::where('user_id',$id)->count()`, `$u->onboarding_completed_at` (byte-identity across re-entries), `$u->active_campaign`, `PointAward::where('user_id',$id)->where('dedup_key','like','%terminal%')->count()` (stays 1).
  - Transcript ambiguity: read `ai_messages` rows for the conversation rather than trusting a partial SSE render.
  - The 202-queued path is deterministically testable: hold `Cache::lock('fyn:inflight:'.$convId, 300)->get()` from tinker, send, expect the honest queued line, `forceRelease()` after.
- **The loop**: RED → `systematic-debugging` diagnosis with file:line evidence (never speculate) → fix on-branch → redeploy (Gate 4 repeats, including the bundle grep) → **re-walk from D1** (fixes break other things — the #610 wave re-verified every round). Exit ONLY when every walk row is GREEN as defined, or a genuine CSJ decision is surfaced with everything else green.
- **Test-user hygiene**: soft-delete disposable users after the gate (`User::find($id)->delete()` via tinker); NEVER delete or mutate the standing test user beyond what the walk itself writes; record any standing-user data drift in the patch notes.

**Gate 6 — regression matrix (inside Slice D).** D4 is mandatory, not optional: one full savetax fresh walk + one full pensioncheck fresh walk on the deployed branch. The unit-level mirror: the characterisation pins (savetax/pensioncheck section orders + verify configs byte-identical) must be in Slice C's suite so cross-campaign drift fails BEFORE the browser.

**Gate 7 — post-merge duties.** Admin-merge each slice (`gh pr merge N --merge --admin`); retarget stacked PRs to dev BEFORE deleting base branches; after the final merge put csjones back on dev (`git checkout dev && git pull`) and confirm HEAD + bundle; write `inheritancecheck-patch-notes-technical.md` + `-feature-notes-user.md` to the day's Updates folder (repo + vault); update CSJTODO; extend the campaign audit docs' file inventories if surfaces moved.

## Done

Slices A–C merged to dev (admin-merge pattern), csjones deployed + D-gate GREEN through every gate above, patch notes + user-facing feature notes written to the day's Updates folder, CSJTODO updated, prod untouched (release window extends — CSJ's call).
