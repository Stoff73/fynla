# Apple App Store server verifier review

**Reviewed:** 18 July 2026
**Decision:** **REJECTED / BLOCKED.** The locked PHP package must not be used to enable Apple billing.

**Approved replacement:** The rejected package has now been removed in favour of Apple's official, hash-locked Python runtime. See [`apple-python-verifier-review.md`](apple-python-verifier-review.md). The rejection evidence below remains unchanged.

## Selected package

| Item | Reviewed value |
| --- | --- |
| Package | `hoels/app-store-server-library-php` |
| Constraint | `^2.0` |
| Locked release | `2.0.0` (1 March 2026) |
| Source commit | `a47a74bbd298ee372324ce75108e06e824ec8ad4` |
| Licence | MIT |
| Maintenance | Third-party; not maintained or endorsed by Apple |
| Upstream relationship | The maintainer describes it as a close PHP port of Apple's official Python implementation, with Swift influences |

Primary sources: the [package repository and support policy](https://github.com/hoels/app-store-server-library-php), the [2.0.0 package metadata](https://packagist.org/packages/hoels/app-store-server-library-php#2.0.0), and Apple's [official server-library guidance](https://developer.apple.com/documentation/AppStoreServerAPI/simplifying-your-implementation-by-using-the-app-store-server-library).

Apple publishes official Swift, Java, Node.js and Python libraries, but no official PHP library. The package remains locked only to make this review reproducible. Task 1 and Package 4 cannot proceed to billing enablement until either an upstream fix is independently audited or CSJ approves moving verification to an official-library runtime. This review does not select an alternative runtime.

## Runtime and locked dependency review

Release 2.0.0 requires PHP 8.1 or newer, `ext-json`, `ext-mbstring` and `ext-openssl`. It also requires `firebase/php-jwt:^7.0`, `guzzlehttp/guzzle:^7.8`, `hoels/ocsp-php:^0.1` and `phpseclib/phpseclib:^3.0`.

Composer added and locked only these previously absent packages:

- `hoels/app-store-server-library-php` 2.0.0;
- `firebase/php-jwt` 7.1.0;
- `hoels/ocsp-php` 0.1.0;
- `phpseclib/phpseclib` 3.0.55; and
- `paragonie/random_compat` 9.99.100.

The existing Guzzle 7.10.0 and PSR-7 2.9.0 locks did not change. Local CLI verification found OpenSSL, JSON and mbstring loaded. `composer check-platform-reqs --lock --no-dev` confirmed the three verifier extensions, but the whole-project check is red because the workstation runs PHP 8.5.2 while the existing `sabberworm/php-css-parser` lock supports PHP only through 8.4. This project resolves dependencies for PHP 8.3.30 through Composer's platform setting.

Before dev deployment, verify the deployed SiteGround PHP runtime is compatible and explicitly run:

```text
php -r 'foreach (["openssl", "json", "mbstring"] as $extension) { echo $extension.": ".(extension_loaded($extension) ? "loaded" : "MISSING").PHP_EOL; }'
composer check-platform-reqs --no-dev
```

The SiteGround extension check is pending because this review neither accesses production nor imports deployment secrets.

## Certificate trust

The trust anchor is Apple's published `AppleRootCA-G3.cer`, downloaded over HTTPS from the [Apple PKI repository](https://www.apple.com/certificateauthority/) and checked in at `resources/certificates/apple/AppleRootCA-G3.cer`. OpenSSL reports:

- subject and issuer: `CN=Apple Root CA - G3, OU=Apple Certification Authority, O=Apple Inc., C=US`;
- validity: 30 April 2014 through 30 April 2039;
- critical basic constraint: `CA:TRUE`; and
- SHA-256 fingerprint: `63:34:3A:BF:B8:9A:6A:03:EB:B5:7E:9B:3F:5F:A7:BE:7C:4F:5C:75:6F:30:17:B3:A8:C4:88:C3:65:3E:91:79`.

Any future approved verifier must read this checked-in DER certificate and use it as the trust anchor. It must not download roots during a request and must not treat the JWS `x5c` root as trusted. Apple's [signed-data verification guidance](https://developer.apple.com/videos/play/wwdc2023/10143/) requires constructing the chain back to a previously obtained Apple root and checking certificate status with OCSP.

## Blocking OCSP defects

Release 2.0.0's online checks are not sufficient for a billing security boundary. Its `ChainVerifier::checkOcspStatus()` validates the certificate ID, response signature and a signing-certificate chain, but it does not validate the `thisUpdate` or `nextUpdate` fields. A previously signed `good` response can therefore remain acceptable after its freshness interval and be replayed after the certificate is revoked. This is an OCSP replay risk, not merely an availability or hardening concern.

The implementation also accepts a delegated signing certificate after general chain-signature validation without checking that it carries the `id-kp-OCSPSigning` extended-key-usage purpose. It does not match the response's `ResponderID` (`byName` or `byKey`) to the certificate whose key verifies the response. These omissions mean the code does not establish that the selected delegated signer is the responder authorised for this OCSP response.

[RFC 6960 section 4.2.2.1](https://www.rfc-editor.org/rfc/rfc6960.html#section-4.2.2.1) defines the `thisUpdate`/`nextUpdate` validity interval and says future `thisUpdate` or expired `nextUpdate` responses should be considered unreliable. [Section 4.2.2.2](https://www.rfc-editor.org/rfc/rfc6960.html#section-4.2.2.2) requires delegated responders to be issued by the relevant CA with `id-kp-OCSPSigning`, and requires relying applications to enforce that authorisation.

Apple's official Python verifier at commit [`200e9ac5e14dd5971c451de1e8b6c26e9ae8907e`](https://github.com/apple/app-store-server-library-python/blob/200e9ac5e14dd5971c451de1e8b6c26e9ae8907e/appstoreserverlibrary/signed_data_verifier.py#L241-L342) performs the missing controls: it selects the signing certificate by the response's responder key hash or responder name, enforces `OCSP_SIGNING` EKU for a delegated signer, and requires `this_update` and `next_update` to bound the current time with limited clock skew. The PHP package's claim to mirror Apple's implementation is therefore incomplete at this security-critical boundary.

The package is rejected for billing use. Do not compensate with a custom OCSP or JWS verifier. Resolution requires an audited upstream fix or a CSJ-approved official-library runtime, followed by a new security review.

## Transaction identity controls

The PHP `SignedDataVerifier::verifyAndDecodeSignedTransaction()` validates the signature chain and environment but does not compare the decoded transaction's `bundleId` with the configured bundle. Apple's [official Python method](https://github.com/apple/app-store-server-library-python/blob/200e9ac5e14dd5971c451de1e8b6c26e9ae8907e/appstoreserverlibrary/signed_data_verifier.py#L64-L77) performs that bundle check.

If a verifier is approved later, Fynla's adapter must independently enforce all entitlement identity fields after cryptographic verification: exact bundle ID, expected environment, a server-owned product allowlist, and the expected account token (`appAccountToken`). None may be accepted from untrusted request configuration. The library also deliberately skips signature verification for `XCODE` and `LOCAL_TESTING`; those modes must never be permitted on a deployed endpoint.

## Security advisories and maintenance policy

`composer audit --locked --format=json` on 18 July 2026 reported 11 advisories across six already-locked packages. Every affected package and version was unchanged from the pre-task lock; none targets the verifier or the four newly locked transitive packages. The pre-existing set includes one high Laravel advisory, medium Laravel/Symfony advisories, two medium Guzzle advisories, three medium PSR-7 advisories, and one low Symfony polyfill advisory.

The Guzzle and PSR-7 findings would matter to any future PHP OCSP path even though this task adds no runtime adapter. Their reviewed OCSP usage does not use cookies, and the responder URI comes from an Apple-signed certificate after chain validation, which limits the currently reported attack paths. Nevertheless, update the existing Guzzle/PSR-7 locks to patched releases before any reconsideration of PHP verification, and clear or explicitly disposition the wider project audit before deployment.

The package maintainer supports only the latest major version, including for security fixes. Fynla's update policy is therefore:

1. run `composer audit --locked` in dependency review and release checks;
2. monitor Packagist/GitHub releases and Apple's official server-library changes;
3. do not adopt an upstream release until the OCSP freshness, responder-ID and delegated-signer controls are demonstrated by tests and independently audited;
4. review each new major before adoption, because staying on an old major forfeits upstream security support; and
5. re-download roots only from Apple's PKI repository in a reviewed change, then verify and pin the new fingerprint.

## Review boundary

This change locks the rejected dependency and trust anchor for a reproducible audit only. It does not implement a verifier adapter, accept signed payloads, call Apple, grant entitlements, or add billing behaviour. Package 4 remains blocked pending an architecture decision.
