# Fynla iOS and `/m` Parity Foundations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. In this workspace, use subagents only after the user explicitly authorises delegation.

**Goal:** Deliver PR 1 of the approved parity programme: shared semantic navigation, complete account/settings/subscription surfaces, secure web handoff, readable dashboard actions, consistent naming, and a reliable native bug-report journey on iOS and `/m`.

**Architecture:** Laravel remains the authority and adds only additive contracts: semantic destinations beside legacy paths, server-owned tier comparison rows, and single-use web handoffs. `/m` and native iOS each translate semantic screens into local routes, render the same profile and tier data, and keep platform-specific payment execution. Existing bearer tokens never cross into the desktop SPA; a consumed handoff creates a normal Laravel web session.

**Tech Stack:** PHP 8.3, Laravel 11, Sanctum, Pest/PHPUnit, Vue 3, Vue Router 4, Vitest, Swift 6, SwiftUI, Swift Testing, XCUITest, Xcode iOS Simulator, installed Google Chrome.

## Execution Status

Tasks 1–10 and Task 11 steps 1–9 were completed on 2026-08-09, including the
independent review/fix loop and final cross-surface verification. Task 11 step
10 remains intentionally open: implementation files are neither staged nor
committed pending explicit user approval.

## Global Constraints

- Implement only PR 1 and ledger items M-01 through M-07, M-13, M-27, and M-33 from `docs/superpowers/specs/2026-08-09-ios-m-parity-debugging-design.md`.
- Laravel owns identity, entitlements, financial facts, navigation intent, plan inclusions, and validation; clients do not send financial values as authoritative context.
- Preserve legacy response fields while supported clients adopt additive semantic fields.
- Unknown semantic screens use their explicit allowlisted fallback and emit redacted diagnostics; they never silently open Tax Strategy.
- Keep Personal Information read-only and hide its contextual Edit action until PR 2.
- Keep StoreKit authoritative for iOS product availability, localised price, and currency; keep the existing web/Revolut flow authoritative for `/m` purchase execution.
- Never copy the `/m` bearer token into desktop `sessionStorage`, query strings, cookies, logs, or analytics.
- Handoff tokens are random, hashed at rest, single-use, expire after two minutes, and accept only allowlisted destinations.
- Sensitive privacy/data actions retain confirmation and verification; `/m` may use the secure handoff to the existing desktop privacy UI.
- “Savings” becomes “Bank Accounts” in primary navigation and matching overview headings. “Chattels” becomes “Valuables” in user-facing mobile copy. Existing persisted keys and backward-compatible URLs remain unchanged.
- Browser automation, screenshots, and visual acceptance use the user-installed Google Chrome through the Chrome connector. Do not use Chromium, bundled Playwright Chromium, or the in-app browser.
- Run native user journeys in the Xcode iOS Simulator. Record each failure, root cause, regression test, fix, and green rerun in the phase evidence document.
- Do not stage or commit implementation changes until the user explicitly authorises the implementation commit.

---

### Task 1: Add the additive semantic-destination contract

**Files:**
- Modify: `app/Constants/GateRoutes.php`
- Modify: `app/Services/Mobile/NextActionsService.php`
- Modify: `tests/Architecture/GateRoutesTest.php`
- Modify: `tests/Feature/Mobile/MobileDashboardNextActionsTest.php`

**Interfaces:**
- Produces: `GateRoutes::destination(string $screen, array $params = [], ?string $fallback = null): array{screen:string,params:array<string,int|string>,fallback:string}`.
- Produces: recommendation actions with legacy `action.payload` and additive `action.destination`.
- Preserves: `GateRoutes::resolve()` and `GateRoutes::destinationForRoute()` for existing readiness and onboarding callers.

- [ ] **Step 1: Extend the architecture test before production code**

Add assertions for `NET_WORTH`, `PERSONAL_INFORMATION`, `SUBSCRIPTION`, `SETTINGS`, and `ACHIEVEMENTS`, then assert the typed payload and invalid-fallback rejection:

```php
it('builds an allowlisted semantic destination with an explicit fallback', function (): void {
    expect(GateRoutes::destination(
        GateRoutes::RETIREMENT,
        ['pension_id' => 8472],
        GateRoutes::NET_WORTH,
    ))->toBe([
        'screen' => GateRoutes::RETIREMENT,
        'params' => ['pension_id' => 8472],
        'fallback' => GateRoutes::NET_WORTH,
    ]);
});

it('rejects an unknown semantic fallback', function (): void {
    GateRoutes::destination(GateRoutes::RETIREMENT, [], 'tax-by-accident');
})->throws(InvalidArgumentException::class);
```

- [ ] **Step 2: Run the architecture test and confirm RED**

Run: `./vendor/bin/pest tests/Architecture/GateRoutesTest.php`

Expected: FAIL because the new constants and `destination()` do not exist.

- [ ] **Step 3: Add destination assertions to the dashboard feature test**

Seed recommendations for at least protection, savings, investment, retirement, estate, goals, tax, and an unknown module. Assert every navigate action contains this shape and that the unknown module targets Net Worth rather than Tax Strategy:

```php
expect($navigateActions)->each(fn ($action) => $action
    ->toHaveKeys(['payload', 'destination.screen', 'destination.params', 'destination.fallback']));
expect(collect($navigateActions)->firstWhere('module', 'general')['destination'])
    ->toBe([
        'screen' => GateRoutes::NET_WORTH,
        'params' => [],
        'fallback' => GateRoutes::DASHBOARD,
    ]);
```

- [ ] **Step 4: Run the dashboard test and confirm RED**

Run: `./vendor/bin/pest tests/Feature/Mobile/MobileDashboardNextActionsTest.php --filter=semantic`

Expected: FAIL because `action.destination` is absent.

- [ ] **Step 5: Implement the minimal additive server contract**

Keep the existing map shape for `resolve()`, add the new destinations, rename the savings label, and add the method below. Set the new Personal Information, Subscription, and Settings `mobile` paths to `null` until Task 5 creates their real routes; this keeps the architecture invariant that every advertised mobile path exists.

```php
/** @return array{screen:string,params:array<string,int|string>,fallback:string} */
public static function destination(
    string $screen,
    array $params = [],
    ?string $fallback = null,
): array {
    self::resolve($screen);
    $resolvedFallback = $fallback ?? self::DASHBOARD;
    self::resolve($resolvedFallback);

    foreach ($params as $value) {
        if (! is_int($value) && ! is_string($value)) {
            throw new InvalidArgumentException('Semantic destination parameters must be scalar identifiers.');
        }
    }

    return [
        'screen' => $screen,
        'params' => $params,
        'fallback' => $resolvedFallback,
    ];
}
```

Change `NextActionsService::moduleRoute()` to `moduleDestination()` returning `GateRoutes` constants. Emit both the existing path and the new descriptor:

```php
$screen = $this->moduleDestination((string) ($rec['module'] ?? 'general'));
$route = GateRoutes::resolve($screen);

'action' => [
    'kind' => 'navigate',
    'payload' => $route['mobile'] ?? $route['web'],
    'destination' => GateRoutes::destination($screen),
],
```

- [ ] **Step 6: Run the focused server tests and confirm GREEN**

Run: `./vendor/bin/pest tests/Architecture/GateRoutesTest.php tests/Feature/Mobile/MobileDashboardNextActionsTest.php`

Expected: PASS; existing payload assertions remain green.

- [ ] **Step 7: Run formatting without changing unrelated files**

Run: `./vendor/bin/pint app/Constants/GateRoutes.php app/Services/Mobile/NextActionsService.php tests/Architecture/GateRoutesTest.php tests/Feature/Mobile/MobileDashboardNextActionsTest.php`

Expected: exit 0.

- [ ] **Step 8: Hold the task for review**

Run: `git diff --check && git diff --stat`

Expected: only Task 1 files plus this plan; do not stage or commit without user authorisation.

---

### Task 2: Add client semantic-destination adapters

**Files:**
- Create: `resources/mobile/navigation/semanticDestinations.js`
- Create: `resources/mobile/navigation/__tests__/semanticDestinations.spec.js`
- Modify: `resources/mobile/views/Dashboard.vue`
- Create: `ios-native/Fynla/Core/Navigation/SemanticDestination.swift`
- Modify: `ios-native/Fynla/Features/Dashboard/DashboardModels.swift`
- Modify: `ios-native/Fynla/Features/Dashboard/DashboardView.swift`
- Modify: `ios-native/FynlaTests/DashboardModelsTests.swift`
- Modify: `ios-native/FynlaTests/Fixtures/Dashboard/populated.json`

**Interfaces:**
- Consumes: `action.destination` from Task 1, with legacy `action.payload` fallback.
- Produces `/m`: `resolveMobileDestination(action, recordDiagnostic): string`.
- Produces iOS: `SemanticDestinationResolver.route(for:legacyPath:onUnknown:) -> AppRoute`.

- [ ] **Step 1: Write the `/m` resolver tests**

Cover a known screen, an unknown screen with a known fallback, an unknown screen with an unknown fallback, and a legacy-only action:

```js
expect(resolveMobileDestination({
  payload: '/tax-strategy',
  destination: { screen: 'retirement', params: {}, fallback: 'net_worth' },
})).toBe('/retirement');

expect(resolveMobileDestination({
  destination: { screen: 'future_screen', params: {}, fallback: 'net_worth' },
}, diagnostic)).toBe('/net-worth');
expect(diagnostic).toHaveBeenCalledWith('future_screen');
```

- [ ] **Step 2: Run the `/m` resolver test and confirm RED**

Run: `env PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/bin:/bin:/usr/sbin:/sbin npm run test:run -- resources/mobile/navigation/__tests__/semanticDestinations.spec.js`

Expected: FAIL because the module does not exist.

- [ ] **Step 3: Implement the `/m` resolver and dashboard integration**

Use an immutable screen map with `/savings` retained as the compatible Bank Accounts URL. For detail parameters, URL-encode identifiers. Unknown screens call the supplied redacted diagnostic callback with the screen name only. Change `Dashboard.vue::doAction()` to push the resolved path.

```js
export const MOBILE_DESTINATIONS = Object.freeze({
  dashboard: '/dashboard',
  net_worth: '/net-worth',
  protection: '/protection',
  savings: '/savings',
  investment: '/investment',
  retirement: '/retirement',
  estate: '/estate',
  goals: '/goals',
  tax_strategy: '/tax-strategy',
  holistic_plan: '/holistic-plan',
  achievements: '/achievements',
  personal_information: '/personal-information',
  subscription: '/subscription',
  settings: '/settings',
});
```

- [ ] **Step 4: Write the Swift decoding and routing tests**

Decode the additive object and assert that the semantic value wins over a deliberately wrong legacy Tax Strategy payload. Assert an unknown screen falls back to `.netWorth(category: nil)`.

```swift
#expect(action.action.destination?.screen == "retirement")
#expect(
    SemanticDestinationResolver.route(
        for: action.action.destination,
        legacyPath: "/tax-strategy"
    ) == .retirement(pensionType: nil, id: nil)
)
```

- [ ] **Step 5: Run Swift tests and confirm RED**

Run:

```bash
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging \
  -destination 'platform=iOS Simulator,name=Fynla iPhone 16 Pro iOS 18.6' \
  -parallel-testing-enabled NO \
  -only-testing:FynlaTests/DashboardModelsTests test \
  COMPILER_INDEX_STORE_ENABLE=NO
```

Expected: compile failure because the semantic types do not exist.

- [ ] **Step 6: Implement the Swift descriptor and resolver**

Define:

```swift
struct SemanticDestination: Decodable, Sendable, Equatable {
    let screen: String
    let params: [String: StringOrInt]
    let fallback: String
}

enum StringOrInt: Decodable, Sendable, Equatable {
    case string(String)
    case int(Int)
}
```

Make `DashboardActionDestination.payload` optional and add `destination`. Centralise all current path switches in `SemanticDestinationResolver`; emit only an allowlisted diagnostic operation such as `navigation.unknown_destination`, never parameters.

- [ ] **Step 7: Run both client resolver suites and confirm GREEN**

Run the commands from Steps 2 and 5.

Expected: PASS.

- [ ] **Step 8: Hold the task for review**

Run: `git diff --check && git diff --stat`

Expected: no whitespace errors; do not stage or commit.

---

### Task 3: Build the single-use web handoff

**Files:**
- Create: `database/migrations/2026_08_09_150000_create_web_handoffs_table.php`
- Create: `app/Models/WebHandoff.php`
- Create: `app/Enums/WebHandoffDestination.php`
- Create: `app/Services/Auth/WebHandoffService.php`
- Create: `app/Http/Requests/Auth/IssueWebHandoffRequest.php`
- Create: `app/Http/Controllers/Api/V1/Mobile/WebHandoffController.php`
- Create: `app/Http/Controllers/WebHandoffController.php`
- Modify: `routes/api_v1.php`
- Modify: `routes/web.php`
- Modify: `resources/js/mScaffoldBridge.js`
- Modify: `resources/js/app.js`
- Modify: `resources/js/store/modules/auth.js`
- Modify: `app/Http/Middleware/EncryptCookies.php`
- Modify: `app/Http/Middleware/SecurityHeaders.php`
- Create: `resources/js/__tests__/mScaffoldBridge.spec.js`
- Create: `tests/Feature/Auth/WebHandoffTest.php`

**Interfaces:**
- Produces API: `POST /api/v1/mobile/web-handoffs` with `{destination: admin|subscription|settings|privacy|notifications}`.
- Produces response: `{success:true,data:{url:string,expires_at:string}}`; never returns a bearer token.
- Produces web route: `GET /web-handoff/{token}` which consumes once, creates a web session, and redirects to an allowlisted path.

- [ ] **Step 1: Write failing feature tests for issue and consume**

Test authentication, admin permission, allowed non-admin subscription destination, unknown destination validation, two-minute expiry, token hashing, single use, safe redirect, no-store/no-referrer headers, and a working `/api/auth/user` request through the created web session.

```php
$issued = $this->actingAs($admin)->postJson('/api/v1/mobile/web-handoffs', [
    'destination' => 'admin',
])->assertCreated()->json('data.url');

$token = basename(parse_url($issued, PHP_URL_PATH));
expect(WebHandoff::query()->firstOrFail()->token_hash)
    ->toBe(hash('sha256', $token));

$this->get($issued)
    ->assertRedirect('/admin')
    ->assertHeader('Cache-Control', 'no-store, private')
    ->assertHeader('Referrer-Policy', 'no-referrer');
$this->get($issued)->assertForbidden();
```

- [ ] **Step 2: Run the handoff feature test and confirm RED**

Run: `./vendor/bin/pest tests/Feature/Auth/WebHandoffTest.php`

Expected: FAIL because the routes and table do not exist.

- [ ] **Step 3: Implement the storage and destination allowlist**

Create the table with `user_id`, unique `token_hash`, indexed `destination`, `expires_at`, nullable `consumed_at`, and timestamps. Use `Str::random(64)`, store only `hash('sha256', $plainToken)`, and consume in a transaction with `lockForUpdate()`.

```php
enum WebHandoffDestination: string
{
    case ADMIN = 'admin';
    case SUBSCRIPTION = 'subscription';
    case SETTINGS = 'settings';
    case PRIVACY = 'privacy';
    case NOTIFICATIONS = 'notifications';

    public function path(): string
    {
        return match ($this) {
            self::ADMIN => '/admin',
            self::SUBSCRIPTION => '/settings/subscription?openPricing=1',
            self::SETTINGS => '/settings',
            self::PRIVACY => '/settings/privacy',
            self::NOTIFICATIONS => '/settings/notifications',
        };
    }
}
```

- [ ] **Step 4: Implement issue and consume controllers**

Require `PermissionService::isAdmin()` for `admin`. On consume, reject missing, expired, or consumed rows with 403; mark consumed before `Auth::login($user)`; regenerate the session; set a two-minute, same-site, non-secret `fynla_web_session=1` marker cookie; and redirect only through the enum path.

- [ ] **Step 5: Write the desktop bootstrap test**

```js
document.cookie = 'fynla_web_session=1; path=/';
await import('../mScaffoldBridge.js');
expect(sessionStorage.getItem('auth_token')).toBe('web-session');
expect(localStorage.getItem('m_scaffold_token')).toBe('mobile-secret');
expect(sessionStorage.getItem('auth_token')).not.toBe('mobile-secret');
```

- [ ] **Step 6: Run the bootstrap test and confirm RED**

Run: `env PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/bin:/bin:/usr/sbin:/sbin npm run test:run -- resources/js/__tests__/mScaffoldBridge.spec.js`

Expected: FAIL because the bridge still copies the mobile token.

- [ ] **Step 7: Replace bearer copying with the session marker bridge**

`mScaffoldBridge.js` must consume the marker cookie, set the non-secret in-memory/session sentinel `web-session`, expire the marker, and never read `m_scaffold_token`.

- [ ] **Step 8: Run handoff and bridge tests and confirm GREEN**

Run:

```bash
./vendor/bin/pest tests/Feature/Auth/WebHandoffTest.php
env PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/bin:/bin:/usr/sbin:/sbin npm run test:run -- resources/js/__tests__/mScaffoldBridge.spec.js
```

Expected: PASS.

- [ ] **Step 9: Hold the task for review**

Run: `git diff --check && git status --short`

Expected: only planned files; do not run the migration against a shared database and do not commit.

---

### Task 4: Make the Free/Premium comparison server-owned

**Files:**
- Create: `app/Services/Payment/TierComparisonService.php`
- Modify: `app/Http/Resources/TierConfigurationResource.php`
- Modify: `tests/Feature/Tiers/TierConfigPropagationTest.php`
- Modify: `resources/js/views/Public/PricingPage.vue`
- Modify: `tests/E2E/public/pricing-contract.spec.js`

**Interfaces:**
- Produces: `TierComparisonService::featuresFor(TierConfiguration $tier): array<int,array{key:string,label:string,included:bool,availability:string}>`.
- Extends each `/api/pricing-config` row with `features`; existing matrix, caps, and price fields remain.

- [ ] **Step 1: Write the failing API assertions**

Assert stable ordering and representative labels for `full`, `teaser`, `limited`, and `none`, including `Bank Accounts` and `Valuables` copy.

```php
$this->getJson('/api/pricing-config')
    ->assertJsonPath('data.0.features.0.key', 'dashboard')
    ->assertJsonPath('data.0.features.0.included', true)
    ->assertJsonFragment(['label' => 'Up to 2 bank accounts'])
    ->assertJsonFragment(['label' => 'Valuables — preview only']);
```

- [ ] **Step 2: Run the tier test and confirm RED**

Run: `./vendor/bin/pest tests/Feature/Tiers/TierConfigPropagationTest.php`

Expected: FAIL because `features` is absent.

- [ ] **Step 3: Implement the comparison service and resource field**

Move the existing `FEATURE_LABELS` ordering and wording from `PricingPage.vue` to `TierComparisonService`. Convert capability/cap combinations deterministically and inject the service through the resource using `app(TierComparisonService::class)`.

- [ ] **Step 4: Change desktop Pricing to render `tier.features`**

Delete the client `FEATURE_LABELS` constant and change `tierFeatures(tier)` to return `tier.features || []`. Keep price calculations untouched.

- [ ] **Step 5: Run API and pricing contract tests and confirm GREEN**

Run:

```bash
./vendor/bin/pest tests/Feature/Tiers/TierConfigPropagationTest.php tests/Feature/Tiers/TwoTierIdentityTest.php
env PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/bin:/bin:/usr/sbin:/sbin npm run test:run -- tests/E2E/public/pricing-contract.spec.js
```

Expected: all directly runnable tests pass; the browser acceptance portion remains reserved for installed Chrome in Task 10.

- [ ] **Step 6: Hold the task for review**

Run: `git diff --check && git diff --stat`

Expected: no client-owned feature label map remains.

---

### Task 5: Add `/m` Personal Information, Settings, and Subscription

**Files:**
- Create: `resources/mobile/navigation/navigationModel.js`
- Create: `resources/mobile/navigation/webHandoff.js`
- Create: `resources/mobile/navigation/__tests__/webHandoff.spec.js`
- Create: `resources/mobile/views/PersonalInformation.vue`
- Create: `resources/mobile/views/Settings.vue`
- Create: `resources/mobile/views/NotificationPreferences.vue`
- Create: `resources/mobile/views/Subscription.vue`
- Create: `resources/mobile/views/__tests__/PersonalInformation.spec.js`
- Create: `resources/mobile/views/__tests__/Settings.spec.js`
- Create: `resources/mobile/views/__tests__/Subscription.spec.js`
- Modify: `resources/mobile/router.js`
- Modify: `resources/mobile/components/MobileChrome.vue`
- Modify: `resources/mobile/views/Dashboard.vue`
- Modify: `resources/mobile/components/__tests__/MobileChrome.spec.js`
- Modify: `resources/mobile/__tests__/router.spec.js`
- Modify: `resources/mobile/mixins/upgrade.js`

**Interfaces:**
- Consumes profile: `GET /api/user/profile`.
- Consumes preferences: `GET|PUT /api/v1/mobile/notifications/preferences`.
- Consumes plan rows: `GET /api/pricing-config` and entitlement: `GET /api/payment/subscription-status`.
- Consumes handoff: `POST /api/v1/mobile/web-handoffs`.
- Produces routes: `/personal-information`, `/settings`, `/notifications`, `/subscription`.

- [ ] **Step 1: Write route and shared-navigation tests**

Assert the four routes require authentication and both `MobileChrome` and Dashboard consume the same groups. The primary labels include Dashboard, Bank Accounts, Achievements, Personal Information, Subscription, and Settings.

- [ ] **Step 2: Run the route/navigation tests and confirm RED**

Run: `env PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/bin:/bin:/usr/sbin:/sbin npm run test:run -- resources/mobile/__tests__/router.spec.js resources/mobile/components/__tests__/MobileChrome.spec.js`

Expected: FAIL for missing routes and labels.

- [ ] **Step 3: Extract the shared navigation model**

Export one frozen `primaryNavigationSections` array from `navigationModel.js`. Use it in both shell components; preserve existing routes and use the display label `Bank Accounts` for the compatible `/savings` route.

After the routes exist, change the matching `GateRoutes::MAP` mobile entries from `null` to `/personal-information`, `/subscription`, and `/settings`, and rerun `tests/Architecture/GateRoutesTest.php`.

- [ ] **Step 4: Write and run failing Personal Information tests**

Mock a canonical profile envelope, assert the name, household, domicile, masked NI value, income/expenditure summary, and Net Worth render, and assert no Edit button exists.

- [ ] **Step 5: Implement the read-only Personal Information view**

Use `apiGet('/api/user/profile', store.token)`, render loading/empty/error/retry states, and never provide client-side profile editing in PR 1.

- [ ] **Step 6: Write and run failing Settings tests**

Assert notification toggles persist through the mobile preference endpoint; failed PUT reverts the toggle and shows a retryable message. Assert privacy uses `issueWebHandoff('privacy')`; help, privacy policy, and terms use `/help`, `/privacy`, and `/terms` on the configured web base.

- [ ] **Step 7: Implement Settings and Notification Preferences**

Keep notification switches native to `/m`; route Privacy and data through the secure desktop handoff. Public help/legal links navigate the top window without credentials.

- [ ] **Step 8: Write and run failing Subscription tests**

Assert the current plan and server-owned features render; assert the upgrade CTA issues a `subscription` handoff; assert unavailable payment shows a safe message; assert no token is written into top-frame storage.

- [ ] **Step 9: Implement Subscription and upgrade routing**

Change `upgradeMixin.goUpgrade()` to push `/subscription` inside `/m`. The Subscription CTA calls:

```js
export async function issueWebHandoff(destination) {
  const { ok, data } = await apiPost(
    '/api/v1/mobile/web-handoffs',
    { destination },
    store.token,
  );
  if (!ok || !data?.data?.url) throw new Error('handoff_unavailable');
  (window.top || window).location.href = data.data.url;
}
```

Use the same utility for Admin and delete both direct `sessionStorage.setItem('auth_token', token)` blocks.

- [ ] **Step 10: Run the complete `/m` focused suite and confirm GREEN**

Run:

```bash
env PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/bin:/bin:/usr/sbin:/sbin npm run test:run -- \
  resources/mobile/__tests__/router.spec.js \
  resources/mobile/navigation/__tests__ \
  resources/mobile/components/__tests__/MobileChrome.spec.js \
  resources/mobile/views/__tests__/Dashboard.spec.js \
  resources/mobile/views/__tests__/PersonalInformation.spec.js \
  resources/mobile/views/__tests__/Settings.spec.js \
  resources/mobile/views/__tests__/Subscription.spec.js
```

Expected: PASS with no unhandled errors.

- [ ] **Step 11: Hold the task for review**

Run: `git diff --check && git diff --stat`

Expected: no mobile-to-desktop bearer copying remains.

---

### Task 6: Add native Personal Information, direct Subscription, Settings links, and Admin handoff

**Files:**
- Create: `ios-native/Fynla/Core/Components/SafariSheet.swift`
- Create: `ios-native/Fynla/Core/Navigation/WebHandoffClient.swift`
- Create: `ios-native/Fynla/Features/Profile/PersonalInformationModels.swift`
- Create: `ios-native/Fynla/Features/Profile/PersonalInformationClient.swift`
- Create: `ios-native/Fynla/Features/Profile/PersonalInformationModel.swift`
- Create: `ios-native/Fynla/Features/Profile/PersonalInformationView.swift`
- Create: `ios-native/FynlaTests/PersonalInformationTests.swift`
- Create: `ios-native/FynlaTests/WebHandoffClientTests.swift`
- Create: `ios-native/FynlaTests/Fixtures/Profile/personal-information.json`
- Modify: `ios-native/Fynla/App/AppRouter.swift`
- Modify: `ios-native/Fynla/App/AppRootView.swift`
- Modify: `ios-native/Fynla/App/FynlaApp.swift`
- Modify: `ios-native/Fynla/Core/DeepLinks/DeepLinkParser.swift`
- Modify: `ios-native/Fynla/Features/Navigation/NavigationMenuView.swift`
- Modify: `ios-native/Fynla/Features/Navigation/NavigationDestinationFactory.swift`
- Modify: `ios-native/Fynla/Features/Settings/SettingsModel.swift`
- Modify: `ios-native/Fynla/Features/Settings/SettingsView.swift`
- Modify: `ios-native/FynlaTests/AppRouterTests.swift`
- Modify: `ios-native/FynlaTests/DeepLinkTests.swift`
- Modify: `ios-native/FynlaTests/NavigationMenuTests.swift`

**Interfaces:**
- Produces `AppRoute.personalInformation` and `AppRoute.subscription`.
- Produces `WebHandoffClient.issue(_ destination: WebHandoffDestination) async throws -> URL`.
- Produces `PersonalInformationClient.load() async throws -> PersonalInformationProfile`.

- [ ] **Step 1: Write failing route/menu/deep-link tests**

Assert Personal Information, Subscription, Settings, and Achievements are present and stable; `Savings` is displayed as `Bank Accounts`; `/personal-information` and `/subscription` parse to the new routes; every premium gate routes directly to `.subscription`.

- [ ] **Step 2: Run navigation tests and confirm RED**

Run:

```bash
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging \
  -destination 'platform=iOS Simulator,name=Fynla iPhone 16 Pro iOS 18.6' \
  -parallel-testing-enabled NO \
  -only-testing:FynlaTests/AppRouterTests \
  -only-testing:FynlaTests/DeepLinkTests \
  -only-testing:FynlaTests/NavigationMenuTests test \
  COMPILER_INDEX_STORE_ENABLE=NO
```

Expected: compile/test failure for missing routes and labels.

- [ ] **Step 3: Add routes, menu entries, and direct premium routing**

Wire the two screens through `NavigationDestinationFactory`, `AppRootView`, `FynlaApp`, and `DeepLinkParser`. Replace every `onOpenSubscription: { onRoute(.settings) }` with `.subscription`.

- [ ] **Step 4: Write failing profile client/model tests**

Use the canonical JSON fixture and `TestHTTPTransport`; assert `GET /api/user/profile`, bearer authentication, loading/error/retry behavior, masked NI presentation, and absence of an edit intent.

- [ ] **Step 5: Implement Personal Information models, client, model, and view**

Decode only fields the existing endpoint owns. Present user, household, domicile, income/expenditure summary, and financial position read-only with explicit unavailable rows rather than invented zeroes.

- [ ] **Step 6: Write failing handoff client tests**

```swift
let url = try await client.issue(.admin)
#expect(url.path.contains("/web-handoff/"))
#expect(requests.first?.url?.path == "/fynla/api/v1/mobile/web-handoffs")
#expect(requests.first?.httpBody == Data(#"{"destination":"admin"}"#.utf8))
```

- [ ] **Step 7: Implement native web handoff and Safari sheet**

Extract the existing `SFSafariViewController` wrapper from `SettingsView`. The admin menu action awaits `WebHandoffClient.issue(.admin)` and presents the returned URL in the sheet; show an inline retryable error when issuance fails. Do not use `openURL(adminURL)`.

- [ ] **Step 8: Fix canonical help/legal paths**

Set Settings paths to `/help`, `/privacy`, and `/terms`; validate scheme and host against `AppEnvironment.webBaseURL` before creating each URL. Retain the existing native notification and privacy/data screens.

- [ ] **Step 9: Run the focused native suites and confirm GREEN**

Run the Step 2 command plus `PersonalInformationTests` and `WebHandoffClientTests`.

Expected: PASS.

- [ ] **Step 10: Hold the task for review**

Run: `git diff --check && git diff --stat`

Expected: Xcode discovers new Swift files through the synchronized group; no `.pbxproj` edit is needed.

---

### Task 7: Render the shared subscription comparison in native iOS

**Files:**
- Modify: `ios-native/Fynla/Features/Subscription/SubscriptionAPI.swift`
- Modify: `ios-native/Fynla/Features/Subscription/SubscriptionModel.swift`
- Modify: `ios-native/Fynla/Features/Subscription/SubscriptionView.swift`
- Modify: `ios-native/Fynla/Features/Subscription/SubscriptionManagementView.swift`
- Modify: `ios-native/FynlaTests/SubscriptionAPITests.swift`
- Modify: `ios-native/FynlaTests/SubscriptionModelTests.swift`
- Modify: `ios-native/Fynla/Testing/TestAppDependencies.swift`
- Modify: `ios-native/FynlaUITests/FynlaUITests.swift`

**Interfaces:**
- Adds `SubscriptionAPI.planComparison() async throws -> [PlanComparison]`.
- Adds `SubscriptionModel.plans: [PlanComparison]` without changing StoreKit product selection or prices.

- [ ] **Step 1: Write failing API and model tests**

Assert `GET /api/pricing-config` decodes server plan identifiers and feature rows. Assert a comparison failure does not hide otherwise valid StoreKit products, and assert StoreKit `displayPrice` remains the only price rendered for purchase buttons.

- [ ] **Step 2: Run subscription unit tests and confirm RED**

Run the native test command filtered to `SubscriptionAPITests` and `SubscriptionModelTests`.

Expected: compile failure for `planComparison()`.

- [ ] **Step 3: Implement plan decoding and concurrent loading**

Add:

```swift
struct PlanComparison: Decodable, Sendable, Equatable, Identifiable {
    let tier: String
    let displayName: String
    let features: [PlanFeature]
    var id: String { tier }
}

struct PlanFeature: Decodable, Sendable, Equatable, Identifiable {
    let key: String
    let label: String
    let included: Bool
    let availability: String
    var id: String { key }
}
```

Load comparison alongside entitlement and products; treat comparison as secondary content and retain purchase availability if it fails.

- [ ] **Step 4: Render accessible Free/Premium comparison cards**

Render the server labels and inclusion state above purchase management. Keep monthly/annual button labels derived from `StoreProduct.displayPrice` and `subscriptionPeriod`.

- [ ] **Step 5: Extend UI test fixtures and assertions**

In the free scenario, assert the Free and Premium cards and at least one shared feature label exist before asserting StoreKit prices and the purchase/restore buttons.

- [ ] **Step 6: Run native subscription unit and UI tests and confirm GREEN**

Run the unit command and:

```bash
FYNLA_SIMULATOR_DESTINATION='platform=iOS Simulator,name=iPhone 11' \
  ios-native/scripts/run-ui-smoke.sh \
  -only-testing:FynlaUITests/FynlaUITests/testNativeFreeSubscriptionShowsComparisonAndStoreKitPrices
```

If the script does not forward an extra filter, run the equivalent direct `xcodebuild -only-testing:` command.

- [ ] **Step 7: Hold the task for review**

Run: `git diff --check && git diff --stat`

Expected: server owns inclusions; StoreKit owns iOS price/currency.

---

### Task 8: Fix dashboard text visibility and recommendation routing

**Files:**
- Modify: `resources/mobile/views/dashboard.css`
- Modify: `resources/mobile/views/__tests__/Dashboard.spec.js`
- Modify: `ios-native/Fynla/Features/Dashboard/FocusAreasView.swift`
- Modify: `ios-native/FynlaTests/DashboardModelsTests.swift`
- Modify: `ios-native/FynlaUITests/FynlaUITests.swift`

**Interfaces:**
- Consumes semantic resolver from Task 2.
- Produces multi-line accessible action titles and explanations on both clients.

- [ ] **Step 1: Add failing `/m` regression assertions**

Mount a recommendation with a deliberately long title and explanation. Assert the complete strings are in the rendered row and that `.md-rec__title` no longer has the one-line truncation declarations.

- [ ] **Step 2: Run the dashboard test and confirm RED**

Run the focused Dashboard Vitest command.

Expected: FAIL because the CSS still uses `white-space: nowrap`, `overflow: hidden`, and `text-overflow: ellipsis`.

- [ ] **Step 3: Implement `/m` wrapping**

Use:

```css
.md-rec__title {
  white-space: normal;
  overflow-wrap: anywhere;
}
```

Ensure the explanation/meta row also wraps and retains a minimum 44px tap target.

- [ ] **Step 4: Add failing native UI assertions**

Use the dashboard UI fixture with a long action title and assert the complete accessibility label is exposed and the semantic retirement action opens Retirement even when the fixture’s legacy payload says `/tax-strategy`.

- [ ] **Step 5: Run the native dashboard UI test and confirm RED**

Run direct `xcodebuild` for the new dashboard test only.

Expected: FAIL because `FocusAreasView` uses `.lineLimit(1)` and legacy route selection.

- [ ] **Step 6: Implement native wrapping and semantic navigation**

Remove the action-title line limit, use `.fixedSize(horizontal: false, vertical: true)`, keep focus-card labels compact, and route through `SemanticDestinationResolver`.

- [ ] **Step 7: Run `/m` and native dashboard suites and confirm GREEN**

Run the focused Vitest, Swift unit test, and XCUITest commands.

Expected: PASS; no Tax Strategy fallback.

- [ ] **Step 8: Hold the task for review**

Run: `git diff --check && git diff --stat`.

---

### Task 9: Finish Bank Accounts and Valuables naming parity

**Files:**
- Modify: `resources/mobile/views/modules/Savings.vue`
- Modify: `resources/mobile/views/modules/NetWorth.vue`
- Modify: `resources/mobile/views/modules/NetWorthCategory.vue`
- Modify: `resources/mobile/views/__tests__/ModuleDetail.spec.js`
- Modify: `ios-native/Fynla/Features/Navigation/NavigationDestinationFactory.swift`
- Modify: `ios-native/Fynla/Features/Savings/SavingsView.swift`
- Modify: `ios-native/Fynla/Features/NetWorth/NetWorthView.swift`
- Modify: `ios-native/Fynla/Features/NetWorth/NetWorthCategoryView.swift`
- Modify: `ios-native/Fynla/Features/Dashboard/DashboardModels.swift`
- Modify: `ios-native/FynlaTests/NavigationMenuTests.swift`
- Modify: `ios-native/FynlaTests/NetWorthTests.swift`

**Interfaces:**
- Preserves persisted/API keys `savings` and `chattels` and compatible route `/savings`.
- Changes only user-facing labels/headings to `Bank Accounts` and `Valuables`.

- [ ] **Step 1: Write failing label tests across `/m` and Swift**

Assert primary nav, overview heading, Net Worth row, detail heading, and native route title. Also assert the request and route keys remain `savings` and `chattels`.

- [ ] **Step 2: Run the label tests and confirm RED**

Run the focused Vitest and native Navigation/NetWorth test commands.

Expected: FAIL on old user-facing copy.

- [ ] **Step 3: Replace presentation copy only**

Update headings and accessibility labels; do not rename models, JSON keys, database columns, or legacy paths.

- [ ] **Step 4: Run the focused suites and confirm GREEN**

Expected: PASS with backward-compatible keys.

- [ ] **Step 5: Scan for residual in-scope copy**

Run: `rg -n "Savings|Chattels" resources/mobile ios-native/Fynla app/Constants/GateRoutes.php`

Review every match. Keep only internal names, legal/historical prose, and out-of-scope desktop copy whose change would alter an established product term outside this phase.

- [ ] **Step 6: Hold the task for review**

Run: `git diff --check && git diff --stat`.

---

### Task 10: Reproduce, diagnose, and fix the native bug-report transition

**Files:**
- Modify only after root-cause evidence: `ios-native/Fynla/App/AppRootView.swift`
- Modify only after root-cause evidence: `ios-native/Fynla/Features/Fyn/FynConversationView.swift`
- Modify only after root-cause evidence: `ios-native/Fynla/Features/BugReport/BugReportView.swift`
- Modify: `ios-native/FynlaUITests/FynlaUITests.swift`
- Modify: `ios-native/Fynla/Testing/BugReportUITestSupport.swift`
- Create: `docs/testing/2026-08-09-ios-m-parity-pr1-evidence.md`

**Interfaces:**
- Preserves the user journey: open Fyn, tap Report a problem, review metadata, send, see confirmation.
- Produces a deterministic navigation transition without sleep-based timing.

- [ ] **Step 1: Reproduce the existing failure twice before editing production code**

Run:

```bash
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging \
  -destination 'platform=iOS Simulator,name=Fynla iPhone 16 Pro iOS 18.6' \
  -parallel-testing-enabled NO \
  -only-testing:FynlaUITests/FynlaUITests/testNativeBugReportReviewsMetadataBeforeSubmitting \
  test COMPILER_INDEX_STORE_ENABLE=NO
```

Record timestamps, attachment paths, the last visible screen, and whether `fyn.screen`, `fyn.report`, `bug-report.screen`, and `bug-report.description` exist after the tap.

- [ ] **Step 2: Trace the transition boundary**

Inspect the exact `FynConversationView` report callback, `AppRootView` full-screen cover dismissal, pending route state, `onDisappear`, and navigation path mutation. Add test-only diagnostics at those boundaries, rerun once, and identify the first state transition that does not occur.

- [ ] **Step 3: State one root-cause hypothesis and test it minimally**

Write the hypothesis in the evidence file in the form: `The bug report route is lost because <observed state transition>, evidenced by <diagnostic sequence>.` Change one transition variable only and rerun the isolated test.

- [ ] **Step 4: Strengthen the regression test before the production fix**

Require `bug-report.screen`, enter text through the element type actually exposed by SwiftUI (`textFields` or `textViews`, determined from the captured hierarchy), review metadata, submit, and assert the submitted state. Use `waitForExistence`; do not add fixed sleeps.

- [ ] **Step 5: Implement the smallest root-cause fix**

Use a single source of truth for the pending route and perform the push only after the Fyn cover has definitively dismissed. Do not lengthen timeouts as the fix.

- [ ] **Step 6: Rerun isolated test twice and complete native smoke**

Run the isolated command twice, then `ios-native/scripts/run-ui-smoke.sh`.

Expected: all native UI tests pass on the selected Simulator.

- [ ] **Step 7: Hold the task for review**

Run: `git diff --check && git diff --stat`.

---

### Task 11: Complete automated and user-style cross-client acceptance

**Files:**
- Modify: `docs/testing/2026-08-09-ios-m-parity-pr1-evidence.md`
- Modify only for verified regressions: tests or production files in Tasks 1–10.

**Interfaces:**
- Produces one evidence ledger for all PR 1 traceability items and reruns.

- [ ] **Step 1: Run server contract and feature suites**

```bash
./vendor/bin/pest \
  tests/Architecture/GateRoutesTest.php \
  tests/Feature/Auth/WebHandoffTest.php \
  tests/Feature/Api/UserProfileControllerTest.php \
  tests/Feature/Mobile/MobileDashboardNextActionsTest.php \
  tests/Feature/Mobile/NotificationPreferenceApiTest.php \
  tests/Feature/Tiers/TierConfigPropagationTest.php \
  tests/Feature/Tiers/TwoTierIdentityTest.php
```

Expected: PASS with deprecations separately recorded rather than hidden.

- [ ] **Step 2: Run frontend unit/build verification**

```bash
env PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/bin:/bin:/usr/sbin:/sbin npm run test:run -- \
  resources/js/__tests__/mScaffoldBridge.spec.js \
  resources/mobile/__tests__ \
  resources/mobile/navigation/__tests__ \
  resources/mobile/components/__tests__ \
  resources/mobile/views/__tests__
env PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/bin:/bin:/usr/sbin:/sbin npm run build:mobile
env PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/bin:/bin:/usr/sbin:/sbin VITE_BASE_PATH=/build/ npm run build
```

Expected: PASS; record existing Browserslist staleness separately if still emitted.

- [ ] **Step 3: Run native unit and UI suites**

Run all `FynlaTests`, then `ios-native/scripts/run-ui-smoke.sh` on the selected Fynla iPhone 16 Pro iOS 18.6 Simulator.

Expected: PASS with zero failures.

- [ ] **Step 4: Start the local app and seed one acceptance user**

Use the repository’s existing E2E preparation script and local Laravel server. Record only the seeded user identifier and scenario name, never its password or token.

- [ ] **Step 5: Run the `/m` journey in installed Google Chrome**

Through the Chrome connector, verify at a 390×844 viewport:

1. Primary drawer labels and routes, including Bank Accounts, Achievements, Personal Information, Subscription, Settings, and authorised Admin.
2. Full recommendation text and a non-tax semantic destination.
3. Read-only Personal Information values.
4. Notification preference save/reload and failed-write rollback.
5. Help/legal destinations.
6. Free/Premium comparison and secure web subscription handoff.
7. Single-use Admin handoff, including a rejected replay.
8. Valuables copy in Net Worth.

Capture Chrome screenshots and console/network evidence without bearer tokens.

- [ ] **Step 6: Run the matching native journey in Xcode Simulator**

Verify the same seeded user and sequence, with StoreKit test products supplying localised prices. Capture screenshots for the drawer, long recommendation, profile, comparison, settings/legal sheet, Admin handoff sheet, Bank Accounts, Valuables, and submitted bug report.

- [ ] **Step 7: Apply the defect loop until green**

For each failure, add a ledger row containing surface, step, observed result, expected result, classification, root cause, failing regression test, fix, isolated rerun, and full-journey rerun. Follow systematic debugging and TDD for every new defect.

- [ ] **Step 8: Run final repository checks**

Run:

```bash
git diff --check
git status --short --branch
rg -n "sessionStorage\.setItem\(['\"]auth_token|m_scaffold_token.*auth_token" resources/mobile resources/js
```

Expected: no whitespace errors, no unrelated files, and no bearer bridge.

- [ ] **Step 9: Review against the approved traceability ledger**

Mark M-01–M-07, M-13, M-27, and M-33 with code, automated test, Chrome evidence, and iOS evidence links. Leave every other ledger item explicitly deferred to its approved later PR.

- [ ] **Step 10: Request review and commit authorisation**

Present the diff summary, all verification results, known warnings, and evidence file. Stage and commit only after explicit user approval.

---

## Plan self-review

- Spec coverage: Tasks 1–3 cover the semantic registry and secure handoff; Tasks 4–7 cover server-owned subscription, profile, settings, and navigation parity; Tasks 8–9 cover text and naming; Task 10 covers M-33; Task 11 requires cross-client evidence for every PR 1 ledger item.
- Security coverage: handoff authentication, permission, allowlist, hash-at-rest, expiry, replay, session creation, and token non-propagation each have a negative-path test.
- Rollout coverage: legacy route payloads remain while new clients prefer `destination`; persisted `savings` and `chattels` keys remain.
- Platform authority: server features are shared, StoreKit price/currency is retained on iOS, and web/Revolut remains the `/m` payment executor.
- Cross-phase safety: Personal Information has no Edit action; conversation history and contextual editing remain deferred to PR 2.
- Placeholder scan: all implementation steps name exact interfaces, paths, commands, expected failures, and expected passing behavior.
