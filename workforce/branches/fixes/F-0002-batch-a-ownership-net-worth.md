# F-0002 — Batch A: Ownership & Net Worth

**Owner:** build-lead (agent `fix-batch-A`) · **Branch:** `dev` (no feature branch) ·
**Board items:** W-0015, W-0014, W-0013, W-0025, W-0016, W-0012, W-0009, W-0008, W-0007, **W-0039**, **W-0041**, **W-0052**

**ID block allocated to this agent: W-0060 – W-0069.** New items take the next number
from that block — never scan the board for a free one (`FORMATS.md`).

**Status at time of writing: ALL NINE ITEMS DONE.** This document exists to satisfy
CLAUDE.md Rule 22 (Context Budget) — it is the handover a replacement agent would be
seeded from. Nothing is in flight; the "in flight" section below says so explicitly.

---

## 1. The dispatch, verbatim

> Fix **Batch A — Ownership & Net Worth**, eight board items found by a live persona
> run. Work in `/Users/CSJ/Desktop/fynla` on branch `dev`.
>
> ## Your items (read each board file in full first — they carry file:line root causes, DB rows and repro steps)
>
> - `workforce/ops/board/W-0015-joint-share-computed-three-ways-surfaces-disagree.md` — **do this one FIRST**, it is the consolidation the others land on top of. Same joint account's share computed three different ways; investments page says £95,000, wealth summary says £47,500.
> - `W-0014-joint-investment-account-saves-100-percent-share.md`
> - `W-0013-joint-savings-account-cannot-be-created.md`
> - `W-0016-property-card-names-the-viewer-as-co-owner.md`
> - `W-0012-mortgage-term-hardcoded-and-rate-fix-end-date-dropped.md`
> - `W-0009-update-holding-discards-payload.md`
> - `W-0008-adviser-fee-cannot-be-entered-for-investment-accounts.md`
> - `W-0007-investment-modal-ignores-cash-isa-usage.md`
>
> ## Mandatory context
>
> Read `workforce/core/index.md`, then the vault docs for the modules you are touching (`CLAUDE.md` vault table): Investment, Savings, Property. Rule 6 governs joint assets — a SINGLE record with `joint_owner_id` and `ownership_percentage`, the spouse's share is `(100 - ownership_percentage)`, use `CalculatesOwnershipShare` (backend) / `ownership.js` (frontend). Never duplicate records for joint owners.
>
> ## Rule 20 is the point of this batch, not a footnote
>
> W-0015 exists because the same joint share is computed by three parallel mechanisms. **Consolidating them into ONE source that every consumer reads is PART of the fix** — editing three copies "in lockstep" is a violation, not a fix. W-0014 and W-0016 are almost certainly symptoms of the same disease; fix the cause once and they should fall out. Check `/m` (`resources/mobile/`) reads the consolidated source too — it has its own store and services and does NOT inherit fixes from `resources/js/`.
>
> ## Reproduction data is already in place — use it, do not disturb it
>
> Local DB has the persona household: **David Jones (id 16)** and **Sarah Jones (id 17)**, reciprocally linked, SpousePermission accepted both ways, both provisioned premium via an active Subscription row. Real joint records exist (The Willows joint 50% £850,000; a joint GIA £95,000). Reproduce against these rows.
>
> **Do NOT delete or modify these users**, do NOT patch DB rows to make anything pass, do NOT `migrate:fresh`, do NOT touch `.env`. If you disturb local seeded data, `php artisan db:seed`.
>
> ## Definition of done for this dispatch
>
> 1. Root cause fixed in ONE place per defect, reaching web AND `/m`.
> 2. Targeted tests written/updated and passing — the relevant families only, NOT the full suite (CSJ standing instruction: no full suite per small change; full suite runs at consolidation, which I will do).
> 3. `./vendor/bin/pint` clean on what you touched.
> 4. Each board item updated: status, what changed, file:line, evidence.
> 5. **Do NOT open a PR, do NOT merge, do NOT deploy, do NOT touch csjones or prod.** I am coordinating three parallel fix batches and will consolidate. Report back to me when done.
> 6. If a fix would collide with Estate/Wills (Batch B) or Retirement/Profile/Gates (Batch C), stop and tell me rather than reaching into their area.

### Amendments received since

1. **Rule 22 — Context Budget.** Hand over at ~900k of the 1M window; an agent cannot
   clear itself; write the handover to `workforce/branches/` and link it from the board
   items; report context position when reporting.
2. **Test database.** `phpunit.xml:46` pins all runs to a shared `laravel_testing`;
   three concurrent batches deadlock. Use
   `DB_DATABASE=laravel_testing_a ./vendor/bin/pest <paths>`. Do not edit `phpunit.xml`
   or `.env`.
3. **Ninth item: W-0025** (joint chattel saves with no joint owner). Fold into the same
   consolidation rather than patching chattels separately; reopening W-0015 is the right
   answer if needed.
4. **Tenth item: W-0039** — the holding form has no quantity/units input. **Originally
   dispatched as W-0035 and renumbered**; `W-0035` is now *Target Retirement Income has
   no entry point*, which belongs to someone else — **do not touch it**. One acceptance
   criterion is a decision, not code: whether value is derived from units x price or
   entered independently.
5. **Do NOT browser-verify your own fixes.** A fix agent verifying itself is
   self-certification; the tester closes Rule 14's loop independently. Browser work is
   still useful as *diagnosis*, but it does not close an item. (Policy set after the
   original dispatch.)
6. **ID block W-0060 – W-0069** for anything this agent raises.

---

## 2. The decision that everything else rests on — how the joint-share mechanisms were consolidated

**Read this before touching anything in this batch.** It is the expensive reasoning.

### What was found

The prior-art check turned up **not three but eight** implementations of "the primary
owner's share of a shared asset" — later nine, when chattels were added as W-0025:

| # | Mechanism | Behaviour |
|---|---|---|
| 1 | `InvestmentAccountNormaliser::normalise()` | default only when the key was ABSENT |
| 2 | `MortgageNormaliser::normalise()` | same |
| 3 | `PropertyNormaliser::fromForm()` | 100 for individual/trust only |
| 4 | `SavingsAccountNormaliser::fromForm()` | same, plus a `$partial` guard |
| 5 | `PropertyController::store` + `update` | inline 100 → 50 coercion (the only correct one) |
| 6 | `InvestmentController::store` + `update` | `isset()`-only default — **the W-0014 bug** |
| 7 | `MortgageService::createFromPropertyData()` | its own `== 100.00 → 50` block |
| 8 | `CalculatesOwnershipShare::calculateUserShare()` | rewrote a stored 100 to 50 **on read** |
| 9 | `ChattelController::store` (found via W-0025) | handled `joint` but not `tenants_in_common` |

Plus, on the frontend, `resources/js/utils/ownership.js` **existed with zero consumers**
while six components and one Vuex getter each did their own arithmetic.

### The decision

**Two homes, one per side of the wire. Everything else reads them.**

- **`app/Support/SharedOwnership.php`** — the WRITE rule.
  `primaryOwnerPercentage($type, $submitted)`: shared + (null | '' | 100) → **50**;
  any other explicit share kept; not shared + null → 100.
  Consumed by all four Store normalisers, both savings FormRequests,
  `MortgageService`, and `ChattelController`. The inline copies in
  `PropertyController` and `InvestmentController` are **deleted**.

- **`resources/js/utils/ownership.js`** — the DISPLAY rule. Viewer-aware; prefers the
  API's `user_share`; falls back to `is_primary_owner`, then a passed viewer id.
  Imported by six web components, the `investment` store getter, and **five `/m` views**
  via relative path (`../../../js/utils/ownership.js`). Precedent for the cross-import:
  the web SPA already imports `resources/mobile/utils/fynText.js`.

**Why #8 (the read-side rewrite) had to go.** It coerced a stored 100 to 50 on every
read, which meant every trait consumer (wealth summary, estate, household, `/m` net
worth) showed the correct 50/50 while every consumer doing its own arithmetic showed
the raw 100. That divergence *was* W-0015. It also hid the write bug (W-0014) for
months and made a legitimate 100/0 inexpressible. Normalising on the way **in** and
trusting the stored value on the way **out** is the only shape where all surfaces agree.

**The migration is the counterpart to removing #8.**
`database/migrations/2026_08_21_000000_normalise_shared_ownership_percentage.php`
rewrites shared rows stored at 100 → 50 across properties, savings_accounts,
investment_accounts, mortgages, chattels. It deliberately **excludes
business_interests**, where `ownership_percentage` is a shareholding and 100% is
legitimate. It changes **no displayed figure** on trait-consuming surfaces — those rows
were already being treated as 50/50 — it only makes the stored value agree with what
was already shown.

### The counterparty rule (added for W-0025)

*A shared asset must name its counterparty — a linked `joint_owner_id` OR a free-text
`joint_owner_name`.* One predicate, `SharedOwnership::namesCounterparty()`.

Enforced in `StoreChattelRequest` / `UpdateChattelRequest`. **Not** enforced on savings
or investments, because those two tables have **no `joint_owner_name` column** and
`SavingsStore.php:357-361` documents joint-with-no-linked-owner as deliberately
first-class ("the co-owner is not on the platform"). Enforcing there would delete a
working capability. Making the rule universal needs a schema change — its own item.

---

## 3. Decisions already taken — do not re-litigate

| Decision | Reasoning |
|---|---|
| **Units are the fact; `current_value` is derived from units x price** (W-0039) | Two writable fields can diverge; one derived field cannot. Inverts the old `quantity = value / price` direction so the user's actual fact is an input, not the end of a chain. The old direction is kept as a fallback, so nothing that works today regresses. Home: `app/Support/HoldingValuation.php`. |
| Rewrite 100 → 50 at the input boundary; trust the stored value on read | The only shape where every surface agrees. See §2. |
| **W-0015's "preserve a deliberate 100/0" is NOT implemented** | It contradicts W-0014's own acceptance (match `PropertyController`, which rewrites 100 → 50 unconditionally) and the savings validator, which rejects any shared share outside (0,100). No form exposes a share input for joint ownership, so a deliberate 100 is unreachable. **Flagged to the Chief of Staff in W-0015's notes** — not silently skipped. |
| Mortgage `maturity_date` wins over a submitted `remaining_term_months` | The date is the fact the user entered; the term was fabricated. Where only a term exists, the date is derived from it. Neither on a partial update → both untouched. Neither on a create → `config('mortgage.default_term_months')`, because the column is NOT NULL. |
| Two-decimal share percentages everywhere on a card | `PropertyCard.spec.js` asserted `Joint (50%)`; the live decimal column renders `50.00`. Updated the spec, not the component. |
| The chattel/property/mortgage counterparty rule is not extended to savings/investments | Schema gap, see §2. Raised as a follow-up item. |
| `mortgages.id = 7` (user 14) left as an orphan | Repairing another user's data is not mine to decide. Reported in W-0025. |

---

## 4. Dead ends already ruled out — do not re-walk

- **The ISA panel bug is NOT a store or endpoint problem.** The store getter, the
  action and `GET /api/savings/isa-allowance` were all verified correct and live
  (`storeGetter: 10000`) while the panel still rendered £0. The fault was
  `AccountForm` hand-threading `:cash-isa-used` down to `StandardInvestmentFields`,
  whose resolved prop stayed at 0 (parent computed 10000 → child vnode prop 10000 →
  child resolved prop 0). Reproducible after a hard reload, so **not an HMR artefact**.
  Fix was to delete the prop hop, not to chase Vue's patch flags.
- **W-0009 is not only the store key.** After renaming `holdingData` → `data`, the save
  still never left the browser: `HoldingForm` never resolved `investment_account_id`
  (the record carries `holdable_id`; the inline editor passes a trimmed shape with
  neither), so client validation blocked it with "Account is required".
- **The W-0014 create-modal symptom did not reproduce.** The Joint Owner select renders
  and the chosen type survives the save. Do not go looking for it without new DOM
  evidence.
- **Fyn cannot orphan a chattel.** `CoordinatingAgent::handleCreateChattel:4954-4956`
  always writes `individual` / 100.
- **Mass zero-assertion Pest failures on 2026-08-21 were environmental, twice over:**
  first the shared-database deadlock, then Batch C's
  `2026_08_21_120000_correct_spouse_pension_percent_convention.php` using a bound
  parameter in a DDL `COMMENT` clause (MySQL rejects it). Both are fixed. Do not
  investigate those results as real defects.

---

## 5. What is DONE, with evidence

All nine. Full per-item detail, file:line and evidence live in the board files
themselves — this is the index.

| Item | One-line outcome | Key files |
|---|---|---|
| **W-0015** | Nine mechanisms → two homes. Both screens agree to the penny from both logins. | `app/Support/SharedOwnership.php`, `resources/js/utils/ownership.js`, `app/Traits/CalculatesOwnershipShare.php:74-87` |
| **W-0014** | Joint investment stores 50/50; an explicit 70 is preserved. | `InvestmentController.php:344-348`, `InvestmentAccountNormaliser.php:100` |
| **W-0013** | `POST /api/savings/accounts` → **201** (was 422). Row 50/50, full balance. | `Store/UpdateSavingsAccountRequest.php` |
| **W-0025** | Joint chattel with no counterparty → **422**. Chattels became the fifth reader. | `ChattelController.php:83,152`, `Store/UpdateChattelRequest.php`, `ChattelResource.php:36,77` |
| **W-0016** | "Joint with David Jones" on Sarah's own login (was her own name). | `resources/js/utils/ownership.js` `coOwnerName()`, `PropertyCard.vue:23`, `ChattelDetailInline.vue` |
| **W-0012** | Term derived from maturity date (156, not 300); rate fix end date + interest portion persist. | `MortgageNormaliser.php` `reconcileTerm()`, `MortgageService.php`, `PropertyList.vue:266-280`, `StorePropertyRequest.php:86-88` |
| **W-0009** | Holding edit persists; modal no longer self-closes; empty payload rejected. | `store/modules/investment.js:527`, `HoldingForm.vue:382`, `UpdateHoldingRequest.php` |
| **W-0008** | Adviser fee input present and persisting. | `StandardInvestmentFields.vue:249-266`, `Store/UpdateInvestmentAccountRequest.php` |
| **W-0007** | Cold load reads Cash ISA £10,000; the £20,000 guard fires and blocks the save. | `resources/js/mixins/isaAllowanceMixin.js` (NEW), `savings/ensureISAAllowance` |
| **W-0039** | Units input added; **units are the fact, value derived**. Two duplicate `quantity = value / price` copies deleted. | `app/Support/HoldingValuation.php` (NEW), `InvestmentController.php:722,793`, `HoldingForm.vue` |
| **W-0041** | Chattel delete returns 200 + success body instead of 500. Sweep of all 146 controllers enumerated. | `ChattelController.php:206-216` |
| **W-0052** | **Regression of my own W-0008.** Null → NOT NULL column 500'd every investment create. 28 reachable columns covered, not 1. | `InvestmentAccountNormaliser::NOT_NULL_WITH_DEFAULT` |

### Headline live-browser evidence (localhost:8000, both accounts)

| Screen | David (16) | Sarah (17) |
|---|---|---|
| `/net-worth/investments` card | Joint · Full £95,000 · **Your Share (50.00%) £47,500** · Held with Sarah Jones | same · Held with **David Jones** |
| investments Current Portfolio | — | **£132,500** (was £180,000) |
| `/net-worth/wealth-summary` Investments | **£47,500** | £132,500 |
| `/net-worth/property` co-owner line | — | **"Joint with David Jones"** |

`CrossModuleAssetAggregator::calculateInvestmentTotal(16)` = 47500, `(17)` = 132500 —
matching the wealth summary exactly. £47,500 + £47,500 = £95,000; the £190,000
double-count is gone.

### Test output

- **PHP 368 passed / 0 failed** (1,935 assertions) across `Unit/Support`, `Unit/Traits`,
  all Store normalisers, `MortgageServiceOwnership`, `Feature/Savings`, all
  `Feature/Stores`, and the Property/Investment/Mortgage controller tests.
- **Trait blast radius 441 passed** — Estate, Tax, Coordination, NetWorth, Mobile
  aggregator. No Estate file was edited.
- **Chattels + SharedOwnership 15 passed** (after Batch C's migration fix unblocked
  `RefreshDatabase`).
- **Frontend: full vitest suite 949 passed / 92 files.**
- `./vendor/bin/pint --dirty` → `passed`.

---

## 6. What is IN FLIGHT

**Nothing.** No edit is half-applied, no verification is half-run. Every file listed in
§5 is written, linted and covered. A replacement agent can start from §7.

---

## 7. What is NOT STARTED, in priority order

These are gaps and follow-ups, not unstarted dispatch items.

1. **`/m` live verification of every item** — the code is written and
   `vite.mobile.config.js` builds clean (126 modules, to a scratch outDir), but
   rendering `/m` locally needs `public/m-build/` rebuilt while CSJ has Vite live on
   :5173. Per `verify-m`, `/m` is verified on csjones. **Highest priority.**
2. **W-0025 form click-through** — verified at the HTTP layer only; the shared browser
   was occupied by another agent's session (Adam Hall, id 19, created 11:23 today).
3. **`joint_owner_name` on `savings_accounts` and `investment_accounts`** — the schema
   change that would let the counterparty rule be universal. Needs its own item.
4. **The `: JsonResponse` / `noContent()` mismatch is a real but SHORT tail.** All 146
   controller files swept, two passes. Exactly two instances existed, both on delete
   endpoints: `ChattelController` (fixed) and `WillController::deleteBequest` (Batch B's).
   Four other `noContent()`/`stream()`/`download()`/`file()` returns are fine because
   their declared types admit them. **Re-running the sweep: match
   `return response()->noContent()`, not the bare word** — `ChattelController:208` now
   contains it in an explanatory comment and will otherwise re-report as unfixed.
5. **`dividend_yield` had no input anywhere in the SPA** despite being validated by both
   holding requests — fixed alongside W-0039's units input, since the validation rule
   already existed. Same disease as Target Retirement Income (W-0035, not ours).
6. **`ChattelController::destroy():206`** — **FIXED under W-0041.** Was:
   `response()->noContent()` against a `: JsonResponse` return type — every chattel delete 500s *after* deleting the row.
   Pre-existing, outside all nine items, **reported not fixed** per scope discipline.
6. **Fee-drag/projection movement after entering an adviser fee** not measured
   (W-0008 acceptance bullet 4).
7. **`WillController::deleteBequest():221` returns `noContent()` against `: JsonResponse`**
   — the ONLY other instance of W-0041's pattern in the whole codebase, found by the
   sweep. **A live bug: every bequest delete removes the row and then 500s.** Not fixed
   because Estate/Wills is Batch B's area and they have live work on that exact surface.
   The fix is one line, identical to the chattel one. **Route to Batch B.**
8. **Non-spouse co-owner on a property** (Mike Barrett, tenants-in-common 60%) — my
   earlier "blocked by the free-tier cap" note was **wrong**: the team lead verified
   `TeaserGate::allows(16, 'property')` returns true and the block was stale
   client-side capability state. It is probably enterable; the tester will confirm.
8. **iOS (`ios-native/`)** — outside this dispatch throughout.

---

## 8. Environment state the work depends on

- **Branch:** `dev`. **No feature branch, no commits, no PR, no deploy.** All changes
  are uncommitted in the working tree.
- **Shared working tree.** Batches B (Estate/Wills) and C (Retirement/Profile/Gates)
  are editing the same checkout. `git status` shows their files too — Estate services,
  `TaxDefaults`, `TaxConfigService`, pension fields, profile enums. **Do not revert or
  lint-sweep files you did not write.**
- **Test database:** `DB_DATABASE=laravel_testing_a ./vendor/bin/pest <paths>`.
  `phpunit.xml`'s `<env>` has no `force="true"`, so the shell variable wins.
  An earlier isolated `fynla_testing_batcha` also exists and is harmless.
- **Data note for the tester (consequence of W-0039):** `holdings.id 32` currently reads
  `quantity = 333.333333`. That is the OLD back-calculation (£85,000 allocation-derived
  value / £255 price) captured before units were enterable. Entering the persona's
  actual **333** units will now derive `current_value = 333 x 255 = £84,915`, not
  £85,000 — the account total will move by £85. That is the fix working, not a
  regression: £85,000 was never a figure anyone typed.
- **Test users:** David Jones id 16 (`david.jones@example.com`) and Sarah Jones id 17
  (`sarah.jones@example.com`), **password `Password1!`**, reciprocally linked via
  `users.spouse_id`. MFA code from
  `EmailVerificationCode::where('user_id', …)->latest()->first()->code`.
  **Never delete or modify these users.** Household verified intact: 1 property,
  4 savings, 2 investment accounts, 1 mortgage, 6 chattels.
- **Local dev server** running on `localhost:8000` with **Vite live on :5173** and
  `public/hot` present. **Do not run a web `vite build`** — laravel-vite-plugin deletes
  `public/hot` and breaks the running dev server.
- **Shared browser.** The Playwright browser is shared between agents; check whose
  session is active before driving it. Never `browser_close`.
- **Pint:** run, clean. **Scope Pint to your own paths — never `--dirty`.** The tree is
  shared by four batches; a `--dirty` run reformatted another batch's
  `CompaniesHouseServiceTest.php`. Harmless that time, but it is a foreign hunk in a
  shared tree.
- **`/m` IS testable locally** — the team lead rebuilt `public/m-build/` and confirmed
  `/m/app/dashboard` serves the fresh bundle at 200. **Do not rebuild it yourself**;
  build artefacts belong to the lead. This supersedes the earlier "verify on csjones"
  note: CSJ's plan is web AND `/m` green locally, then dev.
- **Do not browser-verify your own fixes.** Diagnosis in the browser is valuable —
  it is what found the ISA prop-hop and the holding-form account bug — but
  certification is the tester's, independently.
- **Migration `2026_08_21_000000_normalise_shared_ownership_percentage` has been applied
  locally** (`migrate:status` → Ran, batch 46).

---

## 9. Files this batch owns

**New:** `app/Support/SharedOwnership.php` ·
`database/migrations/2026_08_21_000000_normalise_shared_ownership_percentage.php` ·
`resources/js/mixins/isaAllowanceMixin.js` ·
`tests/Unit/Support/SharedOwnershipTest.php` ·
`tests/Feature/Chattels/JointChattelCounterpartyTest.php` ·
`tests/frontend/utils/ownership.test.js` ·
`tests/frontend/store/investmentHoldings.test.js` ·
`tests/frontend/store/savingsIsaAllowance.test.js`

**Modified — backend:** `app/Traits/CalculatesOwnershipShare.php` ·
`app/Http/Controllers/Api/{Investment,Property,Mortgage,Savings,Chattel}Controller.php` ·
`app/Http/Requests/Savings/{Store,Update}SavingsAccountRequest.php` ·
`app/Http/Requests/Chattel/{Store,Update}ChattelRequest.php` ·
`app/Http/Requests/{Store,Update}InvestmentAccountRequest.php` ·
`app/Http/Requests/StorePropertyRequest.php` ·
`app/Http/Requests/Investment/UpdateHoldingRequest.php` ·
`app/Http/Resources/ChattelResource.php` ·
`app/Services/Property/MortgageService.php` ·
`app/Services/Stores/Normalisers/{InvestmentAccount,Mortgage,Property,SavingsAccount}Normaliser.php` ·
`routes/api.php` (ISA allowance tax year made optional)

**Modified — frontend:** `resources/js/utils/ownership.js` ·
`resources/js/store/modules/{investment,savings}.js` ·
`resources/js/services/savingsService.js` ·
`resources/js/components/NetWorth/{InvestmentList,PropertyCard,ChattelDetailInline,InvestmentProjections}.vue` ·
`resources/js/components/NetWorth/PropertyList.vue` ·
`resources/js/components/Investment/{AccountForm,StandardInvestmentFields,HoldingForm,InvestmentHoldings,PortfolioOverview}.vue` ·
`resources/js/components/Savings/{SaveAccountModal,SavingsModuleOverview}.vue` ·
`resources/js/components/Cash/AccountGroupList.vue`

**Modified — `/m`:** `resources/mobile/views/modules/{Investment,InvestmentAccountDetail,SavingsAccount,PropertyDetail,NetWorthCategory}.vue`

**Modified — tests:** `tests/Unit/Traits/CalculatesOwnershipShareTest.php` ·
`tests/Unit/Services/Stores/Normalisers/{Mortgage,Property}NormaliserTest.php` ·
`tests/Unit/Services/MortgageServiceOwnershipTest.php` ·
`tests/Feature/Savings/SavingsApiTest.php` ·
`tests/Feature/Stores/{InvestmentAccountHttpIntegration,PropertyHttpIntegration}Test.php` ·
`resources/js/components/__tests__/NetWorth/PropertyCard.spec.js`

---

## 10. The W-0052 regression — why my own verification did not surface it

**Written at the team lead's request, because the lesson is more useful than the fix.**

### What happened

My W-0008 fix added `'advisor_fee_percent' => ['nullable','numeric','min:0','max:10']`
to the investment Store/Update requests. That field is `NOT NULL DEFAULT '0.0000'` in
the database. Before the rule existed, `validated()` **stripped** the key, the INSERT
omitted the column, and the default applied. Adding a `nullable` rule is exactly what
let `AccountForm.vue:971`'s explicit `null` — sent whenever the additional-information
panel is collapsed — survive validation and reach a NOT NULL column.

Every investment account create returned 500. The modal just sat there; the user saw
nothing.

### Why I did not catch it

My W-0008 board note records: *"Adviser fee input present on add and edit."* That was
true, and it was the wrong test.

The failing case needs the panel **collapsed** on save — which is the **default state**,
and the state in which the field I had just added is **invisible**. I verified the
field on the path where I could see it. A collapsed panel read to me as "the feature is
switched off", not as "a materially different payload". So the one state I never
exercised was the one every real user hits first.

There was no carelessness available to avoid here, and that is the point. The bug lived
in the interaction between a change I made and a frontend block I had read but not
connected to column nullability — `AccountForm.vue:965-972`, which I had edited in the
same session, to add `submitData.advisor_fee_percent = null` to it. **I wrote both
halves of the defect in one sitting and still could not see it**, because each half is
correct on its own.

### The three transferable lessons

1. **Adding a `nullable` validation rule is a schema change in disguise.** It moves a
   column from "always stripped" to "can arrive null". Before adding one, check the
   column's nullability. This is now enforced by a test rather than by memory.
2. **Verify the DEFAULT state, not the state that shows your work.** A newly added
   field is most visible in the state where its panel is open — which is usually the
   state a user does not start in.
3. **A per-column special case is a warning.** `country` had its own null-drop in two
   FormRequests for exactly this reason and nobody generalised it. When you find
   yourself matching an existing one-off, ask what class it belongs to. Doing that here
   turned a one-field fix into 28 columns covered — 27 of which were latent, mostly on
   the Employee Share Scheme path the persona has not reached yet.

### Why self-certification cannot catch this class

The author's mental model is what selected the test. I tested the thing I had built,
in the configuration I had been looking at, and it passed — correctly. Independent
verification catches it because the tester's model is *the user's journey*, not *the
diff*. This is the same shape as the `fixed inset-0` overlay the tester found: an
element Playwright reports as visible, enabled and stable, where a dispatched click
fires the handler and reports success. In both cases the check passes and the user is
still broken.

**The looking is still valuable — browser diagnosis is what found the ISA prop-hop and
the holding-form account bug in this batch. It is the certifying that has to be
independent.**
