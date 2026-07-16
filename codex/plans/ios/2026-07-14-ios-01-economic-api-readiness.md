# iOS Package 1: Economic Contract and API Readiness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Use `superpowers:test-driven-development` for each change, `security-and-hardening` for entitlement and payment boundaries, `systematic-debugging` for every failure, `verify-m` at each shared UI checkpoint, and `verification-before-completion` before closing the package. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish the approved two-tier remediation, freeze a backwards-compatible shared client contract, and prove the Laravel platform is safe for a native client without changing or retiring `/m`.

**Architecture:** Execute the existing economic remediation plan as the source implementation plan, then add a client-compatibility ledger and contract tests around authentication, dashboard, module, Fyn and entitlement envelopes. Existing endpoints remain authoritative; later native-only routes are additive.

**Tech Stack:** Laravel 10, PHP 8, MySQL 8, Pest, Vue 3, Vitest, Vite, Playwright/browser verification.

**Primary implementation plan:** `codex/plans/programme/2026-07-14-freemium-economic-contract-remediation.md`

## Global Constraints

- The only tiers after remediation are `free` and `premium`.
- New users receive Free with no Subscription row, no expiry and no payment requirement.
- Premium prices are 699 and 5999 pence for monthly and annual web billing.
- There is no trial in runtime behaviour, scheduled commands, middleware, copy, tests or new API responses.
- Free remains writable within server-enforced count/capability gates.
- Do not change an existing response field's name or type. Add only optional fields until all deployed clients have migrated.
- `/m` continues using its existing bearer flow and `/api/v1/auth/refresh-token`; native sessions are Package 3.
- Do not build StoreKit in this package.
- Preserve unrelated dirty-worktree changes.

## File map

| Path | Responsibility |
|---|---|
| `codex/plans/programme/2026-07-14-freemium-economic-contract-remediation.md` | Complete source plan for tier remediation |
| `app/Services/Tiers/TierResolver.php` | Canonical user tier resolution |
| `app/Services/Stores/TierConfigurationStore.php` | Canonical capabilities, limits and prices |
| `app/Services/Tiers/DbTierGate.php` | Store-boundary create limits |
| `app/Http/Controllers/Api/AuthController.php` | New-user Free assignment and authenticated user contract |
| `app/Http/Controllers/Api/PaymentController.php` | Web/Revolut subscription status |
| `app/Services/Mobile/MobileDashboardAggregator.php` | `/m` shared dashboard contract |
| `routes/api.php`, `routes/api_v1.php` | Existing client endpoints |
| `tests/Feature/Contracts/ClientCompatibilityContractTest.php` | New frozen cross-client response contract |
| `tests/Fixtures/Contracts/` | Sanitised response-shape fixtures |
| `docs/architecture/client-parity-ledger.md` | Release evidence ledger for all three clients |

### Task 1: Execute and close the economic remediation plan

**Files:** Every file explicitly named by `codex/plans/programme/2026-07-14-freemium-economic-contract-remediation.md`.

- [ ] Re-read the approved pricing HTML and the economic remediation plan before changing code.
- [ ] Execute every unchecked task in the economic remediation plan in order, using its exact tests and desktop/`/m` checkpoints.
- [ ] Keep the current no-paid-account fact as a migration simplifier, not as permission to omit churn, grace or future-provider tests.
- [ ] Confirm `rg -n "trial|tier1|tier2|tier3|student|standard|pro|family"` produces only explicitly documented historical, migration or unrelated-domain occurrences.
- [ ] Run the economic plan's full gate and record its evidence before Task 2.

Expected economic invariants:

```php
expect(app(TierResolver::class)->resolve($newUser))->toBe('free');
expect($newUser->subscriptions()->count())->toBe(0);
expect($tierStore->forTier('premium')->price_monthly_pence)->toBe(699);
expect($tierStore->forTier('premium')->price_annual_pence)->toBe(5999);
```

**Intended review boundary:** `fix: establish canonical free and premium contract`

### Task 2: Create the three-surface parity ledger

**Files:** Create `docs/architecture/client-parity-ledger.md`.

- [x] Write a failing documentation check in `tests/Architecture/ClientParityLedgerTest.php` asserting the ledger exists and includes every required capability row.
- [x] Run `./vendor/bin/pest tests/Architecture/ClientParityLedgerTest.php`; expect failure because the ledger is absent.
- [x] Create the ledger with the exact status vocabulary from the programme index: `required`, `not-landed`, `not-applicable`, `green`.
- [x] Populate the initial rows listed in `2026-07-14-native-ios-swift-migration-programme.md`.
- [x] Add columns for automated evidence, manual evidence, last verified date and approving person.
- [x] Make the architecture test reject `green` rows with blank evidence cells.

Core architecture test:

```php
it('tracks every native migration capability', function () {
    $ledger = file_get_contents(base_path('docs/architecture/client-parity-ledger.md'));

    foreach ([
        'Register and verify',
        'Free/Premium entitlement',
        'Dashboard and gamification',
        'Fyn onboarding/advice/write handoff',
        'Income/expenditure/net worth',
        'StoreKit purchase',
        'Account deletion outcome',
    ] as $capability) {
        expect($ledger)->toContain("| {$capability} |");
    }
});
```

- [x] Run the test again; expect PASS.

**Intended review boundary:** `docs: add native client parity ledger`

### Task 3: Freeze authentication and tier response shapes

**Files:** Create `tests/Feature/Contracts/ClientCompatibilityContractTest.php`; modify only response builders required by the economic plan in `app/Http/Controllers/Api/AuthController.php` and the canonical subscription-status controller/service.

- [x] Write a failing Pest test that registers and verifies a new user through `POST /api/auth/register` and `POST /api/auth/verify-code`.
- [x] Assert the successful response still contains the current bearer key used by `/m`, the user object, and no `trial`, `trial_ends_at` or legacy-tier key.
- [x] Assert `GET /api/auth/user` returns `tier_flags.resolved_tier='free'` and the canonical capability data without removing existing fields.
- [x] Assert the canonical subscription-status response uses this stable shape:

```json
{
  "success": true,
  "data": {
    "tier": "free",
    "provider": null,
    "status": "free",
    "renews": false,
    "current_period_end": null,
    "capabilities": {},
    "limits": {}
  }
}
```

- [x] Implement only the missing canonical response mapping identified by the failing tests.
- [x] Add a Premium fixture that proves the same keys and types with `provider='revolut'`.
- [x] Do not snapshot personal values; assert keys/types and canonical enum values.

Run:

```bash
./vendor/bin/pest tests/Feature/Auth/RegistrationTest.php tests/Feature/Auth/AuthenticatedUserTest.php tests/Feature/Payment/SubscriptionStatusTest.php tests/Feature/Contracts/ClientCompatibilityContractTest.php
```

Expected: PASS; no `/m` authentication test changes are needed to tolerate a renamed or removed key.

**Intended review boundary:** `test: freeze client auth and entitlement contracts`

### Task 4: Freeze dashboard, module and Fyn compatibility

**Files:** Modify `tests/Feature/Contracts/ClientCompatibilityContractTest.php`; use existing fixtures from `tests/Feature/Mobile/`; do not change production response code unless the test exposes an inconsistency.

- [ ] Add a failing contract assertion for `GET /api/v1/mobile/dashboard` covering envelope, dashboard level/progress/percentile, module collection, next actions and Fyn insight keys.
- [ ] Add a failing parameterised assertion for every slug accepted by `GET /api/v1/mobile/modules/{module}`.
- [ ] Add contract assertions for conversation create, conversation load and message submission accepting `text/event-stream` or the documented `202` queued JSON envelope.
- [ ] Assert `level_up` remains legal after the Fyn `done` frame in the existing streaming test suite.
- [ ] Assert unknown additive JSON fields do not break `/m` decoding tests.
- [ ] If any inconsistency appears, repair the server at the shared response boundary; do not add a native-specific copy of a financial endpoint.

Run:

```bash
./vendor/bin/pest tests/Feature/Mobile tests/Feature/AI tests/Feature/Contracts/ClientCompatibilityContractTest.php
```

Expected: PASS. If this broad AI selection is slow, first run the named failing file, fix it, then run the full command before closing the task.

**Intended review boundary:** `test: freeze mobile and fyn response contracts`

### Task 5: Add explicit native-client headers without changing web clients

**Files:** Create `app/Http/Middleware/IdentifyNativeClient.php`; modify `app/Http/Kernel.php`, `app/Providers/RouteServiceProvider.php`, `routes/api_v1.php`; create `tests/Feature/Native/NativeClientIdentificationTest.php`.

- [ ] Write a failing test for headers `X-Fynla-Client: ios`, `X-Fynla-Version: 1.0.0` and `X-Fynla-Build: 1`.
- [ ] Make the middleware validate these headers only on `/api/v1/native/*` routes and set normalised request attributes.
- [ ] Reject missing/invalid native headers with status 400 and code `invalid_native_client`, but never apply this rule to `/api`, `/api/v1/mobile` or Apple webhooks.
- [ ] Add a temporary authenticated native health route for foundation testing:

```php
Route::middleware(['auth:sanctum', 'native.client'])
    ->get('/native/health', fn () => response()->json([
        'success' => true,
        'data' => ['api_version' => 'v1'],
    ]));
```

- [ ] Keep minimum-version enforcement out of this package; Package 7 adds it once a real release exists.
- [ ] Add the route to `PreviewWriteInterceptor::EXCLUDED_ROUTES` only if its method is intercepted; a read-only GET should not need an exclusion.

Run:

```bash
./vendor/bin/pest tests/Feature/Native/NativeClientIdentificationTest.php tests/Feature/Mobile
```

Expected: native route requires valid native headers; all existing mobile tests remain PASS.

**Intended review boundary:** `feat: add additive native client identification`

### Task 6: Browser and `/m` gate

**Files:** Update evidence only in `docs/architecture/client-parity-ledger.md` after verification.

- [ ] Start or use the correct dev environment; do not use production credentials or the production SSH connector for csjones.
- [ ] Verify new registration creates a Free user and reaches the desktop application with no trial screen.
- [ ] Verify the same verified account can authenticate through `/m` and load dashboard, module detail and Fyn.
- [ ] Verify a Free count-cap rejection is explanatory on both clients and does not hide existing records.
- [ ] Verify a Premium fixture exposes the same capabilities on desktop and `/m`.
- [ ] Follow the `verify-m` skill's authenticated path; do not treat a cold unauthenticated `/m` navigation as a product failure.
- [ ] Record browser URLs, test identity type (never credentials), build/commit and screenshots in the ledger.

Package gate:

```bash
./vendor/bin/pest tests/Feature/Auth tests/Feature/Payment tests/Feature/Middleware/CheckSubscriptionTest.php tests/Feature/Mobile tests/Feature/Contracts tests/Architecture/ClientParityLedgerTest.php
npm run test
npm run build
npm run build:mobile
```

Expected: all exit 0, the economic plan is fully checked, `/m` remains deployable, and no native implementation has yet modified the Capacitor project.

### Package 1 exit criteria

- [ ] Free and Premium are the only live tier identities.
- [ ] No trial is created or advertised.
- [ ] New registrations are writable Free accounts.
- [ ] Auth, entitlement, dashboard, module and Fyn response shapes are frozen by contract tests.
- [ ] Native client headers are additive and isolated to `/api/v1/native`.
- [ ] Desktop and `/m` evidence is green in the ledger.
- [ ] CSJ approves the Package 1 evidence before StoreKit production work begins.
