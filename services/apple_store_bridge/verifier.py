import hashlib
from pathlib import Path
from typing import Any, Callable, Dict, Optional
from uuid import UUID

from appstoreserverlibrary.models.Environment import Environment
from appstoreserverlibrary.signed_data_verifier import (
    SignedDataVerifier,
    VerificationException,
    VerificationStatus,
)

from .errors import BridgeError

MAX_SIGNED_DATA_BYTES = 256 * 1024
MAX_ROOT_CERTIFICATE_BYTES = 64 * 1024
MAX_ALLOWED_PRODUCTS = 32

_ENVIRONMENTS = {
    "sandbox": Environment.SANDBOX,
    "production": Environment.PRODUCTION,
}


class AppleSignedDataService:
    def __init__(self, verifier_factory=SignedDataVerifier):
        self._verifier_factory = verifier_factory

    def verify_transaction(self, request: dict) -> dict:
        verifier = self._build_verifier(request)
        signed_data = self._signed_data(request)
        decoded = self._verify_official(
            lambda: verifier.verify_and_decode_signed_transaction(signed_data)
        )
        return self._transaction_dict(decoded, request)

    def verify_notification(self, request: dict) -> dict:
        verifier = self._build_verifier(request)
        signed_data = self._signed_data(request)
        decoded = self._verify_official(
            lambda: verifier.verify_and_decode_notification(signed_data)
        )

        notification_uuid = self._canonical_uuid(
            getattr(decoded, "notificationUUID", None),
            "invalid_signed_data",
        )
        notification_type = self._required_enum_string(
            getattr(decoded, "notificationType", None)
        )
        subtype = self._optional_enum_string(getattr(decoded, "subtype", None))
        self._required_positive_int(getattr(decoded, "signedDate", None))

        transaction = None
        renewal = None
        transaction_hash = None
        renewal_hash = None
        data = getattr(decoded, "data", None)
        if data is not None:
            if getattr(data, "bundleId", None) != request["bundle_id"]:
                raise BridgeError("invalid_signed_data")
            if self._decoded_environment(getattr(data, "environment", None)) != request[
                "environment"
            ]:
                raise BridgeError("invalid_signed_data")

            signed_transaction = getattr(data, "signedTransactionInfo", None)
            if signed_transaction is not None:
                signed_transaction = self._nested_signed_data(signed_transaction)
                transaction_hash = hashlib.sha256(
                    signed_transaction.encode("utf-8")
                ).hexdigest()
                transaction = self._transaction_dict(
                    self._verify_official(
                        lambda: verifier.verify_and_decode_signed_transaction(
                            signed_transaction
                        )
                    ),
                    request,
                )

            signed_renewal = getattr(data, "signedRenewalInfo", None)
            if signed_renewal is not None:
                signed_renewal = self._nested_signed_data(signed_renewal)
                renewal_hash = hashlib.sha256(
                    signed_renewal.encode("utf-8")
                ).hexdigest()
                renewal = self._renewal_dict(
                    self._verify_official(
                        lambda: verifier.verify_and_decode_renewal_info(signed_renewal)
                    ),
                    request,
                )

        return {
            "notification_uuid": notification_uuid,
            "notification_type": notification_type,
            "subtype": subtype,
            "environment": request["environment"],
            "transaction": transaction,
            "renewal": renewal,
            "transaction_signed_payload_sha256": transaction_hash,
            "renewal_signed_payload_sha256": renewal_hash,
        }

    def verify_renewal(self, request: dict) -> dict:
        verifier = self._build_verifier(request)
        signed_data = self._signed_data(request)
        decoded = self._verify_official(
            lambda: verifier.verify_and_decode_renewal_info(signed_data)
        )
        return self._renewal_dict(decoded, request)

    def _build_verifier(self, request: dict):
        if type(request) is not dict:
            raise BridgeError("invalid_configuration")

        environment_name = request.get("environment")
        if type(environment_name) is not str or environment_name not in _ENVIRONMENTS:
            raise BridgeError("invalid_configuration")

        bundle_id = request.get("bundle_id")
        if type(bundle_id) is not str or not bundle_id.strip():
            raise BridgeError("invalid_configuration")

        app_apple_id = request.get("app_apple_id")
        if app_apple_id is not None and (
            type(app_apple_id) is not int or app_apple_id <= 0
        ):
            raise BridgeError("invalid_configuration")
        if environment_name == "production" and app_apple_id is None:
            raise BridgeError("invalid_configuration")

        self._allowed_products(request)
        self._expected_account_token(request)

        root_path = request.get("root_certificate_path")
        if type(root_path) is not str or not root_path.strip():
            raise BridgeError("invalid_configuration")
        try:
            root_bytes = Path(root_path).read_bytes()
        except (OSError, ValueError):
            raise BridgeError("invalid_configuration") from None
        if not root_bytes or len(root_bytes) > MAX_ROOT_CERTIFICATE_BYTES:
            raise BridgeError("invalid_configuration")

        return self._verifier_factory(
            [root_bytes],
            True,
            _ENVIRONMENTS[environment_name],
            bundle_id,
            app_apple_id,
        )

    def _transaction_dict(self, decoded: Any, request: dict) -> Dict[str, Any]:
        bundle_id = self._required_string(getattr(decoded, "bundleId", None))
        if bundle_id != request["bundle_id"]:
            raise BridgeError("invalid_signed_data")

        environment = self._decoded_environment(
            getattr(decoded, "environment", None)
        )
        if environment != request["environment"]:
            raise BridgeError("invalid_signed_data")

        product_id = self._required_string(getattr(decoded, "productId", None))
        if product_id not in self._allowed_products(request):
            raise BridgeError("invalid_signed_data")

        app_account_token = self._canonical_uuid(
            getattr(decoded, "appAccountToken", None),
            "invalid_signed_data",
        )
        expected_token = self._expected_account_token(request)
        if expected_token is not None and app_account_token != expected_token:
            raise BridgeError("invalid_signed_data")

        transaction_id = self._required_string(
            getattr(decoded, "transactionId", None)
        )
        original_transaction_id = self._required_string(
            getattr(decoded, "originalTransactionId", None)
        )
        purchase_date = self._required_positive_int(
            getattr(decoded, "purchaseDate", None)
        )
        signed_date = self._required_positive_int(
            getattr(decoded, "signedDate", None)
        )
        expires_date = self._optional_positive_int(
            getattr(decoded, "expiresDate", None)
        )
        revocation_date = self._optional_positive_int(
            getattr(decoded, "revocationDate", None)
        )

        if signed_date < purchase_date:
            raise BridgeError("invalid_signed_data")
        if expires_date is not None and expires_date < purchase_date:
            raise BridgeError("invalid_signed_data")
        if revocation_date is not None and revocation_date < purchase_date:
            raise BridgeError("invalid_signed_data")

        return {
            "transaction_id": transaction_id,
            "original_transaction_id": original_transaction_id,
            "bundle_id": bundle_id,
            "environment": environment,
            "product_id": product_id,
            "app_account_token": app_account_token,
            "purchase_date": purchase_date,
            "expires_date": expires_date,
            "revocation_date": revocation_date,
            "ownership_type": self._optional_enum_string(
                getattr(decoded, "inAppOwnershipType", None)
            ),
            "transaction_reason": self._optional_enum_string(
                getattr(decoded, "transactionReason", None)
            ),
            "signed_date": signed_date,
        }

    def _renewal_dict(self, decoded: Any, request: dict) -> Dict[str, Any]:
        environment = self._decoded_environment(
            getattr(decoded, "environment", None)
        )
        if environment != request["environment"]:
            raise BridgeError("invalid_signed_data")

        allowed_products = self._allowed_products(request)
        product_id = self._required_string(getattr(decoded, "productId", None))
        auto_renew_product_id = self._required_string(
            getattr(decoded, "autoRenewProductId", None)
        )
        if (
            product_id not in allowed_products
            or auto_renew_product_id not in allowed_products
        ):
            raise BridgeError("invalid_signed_data")

        auto_renew_status = self._value(getattr(decoded, "autoRenewStatus", None))
        if type(auto_renew_status) is not int or auto_renew_status not in (0, 1):
            raise BridgeError("invalid_signed_data")

        billing_retry = getattr(decoded, "isInBillingRetryPeriod", None)
        if billing_retry is not None and type(billing_retry) is not bool:
            raise BridgeError("invalid_signed_data")

        expiration_intent = self._value(getattr(decoded, "expirationIntent", None))
        if expiration_intent is not None and type(expiration_intent) is not int:
            raise BridgeError("invalid_signed_data")

        return {
            "original_transaction_id": self._required_string(
                getattr(decoded, "originalTransactionId", None)
            ),
            "product_id": product_id,
            "auto_renew_product_id": auto_renew_product_id,
            "auto_renew_status": auto_renew_status,
            "renewal_date": self._optional_positive_int(
                getattr(decoded, "renewalDate", None)
            ),
            "expiration_intent": expiration_intent,
            "grace_period_expires_date": self._optional_positive_int(
                getattr(decoded, "gracePeriodExpiresDate", None)
            ),
            "is_in_billing_retry_period": billing_retry,
            "environment": environment,
            "signed_date": self._required_positive_int(
                getattr(decoded, "signedDate", None)
            ),
        }

    def _verify_official(self, operation: Callable[[], Any]) -> Any:
        try:
            return operation()
        except VerificationException as error:
            if error.status == VerificationStatus.RETRYABLE_VERIFICATION_FAILURE:
                raise BridgeError(
                    "retryable_verification_failure",
                    retryable=True,
                ) from None
            raise BridgeError("invalid_signed_data") from None

    def _signed_data(self, request: dict) -> str:
        return self._bounded_signed_data(request.get("signed_data"))

    def _nested_signed_data(self, value: Any) -> str:
        return self._bounded_signed_data(value)

    @staticmethod
    def _bounded_signed_data(value: Any) -> str:
        if type(value) is not str or not value.strip():
            raise BridgeError("invalid_signed_data")
        try:
            encoded_length = len(value.encode("utf-8"))
        except UnicodeError:
            raise BridgeError("invalid_signed_data") from None
        if encoded_length > MAX_SIGNED_DATA_BYTES:
            raise BridgeError("invalid_signed_data")
        return value

    @staticmethod
    def _allowed_products(request: dict):
        products = request.get("allowed_product_ids")
        if (
            type(products) is not list
            or not products
            or len(products) > MAX_ALLOWED_PRODUCTS
            or any(type(product) is not str or not product.strip() for product in products)
            or len(set(products)) != len(products)
        ):
            raise BridgeError("invalid_configuration")
        return frozenset(products)

    @classmethod
    def _expected_account_token(cls, request: dict) -> Optional[str]:
        token = request.get("expected_app_account_token")
        if token is None:
            return None
        return cls._canonical_uuid(token, "invalid_configuration")

    @staticmethod
    def _canonical_uuid(value: Any, error_code: str) -> str:
        if type(value) is not str:
            raise BridgeError(error_code)
        try:
            canonical = str(UUID(value))
        except (ValueError, AttributeError, TypeError):
            raise BridgeError(error_code) from None
        if value != canonical:
            raise BridgeError(error_code)
        return value

    @classmethod
    def _decoded_environment(cls, value: Any) -> str:
        raw = cls._value(value)
        if raw == Environment.SANDBOX.value:
            return "sandbox"
        if raw == Environment.PRODUCTION.value:
            return "production"
        raise BridgeError("invalid_signed_data")

    @staticmethod
    def _value(value: Any) -> Any:
        return getattr(value, "value", value)

    @classmethod
    def _required_enum_string(cls, value: Any) -> str:
        return cls._required_string(cls._value(value))

    @classmethod
    def _optional_enum_string(cls, value: Any) -> Optional[str]:
        raw = cls._value(value)
        if raw is None:
            return None
        return cls._required_string(raw)

    @staticmethod
    def _required_string(value: Any) -> str:
        if type(value) is not str or not value.strip():
            raise BridgeError("invalid_signed_data")
        return value

    @staticmethod
    def _required_positive_int(value: Any) -> int:
        if type(value) is not int or value <= 0:
            raise BridgeError("invalid_signed_data")
        return value

    @classmethod
    def _optional_positive_int(cls, value: Any) -> Optional[int]:
        if value is None:
            return None
        return cls._required_positive_int(value)
