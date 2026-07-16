# Client Parity Ledger

This ledger is the release evidence record for desktop web, the `/m` mobile-web pathway and the native SwiftUI client. It records development and csjones staging evidence only. Production verification is deferred to the later batch production promotion and is not part of the current native implementation programme.

## Status vocabulary

- `required`: release-blocking evidence is still required on this surface.
- `not-landed`: the planned native slice has not landed yet.
- `not-applicable`: the capability does not apply to this surface and its platform boundary is documented.
- `green`: automated and manual evidence are both recorded in this row.

A row must not use `green` while either evidence cell is blank. User journeys require browser evidence for desktop and `/m`, and simulator or physical-device evidence for native where the package plan requires it.

## Capability matrix

| Capability | Desktop | `/m` | Native | Native package | Backend owner | Automated evidence | Manual evidence | Last verified | Approving person |
|---|---|---|---|---|---|---|---|---|---|
| Register and verify | required | required | not-landed | Package 3 | AuthController |  |  |  |  |
| Login, verification and multi-factor authentication | required | required | not-landed | Package 3 | AuthController/MFAController |  |  |  |  |
| Free/Premium entitlement | required | required | not-landed | Package 4 | TierResolver/entitlement resolver |  |  |  |  |
| Dashboard and gamification | required | required | not-landed | Package 5 | MobileDashboardAggregator |  |  |  |  |
| Fyn onboarding/advice/write handoff | required | required | not-landed | Package 5 | AiChatController |  |  |  |  |
| Income/expenditure/net worth | required | required | not-landed | Package 6 Wave A | existing module APIs |  |  |  |  |
| Savings/investment | required | required | not-landed | Package 6 Wave B | existing module APIs |  |  |  |  |
| Retirement/protection | required | required | not-landed | Package 6 Wave C | existing module APIs |  |  |  |  |
| Estate/goals | required | required | not-landed | Package 6 Wave D | existing module APIs |  |  |  |  |
| Tax Strategy/Holistic Plan | required | required | not-landed | Package 6 Wave E | existing plan APIs |  |  |  |  |
| Face ID | not-applicable | not-applicable | not-landed | Package 3 | native session service |  |  |  |  |
| StoreKit purchase | not-applicable | not-applicable | not-landed | Package 4 | Apple billing adapter |  |  |  |  |
| Account deletion outcome | required | required | not-landed | Package 7 | GDPRController |  |  |  |  |

## Current Package 1 handoff

- Package: iOS Package 1, Economic Contract and API Readiness
- Commit/PR: dev `aaf27c961d37c6d1897904bdcb29247d718638f8`; Package 1 PR pending
- Backend tests: freemium remediation full suite green before dev deployment; Package 1 Task 2 architecture test 1 passed with 72 assertions; Task 3 auth and entitlement suite 37 passed with 177 assertions; Task 4 Mobile, AI and client contracts 487 passed with 1851 assertions and 3 intentional capture-only skips
- Mobile decoding tests: `tests/frontend/mobile/Dashboard.test.js` 3 passed, including unknown additive response fields
- Swift tests: not applicable until Package 2
- Desktop browser evidence: actual Google Chrome on csjones dev `aaf27c96`; `/fynla/pricing` exposes only Free/Premium with no obsolete copy, and the seeded `young_saver` preview reached `/fynla/dashboard`; screenshot `codex/evidence/ios-package1/csjones-pricing-chrome.png`
- `/m` browser evidence: actual Google Chrome at 390×844 on csjones dev `aaf27c96`; seeded `young_saver` preview exercised the authenticated token bridge, dashboard, Fyn surface and Savings detail with zero application-console errors; screenshot `codex/evidence/ios-package1/csjones-mobile-savings-chrome.png`
- Simulator evidence: not applicable until Package 2
- Physical-device evidence: not applicable until Package 3
- Known exclusions: production and App Store release work are deferred; no production checks are part of this ledger entry
- CSJ approval: pending Package 1 gate
