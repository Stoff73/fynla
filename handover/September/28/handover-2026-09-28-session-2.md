---
type: handover
mode: session-end
date: 2026-09-28
session: 2
repo: fynla
branch: dev
---

# Session Handover — 2026-09-28, Session 2

## Where things stand

Four releases went live on fynla.org today and each was walked live on the web at desktop width. `main` is `a87432b71` (release #958) and `dev` is `cf8c673f2`, which differs from `main` only by the patch-notes PR #959. The patch notes for all four releases are in `September/September28Updates/` (Markdown + PDF). Nothing is half-done. CSJ said to continue with the next handover item tomorrow.

## Priorities for the next session

1. **Fyn "Ask Fyn about this" narration** (Rule 20, one place; load `fyn-architecture` first). Two known defects:
   - The £7,800 pension-input explanation leaves out the personal pension.
   - It says "mechanical-tier strategy", which is `claim_tier` jargon reaching the user.

   Starting points:
   - The card's contextual request comes from `app/Services/Actions/ActionCardService.php:114`.
   - The contextual turn runs through `app/Services/AI/ContextualConversation/ContextualConversationService.php`.
   - `claim_tier` reaches the prompt via `app/Services/AI/Fyn/FynContextAssembler.php`, `app/Services/Coordination/RecommendationsAggregatorService.php` and `ComposedModulePlanService.php`.
   - Enumerate every mechanism before fixing (Rule 20).
2. **Intermittent iOS UI test** `testPR7ParityClosureJourney` ("not hittable … Keyboard Focused" on `net-worth.forecast.rate.property`). Apply the hardware-keyboard fix from the `ios-simulator` skill.
3. **Other modules' how-to batches** (savings 54, protection 32, retirement 26, investment 17, estate 12), in the tax format: `why`, branches, `outcome`, and now `learn:` links. Add `SOURCES` entries to `ActionHowToSeeder`. CSJ approves each batch; the parser treats `edited` as draft.
4. **Smaller items now in `CSJTODO.md` → NEXT:**
   - iOS rendering of `learn_more`;
   - the help-audit section 7 leftovers, such as the spouse form's "A user account will be created" text, no input for `nrb_transferred_from_spouse`, and dead components;
   - the #944 review minors.

## Context to load

- `CSJTODO.md` (NEXT section) — the ranked list above plus the smaller items, pruned today.
- `app/Services/Actions/ActionCardService.php` — where "Ask Fyn about this" starts (`ask_fyn`, line ~114), and where `learn_more` is built.
- `database/seeders/data/action-how-to/tax.md` — the how-to grammar header (now documents `learn:`) and the 21 approved entries, the model for the other modules' batches.
- Memory `project_release_2026_09_28.md` — the day's four releases, the rulings, and the process lessons.

## Completed this session

- **Release #949** (#948 plus walk fixes): personal action cards, deadline lanes, tax rule fixes. The walk fixes:
  - the web Fyn overlay closes on navigation below 1024px (`AiChatPanel::handleNavigation`);
  - onboarding completion keeps "none" declarations (`HouseholdFinancialContext::outlivingOnboarding`);
  - a Stocks and Shares ISA counts for ISA info;
  - the spouse-income unlock opens the spouse details form ("Update my spouse's income");
  - the last "harvest" headings are gone;
  - "Here's your Your".
- **Release #952** (#951), with a migration:
  - `spouse_annual_earnings` is nullable, and the spouse pension top-up is capped at earnings from work (FA 2004 s189–190), with the basic amount when earnings are unknown;
  - past pension payments are only asked for when `TaxStrategyMath::carryForwardCouldApply` holds, meaning earnings above this year's AA **and** cash savings above this year's remaining allowance;
  - on the basic-rate pension card, the "why" is the saving, stated once.
- **Release #955** (#954): `public/pages/help.php` rewritten from the audit, with every figure from TaxConfigService and every rule sourced, plus a new `/help#avcs` section. `Help.vue` and its route are deleted.
- **Release #958** (#957):
  - the how-to `learn:` part becomes card `learn_more` links on web and `/m`, and the four pension how-tos link DB members to `/help#avcs`;
  - the Estate Gifting and Life Policy cards route to the new `/estate/gifting` and `/estate/life-policy`, and Trust goes to `/trusts`;
  - the small gift allowance comes from config;
  - "IHT" is spelled out on those screens.
- **CI fix #956:** the tool-schema golden masters are regenerated for `spouse_annual_earnings`, the `help.php` Pint issue is fixed, and the retirement lock tests are updated.
- **Patch notes:** PRs #950, #953 and #959. Marriage Allowance how-to approved (CSJ).
- **Housekeeping:** the prod test account `c.jones@csjones.co` was purged after the second release walk. csjones is on `dev` `94d5849cc`.

## Verification state

- **CI:**
  - #948 was green at `21adf0a66` before release.
  - #956 and #957 are fully green.
  - #951, #952 and #954 were released before CI finished, at CSJ's call. Their reds were the golden masters and the Pint issue, both fixed in #956.
  - #959 (docs only) was still running at hand-over.
- **Live walks on fynla.org, web at 1440px:** all four releases. For #949, `/m` at 390px was walked too.
- **Not verified:**
  - `/m` on fynla.org for #952, #955 and #958 (all were walked on csjones `/m`);
  - the Trust card, which only renders for estates over £2m;
  - the iPhone app for all of today's work.

## Decisions and dead ends

- **CSJ rulings today:**
  - Spouse pension top-up: ask for earnings from work separately.
  - Carry forward: ask for past pension payments only if the user both earns and has the cash to go above this year's AA. It is "a true outlier".
  - Basic-rate pension "why": swap in "Paying in £X more this year saves £Y of income tax", without repeating it in the summary.
  - How-tos must link to the AVC help.
  - Dead cards and wrong rules get fixed, not listed.
  - Release without waiting for CI when the walk and the touched tests are green ("we must release now").
- **Personal Info residence** was already on the long-term residence rule (`99d91a7fe`). The 26 September audit predated that. The admin Tax Settings domicile block is correct: the old keys only apply to tax years before 6 April 2025.
- **The spouse-income unlock prompt** must not contain entity keywords. The old label "including any pension or rent" routed to pension capture and got the security refusal.
- **How-to links are card-level (`learn_more`), not markup inside step text.** Every client renders the steps as plain text.

## Things that will bite you

- **Each push to a PR branch cancels the running CI**, which caused 5 restarts and about 2.5 hours on #948. Batch walk fixes, push once.
- **Never pass Pest a glob or command-substitution file list.** An empty match runs the full suite, and the hook does not catch it (memory `feedback_never_glob_test_files_into_pest`). Stop a stray run with TaskStop; a `pkill` that names Pest is blocked.
- **Web walks at 1440×900.** The Playwright window persists at 390px from earlier runs (memory `feedback_web_walks_at_desktop_width`).
- **Auto mode blocks** remote writes (csjones and prod), `gh pr merge` into `main`, and edits to prod scripts. Hand CSJ one `!` command or script.
- **For release scripts, always `chmod +x`.** CSJ hit "permission denied" once today.
- **Prod verification codes:** ask CSJ for them. The demo personas (landing page → "See our demo") need no code and have full Estate access.

## Tech debt deferred

- The spouse household field lists are duplicated in two write handlers: `app/Agents/CoordinatingAgent.php` (`handleCaptureSpouseHouseholdData` ~5712 and the `spouse_household` edit branch ~6769).
- `RecordEditForms` checks married status with a literal list. `TaxStrategyMath::isMarriedOrCivilPartner` owns that rule.
- `HouseholdFinancialContext::labelFor('spouse_income_amount')` still carries "(enter 0 if none)", an instruction inside a label that reaches Fyn prompts.
- `RetirementRecommendationAdapter` maps any "Tax Planning" recommendation to `carry_forward_unused_allowance`, which is too broad.
- The gifting and life-policy screens predate Rule 15 and still show decorative icons (grandfathered).

## Branch and deploy state

- Branch: `dev` at `cf8c673f2`; no unpushed commits. The main checkout's pre-existing untracked and modified files are untouched: `workforce/*`, excalidraw diagrams, persona test folders.
- Worktrees: all of today's have been removed.
- Production: `main` `a87432b71` (release #958). csjones: `dev` `94d5849cc` (behind `dev` only by docs).
