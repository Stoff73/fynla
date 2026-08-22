# 11 - Discrepancies

This document lists differences between existing documentation (CLAUDE.md, README) and the codebase audit. Each entry states the documented claim, the actual finding, and the severity.

## Component and Model Counts

| Metric | CLAUDE.md Claims | Audit Found | Difference |
|--------|-----------------|-------------|------------|
| Vue Components | 372 | 313 | Overstated by 59 |
| Models | 68 | 49 named models | Overstated by 19 |
| PHP Services | 141 | 141 | Matches |
| Controllers | 66 | 66 | Matches |
| Vuex Stores | 21 | 21 | Matches |
| Agents | 8 | 8 | Matches |

**Notes on counts:**
- **Vue Components**: The audit counted .vue files across all directories. The original 372 figure likely included files that have since been deleted or were outside standard component directories.
- **Models**: The 68 figure may include pivot tables, config models, or models in subdirectories the audit did not surface. The 49 figure covers core named models plus supporting models (TaxConfiguration, ActuarialLifeTable, etc.).

**Severity**: Low. The counts affect documentation accuracy, not application behaviour. Update CLAUDE.md to reflect current totals.

## Module Count

**CLAUDE.md states**: "covering five modules: Protection, Savings, Investment, Retirement, and Estate Planning"

**Audit found**: Seven functional modules:
1. Protection
2. Savings
3. Investment
4. Retirement
5. Estate Planning
6. Goals & Life Events (separate agent, controller, services, Vuex store, routes)
7. Coordination (CoordinatingAgent with ConflictResolver, PriorityRanker, HolisticPlanner, CashFlowCoordinator)

The Goals module has its own GoalsAgent, GoalsController, 4 dedicated services, a full Vuex store, and 22 Vue components. The Coordination module has its own CoordinatingAgent and 4 services. Both qualify as full modules.

**Severity**: Low. The "five modules" description reflects the original design. Goals and Coordination were added later.

## AuthServiceProvider

**CLAUDE.md states**: No mention of authorization policies.

**Audit found**: `AuthServiceProvider` is empty -- no policies or gates defined. Authorization relies on controller-level ownership checks (`$request->user()->id === $record->user_id`) and middleware (IsAdmin, HasRole, HasPermission).

**Severity**: Informational. The empty provider is intentional; authorization works through other mechanisms.

## Ownership Enum Values

**CLAUDE.md states**: Canonical ownership values are `individual`, `joint`, `tenants_in_common`, `trust`.

**Audit found**: Migrations and models use these values consistently. The Property model also recognises `joint_tenancy` as a display-level sub-type of `joint`. Form validation accepts `individual`, `joint`, `tenants_in_common`, `trust` as documented.

**Severity**: Low. The code matches the documented enums. The `joint_tenancy` distinction exists at the display level only.

## Mail Configuration

**CLAUDE.md states**: No mention of mail configuration.

**Audit found**: Default mailer is `log` (emails write to log file). Production requires MAIL_MAILER=smtp with SMTP credentials. The server generates email verification codes; delivery depends on environment configuration.

**Severity**: Informational. Standard Laravel behaviour.

## Queue Configuration

**Audit found**: Queue connection is `sync`. All jobs execute in the request cycle. Monte Carlo simulations run synchronously.

**Severity**: Informational. No documentation claims otherwise, but worth noting for performance.

## Test Count

**CLAUDE.md states**: No specific test count.

**Audit found**: 103 PHP test files using Pest framework. Coverage spans agents, services (estate, savings, investment, retirement, coordination, auth, GDPR, audit), models, and middleware. Frontend tests are absent beyond the test framework configuration (Vitest + Playwright installed as dev dependencies).

**Severity**: Informational.

## Summary

The discrepancies are minor -- primarily stale counts in CLAUDE.md. The audit found no functional mismatches between documented behaviour and actual code. The architecture, patterns, and conventions described in CLAUDE.md accurately reflect the codebase.

### Recommended Updates to CLAUDE.md

1. Update Vue Components count from 372 to 313 (or re-count to verify)
2. Update Models count from 68 to reflect actual count
3. Update "five modules" to acknowledge Goals and Coordination as distinct modules
