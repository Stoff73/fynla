# Apple App Store official Python verifier review

**Reviewed:** 18 July 2026
**Decision:** **APPROVED FOR THE DEVELOPMENT BRIDGE, SUBJECT TO THE DEPLOYMENT GATES BELOW.**
**Scope:** Dependency, provenance, certificate trust and process-boundary review only. This change does not yet implement the verifier CLI, accept signed payloads, persist Apple transactions or enable Apple billing.

## Selected runtime

| Item | Reviewed value |
| --- | --- |
| Package | `app-store-server-library` |
| Locked release | `3.1.2` (1 June 2026) |
| Source | Apple [`app-store-server-library-python`](https://github.com/apple/app-store-server-library-python/tree/4eaa2241218f5c82c0a7f3e23e2fb3a7c2078092) |
| Source commit | `4eaa2241218f5c82c0a7f3e23e2fb3a7c2078092` (`v3.1.2`) |
| Ownership and publication | PyPI-verified owner Apple; trusted-publishing attestations identify Apple's `ci-release.yml` GitHub release workflow and the source commit above |
| Licence | MIT, confirmed by Apple's [`LICENSE.txt`](https://github.com/apple/app-store-server-library-python/blob/4eaa2241218f5c82c0a7f3e23e2fb3a7c2078092/LICENSE.txt) and package metadata |
| Runtime policy | Python 3.8 or newer and lower than Python 4; local lock/install proof used Python 3.12.6 |
| Upstream security support | Apple supports only the latest major version, including for security updates |

Apple documents the App Store Server Library as its open-source implementation for signed-data verification and publishes official Swift, Java, Node.js and Python libraries. Primary sources are [Apple's server-library guidance](https://developer.apple.com/documentation/AppStoreServerAPI/simplifying-your-implementation-by-using-the-app-store-server-library), [Apple's repository](https://github.com/apple/app-store-server-library-python) and the [PyPI 3.1.2 release](https://pypi.org/project/app-store-server-library/3.1.2/).

PyPI metadata declares `Requires-Python: >=3.7,<4`, while Apple's README states Python 3.8+ and the changelog records that Python 3.7 support was dropped in version 1.5.0. Fynla therefore adopts the stricter documented Python 3.8+ floor. The current development proof on Python 3.12.6 is inside that supported range; the deployment host must be checked independently.

The PyPI release has two trusted-published artifacts, both represented in the checked-in lock:

- wheel `app_store_server_library-3.1.2-py3-none-any.whl`: SHA-256 `8007b3dca89fd08a1123fae2ffdbe7bc72b99a7eabfb0a32e21b45a344159e62`;
- source distribution `app_store_server_library-3.1.2.tar.gz`: SHA-256 `0b9005c93298674c934dfa9bc164cd44bfc3f43a8d0dc3834eaf6f66c3572d45`.

## Hash-locked dependency set

`services/apple_store_bridge/requirements.in` pins only `app-store-server-library==3.1.2`. `requirements.lock` pins that release and every resolved runtime transitive with SHA-256 hashes:

| Locked package | Version |
| --- | --- |
| `app-store-server-library` | `3.1.2` |
| `asn1` | `3.2.0` |
| `attrs` | `26.1.0` |
| `cattrs` | `26.1.0` |
| `certifi` | `2026.6.17` |
| `cffi` | `2.1.0` |
| `charset-normalizer` | `3.4.9` |
| `cryptography` | `49.0.0` |
| `enum-compat` | `0.0.3` |
| `idna` | `3.18` |
| `pycparser` | `3.0` |
| `PyJWT` | `2.13.0` |
| `pyOpenSSL` | `26.3.0` |
| `requests` | `2.34.2` |
| `typing-extensions` | `4.16.0` |
| `urllib3` | `2.7.0` |

The optional `httpx` extra is not selected and is not in the runtime lock. On 18 July 2026, a clean `pip install --require-hashes -r services/apple_store_bridge/requirements.lock` completed successfully, `pip check` found no broken requirements, and the installed Apple package reported exactly `3.1.2`.

`pip-audit 2.10.1 -r services/apple_store_bridge/requirements.lock --disable-pip` reported **no known vulnerabilities** for the locked Python runtime. Audit tooling was installed only in the temporary review environment and is not part of the deployed lock.

`composer audit --locked --format=json` continues to report 11 advisories across six existing PHP packages: Guzzle, PSR-7, Laravel Framework, Symfony HTTP Foundation, Symfony Intl IDN polyfill and Symfony Routing. These are the same unrelated, pre-existing audit set documented in the rejected PHP review; Composer removed the rejected verifier and its four now-unused transitives without updating any existing package version. The high Laravel finding and the remaining medium/low findings remain a release concern outside this narrowly locked replacement and must be dispositioned before production deployment.

## OCSP and signed-data controls

Release 3.1.2 is the first selected release after Apple's explicit OCSP validity update. Apple's [3.1.2 changelog](https://github.com/apple/app-store-server-library-python/blob/4eaa2241218f5c82c0a7f3e23e2fb3a7c2078092/CHANGELOG.md) records validity checking with clock skew using current `cryptography` methods. Review of the exact pinned [`signed_data_verifier.py`](https://github.com/apple/app-store-server-library-python/blob/4eaa2241218f5c82c0a7f3e23e2fb3a7c2078092/appstoreserverlibrary/signed_data_verifier.py) confirms that online verification:

- selects the OCSP response signer by matching `ResponderID` by key hash or responder name;
- accepts the certificate issuer directly, or validates a delegated responder's chain and requires the `OCSP_SIGNING` extended-key usage;
- verifies the OCSP response signature with the selected responder certificate;
- matches the response serial number, issuer key hash and issuer name hash to a newly built request;
- accepts only `GOOD` status with non-null `thisUpdate` and `nextUpdate` values that bound the current time, allowing at most 60 seconds of clock skew; and
- maps request, operating-system and non-200 responder failures to a retryable verification status rather than treating them as valid.

The deployed verifier must enable online checks. Its verified-certificate cache is bounded to 32 entries and 15 minutes; that is Apple's reviewed library behaviour, not a Fynla cryptographic implementation. Fynla will not patch, reimplement or weaken the JWS, certificate-chain or OCSP logic.

The library contains explicit verification bypasses for `XCODE` and `LOCAL_TESTING`. Later bridge tasks must reject those environments before constructing the verifier and permit deployed `SANDBOX` or `PRODUCTION` only. Production must also supply the numeric Apple app ID, as required by the official verifier. After verification, Fynla must independently enforce its exact bundle ID, environment, product allowlist and expected account token.

## Certificate trust policy

The trust anchor remains the checked-in DER certificate `resources/certificates/apple/AppleRootCA-G3.cer`, obtained from [Apple's PKI repository](https://www.apple.com/certificateauthority/). Its pinned properties are:

- subject and issuer: `CN=Apple Root CA - G3, OU=Apple Certification Authority, O=Apple Inc., C=US`;
- critical basic constraint: `CA:TRUE`;
- validity: 30 April 2014 through 30 April 2039; and
- SHA-256 fingerprint: `63:34:3A:BF:B8:9A:6A:03:EB:B5:7E:9B:3F:5F:A7:BE:7C:4F:5C:75:6F:30:17:B3:A8:C4:88:C3:65:3E:91:79`.

The bridge must load this server-controlled file and pass its DER bytes as the trust anchor. It must not download roots during a request, accept a root supplied by the client or trust the JWS `x5c` chain by itself. Root replacement requires a separately reviewed download from Apple's PKI repository and a new exact fingerprint pin.

## Local-process and data boundary

Laravel remains the only public API, identity, persistence and entitlement authority. The Python bridge is a bounded local child process, not a public service.

Later implementation must start the configured Python executable and entry point as an argument array without a shell. Each signed value travels only inside a versioned JSON request on standard input: never in process arguments, environment values, logs, database raw columns or exception messages. The process receives a minimal server-controlled environment and a strict timeout.

The bridge may return only versioned, allowlisted decoded fields or stable Fynla failure codes on standard output. Diagnostics go to standard error as non-sensitive stable codes. It must never return or log the input JWS, `x5c` chain, decoded personal values or App Store private-key material. Laravel stores only the required decoded fields plus the SHA-256 evidence hash. Verification, process, timeout, malformed-output or online-check failure must fail closed and cannot grant or extend Premium.

App Store Server API credentials and the production numeric app ID remain environment-only. Any future private key is a restricted server-local file outside the public root; it is never committed, placed in process arguments or transported through Laravel logs.

## Update and deployment gates

For every Apple library update:

1. confirm the release in Apple's repository and the PyPI trusted-publishing provenance;
2. review the exact source diff, release notes, licence, supported Python range and JWS/OCSP changes;
3. update the direct pin, regenerate all transitive pins and hashes in a temporary virtual environment, and review the complete lock delta;
4. reproduce `pip install --require-hashes`, run `pip check` and audit the exact lock;
5. rerun Python verifier tests, PHP adapter/contract/billing tests and native StoreKit gates; and
6. do not remain on an unsupported major, because Apple supplies security updates only for the latest major version.

Before Apple billing is enabled on a development host, independently prove:

- the deployed Python executable is version 3.8 or newer and lower than 4, can create the isolated runtime and can import exact release `3.1.2` from the checked-in lock;
- PHP can start the configured Python executable and checked-in bridge entry point without a shell, pass requests on standard input, enforce timeout/concurrency limits and parse only the versioned response contract;
- the deployed process user can read the checked-in root certificate but cannot read unrelated secrets or write into application source;
- system time and certificate validation are correct; and
- outbound DNS/TLS and required Apple OCSP and App Store Server API connectivity work under the host firewall policy.

Those are development deployment gates only. No production host access, production credentials, `fynla.org` request or billing activation is authorised by this review. A failed gate blocks new Apple billing state changes while existing login, Revolut and canonical entitlement reads remain available.
