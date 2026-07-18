# Official Apple signed-data fixtures

These two public test assets are copied unchanged from Apple's MIT-licensed
`apple/app-store-server-library-python` repository for verification-only tests.

- Repository: `https://github.com/apple/app-store-server-library-python`
- Library/tag: `app-store-server-library` `3.1.2` / `v3.1.2`
- Audited commit: `4eaa2241218f5c82c0a7f3e23e2fb3a7c2078092`
- License: MIT (`LICENSE.txt` in the upstream repository)

| Local file | Exact upstream path | SHA-256 |
| --- | --- | --- |
| `testCA.der` | `tests/resources/certs/testCA.der` | `48aa70550eab2cd71d51dced44e88f9143b6bc0e1a6f430c19ba9a7cf36654e6` |
| `transactionInfo` | `tests/resources/mock_signed_data/transactionInfo` | `6f3027507d63abc7736c14642c7aa2ccc978510cba6f0d09244e657293ea7ce6` |

The upstream signing key (`tests/resources/certs/testSigningKey.p8`) is
deliberately not copied. No private key or Fynla secret is present here.

Apple's public test CA intentionally lacks the authority identifiers required
by strict production verification. The integration test therefore mirrors
Apple's own fixture setup by disabling online and strict certificate checks on
a verifier created only inside the test. Production `AppleSignedDataService`
does neither: it always enables online checks and retains the official
library's strict certificate-chain checks.

## Upstream license notice

Copyright 2023 Apple Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
