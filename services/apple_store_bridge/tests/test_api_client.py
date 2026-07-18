import hashlib
import json
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import Mock, call

import requests
from appstoreserverlibrary.api_client import (
    APIException,
    GetTransactionHistoryVersion,
)
from appstoreserverlibrary.models.Environment import Environment
from appstoreserverlibrary.models.TransactionHistoryRequest import (
    Order,
    ProductType,
    TransactionHistoryRequest,
)

from services.apple_store_bridge.api_client import AppleServerReconciler
from services.apple_store_bridge.errors import BridgeError


ACCOUNT_TOKEN = "75c42f38-62f1-4d0e-94ea-f8270f5d73fd"
ORIGINAL_TRANSACTION_ID = "apple-original-1"
MONTHLY_PRODUCT = "org.fynla.premium.monthly"
ANNUAL_PRODUCT = "org.fynla.premium.annual"


def transaction_data(
    transaction_id,
    original_transaction_id=ORIGINAL_TRANSACTION_ID,
    account_token=ACCOUNT_TOKEN,
    product_id=MONTHLY_PRODUCT,
):
    return {
        "transaction_id": transaction_id,
        "original_transaction_id": original_transaction_id,
        "bundle_id": "org.fynla.app",
        "environment": "sandbox",
        "product_id": product_id,
        "app_account_token": account_token,
        "purchase_date": 1_720_000_000_000,
        "expires_date": 1_722_678_400_000,
        "revocation_date": None,
        "ownership_type": "PURCHASED",
        "transaction_reason": "PURCHASE",
        "signed_date": 1_720_000_000_100,
    }


def renewal_data(
    original_transaction_id=ORIGINAL_TRANSACTION_ID,
    product_id=MONTHLY_PRODUCT,
    auto_renew_product_id=MONTHLY_PRODUCT,
):
    return {
        "original_transaction_id": original_transaction_id,
        "product_id": product_id,
        "auto_renew_product_id": auto_renew_product_id,
        "auto_renew_status": 1,
        "renewal_date": 1_722_678_400_000,
        "expiration_intent": None,
        "grace_period_expires_date": None,
        "is_in_billing_retry_period": False,
        "environment": "sandbox",
        "signed_date": 1_720_000_000_200,
    }


class RecordingSignedDataService:
    def __init__(self):
        self.transaction_calls = []
        self.renewal_calls = []
        self.transactions = {}
        self.renewals = {}

    def verify_transaction(self, request):
        self.transaction_calls.append(request.copy())
        signed_data = request["signed_data"]
        if signed_data not in self.transactions:
            raise BridgeError("invalid_signed_data")
        return self.transactions[signed_data]

    def verify_renewal(self, request):
        self.renewal_calls.append(request.copy())
        signed_data = request["signed_data"]
        if signed_data not in self.renewals:
            raise BridgeError("invalid_signed_data")
        return self.renewals[signed_data]


class AppleServerReconcilerTest(unittest.TestCase):
    def setUp(self):
        self.temporary_directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary_directory.cleanup)
        self.private_key_path = Path(self.temporary_directory.name) / "AuthKey.p8"
        self.private_key_path.write_bytes(b"private-key-file-bytes")
        self.root_certificate_path = Path(self.temporary_directory.name) / "AppleRoot.cer"
        self.root_certificate_path.write_bytes(b"test-root-certificate")
        self.request = {
            "root_certificate_path": str(self.root_certificate_path),
            "environment": "sandbox",
            "bundle_id": "org.fynla.app",
            "app_apple_id": None,
            "allowed_product_ids": [MONTHLY_PRODUCT, ANNUAL_PRODUCT],
            "expected_app_account_token": ACCOUNT_TOKEN,
            "original_transaction_id": ORIGINAL_TRANSACTION_ID,
            "private_key_path": str(self.private_key_path),
            "key_id": "KEY1234567",
            "issuer_id": "2efc1b61-4c5f-4cf4-8389-4db4f90044ed",
        }
        self.client = Mock()
        self.service = RecordingSignedDataService()
        self.client_factory_calls = []

        def client_factory(*args, **kwargs):
            self.client_factory_calls.append((args, kwargs))
            return self.client

        self.reconciler = AppleServerReconciler(
            client_factory=client_factory,
            signed_data_service_factory=lambda: self.service,
        )

    def test_paginates_history_checks_status_and_verifies_every_signed_value(self):
        self.client.get_transaction_history.side_effect = [
            SimpleNamespace(
                revision="revision-1",
                hasMore=True,
                bundleId="org.fynla.app",
                appAppleId=None,
                environment=Environment.SANDBOX,
                signedTransactions=["history.transaction.1"],
            ),
            SimpleNamespace(
                revision="revision-2",
                hasMore=False,
                bundleId="org.fynla.app",
                appAppleId=None,
                environment=Environment.SANDBOX,
                signedTransactions=["history.transaction.2"],
            ),
        ]
        self.client.get_all_subscription_statuses.return_value = SimpleNamespace(
            bundleId="org.fynla.app",
            appAppleId=None,
            environment=Environment.SANDBOX,
            data=[
                SimpleNamespace(
                    lastTransactions=[
                        SimpleNamespace(
                            originalTransactionId=ORIGINAL_TRANSACTION_ID,
                            signedTransactionInfo="status.transaction.3",
                            signedRenewalInfo="status.renewal.1",
                        )
                    ]
                )
            ],
        )
        self.service.transactions = {
            "history.transaction.1": transaction_data("transaction-1"),
            "history.transaction.2": transaction_data("transaction-2"),
            "status.transaction.3": transaction_data("transaction-3"),
        }
        self.service.renewals = {
            "status.renewal.1": renewal_data(),
        }

        result = self.reconciler.reconcile(self.request)

        self.assertEqual(1, len(self.client_factory_calls))
        args, kwargs = self.client_factory_calls[0]
        self.assertEqual((), args)
        self.assertEqual(b"private-key-file-bytes", kwargs["signing_key"])
        self.assertEqual("KEY1234567", kwargs["key_id"])
        self.assertEqual(self.request["issuer_id"], kwargs["issuer_id"])
        self.assertEqual("org.fynla.app", kwargs["bundle_id"])
        self.assertEqual(Environment.SANDBOX, kwargs["environment"])

        history_calls = self.client.get_transaction_history.call_args_list
        self.assertEqual(2, len(history_calls))
        history_request = history_calls[0].args[2]
        self.assertIsInstance(history_request, TransactionHistoryRequest)
        self.assertEqual(Order.ASCENDING, history_request.sort)
        self.assertIsNone(history_request.revoked)
        self.assertEqual([MONTHLY_PRODUCT, ANNUAL_PRODUCT], history_request.productIds)
        self.assertEqual([ProductType.AUTO_RENEWABLE], history_request.productTypes)
        self.assertEqual(
            [
                call(
                    ORIGINAL_TRANSACTION_ID,
                    None,
                    history_request,
                    GetTransactionHistoryVersion.V2,
                ),
                call(
                    ORIGINAL_TRANSACTION_ID,
                    "revision-1",
                    history_request,
                    GetTransactionHistoryVersion.V2,
                ),
            ],
            history_calls,
        )
        self.client.get_all_subscription_statuses.assert_called_once_with(
            ORIGINAL_TRANSACTION_ID,
            None,
        )

        self.assertEqual(
            [
                "history.transaction.1",
                "history.transaction.2",
                "status.transaction.3",
            ],
            [request["signed_data"] for request in self.service.transaction_calls],
        )
        self.assertEqual(
            ["status.renewal.1"],
            [request["signed_data"] for request in self.service.renewal_calls],
        )
        self.assertTrue(
            all(
                request["expected_app_account_token"] == ACCOUNT_TOKEN
                for request in self.service.transaction_calls
            )
        )
        expected_verifier_keys = {
            "root_certificate_path",
            "environment",
            "bundle_id",
            "app_apple_id",
            "allowed_product_ids",
            "expected_app_account_token",
            "signed_data",
        }
        self.assertTrue(
            all(
                set(request) == expected_verifier_keys
                for request in (
                    self.service.transaction_calls + self.service.renewal_calls
                )
            )
        )

        self.assertEqual(
            {
                "original_transaction_id": ORIGINAL_TRANSACTION_ID,
                "transactions": [
                    {
                        "signed_payload_sha256": hashlib.sha256(
                            signed.encode("utf-8")
                        ).hexdigest(),
                        "data": self.service.transactions[signed],
                    }
                    for signed in [
                        "history.transaction.1",
                        "history.transaction.2",
                        "status.transaction.3",
                    ]
                ],
                "renewals": [
                    {
                        "signed_payload_sha256": hashlib.sha256(
                            b"status.renewal.1"
                        ).hexdigest(),
                        "data": renewal_data(),
                    }
                ],
            },
            result,
        )
        serialized = json.dumps(result)
        self.assertNotIn("history.transaction", serialized)
        self.assertNotIn("status.transaction", serialized)
        self.assertNotIn("status.renewal", serialized)
        self.assertNotIn(str(self.private_key_path), serialized)
        self.assertNotIn("private-key-file-bytes", serialized)

    def test_rejects_only_account_token_mismatch(self):
        self.client.get_transaction_history.return_value = SimpleNamespace(
            revision="done",
            hasMore=False,
            bundleId="org.fynla.app",
            appAppleId=None,
            environment=Environment.SANDBOX,
            signedTransactions=["mismatched.transaction"],
        )
        self.service.transactions = {
            "mismatched.transaction": transaction_data(
                "transaction-1",
                account_token="2efc1b61-4c5f-4cf4-8389-4db4f90044ed",
            )
        }

        with self.assertRaises(BridgeError) as raised:
            self.reconciler.reconcile(self.request)

        self.assertEqual("invalid_signed_data", raised.exception.code)
        self.assertFalse(raised.exception.retryable)
        self.client.get_all_subscription_statuses.assert_not_called()

    def test_rejects_only_transaction_product_mismatch(self):
        self.client.get_transaction_history.return_value = SimpleNamespace(
            revision="done",
            hasMore=False,
            bundleId="org.fynla.app",
            appAppleId=None,
            environment=Environment.SANDBOX,
            signedTransactions=["mismatched.transaction"],
        )
        self.service.transactions = {
            "mismatched.transaction": transaction_data(
                "transaction-1",
                product_id="org.attacker.product",
            )
        }

        with self.assertRaises(BridgeError) as raised:
            self.reconciler.reconcile(self.request)

        self.assertEqual("invalid_signed_data", raised.exception.code)
        self.assertFalse(raised.exception.retryable)
        self.client.get_all_subscription_statuses.assert_not_called()

    def test_rejects_only_renewal_product_mismatch(self):
        self._set_single_renewal(
            renewal_data(product_id="org.attacker.product")
        )

        with self.assertRaises(BridgeError) as raised:
            self.reconciler.reconcile(self.request)

        self.assertEqual("invalid_signed_data", raised.exception.code)
        self.assertFalse(raised.exception.retryable)

    def test_rejects_only_renewal_auto_renew_product_mismatch(self):
        self._set_single_renewal(
            renewal_data(auto_renew_product_id="org.attacker.product")
        )

        with self.assertRaises(BridgeError) as raised:
            self.reconciler.reconcile(self.request)

        self.assertEqual("invalid_signed_data", raised.exception.code)
        self.assertFalse(raised.exception.retryable)

    def test_bounds_status_items_even_when_they_have_no_signed_values(self):
        from services.apple_store_bridge.api_client import MAX_STATUS_ITEMS

        self.client.get_transaction_history.return_value = SimpleNamespace(
            revision="done",
            hasMore=False,
            bundleId="org.fynla.app",
            appAppleId=None,
            environment=Environment.SANDBOX,
            signedTransactions=[],
        )
        self.client.get_all_subscription_statuses.return_value = SimpleNamespace(
            bundleId="org.fynla.app",
            appAppleId=None,
            environment=Environment.SANDBOX,
            data=[
                SimpleNamespace(
                    lastTransactions=[
                        SimpleNamespace(
                            originalTransactionId=ORIGINAL_TRANSACTION_ID,
                            signedTransactionInfo=None,
                            signedRenewalInfo=None,
                        )
                        for _ in range(MAX_STATUS_ITEMS + 1)
                    ]
                )
            ],
        )

        with self.assertRaises(BridgeError) as raised:
            self.reconciler.reconcile(self.request)

        self.assertEqual("response_too_large", raised.exception.code)
        self.assertFalse(raised.exception.retryable)
        self.assertEqual([], self.service.transaction_calls)
        self.assertEqual([], self.service.renewal_calls)

    def test_bounds_status_groups_even_when_they_have_no_items(self):
        from services.apple_store_bridge.api_client import MAX_STATUS_GROUPS

        self.client.get_transaction_history.return_value = SimpleNamespace(
            revision="done",
            hasMore=False,
            bundleId="org.fynla.app",
            appAppleId=None,
            environment=Environment.SANDBOX,
            signedTransactions=[],
        )
        self.client.get_all_subscription_statuses.return_value = SimpleNamespace(
            bundleId="org.fynla.app",
            appAppleId=None,
            environment=Environment.SANDBOX,
            data=[
                SimpleNamespace(lastTransactions=[])
                for _ in range(MAX_STATUS_GROUPS + 1)
            ],
        )

        with self.assertRaises(BridgeError) as raised:
            self.reconciler.reconcile(self.request)

        self.assertEqual("response_too_large", raised.exception.code)
        self.assertFalse(raised.exception.retryable)
        self.assertEqual([], self.service.transaction_calls)
        self.assertEqual([], self.service.renewal_calls)

    def test_rejects_repeated_pagination_revision_before_unbounded_work(self):
        repeating_page = SimpleNamespace(
            revision="same-revision",
            hasMore=True,
            bundleId="org.fynla.app",
            appAppleId=None,
            environment=Environment.SANDBOX,
            signedTransactions=[],
        )
        self.client.get_transaction_history.return_value = repeating_page

        with self.assertRaises(BridgeError) as raised:
            self.reconciler.reconcile(self.request)

        self.assertEqual("response_too_large", raised.exception.code)
        self.assertFalse(raised.exception.retryable)
        self.assertEqual(2, self.client.get_transaction_history.call_count)
        self.client.get_all_subscription_statuses.assert_not_called()

    def test_maps_api_and_network_failures_to_stable_errors(self):
        cases = [
            (APIException(500), "app_store_api_unavailable", True),
            (APIException(429), "app_store_api_unavailable", True),
            (requests.RequestException("private network detail"), "app_store_api_unavailable", True),
            (APIException(404), "app_store_api_rejected", False),
        ]

        for source, code, retryable in cases:
            with self.subTest(source=source.__class__.__name__, status=getattr(source, "http_status_code", None)):
                self.client.reset_mock()
                self.client.get_transaction_history.side_effect = source

                with self.assertRaises(BridgeError) as raised:
                    self.reconciler.reconcile(self.request)

                self.assertEqual(code, raised.exception.code)
                self.assertEqual(retryable, raised.exception.retryable)
                self.assertEqual(code, str(raised.exception))
                self.assertNotIn("private", str(raised.exception))

    def test_rejects_untrusted_configuration_without_exposing_values(self):
        cases = [
            {"private_key_path": "relative/AuthKey.p8"},
            {"private_key": "private-key-material"},
            {"key_id": ""},
            {"issuer_id": "not-a-canonical-uuid"},
            {"expected_app_account_token": None},
            {"original_transaction_id": ""},
            {"environment": "xcode"},
        ]

        for overrides in cases:
            with self.subTest(overrides=list(overrides)):
                request = {**self.request, **overrides}
                with self.assertRaises(BridgeError) as raised:
                    self.reconciler.reconcile(request)

                self.assertEqual("invalid_configuration", raised.exception.code)
                self.assertFalse(raised.exception.retryable)
                self.assertEqual("invalid_configuration", str(raised.exception))

    def _set_single_renewal(self, verified_renewal):
        self.client.get_transaction_history.return_value = SimpleNamespace(
            revision="done",
            hasMore=False,
            bundleId="org.fynla.app",
            appAppleId=None,
            environment=Environment.SANDBOX,
            signedTransactions=[],
        )
        self.client.get_all_subscription_statuses.return_value = SimpleNamespace(
            bundleId="org.fynla.app",
            appAppleId=None,
            environment=Environment.SANDBOX,
            data=[
                SimpleNamespace(
                    lastTransactions=[
                        SimpleNamespace(
                            originalTransactionId=ORIGINAL_TRANSACTION_ID,
                            signedTransactionInfo=None,
                            signedRenewalInfo="status.renewal.1",
                        )
                    ]
                )
            ],
        )
        self.service.renewals = {"status.renewal.1": verified_renewal}


if __name__ == "__main__":
    unittest.main()
