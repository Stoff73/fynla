---
type: handover
mode: session-end
date: 2026-10-07
session: 1
repo: fynla
branch: feat/9-estate-cards
---

# Session Handover — 2026-10-07, Session 1

## Where things stand

- **Item 8b is done and released.**
  - Release s (#1115, main `dae662dc0`) moved the protection needs into the tax config.
  - Release t (#1117, main `b6ca49ef5`) took the ratings off the protection plan.
  - Both were walked on fynla.org, and 8b is crossed off.
- **Item 9 (Estate) is built, but not merged and not on csjones.**
  - It's on branch `feat/9-estate-cards`, PR #1120, pushed at `ca9dab9d1`.
  - Walked locally on the web and /m, apart from the gaps listed below.
- **CSJ stopped the session mid-walk to clear the context.**

## Priorities for the next session

1. **Finish item 9's local walk.** It's the NEXT line under item 9 in `todoCurrent/TODO.md`.
   - **/m at 390 as john@example.com:**
     - the pension card's "Go to it", then the pension's Edit details. Check the new "Who you want it to go to if you die" field shows and saves, then the card clears.
     - the LPA card's "Record a Lasting Power of Attorney with Fyn" link opens Fyn's LPA form.
   - John has no pension; the walk pension I made (id 94) was deleted. Make a fresh one the same way (tinker `PensionStore::createDc`, `IngestSource` lives in `App\Services\Stores`), then delete it after.
   - **Web at 1440:** on the Estate plan page, toggle the charity and larger-gifts actions. The "With actions" panel should read the server's combination: for the Mitchells, both together save £137,575 (not £151,331), and estate to beneficiaries is £1,379,668.
2. **Deploy #1120 to csjones.**
   - Rebuild the deploy script from `deploy-9-csjones.sh` (path below). It must:
     - run `TaxConfigurationSeeder`, then `EstateActionDefinitionSeeder`, then `ActionHowToSeeder`, in that order;
     - upload BOTH `public/build/` and `public/m-build/`, because `resources/mobile/views/ActionCard.vue` changed.
   - There's no migration.
   - CSJ runs it via `!`. Then walk csjones on the web and /m, admin-merge, and release, with the same order of seeders and both bundles in the prod script.
3. **CSJ to approve `gifts_pet_window`.** It's rewritten as CSJ asked: record each gift in Fynla, with a "Record a gift with Fyn" link that opens Fyn's new gift form. It's back to `draft`. The gift form is tested (`EstateFynFormsTest`) but not walked, because the link shows only once the entry is approved.
4. **Then item 10** in list order.

## Context to load

- `todoCurrent/TODO.md` — item 9 holds every Found line, CSJ's 2026-10-07 answers, "Where we stopped" and NEXT.
- `docs/superpowers/specs/2026-10-06-estate-cards-review-design.md` — the review, D1 to D6 (approved), and the corrected step maths in the 3.4 table.
- `database/seeders/data/action-how-to/estate.md` — the six estate how-tos: five approved, gifts in draft.
- `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/5ec3df12-ba49-410d-af55-988fa837290c/scratchpad/deploy-9-csjones.sh` — the csjones script to rebuild. It currently uploads only the web bundle, so add `m-build`. It's in /tmp, so rebuild it from the description above if it's gone.

## Completed this session

- **Releases s and t:** 8b live and walked on fynla.org; patch notes and PDF updated (#1118). The PDF converter now puts a blank line before lists.
- **Item 9 spec:** written with evidence (csjones 152 households) and approved (#1119).
- **Item 9 build** (`ebda6b69d`, `e4d83a468`, `3114a5420`, `87d7f5dae`, `ca9dab9d1`):
  - **Cards:**
    - `iht_position`: the engine's household figures, plus the plan page's steps as figures;
    - one `no_lpa` card, which counts only a registered LPA;
    - `gifts_pet_window` from `FailedGiftTaxCalculator`;
    - `pension_no_beneficiary`, one per pension;
    - `trust_anniversary_due`.
    - Estate's `policy_not_in_trust` is disabled. The retired rows are kept, disabled.
  - **Plan page steps corrected (spec 3.4).** No more life-expectancy multiples, 50% liquidity test, age-50 gate or trust gift sized by the tax.
  - **What-if:** worked out on the server for every combination of the steps that change the tax; the browser does no sums.
  - **Removed invented figures:** the s21 half-surplus and £1,000 floor, the setup estimates, the 85 and 50 defaults, `TaxDefaults` fallbacks, and the three-year will review.
  - **Rule 6:** the estate gate now counts jointly owned assets.
  - **Fyn forms:**
    - a gift form (`CaptureForms::GIFT`) and an LPA form (`CaptureForms::LPA`);
    - `create_power_of_attorney` now returns success and takes the registration date;
    - the pension form has a beneficiary field (one form on every surface);
    - how-to links written `Label | fyn:add/<resource>` render as a Fyn button on web and /m.
  - **Deleted dead code:** `ComprehensiveEstatePlanService` (its life-events method is now `EstateLifeEventsImpact`), the unrouted `EstateController::getComprehensiveEstatePlan`, and `EstateAgent`'s scenario builders.
  - **Smaller fixes:**
    - the list no longer shows "You could save £349,112" (the tax);
    - the LPA card no longer routes to Fyn's property form;
    - the web gift form reads the small gifts limit from config;
    - the `life_cover_position` how-to no longer mentions education;
    - card descriptions are shortened so they don't repeat the how-to.

## Verification state

- **Named tests, all green at `87d7f5dae`:**
  - new: `EstateCardsTest`, `EstateFynFormsTest`, `EstateWhatIfCombinedTest`;
  - about 45 other files touching these classes, in batches of named files;
  - /m Vitest: `ActionCard.spec.js` (29), `FynCaptureForm.spec.js` and `EstateLpa.spec.js`.
- CI not watched.
- **Walked locally:**
  - **web 1440:**
    - Mitchell demo: the Estate tab, the Inheritance Tax card, and the plan page steps;
    - John: LPA link → Fyn LPA form → saved → the card named only health and welfare.
  - **/m 390:** the Mitchell Estate cards, and the pension card's "Go to it" → the pension.
- **Not verified:**
  - /m pension Edit details with the beneficiary field;
  - /m LPA link;
  - the gift form from the card (draft);
  - the what-if toggles in the browser;
  - csjones;
  - iOS (CI only; it gets the beneficiary through typed questions, and doesn't render `learn_more` links yet, item 35).
- **Tech-debt pass not run** (CSJ stopped the session).

## Decisions and dead ends

- **CSJ 2026-10-07:**
  - "yes to all six" (D1 to D6);
  - "a clt is only a clt if it exceeds the nil rate band, same for a pet";
  - "we record the gifts in Fynla, with a link for Fyn to open the gift form in chat. The rest are approved";
  - "the rule is ONE form across all surfaces";
  - the summed what-if and the dead code were "obvious errors" (memory `feedback_fix_adjacent_defects_in_path`, 2026-10-07 entry). Zero "Found (not fixed)" lines before calling an item built.
- **Each Inheritance Tax step stands on its own** against today's tax. Cover in trust pays the tax; it doesn't reduce it.
- **Fyn and the web, in demo mode:** Fyn is switched off for web demos (`ActionCardView::fynAvailable`), so the web hides the Fyn link there. Walk it as john@example.com. /m demos do have Fyn.
- **LPA "mark as registered"** stays a web-page step (/m sends LPA details to the web by design, W-0110).
- **iOS learn links:** iOS doesn't read `learn_more`, so the new link can't break it.

## Things that will bite you

- **Seeder order:** `ActionHowToSeeder` throws if `estate.md` names a key the table doesn't have. Always run `EstateActionDefinitionSeeder` first.
- **The web demo blur (item 38)** blocks clicks about 13 seconds after a demo loads. To walk, send `window.dispatchEvent(new Event('fyn-chat-interaction'))`, which the chat itself sends.
- **Local tinker with a script file** waits on input in the background: append `< /dev/null`.
- **Local /m is a built bundle:** run `VITE_ROUTER_BASE=/ npm run build:mobile` after any `resources/mobile/` change.
- **Verification codes:** newest by `orderByDesc('id')`.
- **CSJ's uncommitted files** (two excalidraw diagrams, the 30 September handover, the workforce logs): never stage them.

## Tech debt deferred

- The tech-debt pass was not run this session.
- Known items:
  - two gift write paths (`EstateController::storeGift` and `CoordinatingAgent::handleCreateEstateGift`, both `Gift::create`);
  - the `✓` glyph in `GiftForm.vue:109` (grandfathered);
  - `EstateAgent` is still about 1,400 lines.

## Branch and deploy state

- **Branch:** `feat/9-estate-cards` (the checkout is on it). It's pushed, so there are no unpushed commits. This handover is committed through a docs PR to dev; see the closing line.
- **fynla.org:** main `b6ca49ef5` (release t).
- **csjones:** dev `44af389e8`; #1120 is not deployed.
