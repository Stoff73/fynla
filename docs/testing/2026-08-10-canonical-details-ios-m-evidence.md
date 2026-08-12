# Canonical detail navigation PR 3 evidence

Date: 2026-08-10
Branch: `codex/ios-m-canonical-details`
Native target: `ios-native/Fynla.xcodeproj`, `Fynla-Staging`
Simulator: Fynla iPhone 16 Pro iOS 18.6 (`B880080D-37ED-453E-A87E-3DE049902ECA`)

## Scope and authority

PR 3 completes the approved canonical-detail slice for Goals, Properties,
Mortgages, Liabilities and Income; reuses those destinations from Net Worth;
aligns Expenditure mode and active totals; and bounds Holistic Plan loading.
Existing balances, income, tax position and expenditure totals are rehydrated
or calculated by Laravel. `/m` and iOS send only action, resource and semantic
navigation identifiers to contextual Fyn.

## Automated verification

- Backend affected suite: 131 tests passed with 1,929 assertions. It covered
  contextual conversation validation/rehydration, profile income and
  expenditure presentation, property/mortgage/liability reads, Net Worth,
  Holistic Plan gating and the affected User Profile services.
- `/m` full suite: 22 files and 114 tests passed.
- `/m` production build: 116 modules transformed successfully. The existing
  Vite chunk-size and Browserslist-age warnings remain non-blocking.
- Native full unit target excluding the separately reproduced StoreKit system
  suite completed without a PR 3 failure. The preceding all-unit result bundle
  recorded 379 passes, 1 skip and 6 StoreKitTest failures.
- The same six StoreKitTest failures (`productUnavailable` / StoreKitTest
  `unknown`) reproduced in isolation on the untouched primary `dev` checkout.
  They are the existing machine/scheme baseline and are unrelated to PR 3.
- The focused PR 3 native suites passed on the exact simulator: API error
  mapping, Holistic Plan, Goals, Net Worth, Income, Expenditure and design
  system.

## iPhone 16 Pro user journey

`FynlaUITests/testPR3CanonicalIncomeExpenditureAndHolisticPlanJourney` passed
through Xcode on the named simulator. It exercised:

1. Drawer → Income → Employment canonical detail.
2. Source, annual amount, frequency, ownership and server-owned tax position.
3. Identifier-only contextual Edit availability on the detail.
4. Drawer → Expenditure → explicit Category detail mode and reconciled server
   total basis.
5. Drawer → Holistic Plan → real ranked plan and effective surplus.

Three screenshots were retained in the Xcode result bundle. The first visual
loop found that a pushed Income detail could retain stale gradient geometry,
leaving its white heading on the light page. `MobilePageHero` now fills its own
deterministic gradient. The same XCUITest reran green and the exported
Employment screenshot shows a complete dark-to-raspberry hero with a clearly
visible heading.

Final simulator result bundle:

`/Users/CSJ/Library/Developer/Xcode/DerivedData/Fynla-epbutbbmpsmzeedyffebqqpdwwtk/Logs/Test/Test-Fynla-Staging-2026.08.10_12-03-24-+0100.xcresult`

Exported final screenshots:

`/private/tmp/fynla-pr3-ui-after`

## Issue and retest ledger

| Surface | Observed result | Root cause | Fix | Green rerun |
| --- | --- | --- | --- | --- |
| Full `/m` suite | Goals contextual-authority contract failed | Overview refactor removed the ownership helper and entity-request branch still used by the shared authority contract | Restore the primary-owner guard and identifier-only canonical goal entity request without restoring overview Edit | Focused Goals tests and all 114 mobile tests passed |
| Native detail heading | Employment title rendered white on the light page after push navigation | Global-positioned gradient slice retained transition geometry | Fill every `MobilePageHero` with the shared gradient directly and retain header accessibility traits/identifiers | PR 3 XCUITest reran green; visual screenshot inspected |
| Native full suite | Six StoreKit system-session cases failed | Existing StoreKitTest environment baseline | Reproduced on untouched `dev`; no PR 3 StoreKit change | All other native tests passed; focused PR 3 and UI journeys green |
| PR lint | Five changed PHP files failed repository formatting rules | Import ordering and fully qualified names did not match the project Pint profile | Apply Pint only to the five reported files | Pint and the 80 directly affected backend tests passed; replacement lint job passed |
| PR architecture | Property contextual resolution bypassed the canonical read boundary | The resolver added a direct `Property` model query | Inject `PropertyStore`, use its joint-aware `findMany` read and remove the model dependency | Contextual contract passed with 31 tests and 126 assertions; replacement architecture job is the merge gate |
| Installed Chrome Net Worth loop | Headline liabilities were £134,500 while the category showed £257,000; Property rendered as 138% of assets | The detailed endpoint exposed full joint balances while the overview used the requesting user's ownership share | Make Property, Mortgage, Investment and Cash detailed values joint-aware for both primary and secondary owners while retaining `full_value` as canonical record context | Two regression cases pass with 20 assertions; the full Net Worth API file passes with 61 assertions |

## Installed Google Chrome `/m` acceptance

PR 3 was merged as `5c1df7e6d3b59a7773a708ef5ccc11ea8dc1b3ce`, deployed to
`https://csjones.co/fynla/m`, and exercised in the user's installed Google
Chrome through the connected extension. No Chromium, bundled Playwright
browser or in-app browser was used.

The seeded Family preview journey passed Goals overview and canonical Goal
detail; Property category, canonical Property detail and linked Mortgage;
canonical Liability detail; Income overview and source detail; reconciled
Expenditure; and the typed Premium Holistic Plan gate. It also exposed the
ownership-share mismatch recorded above. The focused follow-up keeps the
canonical full balance available as `full_value`, but uses the requesting
user's share for every total, percentage and list value on both `/m` and the
native client that consumes the same endpoint.
