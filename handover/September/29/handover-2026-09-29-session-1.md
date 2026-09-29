---
type: handover
mode: session-end
date: 2026-09-29
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-09-29, Session 1

## Where things stand

- **Handover items 1 and 2 are done, and item 3 has started.** Savings is the first module batch, and it's released: #966 went out, then hotfix #967, both walked live on fynla.org.
- **Branches:** `main` is `d48a52742`, whose tree matches `dev` `1b5f3d77f`. Local and csjones are both on `dev` `1b5f3d77f`.
- **Nothing is half-done.** CSJ set the rule for item 3: one module at a time, each taken all the way through (draft → CSJ approves → walk → release) before the next starts. **Protection is next.**

## Priorities for the next session

1. **Protection how-to batch (32 definitions)**, then retirement (26), investment (17) and estate (12), one at a time.
   - **Model it on savings:** use `database/seeders/data/action-how-to/savings.md`, and follow its header rules. They are CSJ rulings from today:
     - Speak about the user's own money ("your target"). Never say "Fynla"; where anything acts, it's Fyn or a named page.
     - Keep established facts and cite them. Never strip one for lack of a fetched page.
     - Handle the household: ISA full, spouse, children.
     - Give every claim a source (Rule 23).
   - **Before drafting:**
     - List which keys actually appear on real local households; savings had 28 of 54.
     - Group keys that are the same action; one heading can name several keys.
     - Plumb the module's recommendation `$vars` into `figures`, the way savings does: `buildRecommendation` → the module's adapter `extra` → the aggregator (already generic) → the card.
     - Add the module to `ActionHowToSeeder::SOURCES`.
   - **Handing over for review:** the draft file must be on `dev`, or the checkout left on the branch, when CSJ is told to review it (memory `feedback_review_files_must_be_on_dev`).
   - **Protection data already gathered:** the definition list is in this session's scratchpad (`prot-defs.txt`), but the scratchpad doesn't persist. Re-run the listing from `ProtectionActionDefinition`.
2. **One rule for "ISA allowance used this year"** (Rule 20 / Rule 23, affects what users see).
   - **The problem:** `ISATracker::buildOwnerStatus` misses Stocks and Shares ISA payments whenever `investment_accounts.tax_year` is stale. Preview user 78 paid £20,000 and still gets "use your ISA allowance". `TaxStrategyMath::estimateIsaSubscriptionsThisYear` ignores the year entirely.
   - **What's needed:** decide what `tax_year` and `isa_subscription_current_year` mean, and make one rule. Also stop `evaluateCashISARecommended` firing when the allowance is used.
   - **Decision to ask CSJ, if the code doesn't settle it:** which field is the source of truth for "paid in this tax year".
3. **The rest of `CSJTODO.md` → NEXT, in order:**
   - the invented 4.00% market-rate fallback;
   - tax plan items carry no working, so Fyn makes up the arithmetic;
   - Ask Fyn from a card is swallowed while onboarding is paused;
   - `/m` can't mark an emergency fund;
   - duplicate savings and tax cards;
   - three savings entries awaiting a source from CSJ;
   - and the older items: iOS `learn_more`, help-audit section 7, the #944 minors.

## Context to load

- `database/seeders/data/action-how-to/savings.md`: the approved model for every module batch. The header states the grammar, the rules and the available facts.
- `CSJTODO.md` (NEXT section): the ranked list, pruned today.
- `app/Services/Actions/ActionHowToFacts.php`: every fact a how-to can use. Household facts were added today (`isa_full`, `{isa_left}`, `has_children`, `{children}`, and the savings config).
- `app/Services/Savings/SavingsActionDefinitionService.php` (`buildRecommendation`, ~line 3700): how a module passes its card's own figures (`figures`) through. Protection needs the same.
- Memory `project_release_2026_09_29.md`: the day's releases, scripts and process lessons.

## Completed this session

- **#960, Ask Fyn narration** (item 1), plus CSJ's Rule 12 ruling:
  - **Card grounding:** the card's question is grounded in the card (`ActionCardService::forAskFynMessage` → `<action_grounding>`, one method with the tax-plan grounding).
  - **Pension parts:** `pension_input_breakdown` publishes each pension's part once.
  - **Tier wording:** `ClaimTier` means `claim_tier` reaches the model only in plain words, everywhere it appears (tool results, live data, the voicing rules, and 28 corpus files).
  - **Certainty:** `CertaintyFilter` removes any sentence stating certainty from the model's text, streamed and stored, across both providers.
- **#962, iOS keyboard flake** (item 2): the CI keyboard setting is now verified, and the harness never types without an on-screen keyboard. Not run locally, per CSJ's rule; CI is the proof.
- **#964, savings how-tos:** 26 entries covering 41 keys, all approved by CSJ.
  - Figures are plumbed through to each card.
  - `PSACalculator` no longer calls a £0 allowance "approaching" when there's no interest.
  - `RateComparator` now returns MoneySavingExpert's provider and date.
  - `DependantsReach::minorChildrenOf` is the one rule for children under 18.
- **#965 and #967:** no engine buckets as topics on cards or list rows (the list now uses the one `topicFor` rule), and a child's Junior ISA is no longer "your Cash ISA".
- **Releases:** #966 (script `release-prod-2026-09-29.sh`) and hotfix #967 (`release-hotfix-2026-09-29.sh`), both in this session's scratchpad. Backups are in `~/release-backups/2026-09-29/` on prod.

## Verification state

- **Walked live on fynla.org** (Carter demo household, web at 1440px):
  - the emergency fund card;
  - "Consider a Cash ISA", with the ISA-left, spouse and children branches, and no Junior ISA read as the parent's;
  - the actions list, with no "Lifecycle" or "Warning".
  - fynla.org and `/m` return 200, and the log is clean.
- **Walked on csjones, web and `/m`:**
  - Ask Fyn on a pension card (user 402);
  - the "how sure" question, with no certainty wording;
  - savings rate and zero-rate cards;
  - the hotfix.
- **Tests:** only the touched test files were run locally, all green.
- **CI was not waited on.** CSJ ruling: never wait on or gate on the full suites. #960 was fully green; the later PRs were merged before their Unit and Feature jobs finished.
- **Not verified:**
  - `/m` on fynla.org for the savings cards (checked on csjones `/m`);
  - the iPhone app for anything today.

## Decisions and dead ends

- **CSJ rulings today:**
  - **Rule 12:** Fyn never states certainty; figures must be empirical, and the rule is enforced on the output.
  - **No full suites, local or CI:** never wait on them and never gate on them. The gate is the touched tests plus a live walk.
  - **Never run iOS simulator tests on the laptop.**
  - **One task at a time:** finish savings through release before starting protection.
  - **Settled ISA facts:** ISAs are tax-free, one owner only, and the allowance doesn't carry over.
  - **User-facing text never says "Fynla"**, and targets are the user's.
- **Dead ends:**
  - **A prompt line alone did not stop grok grading its certainty,** which is why the filter exists. Renaming the basis label from "Fixed arithmetic" to "Worked from your figures" was also needed, because "fixed" invited "firm".
  - **MoneyHelper blocks automated fetching** (403, bot check), so it can't be cited. Three savings entries are waiting on a source because of it.
  - **The Annual Allowance breakdown added to `AnnualAllowanceChecker` was reverted.** The tool-result depth cap trims it to "[nested N items]", so the model never saw it.
- **Simplify, don't rebuild:** the `strategy_*` savings keys are only internal fallback labels, never shown to users. The strategies reach users through the concrete cards.

## Things that will bite you

- **The release skill can't be invoked by the model**, and auto mode blocks merging into `main`, remote writes to prod, and some csjones writes. Hand CSJ one script to run with `!`, as today.
- **Another session was working in this repo today**, on PRs `fix/m-logout-clears-framed-desktop-token` and `fix/invited-spouse-income-double-count`. Don't touch its branches.
- **The iOS dashboard decodes `DashboardAction.meta` strictly as a string.** Never send `null`.
- **Store boundary tests forbid some imports:** services may not import `DCPension`, and `RateComparator` may not import `SavingsMarketRate`. Put the logic in the owning domain class.
- **csjones walk accounts with `Password1!`:** 402 (`savetax-e2e-web-0916@example.com`), 404, 416 and others. User 427's password is unknown, and resetting it counts as a blocked remote write.
- **zsh doesn't split `$VAR` file lists.** Use `bash -c` or literal names. Never glob Pest.

## Tech debt deferred

In `docs/tech-debt-report.md`:
- **Critical:** the two ISA-used sums, and the invented 4.00% rate fallback.
- **Warnings:**
  - `RateComparator` duplicates its year-fallback logic (`:78-89` and `:146-148`);
  - service locators in new code (`FynContextAssembler`, `ActionHowToFacts`);
  - a local pounds formatter in `FynContextAssembler::pensionInputSentence`;
  - two identical flush blocks in `HasAiChat.php:683` and `:805`.

## Branch and deploy state

- **Branch:** `dev` at `1b5f3d77f`, with the handover commit on top. No unpushed commits after the push.
- **Local working tree:** CSJ's pre-existing modified excalidraw and `workforce/*` files, and untracked folders, left untouched.
- **Production:** `main` `d48a52742`. **csjones:** `dev` `1b5f3d77f`.
