---
type: handover
mode: session-end
date: 2026-10-08
session: 3
repo: fynla
branch: dev
---

# Session Handover — 2026-10-08, Session 3

## Where things stand

- **Item 17a (Fyn on OpenAI GPT-6 Luna) is built, merged to dev and walked on csjones. It is not released.**
  - The walk on csjones was the full run CSJ defined: a single account on web, then /m, then a joint partner invited, accepted and onboarded.
  - Every Fyn question logged `gpt-6-luna`, priced. OpenAI holds 0 stored completions.
- **The walk found defects.** Two are fixed and live on csjones (#1152, #1153). Several are recorded and **not yet fixed**.
- **CSJ's instruction for the next session: "ensure that the issues are addressed in the next session."**
- **Nothing is waiting on CSJ.**
  - CSJ will set `AI_PROVIDER=openai` in the server `.env` himself.
  - The cookie behaviour stays as it is for now.

## Priorities for the next session

These follow `todoCurrent/TODO.md` item 17a, "Where we stopped (2026-10-08, session end)", in that order. For each one: fix the root cause, run the named tests, open a PR to dev, merge it, `git pull` on csjones, then walk it there.

**On csjones, never run `cache:clear`** for a PHP-only change: it resets the AI provider to xAI (see "Things that will bite you").

1. **Joint interest figure on /m Bank Accounts.**
   - The insight reads "Personal Savings Allowance Exceeded: Your estimated annual savings interest of £960 exceeds your £500 Personal Savings Allowance" for Alex (csjones user 502).
   - £960 is the whole joint account's interest. Alex's half is £480, which is inside the allowance (ITA 2007 s12B).
   - The Tax Strategy page and Fyn's working already use £480. One figure everywhere (memory `feedback_one_figure_every_surface_fetched`).
   - Find the savings insight that builds this, and give it the user's share (`CalculatesOwnershipShare`).
2. **Trace Jamie's surplus.**
   - Fyn told Jamie (csjones user 503): "household spending of £3,800.00 a month and a monthly surplus of −£266.70".
   - Hand figure: take-home £1,853.30 a month (£26,000 less £2,686 tax and £1,074.40 NI, divided by 12), less £1,900 (her stored half of the spending), less £130 pension = **−£176.70**.
   - Find the affordability source before calling either figure wrong (Rule 23).
3. **Wording, all user-facing:**
   - Fyn's working says the threshold "was raised to £50,870 by your Gift Aid and personal pension payments". Alex has no personal pension payment; only Gift Aid raised it.
   - The duplicate question opens "I couldn't save Easy access savings: You already have …". CSJ's ruling is ask, never refuse.
   - "Thanks — I've noted monthly spending of £1,900" after Jamie typed £3,800 for the household. It must say this is Jamie's half of the shared figure (`SharedExpenditure` joint mode).
   - The suggested-question chips read `How do I "You have no will recorded"?`.
4. **Tech debt from today** (`docs/tech-debt-report.md`):
   - one list of the records a joint owner may change;
   - one entity-to-store map in `CoordinatingAgent`;
   - one "clear both owners' caches" helper.
5. **Release** (CSJ types /release). #1148, #1149, #1151, #1152 and #1153 are on dev.
   - Before walking fynla.org, CSJ sets `AI_PROVIDER=openai` in the prod `.env`.
   - Walk fynla.org on web and /m, then CSJ checks on the iPhone (the native app talks only to fynla.org).

Then item 18, then 19 and 20 on the list.

## Context to load

- `todoCurrent/TODO.md` (item 17a, from "Released to dev (2026-10-08)" down) — the full walk record, every Found line with figures, and CSJ's answers.
- `docs/tech-debt-report.md` — today's tech debt, priority 4.
- `app/Services/Coordination/HouseholdFinancialContext.php` (`linkedSpouseEarnings`) — the #1153 fix. It's the pattern for priority 1: put figures on one footing.
- `app/Support/SharedOwnership.php` (`fromEditor`) and `app/Rules/LinkedCoOwner.php` — the joint-owner rules shipped today. Read them before touching anything ownership-related.
- `app/Services/AI/AiProvider.php` (lines 66-93) — the cache-only provider switch behind the deploy trap.

## Completed this session

- **#1151** (`91eda4d3b`): joint ownership. CSJ's rulings: "for joint accounts, both parties have ownership", and "joint should extend to cover joint goals, chattels and business interests".
  - Either owner changes or removes a joint record on web, /m and Fyn. Covers savings, investment, property, mortgage, liability, goal, chattel and business interest. The record stays the primary owner's.
  - Forms speak from the editor's side: `SharedOwnership::fromEditor` on the server, and `coOwnerId` / `userSharePercent` in the forms.
  - **Security fix:** `LinkedCoOwner` on all 17 co-owner request fields. Before, any user id passed, so a record could be handed to a stranger.
  - **Fyn's edit form writes only what the user changed.** An unchanged save had:
    - renamed 51 of 67 accounts;
    - changed `premium_bonds` / `junior_isa` to `easy_access`;
    - written ISA paid-in to the wrong column.
  - Truthful read-back: "Already on file", and the stored ownership is named.
  - Duplicate guard covers joint records.
  - Fyn's deletes now go through the stores.
  - Viewer's-share displays on the valuable, business and property screens.
  - The valuable detail reloads after a save.
  - `StoreEnumRulesMatchColumnsTest` is green again (red on dev since `0148e4659`).
- **#1148** (`8efbbc650`): OpenAI GPT-6 Luna provider.
  - Also fixed: the model env key is now `OPENAI_MODEL`, because the old sidecar name is barred by `NoStaleReferencesTest`.
  - `AiSettings.vue` lint.
  - Mobile impact declared as `no-counterpart-approved` (admin only).
- **#1149** (`7fcbc2349`): the pension working names the interest the Personal Savings Allowance covers.
- **#1152** (`1583b4eda`): a capture where nothing changed is never narrated as "Updated" (`HasAiChat::captureTurnCompleteDirective`).
- **#1153** (`3e7be5889`): a linked partner's earnings sit inside their income on the spouse form. It had shown £79,440 total with £84,000 from work.
- **csjones:** deployed at dev and switched to OpenAI GPT in the admin panel. Smoke checks: homepage found "Get started for free"; /m returns 200.

## Verification state

- **CI on #1151:** lint, Unit, Feature, Architecture, Integration, browser-smoke, builds and frontend all passed at `957be9ee8`. iOS Native was still running and was not gated on (standing rule).
- **#1148 and #1149:** merged after their 17a-own failures were fixed. Their remaining red was the dev-wide `StoreEnumRulesMatchColumnsTest` that #1151 fixed. Their final CI after the dev merge was **not waited on** (CSJ: never wait on CI).
- **Local tests, run one at a time in the foreground on the touched files:**
  - 395 + 145 + 25 + 198 + 53 + 31 passed;
  - frontend specs 43 + 76 passed.
- **Walked live on csjones at `3e7be5889`:** Alex and Jamie, web and /m. Figures checked by hand are in TODO.md.
- **Not verified:**
  - iOS (laptop simulator never used; CSJ checks on the iPhone after release);
  - the /m "Edit details" button for a joint owner, because it's hidden while onboarding is paused (`MobileChrome.vue:29`);
  - fynla.org (not released).

## Decisions and dead ends

- **CSJ's rulings (2026-10-08). Do not re-ask:**
  - Both parties own joint accounts. This extends to goals, chattels and business interests, **not life events**.
  - CSJ sets the provider in the `.env`.
  - Leave the cookie behaviour as it is for now.
  - Walks **accept** cookies, never decline (memory `feedback_walks_accept_cookies`).
- **The owner's "Validation failed for account update"** from the morning handover was the test fixture, not the app. The users weren't spouse-linked both ways, and `SavingsStore::validateOwnershipLinks` rightly refused.
- **Joint-owner edits must normalise as the record's owner.** `InvestmentAccountNormaliser` and `MortgageNormaliser` stamp `user_id`. Never pass the editor.
- **Stores carry on as `$record->user`** after finding the record for either owner. `InvestmentAccountStore::moveDividendTotal` reads the total from the database for that reason.
- **The Decisions API does not suit Fyn** (morning).
- **Goals' `ownership_percentage` means nothing** (W-0038, CSJ 2026-08-26). A joint goal stored at 100 is not a defect.

## Things that will bite you

- **The AI provider switch lives only in the cache** (`AiProvider.php:69-93`). Every `cache:clear` puts the server back on `.env` `AI_PROVIDER`, which is `xai` on both servers until CSJ edits it.
  - For PHP-only changes on csjones, do `git pull` alone. If you do clear caches, switch the provider again in Admin > AI > AI Provider.
- **CI watching:** never wait on CI (CSJ was angry at 2 hours lost today). Merge when the web and /m checks you need are green, or when told to.
- **Never run two pest runs at once.** They share the test database and both go red. One foreground run at a time (memory updated).
- **zsh doesn't split `$VAR` lists in `for`.** Use arrays, or `while read`. A loop silently compared nothing twice today.
- **/m on csjones:** the iPhone user agent needs `Network.setUserAgentOverride` plus `setCacheDisabled`. The menu button is at (338, 32), and pages sit inside `iframe[title="Fynla"]`.
- **Codes on csjones over ssh (`~/.ssh/fynlaDev`):**
  - Registration codes are in `pending_registrations.verification_code` (`getRawOriginal`).
  - Sign-in codes are in `email_verification_codes`.
  - Type one digit per box. `pressSequentially` dropped digits.
- **Walk accounts on csjones (password `Password1!`):** Alex 502 (`slaterjoneschris+devluna-a@gmail.com`), Jamie 503 (`+devluna-b`), invitation 9.
- **Walk accounts locally:** Robin 141, Sam 142, Jordan 143 (`+luna-a/b/c`). Local valuable 122 and goal 219 belong to Sam and Jordan's walk.

## Tech debt deferred

See `docs/tech-debt-report.md`:
- `CoordinatingAgent.php:152` vs `ContextualResourceResolver.php:224`: two rules for which records a joint owner may change.
- `CoordinatingAgent.php:6625` and `:6855`: two entity-to-store maps.
- The "clear both owners' caches" code is copied across the Savings, Investment, BusinessInterest and Chattel controllers.
- `config/services.php` has no `strict_types` (predates today).
- `moveDividendTotal` is subtle.

## Branch and deploy state

- **Branch:** `dev`, at `3e7be5889` (the local checkout was moved off the merged `feat/17a-openai-provider`).
- **Uncommitted:** CSJ's own files only (diagrams, the Sept 30 handover, workforce logs), plus this handover, `todoCurrent/TODO.md` and `docs/tech-debt-report.md`, which go to dev through the docs PR.
- **Deploy status:**
  - csjones is on dev `3e7be5889`, provider OpenAI GPT.
  - fynla.org is unchanged and still on xAI.
