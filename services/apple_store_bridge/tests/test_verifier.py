import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import Mock

from appstoreserverlibrary.models.Environment import Environment
from appstoreserverlibrary.signed_data_verifier import (
    SignedDataVerifier,
    VerificationException,
    VerificationStatus,
)

from services.apple_store_bridge.errors import BridgeError
from services.apple_store_bridge.verifier import AppleSignedDataService


MONTHLY_PRODUCT = "org.fynla.premium.monthly"
ANNUAL_PRODUCT = "org.fynla.premium.annual"
ACCOUNT_TOKEN = "75c42f38-62f1-4d0e-94ea-f8270f5d73fd"


class Value:
    def __init__(self, value):
        self.value = value


class RecordingFactory:
    def __init__(self, verifier):
        self.verifier = verifier
        self.calls = []

    def __call__(
        self,
        root_certificates,
        enable_online_checks,
        environment,
        bundle_id,
        app_apple_id,
    ):
        self.calls.append(
            (
                root_certificates,
                enable_online_checks,
                environment,
                bundle_id,
                app_apple_id,
            )
        )
        return self.verifier


class AppleSignedDataServiceTest(unittest.TestCase):
    def setUp(self):
        self.temp_dir = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp_dir.cleanup)
        self.root_path = Path(self.temp_dir.name) / "root.der"
        self.root_path.write_bytes(b"public-root-der")

        self.transaction = SimpleNamespace(
            transactionId="transaction-1",
            originalTransactionId="original-1",
            bundleId="org.fynla.app",
            environment=Environment.SANDBOX,
            productId=MONTHLY_PRODUCT,
            appAccountToken=ACCOUNT_TOKEN,
            purchaseDate=1_720_000_000_000,
            expiresDate=1_722_678_400_000,
            revocationDate=None,
            inAppOwnershipType=Value("PURCHASED"),
            transactionReason=Value("PURCHASE"),
            signedDate=1_720_000_000_100,
        )
        self.renewal = SimpleNamespace(
            originalTransactionId="original-1",
            productId=MONTHLY_PRODUCT,
            autoRenewProductId=ANNUAL_PRODUCT,
            autoRenewStatus=Value(1),
            renewalDate=1_722_678_400_000,
            expirationIntent=None,
            gracePeriodExpiresDate=None,
            isInBillingRetryPeriod=False,
            environment=Environment.SANDBOX,
            signedDate=1_720_000_000_100,
        )
        self.verifier = Mock()
        self.verifier.verify_and_decode_signed_transaction.return_value = (
            self.transaction
        )
        self.verifier.verify_and_decode_renewal_info.return_value = self.renewal
        self.factory = RecordingFactory(self.verifier)
        self.service = AppleSignedDataService(verifier_factory=self.factory)

    def request(self, **overrides):
        request = {
            "root_certificate_path": str(self.root_path),
            "environment": "sandbox",
            "bundle_id": "org.fynla.app",
            "app_apple_id": None,
            "allowed_product_ids": [MONTHLY_PRODUCT, ANNUAL_PRODUCT],
            "expected_app_account_token": ACCOUNT_TOKEN,
            "signed_data": "signed.secret.jws",
        }
        request.update(overrides)
        return request

    def assert_bridge_error(self, code, callback, retryable=False):
        with self.assertRaises(BridgeError) as caught:
            callback()

        self.assertEqual(code, caught.exception.code)
        self.assertEqual(retryable, caught.exception.retryable)
        self.assertEqual(code, str(caught.exception))
        return caught.exception

    def test_verifies_and_returns_only_allowlisted_transaction_fields(self):
        result = self.service.verify_transaction(self.request())

        self.assertEqual(
            {
                "transaction_id": "transaction-1",
                "original_transaction_id": "original-1",
                "bundle_id": "org.fynla.app",
                "environment": "sandbox",
                "product_id": MONTHLY_PRODUCT,
                "app_account_token": ACCOUNT_TOKEN,
                "purchase_date": 1_720_000_000_000,
                "expires_date": 1_722_678_400_000,
                "revocation_date": None,
                "ownership_type": "PURCHASED",
                "transaction_reason": "PURCHASE",
                "signed_date": 1_720_000_000_100,
            },
            result,
        )
        self.assertNotIn("signed_data", result)
        self.assertNotIn("x5c", result)
        self.verifier.verify_and_decode_signed_transaction.assert_called_once_with(
            "signed.secret.jws"
        )
        self.assertEqual(
            [
                (
                    [b"public-root-der"],
                    True,
                    Environment.SANDBOX,
                    "org.fynla.app",
                    None,
                )
            ],
            self.factory.calls,
        )

    def test_builds_production_verifier_only_with_exact_numeric_app_id(self):
        self.transaction.environment = Environment.PRODUCTION
        result = self.service.verify_transaction(
            self.request(environment="production", app_apple_id=123456789)
        )

        self.assertEqual("production", result["environment"])
        self.assertEqual(Environment.PRODUCTION, self.factory.calls[0][2])
        self.assertIs(self.factory.calls[0][1], True)
        self.assertEqual(123456789, self.factory.calls[0][4])

        for value in (None, True, False, "123456789", 123.0, 0, -1):
            with self.subTest(app_apple_id=value):
                fresh = AppleSignedDataService(
                    verifier_factory=RecordingFactory(self.verifier)
                )
                self.assert_bridge_error(
                    "invalid_configuration",
                    lambda value=value, fresh=fresh: fresh.verify_transaction(
                        self.request(
                            environment="production",
                            app_apple_id=value,
                        )
                    ),
                )

    def test_rejects_unsupported_environments_and_invalid_trusted_configuration(self):
        invalid_requests = (
            {"environment": "Sandbox"},
            {"environment": "Xcode"},
            {"environment": "xcode"},
            {"environment": "LocalTesting"},
            {"environment": "local_testing"},
            {"environment": 1},
            {"bundle_id": ""},
            {"bundle_id": 1},
            {"allowed_product_ids": []},
            {"allowed_product_ids": [MONTHLY_PRODUCT, MONTHLY_PRODUCT]},
            {"allowed_product_ids": [MONTHLY_PRODUCT, ""]},
            {"allowed_product_ids": "not-a-list"},
        )

        for overrides in invalid_requests:
            with self.subTest(overrides=overrides):
                self.assert_bridge_error(
                    "invalid_configuration",
                    lambda overrides=overrides: self.service.verify_transaction(
                        self.request(**overrides)
                    ),
                )

    def test_rejects_unreadable_or_empty_root_without_leaking_its_path(self):
        missing = str(Path(self.temp_dir.name) / "sensitive-root-name.der")
        error = self.assert_bridge_error(
            "invalid_configuration",
            lambda: self.service.verify_transaction(
                self.request(root_certificate_path=missing)
            ),
        )
        self.assertNotIn(missing, str(error))

        self.root_path.write_bytes(b"")
        self.assert_bridge_error(
            "invalid_configuration",
            lambda: self.service.verify_transaction(self.request()),
        )

    def test_rejects_invalid_or_oversized_signed_input(self):
        for value in (None, "", "   ", 1, True, "x" * 262_145):
            with self.subTest(value_type=type(value).__name__):
                self.assert_bridge_error(
                    "invalid_signed_data",
                    lambda value=value: self.service.verify_transaction(
                        self.request(signed_data=value)
                    ),
                )

    def test_accepts_signed_input_at_the_256_kib_boundary(self):
        result = self.service.verify_transaction(
            self.request(signed_data="x" * (256 * 1024))
        )

        self.assertEqual("transaction-1", result["transaction_id"])

    def test_rejects_transaction_identity_and_evidence_mismatches(self):
        mutations = {
            "bundle": ("bundleId", "org.attacker.app"),
            "environment": ("environment", Environment.PRODUCTION),
            "product": ("productId", "org.attacker.product"),
            "token_mismatch": (
                "appAccountToken",
                "8e681f74-77f5-429f-96ee-6e56019d97f3",
            ),
            "token_not_uuid": ("appAccountToken", "user-1"),
            "missing_token": ("appAccountToken", None),
            "missing_transaction": ("transactionId", None),
            "missing_original": ("originalTransactionId", ""),
            "missing_purchase": ("purchaseDate", None),
            "missing_signed": ("signedDate", None),
            "bool_purchase": ("purchaseDate", True),
            "bool_signed": ("signedDate", False),
            "expiry_before_purchase": ("expiresDate", 1_719_999_999_999),
            "signature_before_purchase": ("signedDate", 1_719_999_999_999),
        }

        original_values = {}
        for case, (attribute, value) in mutations.items():
            with self.subTest(case=case):
                original_values[attribute] = getattr(self.transaction, attribute)
                setattr(self.transaction, attribute, value)
                try:
                    self.assert_bridge_error(
                        "invalid_signed_data",
                        lambda: self.service.verify_transaction(self.request()),
                    )
                finally:
                    setattr(self.transaction, attribute, original_values[attribute])

    def test_nullable_expected_token_still_requires_a_valid_signed_token(self):
        result = self.service.verify_transaction(
            self.request(expected_app_account_token=None)
        )
        self.assertEqual(ACCOUNT_TOKEN, result["app_account_token"])

        self.transaction.appAccountToken = None
        self.assert_bridge_error(
            "invalid_signed_data",
            lambda: self.service.verify_transaction(
                self.request(expected_app_account_token=None)
            ),
        )

    def test_rejects_noncanonical_expected_token(self):
        self.assert_bridge_error(
            "invalid_configuration",
            lambda: self.service.verify_transaction(
                self.request(expected_app_account_token=ACCOUNT_TOKEN.upper())
            ),
        )

    def test_maps_official_verification_failures_without_source_details(self):
        cases = (
            (
                VerificationStatus.RETRYABLE_VERIFICATION_FAILURE,
                "retryable_verification_failure",
                True,
            ),
            (VerificationStatus.INVALID_CERTIFICATE, "invalid_signed_data", False),
            (VerificationStatus.VERIFICATION_FAILURE, "invalid_signed_data", False),
        )

        for status, code, retryable in cases:
            with self.subTest(status=status):
                source = VerificationException(status)
                source.__cause__ = ValueError("private certificate detail")
                self.verifier.verify_and_decode_signed_transaction.side_effect = source
                error = self.assert_bridge_error(
                    code,
                    lambda: self.service.verify_transaction(self.request()),
                    retryable=retryable,
                )
                self.assertNotIn("private certificate detail", str(error))

        self.verifier.verify_and_decode_signed_transaction.side_effect = None

    def test_projects_only_allowlisted_renewal_fields(self):
        result = self.service.verify_renewal(self.request())

        self.assertEqual(
            {
                "original_transaction_id": "original-1",
                "product_id": MONTHLY_PRODUCT,
                "auto_renew_product_id": ANNUAL_PRODUCT,
                "auto_renew_status": 1,
                "renewal_date": 1_722_678_400_000,
                "expiration_intent": None,
                "grace_period_expires_date": None,
                "is_in_billing_retry_period": False,
                "environment": "sandbox",
                "signed_date": 1_720_000_000_100,
            },
            result,
        )
        self.assertNotIn("app_account_token", result)
        self.assertNotIn("signed_data", result)

    def test_rejects_invalid_renewal_identity_products_status_or_environment(self):
        mutations = {
            "original": ("originalTransactionId", None),
            "product": ("productId", "org.attacker.product"),
            "renew_product": ("autoRenewProductId", "org.attacker.product"),
            "status_bool": ("autoRenewStatus", Value(True)),
            "status_unknown": ("autoRenewStatus", Value(2)),
            "billing_retry_type": ("isInBillingRetryPeriod", 1),
            "environment": ("environment", Environment.PRODUCTION),
            "signed_date": ("signedDate", None),
        }

        for case, (attribute, value) in mutations.items():
            with self.subTest(case=case):
                original = getattr(self.renewal, attribute)
                setattr(self.renewal, attribute, value)
                try:
                    self.assert_bridge_error(
                        "invalid_signed_data",
                        lambda: self.service.verify_renewal(self.request()),
                    )
                finally:
                    setattr(self.renewal, attribute, original)

    def test_verifies_nested_notification_transaction_and_renewal(self):
        data = SimpleNamespace(
            bundleId="org.fynla.app",
            environment=Environment.SANDBOX,
            signedTransactionInfo="nested.transaction.jws",
            signedRenewalInfo="nested.renewal.jws",
        )
        notification = SimpleNamespace(
            notificationUUID="9ad56bd2-0bc6-42e0-af24-fd996d87a1e6",
            notificationType=Value("DID_RENEW"),
            subtype=Value("BILLING_RECOVERY"),
            signedDate=1_720_000_000_200,
            data=data,
        )
        self.verifier.verify_and_decode_notification.return_value = notification

        result = self.service.verify_notification(
            self.request(expected_app_account_token=None)
        )

        self.assertEqual(
            {
                "notification_uuid": "9ad56bd2-0bc6-42e0-af24-fd996d87a1e6",
                "notification_type": "DID_RENEW",
                "subtype": "BILLING_RECOVERY",
                "environment": "sandbox",
                "transaction": unittest.mock.ANY,
                "renewal": unittest.mock.ANY,
                "transaction_signed_payload_sha256": (
                    "929d6e24d3f87f502e1468c2cc6858e88dfac5f0e3d72decf7dc3ec1c6b9fba9"
                ),
                "renewal_signed_payload_sha256": (
                    "457b48a7cab1251249c7770b0a3fad1a87c4879b39c0dccc321fbc7539a8676c"
                ),
            },
            result,
        )
        self.assertEqual("transaction-1", result["transaction"]["transaction_id"])
        self.assertEqual("original-1", result["renewal"]["original_transaction_id"])
        self.assertEqual(
            [unittest.mock.call("nested.transaction.jws")],
            self.verifier.verify_and_decode_signed_transaction.call_args_list,
        )
        self.verifier.verify_and_decode_renewal_info.assert_called_once_with(
            "nested.renewal.jws"
        )

    def test_accepts_valid_notification_without_nested_subscription_data(self):
        notification = SimpleNamespace(
            notificationUUID="9ad56bd2-0bc6-42e0-af24-fd996d87a1e6",
            notificationType=Value("TEST"),
            subtype=None,
            signedDate=1_720_000_000_200,
            data=SimpleNamespace(
                bundleId="org.fynla.app",
                environment=Environment.SANDBOX,
                signedTransactionInfo=None,
                signedRenewalInfo=None,
            ),
        )
        self.verifier.verify_and_decode_notification.return_value = notification

        result = self.service.verify_notification(
            self.request(expected_app_account_token=None)
        )

        self.assertEqual("TEST", result["notification_type"])
        self.assertIsNone(result["subtype"])
        self.assertIsNone(result["transaction"])
        self.assertIsNone(result["renewal"])

    def test_rejects_notification_identity_or_outer_data_mismatch(self):
        data = SimpleNamespace(
            bundleId="org.fynla.app",
            environment=Environment.SANDBOX,
            signedTransactionInfo=None,
            signedRenewalInfo=None,
        )
        notification = SimpleNamespace(
            notificationUUID="9ad56bd2-0bc6-42e0-af24-fd996d87a1e6",
            notificationType=Value("TEST"),
            subtype=None,
            signedDate=1_720_000_000_200,
            data=data,
        )
        self.verifier.verify_and_decode_notification.return_value = notification

        mutations = (
            (notification, "notificationUUID", "not-a-uuid"),
            (notification, "notificationType", None),
            (notification, "signedDate", True),
            (data, "bundleId", "org.attacker.app"),
            (data, "environment", Environment.PRODUCTION),
        )
        for owner, attribute, value in mutations:
            with self.subTest(attribute=attribute, value=value):
                original = getattr(owner, attribute)
                setattr(owner, attribute, value)
                try:
                    self.assert_bridge_error(
                        "invalid_signed_data",
                        lambda: self.service.verify_notification(
                            self.request(expected_app_account_token=None)
                        ),
                    )
                finally:
                    setattr(owner, attribute, original)


class OfficialAppleFixtureIntegrationTest(unittest.TestCase):
    fixtures = Path(__file__).with_name("fixtures")

    def verifier(self):
        verifier = SignedDataVerifier(
            [(self.fixtures / "testCA.der").read_bytes()],
            False,
            Environment.SANDBOX,
            "com.example",
            1234,
        )
        # This mirrors Apple's own v3.1.2 fixture test setup. The public test CA
        # intentionally lacks authority identifiers; production never changes
        # strict checks and always enables online checks.
        verifier._chain_verifier.enable_strict_checks = False
        return verifier

    @staticmethod
    def tamper_part(jws, part_index):
        parts = jws.split(".")
        position = len(parts[part_index]) // 2
        replacement = "A" if parts[part_index][position] != "A" else "B"
        parts[part_index] = (
            parts[part_index][:position]
            + replacement
            + parts[part_index][position + 1 :]
        )
        return ".".join(parts)

    def test_official_fixture_verifies_and_all_three_jws_parts_reject_tampering(self):
        jws = (self.fixtures / "transactionInfo").read_text().strip()

        decoded = self.verifier().verify_and_decode_signed_transaction(jws)
        self.assertEqual(Environment.SANDBOX, decoded.environment)
        self.assertEqual("com.example", decoded.bundleId)

        for part_index, label in enumerate(("header", "payload", "signature")):
            with self.subTest(part=label):
                with self.assertRaises(VerificationException):
                    self.verifier().verify_and_decode_signed_transaction(
                        self.tamper_part(jws, part_index)
                    )

        self.assertFalse((self.fixtures / "testSigningKey.p8").exists())


if __name__ == "__main__":
    unittest.main()
