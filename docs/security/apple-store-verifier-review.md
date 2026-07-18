# Apple App Store server verifier review

**Reviewed:** 18 July 2026
**Decision:** Approved for use behind Fynla's Apple verifier adapter, subject to the deployment and update gates below.

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

Apple publishes official Swift, Java, Node.js and Python libraries, but no official PHP library. This package is therefore acceptable only behind Fynla's own adapter so it can be replaced without leaking third-party types through the billing domain.

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

## Certificate trust and revocation policy

The trust anchor is Apple's published `AppleRootCA-G3.cer`, downloaded over HTTPS from the [Apple PKI repository](https://www.apple.com/certificateauthority/) and checked in at `resources/certificates/apple/AppleRootCA-G3.cer`. OpenSSL reports:

- subject and issuer: `CN=Apple Root CA - G3, OU=Apple Certification Authority, O=Apple Inc., C=US`;
- validity: 30 April 2014 through 30 April 2039;
- critical basic constraint: `CA:TRUE`; and
- SHA-256 fingerprint: `63:34:3A:BF:B8:9A:6A:03:EB:B5:7E:9B:3F:5F:A7:BE:7C:4F:5C:75:6F:30:17:B3:A8:C4:88:C3:65:3E:91:79`.

The future Fynla adapter must read this checked-in DER certificate and pass its bytes as the trusted-root list. It must not download roots during a request and must not treat the JWS `x5c` root as trusted. Apple's [signed-data verification guidance](https://developer.apple.com/videos/play/wwdc2023/10143/) requires constructing the chain back to a previously obtained Apple root and checking certificate status with OCSP.

For newly received transactions and notifications, configure `SignedDataVerifier` with online checks enabled. The reviewed implementation then validates the ES256 algorithm, the three-certificate header shape, leaf and intermediate signatures and validity, Apple receipt/WWDR OIDs, the chain against the supplied root, and signed OCSP responses. It caches a successfully checked leaf/intermediate public key for 15 minutes. Disabling online checks is not the normal runtime policy and requires an explicit historical-verification use case and separate review.

The library deliberately skips signature verification for `XCODE` and `LOCAL_TESTING`. The future adapter must select the expected environment from trusted server configuration, never from request data, and must never permit those modes on a deployed endpoint.

## Security advisories and maintenance policy

`composer audit --locked --format=json` on 18 July 2026 reported 11 advisories across six already-locked packages. Every affected package and version was unchanged from the pre-task lock; none targets the verifier or the four newly locked transitive packages. The pre-existing set includes one high Laravel advisory, medium Laravel/Symfony advisories, two medium Guzzle advisories, three medium PSR-7 advisories, and one low Symfony polyfill advisory.

The Guzzle and PSR-7 findings matter to the future OCSP path even though this task adds no runtime adapter. Their reviewed OCSP usage does not use cookies, and the responder URI comes from an Apple-signed certificate after chain validation, which limits the currently reported attack paths. Nevertheless, update the existing Guzzle/PSR-7 locks to patched releases before enabling Apple billing, and clear or explicitly disposition the wider project audit before deployment.

The package maintainer supports only the latest major version, including for security fixes. Fynla's update policy is therefore:

1. run `composer audit --locked` in dependency review and release checks;
2. monitor Packagist/GitHub releases and Apple's official server-library changes;
3. apply compatible 2.x security/bugfix releases promptly with focused verification;
4. review each new major before adoption, because staying on an old major forfeits upstream security support; and
5. re-download roots only from Apple's PKI repository in a reviewed change, then verify and pin the new fingerprint.

## Review boundary

This change locks the dependency and trust anchor only. It does not implement a verifier adapter, accept signed payloads, call Apple, grant entitlements, or add billing behaviour.
