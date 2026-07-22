# Freemium Remediation Acceptance Evidence

Date: 2026-07-16

Branch: `codex/freemium-task14-acceptance`

Implementation commit: `243c70c`

Stack base: `codex/freemium-task13-canonical-docs` (`fb71427`)

## Current gate status

Local regression and browser acceptance are green. Remote migration, csjones acceptance, vault updates and Swift readiness remain blocked until the exact Task 12 pre-migration audits have run against the deployed prerequisite code and returned zero unsafe rows on the target environment. No remote migration or deployment was performed from this branch.

The settled dev-server fact remains unchanged: there are no current paid subscribers in the dev database. That question was not reopened during this task.

## Verification results

| Check | Result |
|---|---|
| Static obsolete-contract sweep | No reachable client/server contract match; retained matches classified below |
| Focused backend suites from Task 14 Step 2 | Green, run serially |
| Frontend unit suite | 54 files, 726 tests passed |
| Production web build | Passed with Node.js 20.19.5 |
| Consolidated Playwright acceptance | 24 passed, 5 intentional project skips, 0 failed |
| Focused live Free-cap browser scenario | 1 passed; all six records remained at the configured caps |
| Complete Pest suite | 6,020 passed, 30 skipped, 119,982 assertions; 1,618.74 seconds |
| PHP formatting and syntax | Pint clean after one braces-only correction; changed PHP files parse cleanly |
| Diff hygiene | `git diff --check` passed |
| Database restoration | `php artisan db:seed` completed after database-mutating verification |

The complete Pest run exposed and then verified fixes for payment-settlement deadlock retry, missing tier configuration in isolated feature tests, canonical Store-boundary use in end-to-end fixtures, a pre-seed teaser-gate degradation path, a brittle tax-configuration row count and a noisy single-sample performance assertion.

## Local journey coverage

- Free, Premium monthly and Premium yearly registration intents were exercised through the real registration UI and verified against the returned checkout intent and canonical checkout route.
- Free remained writable below its limits. Savings, investments, pensions, property, Goals and Life Events were driven to their exact Store-boundary caps in the browser; the follow-up database payload remained `2, 2, 2, 1, 2, 1`.
- Mortgage cap enforcement remained covered at the service/API boundary with its approved cap of 10.
- Free upgrade entry points exposed one Premium choice. Premium users and payment-disabled responses exposed no upgrade action or empty pricing modal.
- Pending checkout remained Free. Active, cancelled, past-due, grace and terminal paid presentation were exercised without trial state.
- Cancellation retained Premium access to the paid-through date. Terminal paid state presented read-only recovery and retention behaviour.
- Desktop and `/m` upgrade bridges were exercised. The `/m` Estate action transferred the mobile authentication token and opened the canonical subscription options outside the iframe.
- Premium-only API denial/teaser shaping, Fyn billing status and invoice tools, verified payment settlement, and duplicate-provider callback safety remained green in the focused backend suites and complete Pest run.
- Local browser acceptance reaches both Premium checkout cycles. A real Revolut sandbox confirmation is deliberately deferred to the gated csjones pass; local backend tests cover the verified-provider settlement transition without creating an external charge.

## Static sweep retained matches

The Task 14 sweep found no active trial copy, trial endpoint, Stripe claim, retired-tier selection or obsolete subscription route in reachable application contracts. The following retained references are deliberate:

| Paths | Reason retained |
|---|---|
| `tests/E2E/public/freemium-copy-contract.spec.js`, `tests/E2E/public/pricing-contract.spec.js`, `tests/E2E/freemium/subscription-states.spec.js`, `tests/Feature/Public/FreemiumCopyContractTest.php`, `tests/Feature/Public/PricingContractTest.php` | Negative assertions that fail if obsolete trial, provider or retired-tier presentation becomes reachable. |
| `tests/Feature/Payment/TierKeyAcceptanceTest.php`, `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/Tiers/UsersTierColumnTest.php`, `tests/Feature/Tiers/TwoTierIdentityTest.php`, `tests/Feature/Tiers/TierConfigurationsTableTest.php`, `tests/Unit/Services/Payment/SubscriptionStatusServiceTest.php` | Rejection/normalisation assertions for retired input and output keys. |
| `tests/Feature/Console/AuditTierCollapseTest.php`, `tests/Feature/Payment/PremiumPaymentSafetyTest.php`, `tests/Feature/Tiers/TierCollapseMigrationTest.php` | Historical-row fixtures proving audit, settlement safety and migration behaviour for retired tier values. |
| `tests/Feature/Payment/TrialExtensionDiscountDeactivationTest.php`, `tests/Feature/Admin/DiscountCodeAdminTest.php`, `tests/Unit/Services/Payment/DiscountCodeServiceTest.php` | Legacy discount fixtures proving trial-extension discounts are inactive and cannot be used. |
| `tests/Feature/Payment/SubscriptionStatusTest.php`, `tests/Feature/AI/BillingToolsTest.php`, `tests/Unit/Services/Payment/SubscriptionStatusServiceTest.php` | Negative contract assertions proving trial keys and the retired trial endpoint are absent. |
| `tests/Feature/Database/TrialSchemaRemovalTest.php` | Up/down and unsafe-row guard coverage for the Task 12 destructive schema migration. |
| `database/migrations/2026_02_12_100001_create_subscriptions_table.php`, `2026_02_12_100003_add_plan_fields_to_users_table.php`, `2026_02_24_100002_add_revolut_ids_to_users_and_subscriptions.php`, `2026_04_08_100001_create_discount_codes_table.php`, `2026_04_14_122656_add_subscriptions_indexes.php`, `2026_04_14_123409_add_lifecycle_welcome_to_discount_codes_type_enum.php`, `2026_05_07_000001_add_deletion_tracking_to_users_table.php`, `2026_05_17_100000_create_tier_configurations_table.php`, `2026_05_17_100001_add_tier_to_users_table.php`, `2026_05_17_100002_add_tier_keys_to_subscriptions_plan_enum.php` | Immutable applied migration history. |
| `database/migrations/2026_07_15_000000_collapse_tier_identity_to_free_premium.php`, `2026_07_15_000003_add_pending_subscription_status.php`, `2026_07_15_000004_deactivate_trial_extension_discount_codes.php`, `2026_07_15_000005_remove_trial_subscription_schema.php` | Explicit forward migration, preflight and rollback compatibility paths. |
| `database/schema/mysql-schema.sql` | Current schema has no trial columns or `trialing` subscription status. Retired plan enum values and the inactive legacy discount type remain storage-compatible for historical records; active APIs and tier configuration expose only Free and Premium. |
| `app/Services/Tiers/TierCollapsePreflight.php`, `app/Services/Stores/TierConfigurationStore.php` | Named retired-tier sets used only to audit, reject or clean historical values. |
| `tests/Unit/Services/Goals/GoalAssignmentServiceSDLTTest.php`, `tests/Unit/Services/Retirement/SalarySacrificeNicCapTest.php` | “Tier 1” refers to a historical test/audit classification, not a selectable subscription tier. |

## Plan and debt audit

The changed-file review found no unresolved in-scope acceptance issue after the green canonical suite. The requested `tech-debt-session` skill was not installed in this environment, so its named automation could not run; the same changed-file scope was reviewed manually and all in-scope findings were either fixed in `243c70c` or covered by the verification above.

## Remaining gated work

1. Deploy all prerequisite freemium PRs through `dev` to csjones.
2. Run the exact Task 12 read-only pre-migration audits on the target database and retain their zero-row output.
3. Run the approved Task 12 migration and required reseed only after that audit is safe.
4. Complete the real Revolut sandbox monthly and annual confirmations, deployed desktop and `/m` acceptance, database/network evidence and screenshots on csjones.
5. Update the vault through `vault-sync` when available and after deployed code is verified.
6. Mark Swift readiness only when every Task 14 readiness condition is demonstrated on csjones.

Until those steps are complete, this evidence is local acceptance for a draft PR, not a release or Swift-readiness sign-off.
