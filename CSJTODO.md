# CSJTODO — Fynla

*Last updated: 25 April 2026 — session 73 (Sprint 0 Tasks 0.3 + 0.4 + 0.5 complete)*
*Previous session: 24 April 2026 — session 72 (Sprint 0.2 sidecar deletion + phpunit 4G memory bump)*

---

## Session 73 (25 April morning) — Sprint 0 Task 0.5 complete: 16 create handlers fill_form → direct DB::transaction writes

### Completed

#### Sprint 0 Task 0.5 — convert every `create_*` AI tool handler from `fill_form` (UI hydration) to direct `DB::transaction` writes — **DONE** (13 commits)

- [x] **0.5.a `create_savings_account`** (`b7a881d`) — 6 tests. Validates AI input, ISA auto-inference (`is_isa: true` → `account_type: cash_isa`), AI→DB enum mapping (`fixed_term`→`fixed`, `regular_saver`→`easy_access`), preserves duplicate-name check + `invalidateUserCache`.
- [x] **0.5.b `create_investment_account`** (`bed4222`) — 6 tests. AI→DB account_type mapping (`stocks_shares_isa`→`isa` with `isa_type='stocks_shares'`, `personal_investment_account`→`gia`); ISA-must-be-individual hard rejection (mirrors `InvestmentController::storeAccount` UK-tax rule); pass-through for VCT / EIS / private-company / employee-scheme fields.
- [x] **0.5.c `create_holding`** (`fec1b7c`) — 5 tests. Polymorphic `holdable_type=InvestmentAccount` write; `current_value` derived from `allocation_percent * account.current_value` when AI didn't supply quantity/price (DB column is NOT NULL with no default).
- [x] **0.5.d `create_pension`** (`43086a8`) — 6 tests. DC vs DB branch with separate model writes — DC defaults `pension_type=occupational`, DB defaults `scheme_type=final_salary`; AI scheme_type → DC pension_type mapping (`workplace`→`occupational`, `sipp`→`sipp`, etc.).
- [x] **0.5.e `create_property`** (`0054141`) — 7 tests. Atomic `Property + Mortgage` write inside one transaction when AI flags `has_mortgage` (or supplies legacy `outstanding_mortgage > 0`); ownership defaults from ownership_type (joint/tenants_in_common → 50%, individual → 100%, trust → 0); honours both AI param names (`mortgage_outstanding_balance`, `mortgage_interest_rate`) and legacy Anthropic ones (`outstanding_mortgage`, `mortgage_rate`).
- [x] **0.5.f `create_mortgage`** (`8763670`) — 6 tests. Standalone mortgage; resolves target property via fuzzy match on `address_line_1` / `postcode` / `city` LIKE `%hint%`; falls back to user's only property when one exists; returns `error.error_type=missing_property` when none, `property_match_failed` when hint unmatched.
- [x] **0.5.g `create_protection_policy`** (`c503eae`) — 7 tests. Branches across 3 models (`LifeInsurancePolicy` / `CriticalIllnessPolicy` / `IncomeProtectionPolicy`) by AI `policy_type`; strips `_ci` suffix to match `critical_illness_policies.policy_type` enum's bare values (`standalone`, `accelerated`); maps generic `term` → `level_term` for life policies.
- [x] **0.5.h-j `create_asset / create_liability / create_estate_gift`** (`87637e8`) — 9 tests. Estate `Asset` with `valuation_date` defaulted + `is_iht_exempt`; `Liability` maps `loan` → `personal_loan`; `Gift` resolves family-name references via existing `resolveFamilyNames` helper.
- [x] **0.5.k `create_family_member`** (`d2c1253`) — 7 tests. Direct-write only — AI tool schema has no `email` parameter so `SpouseLinkingService::linkOrCreateSpouse` doesn't apply (spouse linking remains the onboarding director's territory). Maps AI relationship enums (`step_child`→`child`, `partner`→`other_dependent`) and appends mapping note. Education status inferred from DOB for children.
- [x] **0.5.l `create_trust`** (`17ea6df`) — 6 tests. Derives `is_relevant_property_trust` from `trust_type` (true for `discretionary` / `accumulation_maintenance`, false otherwise — mirrors `TrustController::createTrust`); settlor defaults to user's full name; `TrustObserver::created` continues to emit the matching CLT `Gift` row.
- [x] **0.5.m-n `create_business_interest / create_chattel`** (`adf2062`) — 7 tests. BusinessInterest persists with `valuation_date` defaulted; Chattel maps AI category enums (`jewellery`→`jewelry`, `antiques`→`antique`, `collectibles`→`collectible`, `vehicles`→`vehicle`) to canonical singular DB values.
- [x] **0.5.o-p `create_goal / create_life_event`** (`87bb04f`) — 8 tests. Goal sets `custom_goal_type_name` automatically when `goal_type=custom`; LifeEvent defaults `certainty=likely` and ownership defaults to individual / 100%.
- [x] **0.5.q rollup tests** (`71aa98a`) — 5 tests across 3 files:
  - `DirectWriteCoverageTest` — exactly 1 `'action' => 'fill_form'` site remaining (in `handleUpdateRecord` — 0.7's territory); all 16 handlers contain `'success' => true`, `'created' => true`, and `DB::transaction`.
  - `DirectWriteObserverFireTest` — every direct-write handler fires its model's `created` Eloquent event (so risk / goal / net-worth observers continue to run).
  - `DirectWriteTransactionRollbackTest` — validation failure leaves zero rows; DB-level rollback contract verified for the property+mortgage atomic write path.

#### Test coverage summary
- [x] **New:** 12 files in `tests/Feature/AI/DirectWrite/` — 85 tests, 357 assertions, all green.
- [x] **Regression check:** `./vendor/bin/pest tests/Feature/AI tests/Feature/Fyn tests/Feature/Onboarding tests/Architecture` → **259/259 passing, 0 regressions.**

#### Spec amendment captured in plan
- [x] Source spec said the lone surviving `'action' => 'fill_form'` site after S0.5 would live in `handleCreateWhatIfScenario` (per INV-2.5.6). Reality: `handleCreateWhatIfScenario` returns `'action' => 'navigate'`, and the only remaining `fill_form` is in `handleUpdateRecord`. Plan updated with delivery note; coverage test pins this so 0.7 can rewrite the path without re-litigating the architectural floor.

### NOT Done — Outstanding for next session

#### Sprint 0 continuation — Task 0.6 next
- [ ] **S0.6 — Billing / subscription tools** (`get_subscription_status`, `list_invoices`, `get_current_plan`). Add to both `AiToolDefinitions` (Anthropic) + `XaiToolDefinitions` (with `strict: true` wrap); 3 new handlers in `CoordinatingAgent::executeTool` switch; tests at `tests/Feature/AI/BillingToolsTest.php` + `tests/Architecture/ToolCatalogueParityTest.php`. Brings catalogue to 40/40. See [[April/April24Updates/plan/10-sprint-0-plan|plan]] §S0.6.

- [ ] **S0.7 — `update_record` allowlist + strict schema.** Replaces the 2-field blocklist (`user_id`, `id`) with per-entity-type allowlist via `UpdateRecordAllowlist::MAP`; replaces `fields` schema with `oneOf` keyed on `entity_type`; xAI wraps with `strict: true`. **This is the path that owns the lone surviving `'action' => 'fill_form'` site after S0.5** — 0.7 can rewrite it now without affecting 0.5's contract.

- [ ] **S0.8 — `delete_record` two-phase confirmation.** First call returns `{requires_confirmation: true, confirmation_token: <sha256>, preview_message}`; second call with matching token + same-day salt proceeds within `DB::transaction`.

- [ ] **S0.9 through S0.17** — see plan; nothing changed in scope from session 72's CSJTODO.

#### Tech debt logged from session 73 (deferred — not blocking S0.6)

- [ ] **W1 — Repeated success-envelope literal across 16 handlers.** The 7-key return shape `['success' => true, 'created' => true, 'entity_type' => ..., 'entity_id' => ..., 'name' => ..., 'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])), 'message' => ...]` repeats verbatim 16 times. Extract to `private function createdEnvelope(string $entityType, Model $entity, array $payload, string $nameField, string $message): array`. Each handler shrinks ~7 lines. Defer to a follow-up "S0.5 polish" pass.

- [ ] **W2 — Long handler bodies.** 10 of the 16 new handlers exceed 60 lines (largest: `handleCreateInvestmentAccount` 134, `handleCreateProperty` 130, `handleCreateProtectionPolicy` 130). The repeated `foreach ([...] as $f) { if (isset($input[$f]) && is_numeric($input[$f])) { $payload[$f] = (float) $input[$f]; } }` pattern across handlers could lift into `mergeOptionalFields(...)` helper. Defer until after Sprint 0.7/0.8.

- [ ] **S1 — Extract `handleCreateProperty`'s mortgage-write block** into `private function persistMortgageForProperty(...)`. Keeps the property handler at ~70 lines and makes the mortgage logic separately testable. Defer.

- [ ] **Pre-existing — `CoordinatingAgent.php` is 3,568 lines** (S0.5 added net ~250 lines). Past the 500-line "split" threshold but splitting has been deferred because every handler shares private helpers (`validateToolInput`, `previewBlocked`, `checkForDuplicate`, `invalidateUserCache`, `resolveFamilyNames`). Worth revisiting after Sprint 0.7/0.8 (update + delete handlers) land.

### Context for Next Session

Sprint 0 is **5/17 tasks done** (0.1, 0.2, 0.3, 0.4, 0.5 ticked). Branch `feature/fyn-persona-split` is 85 commits ahead of `main`, just pushed to `origin`. Working tree clean. 4 personasplit services deleted in session 70 (S0.3) — CLAUDE.md metrics row updated 262 → 260 PHP services.

**Start session 74 with S0.6** (billing tools — small, mechanical, 3 read-only handlers + 2 test files). It's the lowest-risk next slice and lets you keep momentum before S0.7's allowlist work, which is heavier.

**The lone surviving `'action' => 'fill_form'` lives in `handleUpdateRecord`** — that's intentional. Sprint 0.7 owns it, not 0.5. The `DirectWriteCoverageTest` pins this so any accidental re-introduction of `fill_form` on a create handler will fail loudly.

**No deploy this session.** Branch is mid-Sprint-0; nothing to ship until 0.6 → 0.17 land. Per [[memory:feedback_main_via_dev_only|feedback_main_via_dev_only]]: nothing merges to main without first being on dev + browser-tested.

---

## Outstanding — Tech Debt Deferred (carried from earlier sessions)

### From session 72
- [ ] **Known flake — `WillBuilderApiTest::pre-populate`** (pre-existing, 30% fail rate). `tests/Feature/Estate/WillBuilderApiTest.php:17-21` creates user with `first_name='James' + surname='Carter'`. `UserFactory.php:30` sets `middle_name` via `fake()->optional(0.3)->firstName()`. `User.php:292` concatenates first+middle+surname in `full_name`. Test asserts `'James Carter'` → fails 30% when faker rolls a middle name. **Fix: add `'middle_name' => null` to the factory override in the test.** One-line PR, separate from Sprint 0 work.

- [ ] **Deploy PR #235 main-test-fixes** to `csjones.co/fynla` then `fynla.org`. Upload 9 files per [[April/April25Updates/deployInherit|deployInherit]]. No migrations, no build. SSH + cache clears. Browser smoke Estate + Holdings. After dev soak: open `dev → main` PR (will include PR #236 phpunit bump alongside PR #235 fixes).

### From earlier sessions
- [ ] **Production cleanup of `build.old/`** + 19 historical sandbox CheckoutPage chunks (after 24h) — flagged session 68.

- [ ] **Exercise edit-mode auto-expand** + collapsed-form submit → DB + onboarding-path for the field-collapse toggle work — flagged session 67.

## Known Issues
- [ ] **Production lifecycle engine throttle** (resolved in session 68 — `LIFECYCLE_THROTTLE_MS=150` in prod `.env`, engine re-enabled). Listed here only for handover continuity; no action.

## Deploy Status

| Environment | Branch | State |
|---|---|---|
| `fynla.org` (production) | `main` | Last release: PR #228 + PR #231 hotfix (sessions 67–68, 23 April). |
| `csjones.co/fynla` (dev) | `dev` | Last deploy: session 65 (afternoon, 23 April) — lifecycle engine + Awin + insights CMS + session 66/67 UI fixes. PR #235 main-test-fixes still pending CSJ deploy from session 71 (see deferred above). |
| `feature/fyn-persona-split` | (none) | 85 commits ahead of main, 5/17 Sprint 0 tasks done. NOT deploying — still mid-Sprint. |

---

*This file is the canonical handover. `session-start` reads this first.*
