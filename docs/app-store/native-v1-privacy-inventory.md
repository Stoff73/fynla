# Native v1 privacy inventory

**Scope:** `org.fynla.app` and `org.fynla.app.dev`, using the existing Laravel backend. This inventory covers data the native app sends, receives or makes available through Fynla services. It does not change the permanent `/m` web application.

**Release rule:** The checked-in manifest, App Store Connect App Privacy answers and public privacy policy must describe the same behaviour. Any change to native data flow, provider, SDK or required-reason API reopens this inventory.

## Manifest decision

- Tracking: **No**. The native target has no advertising, tracking, attribution or cross-company profiling code.
- Tracking domains: none.
- Linkage: every server-collected category below is linked to the signed-in Fynla account. No category is used for tracking.
- Native dependencies: Apple system frameworks only. The Xcode project has no Swift package products, embedded third-party frameworks or analytics/advertising SDK.
- Payment-card and bank-account details used to pay for Fynla are not collected by the app or Fynla backend. Apple and Revolut process payment instruments; Fynla retains provider transaction and entitlement evidence.
- Crash and performance telemetry are not collected by a native SDK. A user can explicitly submit a bug report with the displayed diagnostic context, which is declared as Customer Support and Other Diagnostic Data.

## App Privacy data mapping

| Apple data type | Native/server examples | Purpose | Source evidence | Retention/deletion |
|---|---|---|---|---|
| Name | Registration and account name; spouse/dependant names | App Functionality | `RegistrationModel`, authenticated account and household planning APIs | Account/financial retention policy; included in export and erasure workflow |
| Email Address | Registration, sign-in, verification, account notifications | App Functionality | Native authentication and settings clients | Account retention; verification/audit evidence follows documented legal windows |
| Phone Number | Account contact data available to the shared financial profile | App Functionality | Shared Laravel user/profile data used by native-backed journeys | Account/financial retention; export/erasure applies |
| Physical Address | Household/profile and property-planning address data | App Functionality | Shared Laravel profile and property data | Account/financial retention; export/erasure applies |
| Health | Health/smoking status and protection-policy conditions | App Functionality; Product Personalization | Protection data and Fyn financial context | Special-category consent and account/financial retention controls apply |
| Other Financial Info | Income, expenditure, assets, debts, savings, investments, pensions, protection, estate, goals and tax plan | App Functionality; Product Personalization | Native module clients and Fyn context/write tools | Financial-data retention; export/erasure applies subject to regulatory retention |
| Other User Content | Fyn messages, goals, support descriptions and optional financial documents handled by the shared service | App Functionality; Product Personalization | Fyn conversation, goal and export APIs | Conversation/document lifecycle plus account retention; export/erasure applies |
| Customer Support | User-authored bug report description/category/severity | App Functionality | `BugReportSubmission` | Support/audit retention; user sees the submitted diagnostic context first |
| User ID | Laravel user ID, conversation references and server-issued App Account Token association | App Functionality | Authentication, native session and StoreKit APIs | Account/audit retention; provider audit evidence survives only where legally required |
| Device ID | Native session UUID and APNs device token | App Functionality | Native-session and push registration APIs | Revoked/removed at sign-out or deletion where possible; security audit windows apply |
| Purchase History | Product, transaction/original transaction identifiers, entitlement status and renewal/revocation facts | App Functionality | StoreKit acknowledgement, Apple Notification V2, reconciliation and Revolut entitlement projection | Payment/provider audit evidence follows the documented six/seven-year windows |
| Other Usage Data | Login/session timestamps, route/request activity and consent/export/deletion events | App Functionality | Native session, security and GDPR audit paths | Security/GDPR audit windows in the public policy and retention policy |
| Other Diagnostic Data | App version/build, OS, environment, route, request reference and native-session reference in an explicit bug report | App Functionality | `BugReportMetadata` and review screen | Submitted only after affirmative review; support/audit retention applies |
| Other Data Types | Date of birth, gender, National Insurance number, marital/family relationship and consent state | App Functionality; Product Personalization | Shared identity/household profile and consent APIs | Account/financial/GDPR retention; export/erasure applies subject to legal retention |

All rows are declared `Linked = true` and `Tracking = false`. App Store Connect should use this table as the answer source, not infer answers from screenshots or the old Capacitor binary.

## Required-reason APIs

| Category | Reason | Exact use | Data handling constraint |
|---|---|---|---|
| User Defaults | `CA92.1` | App-only Face ID preference and one-time legacy Capacitor cleanup completion marker | Values remain in the app container and are not sent off-device |
| File Timestamp | `C617.1` | Reads `contentModificationDateKey` for Fynla export files in the app-owned temporary directory so files older than 24 hours can be deleted | Timestamp stays on-device and is used only for app-container cleanup |

The final Swift source contains no direct disk-space, system-boot-time or active-keyboard required-reason API. Re-run the source/archive inventory if that changes.

## Provider/data-flow reconciliation

| Recipient or processor | Data involved | Native purpose | Public-policy status |
|---|---|---|---|
| Fynla Laravel/SiteGround | All account, plan, security, consent and support data above | Core service and security | Present, but native device/push details should be made explicit during legal review |
| Anthropic | User message and selected Fyn financial context | Generate Fyn responses | Present; legal/product review must confirm whether health fields can enter context because the policy separately says health data is not shared with third parties |
| Apple App Store / StoreKit | Product, transaction/original transaction ID, app-account token, subscription status | Purchase, restore, reconciliation and entitlement | **Gap:** payment processor is generic; Apple must be named and provider metadata/retention described |
| Apple Push Notification service | APNs token and generic notification payload | Deliver privacy-safe notifications | **Gap:** APNs processing is not explicit in the current policy |
| Revolut | Existing web subscription and provider transaction evidence | Shared canonical entitlement and web billing management | **Gap:** payment processor is generic; Revolut must be named and provider metadata/retention described |

## Pre-submission reconciliation gates

- [x] Manifest declares no tracking and no tracking domains.
- [x] Manifest declares every source-proven required-reason API: `CA92.1` and `C617.1`.
- [x] Native target contains no third-party runtime dependency or analytics/advertising SDK.
- [ ] Generate Xcode's privacy report from the exact signed release archive and reconcile it against this file.
- [ ] Enter App Store Connect App Privacy answers from the table above and capture review evidence.
- [ ] Obtain CSJ/legal approval for public-policy wording that names Apple StoreKit/App Store, APNs and Revolut and resolves the health-context statement.
- [ ] Reconcile final public-policy wording line by line against this inventory before submission.

The public privacy policy is intentionally not edited in this implementation commit because the plan requires CSJ/legal review of policy wording. The unresolved items block App Store submission, not local native development or the existing dev backend.

## Primary Apple references

- [Privacy manifest files](https://developer.apple.com/documentation/bundleresources/privacy-manifest-files)
- [Adding a privacy manifest](https://developer.apple.com/documentation/bundleresources/adding-a-privacy-manifest-to-your-app-or-third-party-sdk)
- [Describing data use](https://developer.apple.com/documentation/bundleresources/describing-data-use-in-privacy-manifests)
- [Required-reason API categories and approved reasons](https://developer.apple.com/documentation/bundleresources/app-privacy-configuration/nsprivacyaccessedapitypes/nsprivacyaccessedapitype)
