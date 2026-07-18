import hashlib
from pathlib import Path
from typing import Any, Callable, Dict, List
from uuid import UUID

import requests
from appstoreserverlibrary.api_client import (
    APIException,
    AppStoreServerAPIClient,
    GetTransactionHistoryVersion,
)
from appstoreserverlibrary.models.Environment import Environment
from appstoreserverlibrary.models.TransactionHistoryRequest import (
    Order,
    ProductType,
    TransactionHistoryRequest,
)

from .errors import BridgeError
from .verifier import AppleSignedDataService

MAX_PRIVATE_KEY_BYTES = 64 * 1024
MAX_ROOT_CERTIFICATE_BYTES = 64 * 1024
MAX_PAGES = 50
MAX_SIGNED_VALUES = 1_000
MAX_STATUS_GROUPS = 1_000
MAX_STATUS_ITEMS = 1_000
MAX_SIGNED_DATA_BYTES = 256 * 1024

_ENVIRONMENTS = {
    "sandbox": Environment.SANDBOX,
    "production": Environment.PRODUCTION,
}

_REQUEST_KEYS = {
    "version",
    "operation",
    "root_certificate_path",
    "environment",
    "bundle_id",
    "app_apple_id",
    "allowed_product_ids",
    "expected_app_account_token",
    "original_transaction_id",
    "private_key_path",
    "key_id",
    "issuer_id",
}


class AppleServerReconciler:
    def __init__(
        self,
        client_factory=AppStoreServerAPIClient,
        signed_data_service_factory=AppleSignedDataService,
    ):
        self._client_factory = client_factory
        self._signed_data_service_factory = signed_data_service_factory

    def reconcile(self, request: dict) -> Dict[str, Any]:
        configuration = self._configuration(request)
        client = self._build_client(configuration)
        signed_data_service = self._signed_data_service_factory()
        history_request = TransactionHistoryRequest(
            sort=Order.ASCENDING,
            revoked=None,
            productIds=configuration["allowed_product_ids"],
            productTypes=[ProductType.AUTO_RENEWABLE],
        )

        transactions: List[Dict[str, Any]] = []
        renewals: List[Dict[str, Any]] = []
        signed_value_count = 0
        revision = None
        seen_revisions = set()

        for _page_number in range(MAX_PAGES):
            history = self._call_api(
                lambda: client.get_transaction_history(
                    configuration["original_transaction_id"],
                    revision,
                    history_request,
                    GetTransactionHistoryVersion.V2,
                )
            )
            self._validate_response_identity(history, configuration)

            signed_transactions = getattr(history, "signedTransactions", None)
            if signed_transactions is None:
                signed_transactions = []
            if type(signed_transactions) is not list:
                raise BridgeError("invalid_signed_data")

            for signed_transaction in signed_transactions:
                signed_value_count += 1
                self._check_result_count(signed_value_count)
                transactions.append(
                    self._transaction_evidence(
                        signed_transaction,
                        signed_data_service,
                        configuration,
                    )
                )

            has_more = getattr(history, "hasMore", None)
            if type(has_more) is not bool:
                raise BridgeError("invalid_signed_data")
            if not has_more:
                break

            next_revision = getattr(history, "revision", None)
            if (
                type(next_revision) is not str
                or not next_revision
                or next_revision in seen_revisions
            ):
                raise BridgeError("response_too_large")
            seen_revisions.add(next_revision)
            revision = next_revision
        else:
            raise BridgeError("response_too_large")

        status = self._call_api(
            lambda: client.get_all_subscription_statuses(
                configuration["original_transaction_id"],
                None,
            )
        )
        self._validate_response_identity(status, configuration)

        groups = getattr(status, "data", None)
        if groups is None:
            groups = []
        if type(groups) is not list:
            raise BridgeError("invalid_signed_data")

        status_group_count = 0
        status_item_count = 0
        for group in groups:
            status_group_count += 1
            self._check_status_count(status_group_count, MAX_STATUS_GROUPS)
            last_transactions = getattr(group, "lastTransactions", None)
            if last_transactions is None:
                last_transactions = []
            if type(last_transactions) is not list:
                raise BridgeError("invalid_signed_data")

            for item in last_transactions:
                status_item_count += 1
                self._check_status_count(status_item_count, MAX_STATUS_ITEMS)
                item_original = getattr(item, "originalTransactionId", None)
                if item_original != configuration["original_transaction_id"]:
                    raise BridgeError("invalid_signed_data")

                signed_transaction = getattr(item, "signedTransactionInfo", None)
                if signed_transaction is not None:
                    signed_value_count += 1
                    self._check_result_count(signed_value_count)
                    transactions.append(
                        self._transaction_evidence(
                            signed_transaction,
                            signed_data_service,
                            configuration,
                        )
                    )

                signed_renewal = getattr(item, "signedRenewalInfo", None)
                if signed_renewal is not None:
                    signed_value_count += 1
                    self._check_result_count(signed_value_count)
                    renewals.append(
                        self._renewal_evidence(
                            signed_renewal,
                            signed_data_service,
                            configuration,
                        )
                    )

        return {
            "original_transaction_id": configuration["original_transaction_id"],
            "transactions": transactions,
            "renewals": renewals,
        }

    def _configuration(self, request: dict) -> Dict[str, Any]:
        if type(request) is not dict or not set(request).issubset(_REQUEST_KEYS):
            raise BridgeError("invalid_configuration")

        environment_name = request.get("environment")
        bundle_id = self._bounded_string(request.get("bundle_id"), 255)
        key_id = self._bounded_string(request.get("key_id"), 128)
        issuer_id = self._canonical_uuid(request.get("issuer_id"))
        expected_token = self._canonical_uuid(
            request.get("expected_app_account_token")
        )
        original_transaction_id = self._bounded_string(
            request.get("original_transaction_id"),
            191,
        )

        if environment_name not in _ENVIRONMENTS:
            raise BridgeError("invalid_configuration")

        app_apple_id = request.get("app_apple_id")
        if app_apple_id is not None and (
            type(app_apple_id) is not int or app_apple_id <= 0
        ):
            raise BridgeError("invalid_configuration")
        if environment_name == "production" and app_apple_id is None:
            raise BridgeError("invalid_configuration")

        products = request.get("allowed_product_ids")
        if (
            type(products) is not list
            or not products
            or len(products) > 32
            or any(
                type(product) is not str
                or not product
                or product.strip() != product
                or len(product.encode("utf-8")) > 100
                for product in products
            )
            or len(set(products)) != len(products)
        ):
            raise BridgeError("invalid_configuration")

        root_path = self._trusted_file_path(
            request.get("root_certificate_path"),
            MAX_ROOT_CERTIFICATE_BYTES,
        )
        private_key_path = self._trusted_file_path(
            request.get("private_key_path"),
            MAX_PRIVATE_KEY_BYTES,
        )

        return {
            **request,
            "environment": environment_name,
            "environment_value": _ENVIRONMENTS[environment_name],
            "bundle_id": bundle_id,
            "key_id": key_id,
            "issuer_id": issuer_id,
            "expected_app_account_token": expected_token,
            "original_transaction_id": original_transaction_id,
            "allowed_product_ids": products.copy(),
            "root_certificate_path": str(root_path),
            "private_key_path": str(private_key_path),
            "app_apple_id": app_apple_id,
        }

    def _build_client(self, configuration: Dict[str, Any]):
        try:
            signing_key = Path(configuration["private_key_path"]).read_bytes()
            return self._client_factory(
                signing_key=signing_key,
                key_id=configuration["key_id"],
                issuer_id=configuration["issuer_id"],
                bundle_id=configuration["bundle_id"],
                environment=configuration["environment_value"],
            )
        except (OSError, ValueError, TypeError):
            raise BridgeError("invalid_configuration") from None

    def _transaction_evidence(
        self,
        signed_data: Any,
        service: AppleSignedDataService,
        configuration: Dict[str, Any],
    ) -> Dict[str, Any]:
        signed_data = self._bounded_signed_data(signed_data)
        data = service.verify_transaction(
            self._verification_payload(configuration, signed_data)
        )
        if (
            data.get("original_transaction_id")
            != configuration["original_transaction_id"]
            or data.get("app_account_token")
            != configuration["expected_app_account_token"]
            or data.get("product_id")
            not in configuration["allowed_product_ids"]
        ):
            raise BridgeError("invalid_signed_data")
        return self._evidence(signed_data, data)

    def _renewal_evidence(
        self,
        signed_data: Any,
        service: AppleSignedDataService,
        configuration: Dict[str, Any],
    ) -> Dict[str, Any]:
        signed_data = self._bounded_signed_data(signed_data)
        data = service.verify_renewal(
            self._verification_payload(configuration, signed_data)
        )
        if (
            data.get("original_transaction_id")
            != configuration["original_transaction_id"]
            or data.get("product_id")
            not in configuration["allowed_product_ids"]
            or data.get("auto_renew_product_id")
            not in configuration["allowed_product_ids"]
        ):
            raise BridgeError("invalid_signed_data")
        return self._evidence(signed_data, data)

    def _validate_response_identity(
        self,
        response: Any,
        configuration: Dict[str, Any],
    ) -> None:
        if (
            getattr(response, "bundleId", None) != configuration["bundle_id"]
            or self._environment_name(getattr(response, "environment", None))
            != configuration["environment"]
        ):
            raise BridgeError("invalid_signed_data")

        expected_app_id = configuration["app_apple_id"]
        if (
            expected_app_id is not None
            and getattr(response, "appAppleId", None) != expected_app_id
        ):
            raise BridgeError("invalid_signed_data")

    @staticmethod
    def _call_api(operation: Callable[[], Any]) -> Any:
        try:
            return operation()
        except APIException as error:
            if error.http_status_code == 429 or error.http_status_code >= 500:
                raise BridgeError(
                    "app_store_api_unavailable",
                    retryable=True,
                ) from None
            raise BridgeError("app_store_api_rejected") from None
        except requests.RequestException:
            raise BridgeError(
                "app_store_api_unavailable",
                retryable=True,
            ) from None

    @staticmethod
    def _evidence(signed_data: str, data: dict) -> Dict[str, Any]:
        return {
            "signed_payload_sha256": hashlib.sha256(
                signed_data.encode("utf-8")
            ).hexdigest(),
            "data": data,
        }

    @staticmethod
    def _bounded_signed_data(value: Any) -> str:
        if type(value) is not str or not value or value.strip() != value:
            raise BridgeError("invalid_signed_data")
        try:
            size = len(value.encode("utf-8"))
        except UnicodeError:
            raise BridgeError("invalid_signed_data") from None
        if size > MAX_SIGNED_DATA_BYTES:
            raise BridgeError("response_too_large")
        return value

    @staticmethod
    def _check_result_count(count: int) -> None:
        if count > MAX_SIGNED_VALUES:
            raise BridgeError("response_too_large")

    @staticmethod
    def _check_status_count(count: int, maximum: int) -> None:
        if count > maximum:
            raise BridgeError("response_too_large")

    @staticmethod
    def _verification_payload(
        configuration: Dict[str, Any],
        signed_data: str,
    ) -> Dict[str, Any]:
        return {
            "root_certificate_path": configuration["root_certificate_path"],
            "environment": configuration["environment"],
            "bundle_id": configuration["bundle_id"],
            "app_apple_id": configuration["app_apple_id"],
            "allowed_product_ids": configuration["allowed_product_ids"],
            "expected_app_account_token": configuration[
                "expected_app_account_token"
            ],
            "signed_data": signed_data,
        }

    @staticmethod
    def _trusted_file_path(value: Any, maximum_bytes: int) -> Path:
        if type(value) is not str or not value or value.strip() != value:
            raise BridgeError("invalid_configuration")
        path = Path(value)
        if not path.is_absolute():
            raise BridgeError("invalid_configuration")
        try:
            size = path.stat().st_size
        except OSError:
            raise BridgeError("invalid_configuration") from None
        if not path.is_file() or size <= 0 or size > maximum_bytes:
            raise BridgeError("invalid_configuration")
        return path

    @staticmethod
    def _bounded_string(value: Any, maximum_bytes: int) -> str:
        if (
            type(value) is not str
            or not value
            or value.strip() != value
            or len(value.encode("utf-8")) > maximum_bytes
        ):
            raise BridgeError("invalid_configuration")
        return value

    @staticmethod
    def _canonical_uuid(value: Any) -> str:
        if type(value) is not str:
            raise BridgeError("invalid_configuration")
        try:
            canonical = str(UUID(value))
        except (ValueError, AttributeError, TypeError):
            raise BridgeError("invalid_configuration") from None
        if value != canonical:
            raise BridgeError("invalid_configuration")
        return value

    @staticmethod
    def _environment_name(value: Any) -> str:
        raw = getattr(value, "value", value)
        if raw == Environment.SANDBOX.value:
            return "sandbox"
        if raw == Environment.PRODUCTION.value:
            return "production"
        raise BridgeError("invalid_signed_data")
