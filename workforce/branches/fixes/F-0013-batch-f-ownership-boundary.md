---
id: F-0013
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/05-perimeter.md, core/constitution/08-process.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-21T21:15:00Z
status: active
---

# F-0013 — Batch F: the shared-ownership boundary, and the consent split

**Agent:** build-lead (`fix-batch-F`) · **Written:** 2026-08-21
**Branch:** `dev` (shared working tree — nothing committed, no PR, by instruction)
**Number issued by team-lead.** I first wrote this as `F-0012` before the number
was issued and collided with `fix-batch-G`'s IHT work (`W-0154` already claimed
`F-0012-batch-g-iht-household.md`). Corrected to `F-0013` on instruction — which is
exactly the collision the issue-don't-choose rule exists to prevent, and I caused a
third one an hour after it was written.

**Predecessor:** `F-0007-batch-f-analytics-consent.md` (W-0047, W-0049). This
document continues the same agent onto the ownership boundary; read F-0007 first
for the consent work.

| Item | Status | Notes |
|---|---|---|
| W-0040 100/0 unexpressible | **DONE** | `handoff` → quality-lead. Mechanism + the three arch failures |
| W-0161 Fyn joint liability at 100/0 | **DONE** | `handoff` → quality-lead. Found by the W-0040 census |
| W-0172 TIC property's mortgage at joint 50% | **DONE (share)** | `handoff` → quality-lead. Type clause needs a decision — see §4 |
| W-0162 mortgages cannot really hold TIC | **RAISED** | `queued`. Not a defect; a column that promises what the app rejects |
| W-0049 consent split | **DONE** | Appended to F-0007's item |
| W-0042 off-platform co-owner on savings/investments | **NOT STARTED** | **Blocked on a CSJ product decision** — see §5 |
| W-0043 the orphaned mortgage | **NOT STARTED** | `fix-batch-G` owns the investigation and has handed it over |

---

## 1. Amendments received since F-0007

1. **Consent split** — `TYPE_COOKIES` → `cookies_analytics` + `cookies_affiliate`,
   both from the one click. Jumped the queue; window closed at commit.
2. **Three architecture failures** in my in-flight work, blocking the commit.
3. **W-0043 dropped then returned** — `fix-batch-G` owns the investigation, I own
   the code. **Read its working notes from the bottom.**
4. **W-0172 added**, high, live user-visible wrong figure.
5. **Do not re-record the tool-schema golden masters.** Tell the lead and stop.
6. **Branch numbers are issued by the lead**, not chosen at close-out.

---

## 2. The consent split (W-0049, continued)

`compliance-lead`'s review found that one `cookies` row covered two materially
different activities and so could not say which the visitor agreed to. Split into
`TYPE_COOKIES_ANALYTICS` + `TYPE_COOKIES_AFFILIATE` (`UserConsent.php:40,42`), with
`COOKIE_BANNER_TYPES` (`:51-53`) as the one home for the list.
`CookieConsentService::record()` loops it and writes both, one call, one moment, one
subject. `GDPRController.php:71` excludes both via the same list.

**Unchanged, deliberately:** the button, the endpoint, the moment, the status
cookie, the middleware gate, the wall. Two rows for one user action is not two write
paths.

**No data migration.** Not deployed. Four `cookies` rows exist on the LOCAL dev
database (ids 110-113, anonymous, 18:33–19:32 today) — artefacts of my own requests.
**Left alone deliberately**; hand-deleting consent records is not a habit worth
forming.

---

## 3. The ownership boundary (W-0040, W-0161)

Full detail in the board items. The short version a replacement needs:

- **`SharedOwnership` can now tell a stated share from an inherited one.**
  `statedShare()`, `isValidSharedSplit()`, and `applyTo($data, $type, $existing)`.
  A stated share is honoured or refused, never rewritten; an absent one defaults,
  or inherits from the stored record **when that stored share is itself a valid
  shared split** (an individual record's 100 is not, so individual → joint
  conversion still re-defaults to 50).
- **The refusal has one home:** `app/Http/Traits/ValidatesSharedOwnership.php`,
  called from all eight asset form requests.
- **Five copies outside the one home were converged** — three in
  `CoordinatingAgent`, one in `PropertyNormaliser::fromFyn`, one in
  `LiabilityStore`. The last was a live bug (W-0161).
- **Two forms stopped stating a share they did not mean** — `PropertyForm.vue`
  (only for tenants in common) and `ChattelFormModal.vue` (only for joint).

### The three architecture failures — decided, not exempted

| Failure | Decision |
|---|---|
| trait fails "extends FormRequest" | **Moved** to `app/Http/Traits/`, where `SanitizedErrorResponse`, `GatesEstateAccess` and `TierLimitResponse` already live. The expectation is right and a shared trait is what it excludes. |
| trait fails "suffix Request" | same move |
| `SavingsController` uses `App\Models\SavingsAccount` | **The store owns the lookup.** `SavingsStore::find($id, $user)` already existed. `SavingsStoreBoundaryTest`'s docblock says the allowlist is FINAL and all joint-aware reads funnel through the store — and `PropertyStoreBoundaryTest:154` *does* list `PropertyController`, so savings being stricter is deliberate. |

**Architecture suite: 149 passed, 1 skipped, 0 failed** (before: 148 / 3 failed).

---

## 4. W-0172 — done, with one clause deliberately not done

**Fixed:** `MortgageService::createFromPropertyData()` now takes the share from the
parent property when the mortgage states none, via
`SharedOwnership::applyTo($data, $type, $property)`. A stated mortgage share still
wins. £48,000 of £120,000, proven at the endpoint.

**NOT done, and it needs a decision.** Acceptance 1 also asks the mortgage to inherit
the property's `ownership_type`. The wizard's Borrower(s) control offers only
"Just me" / "Joint borrowers", so it **states** `joint` — honouring the clause
literally means overriding a stated value, the exact thing W-0040 established must
never happen, and it breaks a test pinning a deliberate prior decision that mortgage
liability is configured independently of property ownership. **Every figure in the
item comes right without it.**

### The mistake I nearly made — read this before touching the coercion

I first also removed the `tenants_in_common` → `joint` coercion, on the strength of
finding `2026_01_17_100145_add_tenants_in_common_to_mortgages_ownership_type`, which
makes the code comment ("mortgages only support individual and joint") look plainly
stale.

**It is stale about the column and correct about the application.**
`MortgageStore::validateCanonical:304` is `'in:individual,joint'`, and seven
consumers decide shared-ness by testing `ownership_type === 'joint'` exactly. A TIC
mortgage would read as **individual** everywhere and charge **100%** of the debt.

A migration tells you the database is ready. It tells you nothing about the app.
Filed as **W-0162**.

---

## 5. W-0042 — not started, and blocked

**Its acceptance is a question for CSJ, not a defect to fix:** *"Should a shared
savings or investment account be able to name an off-platform co-owner?"* The item
itself frames it as "a schema and product decision, not a bug", and records that
`fix-batch-A` was **right** not to enforce the counterparty rule there, because
joint-with-no-linked-owner is deliberately first-class on those two tables
(`SavingsStore.php:357-361`) and enforcing it without the column would delete a
working capability.

**Nothing should be built until that is answered.** If the answer is yes it is large:
two migrations, two models, four form requests, two web forms, their `/m`
counterparts, the resources, **and the Fyn tool catalogue** — which trips the
standing instruction to stop rather than re-record golden masters.

---

## 6. Environment state a replacement depends on

- **Working tree `dev`, shared, six agents.** Nothing committed by me. No branch, no
  PR, no deploy.
- **Test database `laravel_testing_b`.** `pgrep -f "vendor/bin/pest"` first.
- **`DEPR`/`!` is not failure.** Now documented in `tests/CLAUDE.md`. Read the
  summary line: no `failed` count means nothing failed. This cost two agents time
  today, once on my own file.
- **Local dev DB carries `2026_08_21_140000_add_anonymous_subject_to_user_consents_table`.**
  Applied with `--path=`. Additive and nullable; `db:seed` unaffected.
- **`pint` reformatted `app/Agents/CoordinatingAgent.php`** while formatting my
  changes — `single_quote`, `braces_position`, `ordered_imports`. That file is
  mid-edit by another agent; the churn is formatting only.
- **I have stopped editing** `SharedOwnership`, both savings requests and
  `SavingsController`, so the pre-commit verification can run.
- No production access used. No users created, deleted or provisioned.

---

## 7. Test evidence

All under `DB_DATABASE=laravel_testing_b`.

| Suite | Result |
|---|---|
| Ownership consolidated (`SharedOwnershipTest`, `CalculatesOwnershipShareTest`, `MortgageServiceOwnershipTest`, `Unit/Services/Stores`, `CoordinatingAgentJointOwnerTest`, `Feature/NetWorth`, `Feature/Chattels`, `Feature/Savings`, `Feature/Investment`, `CaptureAccuracyGateTest`) | **427 passed** |
| Re-run from the current tree after the arch fixes | **440 passed** |
| Mortgage + net worth after W-0172 | **59 passed** |
| Consent families | **85 passed** |
| Architecture | **149 passed, 0 failed** |
| Frontend (`vitest`) | **22 passed** |
| `pint --test` on every file touched | clean |

New test files:
`tests/Feature/NetWorth/StatedOwnershipShareTest.php` (15),
`tests/Feature/NetWorth/MortgageInheritsPropertyShareTest.php` (5),
`tests/Unit/Services/Stores/LiabilityStoreOwnershipTest.php` (4),
`tests/Feature/Consent/CookieConsentTest.php` (15, from F-0007),
`tests/Feature/Middleware/CaptureAwcCookieTest.php` (6, from F-0007),
`resources/js/__tests__/cookieConsent.spec.js` (8, from F-0007).

**Nine tests that pinned defects were rewritten, never deleted** — each records what
it used to assert and why.

---

## 8. Decisions a replacement must not re-litigate

1. Anonymous subject on `user_consents`, not a second store.
2. `cookies_analytics` + `cookies_affiliate`, both from one click, one endpoint.
3. Record in the database, gate on the cookie — the middleware is global.
4. Refusal, not "resolve to individual ownership", for a stated 0 or 100.
5. Only a share that is itself a valid shared split is ever inherited.
6. The trait lives in `App\Http\Traits`; the arch expectation stands unweakened.
7. The savings store owns its own reads.
8. The mortgage TIC → joint coercion **stays** until W-0162 is decided.
9. A form states a share only where it lets the user set one.

## 9. Dead ends already walked

- Reading consent from the database inside `CaptureAwcCookie` — global middleware,
  no session, no resolved user.
- Letting `PUT /api/auth/gdpr/consents` write cookie consent — a second write path.
- A shared client module for all three banner surfaces — the public funnel script is
  unbundled and cannot import from `resources/`.
- Holding the `awc` value server-side pending consent — processing for marketing
  before consent.
- `->change()` on a constrained column — drops the FK on some MySQL 8 builds.
- Asserting a cleared cookie's value is `''` — it is `null`.
- `withUnencryptedCookie()` on a JSON request without `withCredentials()` — silently
  dropped.
- Removing the mortgage TIC coercion — see §4.
