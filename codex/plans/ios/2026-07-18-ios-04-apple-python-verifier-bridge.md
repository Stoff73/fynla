# iOS Package 4 Apple Python Verifier Bridge Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Use `superpowers:test-driven-development`, `security-and-hardening` and `verification-before-completion`. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the rejected third-party PHP App Store verifier with Apple's official Python library while keeping Laravel as the only public backend and canonical entitlement authority for desktop, `/m` and iOS.

**Architecture:** Laravel invokes a pinned Python CLI through an argv-array `Symfony Process`, sends signed data through standard input and receives a versioned allowlisted JSON result. Python performs Apple cryptographic verification and App Store Server API calls; Laravel retains authentication, validation, persistence, idempotency and provider-neutral entitlement resolution.

**Tech Stack:** Laravel 10/PHP 8.3, Symfony Process, Python `>=3.12,<3.13`, Apple `app-store-server-library==3.1.2`, MySQL 8, Pest, Python `unittest`.

## Global Constraints

- The iOS app calls only the current Laravel backend; it never receives a Python URL or separate identity.
- Laravel remains the only public API, user store, persistence boundary and entitlement authority.
- Products are exactly `org.fynla.premium.monthly` and `org.fynla.premium.annual`.
- Bundle ID is exactly `org.fynla.app`; deployed verification environments are only `sandbox` and `production`.
- The production numeric app ID and App Store API credentials are environment-only; no value is placed in Swift.
- `XCODE` and `LOCAL_TESTING` verification bypasses are rejected at both PHP and Python boundaries.
- Signed payloads travel through process standard input, never argv, logs, database raw columns or exception messages.
- Store only SHA-256 evidence plus the decoded allowlisted fields defined by Package 4.
- Python returns stable Fynla error codes; clients never receive certificate, subprocess or private-key details.
- An unverified, timed-out or malformed result cannot grant or extend Premium and cannot acknowledge a StoreKit transaction.
- Existing Revolut and already-projected entitlements remain readable when the Python verifier is unavailable.
- Use one Package 4 branch and PR. Do not access or change production.
- Never stage or commit `ios-native/Fynla.xcodeproj/project.xcworkspace/xcuserdata/CSJ.xcuserdatad/UserInterfaceState.xcuserstate`.

## Package 4 sequencing

1. Execute Bridge Task 1 now; it replaces original Package 4 Task 1.
2. Execute original Package 4 Tasks 2–4 unchanged.
3. Execute Bridge Tasks 2–4; they replace original Package 4 Task 5.
4. Execute original Package 4 Task 6.
5. Execute Bridge Task 5 inside original Package 4 Task 7.
6. Execute original Package 4 Tasks 8–10.
7. Execute Bridge Task 6 as part of the Package 4 gate, followed by original Task 11.

---

### Task 1: Lock and audit the official Apple Python runtime

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Create: `services/apple_store_bridge/requirements.in`
- Create: `services/apple_store_bridge/requirements.lock`
- Create: `docs/security/apple-python-verifier-review.md`
- Modify: `docs/security/apple-store-verifier-review.md`
- Modify: `tests/Unit/Services/Billing/Apple/AppleVerifierDependencyTest.php`

**Interfaces:**
- Consumes: checked-in `resources/certificates/apple/AppleRootCA-G3.cer` and the rejected PHP audit at commit `e03b6730`.
- Produces: a hash-locked Apple Python runtime and a certificate/dependency test that later bridge tasks can trust.

- [ ] **Step 1: Replace the dependency test with a failing official-runtime test**

Keep the existing subject, issuer and fingerprint assertions, remove `AppStoreServerLibrary\SignedDataVerifier`, and add these assertions:

```php
$composer = file_get_contents(dirname(__DIR__, 5).'/composer.json');
$requirements = file_get_contents(dirname(__DIR__, 5).'/services/apple_store_bridge/requirements.in');
$lock = file_get_contents(dirname(__DIR__, 5).'/services/apple_store_bridge/requirements.lock');

self::assertNotFalse($composer);
self::assertStringNotContainsString('hoels/app-store-server-library-php', $composer);
self::assertSame("app-store-server-library==3.1.2\n", $requirements);
self::assertStringContainsString('app-store-server-library==3.1.2', $lock);
self::assertStringContainsString('--hash=sha256:', $lock);
```

- [ ] **Step 2: Run the focused test and prove RED**

Run:

```bash
./vendor/bin/pest tests/Unit/Services/Billing/Apple/AppleVerifierDependencyTest.php
```

Expected: FAIL because the rejected Composer package is still present and the Python requirement files do not exist.

- [ ] **Step 3: Remove the rejected PHP verifier without updating unrelated packages**

Run Composer with scripts disabled and inspect the lock diff:

```bash
composer remove hoels/app-store-server-library-php --no-scripts --no-interaction
composer validate --strict --no-check-publish
git diff -- composer.json composer.lock
```

Expected: the direct package and its now-unused `firebase/php-jwt`, `hoels/ocsp-php`, `phpseclib/phpseclib` and `paragonie/random_compat` additions disappear; unrelated existing versions do not change.

- [ ] **Step 4: Generate a hash-locked official Python dependency set**

Create `requirements.in` with exactly:

```text
app-store-server-library==3.1.2
```

Generate and verify the lock in a temporary virtual environment:

```bash
python3 -m venv /private/tmp/fynla-apple-lock-venv
/private/tmp/fynla-apple-lock-venv/bin/pip install pip-tools
/private/tmp/fynla-apple-lock-venv/bin/pip-compile --generate-hashes --resolver=backtracking --output-file=services/apple_store_bridge/requirements.lock services/apple_store_bridge/requirements.in
/private/tmp/fynla-apple-lock-venv/bin/pip install --require-hashes -r services/apple_store_bridge/requirements.lock
/private/tmp/fynla-apple-lock-venv/bin/python -c "import importlib.metadata; assert importlib.metadata.version('app-store-server-library') == '3.1.2'"
```

Expected: every requirement has one or more SHA-256 hashes and the installed Apple package reports `3.1.2`.

- [ ] **Step 5: Record the security decision**

`apple-python-verifier-review.md` must record:

- Apple ownership, MIT licence, PyPI trusted-publishing provenance, release `3.1.2`, source commit `4eaa2241218f5c82c0a7f3e23e2fb3a7c2078092` and Python support;
- exact locked transitives and current advisory results;
- verified OCSP freshness, responder identity and delegated `OCSP_SIGNING` checks;
- checked-in Apple Root CA G3 trust anchoring;
- subprocess/stdin boundary and prohibition on raw signed-data logging;
- update policy: review each Apple release, regenerate hashes, run Python/PHP/native gates;
- development-host Python/process/outbound-network checks required before enabling Apple billing.

Update the rejected PHP review to link the approved replacement without changing its rejection evidence.

- [ ] **Step 6: Verify GREEN and commit**

Run:

```bash
./vendor/bin/pest tests/Unit/Services/Billing/Apple/AppleVerifierDependencyTest.php
./vendor/bin/pint --test tests/Unit/Services/Billing/Apple/AppleVerifierDependencyTest.php
composer validate --strict --no-check-publish
git diff --check
```

Expected: focused Pest PASS with at least the existing 10 certificate assertions plus dependency assertions; all other commands exit 0.

Commit:

```bash
git add composer.json composer.lock services/apple_store_bridge/requirements.in services/apple_store_bridge/requirements.lock docs/security/apple-python-verifier-review.md docs/security/apple-store-verifier-review.md tests/Unit/Services/Billing/Apple/AppleVerifierDependencyTest.php
git commit -m "build: use official apple server verifier"
```

---

### Task 2: Define the versioned fail-closed bridge contract

**Files:**
- Create: `services/apple_store_bridge/__init__.py`
- Create: `services/apple_store_bridge/errors.py`
- Create: `services/apple_store_bridge/contract.py`
- Create: `services/apple_store_bridge/tests/__init__.py`
- Create: `services/apple_store_bridge/tests/test_contract.py`

**Interfaces:**
- Consumes: JSON text from standard input.
- Produces: `read_request(stream) -> dict`, `success(data) -> dict` and `failure(error) -> dict` with contract version `1`.

- [ ] **Step 1: Write failing contract tests**

```python
import io
import unittest

from services.apple_store_bridge.contract import failure, read_request, success
from services.apple_store_bridge.errors import BridgeError


class ContractTest(unittest.TestCase):
    def test_accepts_only_version_one_and_known_operations(self):
        request = read_request(io.StringIO(
            '{"version":1,"operation":"verify_transaction","signed_data":"a.b.c"}'
        ))
        self.assertEqual("verify_transaction", request["operation"])

        with self.assertRaisesRegex(BridgeError, "unsupported_contract"):
            read_request(io.StringIO('{"version":2,"operation":"verify_transaction"}'))

        with self.assertRaisesRegex(BridgeError, "unsupported_operation"):
            read_request(io.StringIO('{"version":1,"operation":"unknown"}'))

    def test_outputs_stable_envelopes(self):
        self.assertEqual({"version": 1, "ok": True, "data": {"id": "1"}}, success({"id": "1"}))
        error = BridgeError("invalid_signature", retryable=False)
        self.assertEqual(
            {"version": 1, "ok": False, "error": {"code": "invalid_signature", "retryable": False}},
            failure(error),
        )
```

- [ ] **Step 2: Run and prove RED**

Run:

```bash
python3 -m unittest services.apple_store_bridge.tests.test_contract -v
```

Expected: import failure because the contract modules do not exist.

- [ ] **Step 3: Implement the minimal contract**

`errors.py`:

```python
class BridgeError(Exception):
    def __init__(self, code: str, retryable: bool = False):
        super().__init__(code)
        self.code = code
        self.retryable = retryable
```

`contract.py`:

```python
import json
from typing import IO, Any, Dict

from .errors import BridgeError

CONTRACT_VERSION = 1
OPERATIONS = frozenset({
    "health",
    "verify_transaction",
    "verify_notification",
    "verify_renewal",
    "reconcile",
})


def read_request(stream: IO[str]) -> Dict[str, Any]:
    try:
        request = json.load(stream)
    except (json.JSONDecodeError, UnicodeError) as exc:
        raise BridgeError("malformed_request") from exc
    if not isinstance(request, dict):
        raise BridgeError("malformed_request")
    if request.get("version") != CONTRACT_VERSION:
        raise BridgeError("unsupported_contract")
    if request.get("operation") not in OPERATIONS:
        raise BridgeError("unsupported_operation")
    return request


def success(data: Dict[str, Any]) -> Dict[str, Any]:
    return {"version": CONTRACT_VERSION, "ok": True, "data": data}


def failure(error: BridgeError) -> Dict[str, Any]:
    return {
        "version": CONTRACT_VERSION,
        "ok": False,
        "error": {"code": error.code, "retryable": error.retryable},
    }
```

- [ ] **Step 4: Verify GREEN and commit**

Run:

```bash
python3 -m unittest services.apple_store_bridge.tests.test_contract -v
python3 -m compileall -q services/apple_store_bridge
git diff --check
```

Expected: two tests PASS; compilation and diff check exit 0.

Commit:

```bash
git add services/apple_store_bridge
git commit -m "feat: define apple verifier bridge contract"
```

---

### Task 3: Verify signed Apple data with the official library

**Files:**
- Create: `services/apple_store_bridge/verifier.py`
- Create: `services/apple_store_bridge/cli.py`
- Create: `services/apple_store_bridge/tests/test_verifier.py`
- Create: `services/apple_store_bridge/tests/test_cli.py`
- Create: `services/apple_store_bridge/tests/fixtures/README.md`
- Add: official Apple-library signed-data fixtures under `services/apple_store_bridge/tests/fixtures/`

**Interfaces:**
- Consumes: trusted request fields `root_certificate_path`, `environment`, `bundle_id`, nullable `app_apple_id`, `allowed_product_ids`, nullable `expected_app_account_token` and `signed_data`.
- Produces: an allowlisted transaction, notification or renewal dictionary. It never returns the input JWS or `x5c` chain.

- [ ] **Step 1: Write failing unit tests around an injected official verifier**

The tests must prove:

```python
service = AppleSignedDataService(verifier_factory=factory)
result = service.verify_transaction(valid_request)

self.assertEqual("org.fynla.premium.monthly", result["product_id"])
self.assertEqual(valid_request["expected_app_account_token"], result["app_account_token"])
self.assertNotIn("signed_data", result)
self.assertNotIn("x5c", result)
```

Also test wrong bundle, environment, product and account token; missing IDs/dates; official `RETRYABLE_VERIFICATION_FAILURE`; tampered fixture signature/header/payload; and notification nested transaction/renewal verification.

- [ ] **Step 2: Run and prove RED**

Run with the Task 1 virtual environment:

```bash
/private/tmp/fynla-apple-lock-venv/bin/python -m unittest services.apple_store_bridge.tests.test_verifier -v
```

Expected: import failure because `AppleSignedDataService` does not exist.

- [ ] **Step 3: Implement strict configuration and DTO mapping**

The service constructor and public methods are exactly:

```python
class AppleSignedDataService:
    def __init__(self, verifier_factory=SignedDataVerifier):
        self._verifier_factory = verifier_factory

    def verify_transaction(self, request: dict) -> dict:
        verifier = self._build_verifier(request)
        decoded = verifier.verify_and_decode_signed_transaction(request["signed_data"])
        return self._transaction_dict(decoded, request)

    def verify_notification(self, request: dict) -> dict:
        verifier = self._build_verifier(request)
        decoded = verifier.verify_and_decode_notification(request["signed_data"])
        transaction = None
        renewal = None
        if decoded.data and decoded.data.signedTransactionInfo:
            transaction = self._transaction_dict(
                verifier.verify_and_decode_signed_transaction(decoded.data.signedTransactionInfo),
                request,
            )
        if decoded.data and decoded.data.signedRenewalInfo:
            renewal = self._renewal_dict(
                verifier.verify_and_decode_renewal_info(decoded.data.signedRenewalInfo),
                request,
            )
        return {
            "notification_uuid": decoded.notificationUUID,
            "notification_type": self._value(decoded.notificationType),
            "subtype": self._value(decoded.subtype),
            "environment": request["environment"],
            "transaction": transaction,
            "renewal": renewal,
        }

    def verify_renewal(self, request: dict) -> dict:
        verifier = self._build_verifier(request)
        return self._renewal_dict(
            verifier.verify_and_decode_renewal_info(request["signed_data"]),
            request,
        )
```

`_build_verifier` must map only lowercase `sandbox`/`production`, require the production numeric app ID, read the checked-in DER root and return `SignedDataVerifier([root_bytes], True, environment, bundle_id, app_apple_id)`. `_transaction_dict` must independently compare bundle, environment, exact product allowlist and expected account token before returning only:

```text
transaction_id, original_transaction_id, bundle_id, environment, product_id,
app_account_token, purchase_date, expires_date, revocation_date,
ownership_type, transaction_reason, signed_date
```

`_renewal_dict` returns only original transaction/product/auto-renew product IDs, auto-renew status, renewal date, expiration intent, grace-period expiry, billing-retry flag, environment and signed date.

Map `VerificationStatus.RETRYABLE_VERIFICATION_FAILURE` to `BridgeError("retryable_verification_failure", True)` and all other library verification failures to `BridgeError("invalid_signed_data", False)`. Never embed the source exception message.

- [ ] **Step 4: Implement the CLI without shell/log output**

`cli.py` must expose `main(stdin=sys.stdin, stdout=sys.stdout) -> int`, call `read_request`, dispatch only the explicit operations, serialize exactly one `success(result)` or `failure(error)` envelope to stdout and return `0` for success or `2` for a handled failure. `health` returns:

```json
{"version":1,"ok":true,"data":{"service":"apple-store-bridge","contract":1,"library":"3.1.2"}}
```

Unexpected exceptions become `verifier_unavailable`; stderr contains only that stable code.

- [ ] **Step 5: Add real official-fixture verification**

Copy only the minimum signed-data test fixtures from Apple's MIT-licensed `v3.1.2` test resources, record each upstream path and commit in the fixture README, and test valid plus one-byte-tampered inputs. Do not invent production-like private keys or add any Fynla secret.

- [ ] **Step 6: Verify GREEN and commit**

Run:

```bash
/private/tmp/fynla-apple-lock-venv/bin/python -m unittest discover -s services/apple_store_bridge/tests -v
/private/tmp/fynla-apple-lock-venv/bin/python -m services.apple_store_bridge.cli <<< '{"version":1,"operation":"health"}'
python3 -m compileall -q services/apple_store_bridge
git diff --check
```

Expected: all Python tests PASS; health prints the exact safe envelope; no signed input appears in output.

Commit:

```bash
git add services/apple_store_bridge
git commit -m "feat: verify apple signed data with official library"
```

---

### Task 4: Connect Laravel to the internal verifier bridge

**Files:**
- Create: `config/apple_store.php`
- Modify: `.env.example`
- Create: `app/Services/Billing/Apple/AppleSignedDataVerifier.php`
- Create: `app/Services/Billing/Apple/PythonAppleSignedDataVerifier.php`
- Create: `app/Services/Billing/Apple/AppleBridgeClient.php`
- Create: `app/Services/Billing/Apple/SymfonyAppleBridgeClient.php`
- Create: `app/Data/Billing/Apple/VerifiedAppleTransaction.php`
- Create: `app/Data/Billing/Apple/VerifiedAppleNotification.php`
- Create: `app/Exceptions/Billing/AppleVerificationException.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `tests/Unit/Services/Billing/Apple/PythonAppleSignedDataVerifierTest.php`
- Create: `tests/Integration/Services/Billing/Apple/SymfonyAppleBridgeClientTest.php`

**Interfaces:**
- Consumes: the bridge contract from Tasks 2–3.
- Produces: Fynla-owned PHP DTOs and `AppleSignedDataVerifier`; original Package 4 Tasks 6–8 depend on these types.

- [ ] **Step 1: Write failing adapter and process tests**

Tests must prove the process argv is exactly `[configured-python, configured-cli,]`, the signed value appears only in stdin JSON, the timeout is enforced, stderr/source exceptions are not exposed, malformed/version-mismatched output fails closed, and the adapter maps the allowlisted success fields into immutable DTOs.

- [ ] **Step 2: Run and prove RED**

Run:

```bash
./vendor/bin/pest tests/Unit/Services/Billing/Apple/PythonAppleSignedDataVerifierTest.php tests/Integration/Services/Billing/Apple/SymfonyAppleBridgeClientTest.php
```

Expected: class-not-found failures for the new Fynla interfaces.

- [ ] **Step 3: Define the Fynla interface and DTOs**

```php
interface AppleSignedDataVerifier
{
    public function verifyTransaction(
        string $jws,
        string $expectedEnvironment,
        ?string $expectedAppAccountToken,
    ): VerifiedAppleTransaction;

    public function verifyNotification(
        string $jws,
        string $expectedEnvironment,
    ): VerifiedAppleNotification;
}
```

`VerifiedAppleTransaction` is a `final readonly class` with typed properties matching the twelve allowlisted transaction fields in Task 3. Dates are nullable `CarbonImmutable`; identifiers/environment/product/token are strings. `VerifiedAppleNotification` contains notification UUID/type/subtype/environment plus nullable verified transaction and renewal DTOs.

- [ ] **Step 4: Implement the process client**

```php
interface AppleBridgeClient
{
    /** @return array<string, mixed> */
    public function call(string $operation, array $payload): array;
}
```

`SymfonyAppleBridgeClient` must build an argv-array `Process`, set JSON input, a 40-second timeout and a minimal environment, then validate response size, JSON, contract version, `ok` and stable error fields. It must never concatenate a shell command, include JWS in an exception/log or return stderr. Non-zero exit with a valid failure envelope maps its stable code; malformed output maps `verifier_unavailable`.

- [ ] **Step 5: Add trusted configuration and binding**

`config/apple_store.php` defines bundle `org.fynla.app`, exact product array, lowercase runtime environment, checked-in root path, nullable numeric app ID, Python executable, CLI path, process timeout, key ID, issuer ID and private-key path. `.env.example` lists names with blank credential values. Bind `AppleSignedDataVerifier` to `PythonAppleSignedDataVerifier` and `AppleBridgeClient` to `SymfonyAppleBridgeClient`.

- [ ] **Step 6: Verify GREEN and commit**

Run:

```bash
./vendor/bin/pest tests/Unit/Services/Billing/Apple/PythonAppleSignedDataVerifierTest.php tests/Integration/Services/Billing/Apple/SymfonyAppleBridgeClientTest.php
./vendor/bin/pint --test app/Services/Billing/Apple app/Data/Billing/Apple app/Exceptions/Billing tests/Unit/Services/Billing/Apple tests/Integration/Services/Billing/Apple
git diff --check
```

Expected: all bridge/adapter tests PASS; formatting and diff check exit 0.

Commit:

```bash
git add config/apple_store.php .env.example app/Services/Billing/Apple app/Data/Billing/Apple app/Exceptions/Billing/AppleVerificationException.php app/Providers/AppServiceProvider.php tests/Unit/Services/Billing/Apple tests/Integration/Services/Billing/Apple
git commit -m "feat: bridge laravel to apple verifier"
```

---

### Task 5: Reconcile through Apple's official App Store Server API client

**Files:**
- Create: `services/apple_store_bridge/api_client.py`
- Modify: `services/apple_store_bridge/cli.py`
- Create: `services/apple_store_bridge/tests/test_api_client.py`
- Create: `app/Services/Billing/Apple/AppleStoreServerClient.php`
- Create: `app/Services/Billing/Apple/PythonAppleStoreServerClient.php`
- Create: `app/Data/Billing/Apple/AppleReconciliationBatch.php`
- Create: `tests/Unit/Services/Billing/Apple/PythonAppleStoreServerClientTest.php`

**Interfaces:**
- Consumes: original transaction ID, expected app account token and server-only App Store API configuration.
- Produces: `AppleStoreServerClient::reconcile(string $originalTransactionId, string $expectedAppAccountToken): AppleReconciliationBatch` containing only verified allowlisted transaction/renewal DTOs and SHA-256 evidence.

- [ ] **Step 1: Write failing pagination/status/API-failure tests**

With an injected official client and signed-data service, prove that reconciliation:

- calls `get_transaction_history(original_transaction_id, revision, history_request, GetTransactionHistoryVersion.V2)` until `hasMore` is false;
- calls `get_all_subscription_statuses(original_transaction_id, None)`;
- independently verifies every returned signed transaction and renewal value;
- rejects any product/account-token mismatch;
- returns hashes plus allowlisted fields, never signed JWS;
- maps 5xx/network API failures to retryable and 4xx identity/input failures to permanent stable codes.

- [ ] **Step 2: Run and prove RED**

Run:

```bash
/private/tmp/fynla-apple-lock-venv/bin/python -m unittest services.apple_store_bridge.tests.test_api_client -v
```

Expected: import failure because `AppleServerReconciler` does not exist.

- [ ] **Step 3: Implement paginated verified reconciliation**

Construct `AppStoreServerAPIClient` from the server-local private-key file, key ID, issuer ID, bundle and trusted environment. Use:

```python
history_request = TransactionHistoryRequest(
    sort=Order.ASCENDING,
    revoked=None,
    productIds=request["allowed_product_ids"],
    productTypes=[ProductType.AUTO_RENEWABLE],
)
response = client.get_transaction_history(
    request["original_transaction_id"],
    revision,
    history_request,
    GetTransactionHistoryVersion.V2,
)
status = client.get_all_subscription_statuses(
    request["original_transaction_id"],
    None,
)
```

For each signed value, compute SHA-256 in memory, verify through `AppleSignedDataService`, then discard the signed string. Return only verified DTO dictionaries/hashes. Cap total pages and result count with explicit `response_too_large` failure.

- [ ] **Step 4: Add the PHP client and DTO**

```php
interface AppleStoreServerClient
{
    public function reconcile(
        string $originalTransactionId,
        string $expectedAppAccountToken,
    ): AppleReconciliationBatch;
}
```

`PythonAppleStoreServerClient` calls bridge operation `reconcile` with trusted configuration and maps the safe result into readonly DTOs. It never reads or accepts private-key material from a request/controller.

- [ ] **Step 5: Verify GREEN and commit**

Run:

```bash
/private/tmp/fynla-apple-lock-venv/bin/python -m unittest discover -s services/apple_store_bridge/tests -v
./vendor/bin/pest tests/Unit/Services/Billing/Apple/PythonAppleStoreServerClientTest.php tests/Unit/Services/Billing/Apple
git diff --check
```

Expected: Python and PHP tests PASS; diff check exits 0.

Commit:

```bash
git add services/apple_store_bridge app/Services/Billing/Apple app/Data/Billing/Apple tests/Unit/Services/Billing/Apple
git commit -m "feat: reconcile through official app store client"
```

---

### Task 6: Prove the current backend runtime and seamless three-surface boundary

**Files:**
- Create: `app/Console/Commands/AppleStoreBridgeHealth.php`
- Modify: `app/Console/Kernel.php`
- Create: `tests/Feature/Console/AppleStoreBridgeHealthTest.php`
- Modify: `.github/workflows/quality.yml`
- Modify: `docs/architecture/client-parity-ledger.md`
- Modify: `docs/security/apple-python-verifier-review.md`

**Interfaces:**
- Consumes: completed Package 4 bridge, entitlement resolver and native/desktop/mobile APIs.
- Produces: a read-only health gate and durable evidence that all clients use the current Laravel backend and canonical entitlement.

- [ ] **Step 1: Write a failing read-only health-command test**

The command must check configured executable/CLI/root paths, run bridge `health`, assert contract/library versions and report only safe booleans/versions. It must never print configuration values or secrets. Test success, missing runtime, timeout, wrong contract and wrong library version.

- [ ] **Step 2: Run and prove RED**

Run:

```bash
./vendor/bin/pest tests/Feature/Console/AppleStoreBridgeHealthTest.php
```

Expected: command-not-found failure.

- [ ] **Step 3: Implement the health command and CI lane**

Command signature:

```text
apple-store:bridge-health --json
```

Success JSON is exactly:

```json
{"success":true,"python":true,"bridge":true,"contract":1,"library":"3.1.2","root_certificate":true}
```

Failure exits `1` with the same keys and no paths, JWS, environment values or exception text. CI installs `requirements.lock` with `--require-hashes`, runs Python unit tests, the PHP bridge suites and the health command.

- [ ] **Step 4: Run local and development-only gates**

Local:

```bash
php artisan apple-store:bridge-health --json
./vendor/bin/pest tests/Unit/Services/Billing/Apple tests/Feature/Native/Billing tests/Feature/Webhooks/Apple tests/Feature/Mobile tests/Feature/Contracts
python3 -m unittest discover -s services/apple_store_bridge/tests -v
```

Development host, after the normal authorised dev deployment:

```bash
python3 --version
python3 -m pip install --require-hashes -r services/apple_store_bridge/requirements.lock
php artisan apple-store:bridge-health --json
```

Expected: Python is supported, hashed install succeeds and health JSON reports all safe fields true. Do not access production.

- [ ] **Step 5: Record seamless client evidence**

The parity ledger must record that iOS submits to Laravel, Laravel alone persists the provider-neutral grant, and the same user's Apple Premium is returned by native entitlement, desktop auth/subscription status and `/api/v1/mobile/dashboard`. Record test names and hashes/IDs only; never signed payloads.

- [ ] **Step 6: Verify and commit**

Run:

```bash
./vendor/bin/pest tests/Feature/Console/AppleStoreBridgeHealthTest.php tests/Unit/Services/Billing/Apple
python3 -m unittest discover -s services/apple_store_bridge/tests -v
git diff --check
```

Expected: PASS and clean diff check.

Commit:

```bash
git add app/Console/Commands/AppleStoreBridgeHealth.php app/Console/Kernel.php tests/Feature/Console/AppleStoreBridgeHealthTest.php .github/workflows/quality.yml docs/architecture/client-parity-ledger.md docs/security/apple-python-verifier-review.md
git commit -m "test: gate official apple verifier runtime"
```

## Completion criteria

- The rejected PHP verifier and its unused new transitives are absent from Composer.
- Apple `app-store-server-library==3.1.2` and all Python transitives are hash-locked and security-reviewed.
- iOS calls only current Laravel routes and shares one user/entitlement state with desktop and `/m`.
- Laravel sends signed data only through stdin to a bounded no-shell process.
- Python verifies chain/signature/OCSP/bundle/environment and Fynla enforces product/token.
- Raw JWS, `x5c`, private keys and certificate details never appear in client responses, logs or persisted raw columns.
- Reconciliation uses Apple's official API client and verifies every returned signed value before projection.
- Runtime or verification failures fail closed without breaking existing entitlement reads.
- Local/CI/development-only health gates pass before Apple billing is enabled.
