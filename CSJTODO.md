# CSJTODO — Fynla

*Last updated: 25 April 2026 — session 74 (Sprint 0 Tasks 0.6 + 0.7 + 0.8 complete)*
*Previous session: 25 April 2026 — session 73 (Sprint 0 Task 0.5 complete)*

---

## Session 74 (25 April mid-morning) — Sprint 0 Tasks 0.6 + 0.7 + 0.8 complete

### Completed

#### Sprint 0 Task 0.6 — Billing / subscription tools — **DONE** (commit `dcf35ed`)

- [x] **3 read-only zero-parameter tools** added on both Anthropic and xAI providers: `get_subscription_status`, `list_invoices`, `get_current_plan`. Catalogue now 40/40 with parity verified by `ToolCatalogueParityTest` in both preview and live modes.
- [x] **Adapted to live schema** rather than spec's aspirational shape — `Subscription` carries plan as a string slug + decimal pounds (no `subscription_plan_id` FK or `plan` relation), `Invoice` stores `total_amount` in pence + storage-relative `pdf_path`. Handlers convert pence → pounds, expose `/api/payment/invoices/{id}/download` as `pdf_url`, resolve matching `SubscriptionPlan` via `findBySlug` with `ucfirst()` fallback.
- [x] **Read-only, exposed in both modes** — preview personas get canonical `status:'none'` / empty invoice / `tier:'none'` shapes, useful for honest answers about subscription state.
- [x] **11 tests** in `BillingToolsTest.php` (none / active / cancelled / trial subscription, empty / populated invoices, pence-to-pound conversion, ordering, yearly-vs-monthly resolution) + **4 tests** in `ToolCatalogueParityTest.php` (parity in both modes + presence checks).

#### Sprint 0 Task 0.7 — `update_record` allowlist + strict schema — **DONE** (commit `384b1fb`)

- [x] **`UpdateRecordAllowlist::MAP`** — per-entity positive allowlist replacing the 2-field blocklist (`user_id`, `id`). 18 entity types covered with DB-field names matching live schema (corrected against spec's draft — e.g. `current_balance` not `balance`, `current_fund_value` not `pot_value`, `premium_amount` not `monthly_premium`).
- [x] **Forbidden fields** now return `fields_not_allowed`: `Trust.settlor`, `Mortgage.start_date`, `Mortgage.mortgage_type`, `FamilyMember.relationship`, every identity FK (`user_id`, `joint_owner_id`, `household_id`, `trust_id`, `linked_user_id`, `holdable_id`), audit timestamps. Holding (polymorphic) deferred — would need separate lookup path; not blocking.
- [x] **Schema-level enforcement** — Anthropic uses oneOf per entity_type with `additionalProperties:false` on each branch; xAI cannot use oneOf in strict function calling, so falls back to a union schema (all allowed field names with `additionalProperties:false`) — runtime allowlist re-checked.
- [x] **Field-aliasing layer expanded** — LLM may use schema names (DB-direct) OR legacy aliases (`monthly_premium`, `pot_value`, etc.) and the handler maps them to DB names before allowlist consultation.
- [x] **`handleUpdateRecord` rewritten to direct-write** via `DB::transaction`. **Last `'action' => 'fill_form'` site in `CoordinatingAgent` is gone.** `DirectWriteCoverageTest` updated from "exactly 1 fill_form site (in handleUpdateRecord)" → "zero fill_form sites".
- [x] **23 tests** — 10 unit (`UpdateRecordAllowlistTest.php` — forbidden fields, identity FK absence, timestamp absence, 18-type coverage, union shape) + 13 feature (`UpdateRecordSecurityTest.php` — Trust.settlor / Mortgage.start_date / mortgage_type / FamilyMember.relationship rejection, identity FK rejection, success paths, preview blocking, cross-user isolation, no fill_form on success).

#### Sprint 0 Task 0.8 — `delete_record` two-phase confirmation — **DONE** (commit `fcdc1a3`)

- [x] **Two-phase token flow** — `handleDeleteRecord` returns deterministic SHA-256 token on first call; deletion proceeds only when LLM echoes the exact token on a second call.
- [x] **Token bound to `(user_id, entity_type, entity_id, today's date)`** — same-day salt prevents replay across days; tokens isolated per user / per record / per type.
- [x] **`hash_equals()` for constant-time comparison** — prevents timing-side-channel probes.
- [x] **Not-found / cross-user check happens AFTER token match** — a stranger holding only an entity_id cannot probe for record existence (they would need a token bound to their own user_id, which won't match anyone else's record).
- [x] **Schema** — Anthropic adds `confirmation_token` as optional with rich description; xAI adds it to required (with nullable type) so strict mode stays happy. Tool descriptions on both providers spell out the two-phase contract.
- [x] **9 tests** in `DeleteRecordConfirmationTest.php` — deterministic-hash, second-call success, wrong-token rejection, cross-user / cross-entity_id / cross-entity_type isolation, yesterday's-token rejection, preview short-circuit, post-token cross-user not_found.

#### Test results (cumulative session-74)

- [x] **49 new tests + 1 updated** (DirectWriteCoverageTest assertion flipped 1→0).
- [x] **Regression sweep:** `./vendor/bin/pest tests/Feature/AI tests/Feature/Fyn tests/Feature/Onboarding tests/Architecture tests/Unit/Constants` → **334/334 passing, 1296 assertions, 0 regressions.**

#### Harness chore

- [x] **`2939452`** — Trimmed `session-start` skill to a lean bootstrap (CLAUDE.md and MEMORY.md are auto-loaded by the harness, so the prior bulk-load phases were duplicating context); removed `fynla-windows` marketplace listing (stubbed plugin path, misleading description).

### NOT Done — Outstanding for next session

#### Sprint 0 continuation — Task 0.9 next

- [ ] **S0.9 — Consent runtime check.** `AiChatController::sendMessage` + `startOnboarding` call `ConsentService::hasConsent($user, 'ai_chat')` at entry; 403 JSON `{error: 'consent_required', required: 'ai_chat'}` on false; mid-stream withdrawal triggers `consent_required` SSE + stream close. Files: `ConsentService` (add `TYPE_AI_CHAT`), migration to widen `user_consents.type`, controller guard, frontend SSE handler in `aiChat.js`. See [[April/April24Updates/plan/10-sprint-0-plan|plan]] §S0.9.

- [ ] **S0.10 — User-content sanitisation + structural separation** (`UserContentSanitiser`, regex strip + `<user_provided>` wrapping, applied in AdvicePromptBuilder + OnboardingPromptBuilder).

- [ ] **S0.11 — Reliability bundle (6 sub-steps).** Token-budget atomic, SSE abort instrumentation, idempotency-key middleware, provider-swap lock, gap-fill DB dedup, generateTitle sanitation, summariseToolResult preserves entity_id. New tables: `ai_daily_usage`, `ai_request_idempotency`, `ai_abort_events`.

- [ ] **S0.12 through S0.17** — see [[April/April24Updates/plan/10-sprint-0-plan|plan]]; nothing changed in scope.

#### Tech debt logged from session 74 (deferred — not blocking S0.9)

- [ ] **W1 — `CoordinatingAgent.php` is now 3,718 lines.** Pre-existing structural concern from the two-Fyn collapse / direct-write conversion — at session start was already 3,568 lines, so the +150 lines this session is small relative to the file. Sprint 4 backlog candidate: extract handler families (`Billing*Handlers`, `EstateUpdateHandlers`, `OnboardingCaptureHandlers`) into traits or services. Splitting now would conflict with the same-file test pins (`DirectWriteCoverageTest`, `DispatchRoutingTest`).

- [ ] **S1 — `handleUpdateRecord` field-aliasing chain is long** (16-branch `match ($entityType)`, ~25 lines). When S0.7 results stable, consider promoting to `app/Constants/UpdateRecordFieldAliases.php` alongside the allowlist. Not urgent — most aliases will become dead code now that the LLM is schema-constrained to DB names directly.

- [ ] **S2 — `handleListInvoices` queries `Invoice` directly** rather than `$user->invoices()`. `User` doesn't have an `invoices()` HasMany relation (only `subscriptions()` and `payments()`). One-line fix on `User` model but cross-cuts other consumers (`PaymentController::billingHistory`), so out of scope for S0.6.

#### Carried from session 73 (still deferred)

- [ ] **W1 carried — repeated success-envelope literal across 16 create handlers.** The 7-key return shape `['success' => true, 'created' => true, 'entity_type' => ..., 'entity_id' => ..., 'name' => ..., 'persisted_fields' => ..., 'message' => ...]` repeats verbatim. Extract to `private function createdEnvelope(...)`. Defer to "S0.5 polish".
- [ ] **W2 carried — long handler bodies.** 10 of 16 create handlers exceed 60 lines. The `foreach ([...] as $f) { if (isset($input[$f]) && is_numeric($input[$f])) { $payload[$f] = (float) $input[$f]; } }` pattern is mechanically identical — extract to `mergeOptionalFields(...)`. Defer.
- [ ] **S1 carried — Extract `handleCreateProperty`'s mortgage-write block** into `persistMortgageForProperty(...)`. Defer.

### Context for Next Session

Sprint 0 is **8/17 tasks done** (0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8 ticked). Branch `feature/fyn-persona-split` is **90 commits ahead of main**, just pushed to `origin/feature/fyn-persona-split`. Working tree clean.

**Start session 75 with S0.9** (consent runtime check). It's a focused controller-side change with one migration and one frontend handler — should be a one-session task. After that S0.10 (sanitiser) is similarly small. S0.11 (reliability bundle) is the next big piece — 6 sub-steps, 3 migrations, multiple new models.

**No deploy this session.** Branch is mid-Sprint-0; nothing to ship until 0.9 → 0.17 land. Per [[memory:feedback_main_via_dev_only|feedback_main_via_dev_only]]: nothing merges to main without first being on dev + browser-tested.

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
| `feature/fyn-persona-split` | (none) | 90 commits ahead of main, 8/17 Sprint 0 tasks done. NOT deploying — still mid-Sprint. |

---

*This file is the canonical handover. `session-start` reads this first.*
