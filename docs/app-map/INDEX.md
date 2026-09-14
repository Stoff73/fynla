# Fynla application map — index

Section-by-section maps of what the code actually does. Produced with the `app-map` skill (`.claude/skills/app-map/`). Every claim in a map cites a file and line read, a test run, or a Playwright interaction performed in that run; anything else is marked "I COULD NOT VERIFY".

Issues found while mapping are raised, not fixed, in `<Month>/<Month><D>Updates/mappingBugs<YYYY-MM-DD>.md` as `MB-NN` entries.

| # | Section | File | Status | Commit | Date | Diagrams | Issues |
|---|---|---|---|---|---|---|---|
| 00 | Overview (baseline) | `00-overview.md` | overview | `28194e804` | 2026-09-14 | `map-overview-whole-app-flow`, `map-overview-module-graph`, `map-overview-cross-module-dependencies` | MB-01 to MB-13 (`September/September14Updates/mappingBugs2026-09-14.md`) |
| 01 | Auth, registration, sessions | `01-auth-registration-sessions.md` | mapped | `28194e804` | 2026-09-14 | `map-auth-registration-flow`, `map-auth-login-flow`, `map-auth-recovery-and-lifecycle`, `map-auth-native-session-lifecycle` | MB-18 to MB-22 |
| 02 | Onboarding and SaveTax campaign | | not started | | | | |
| 03 | Dashboard | | not started | | | | |
| 04 | User profile and household (spouse linking) | | not started | | | | |
| 05 | Protection | | not started | | | | |
| 06 | Savings | | not started | | | | |
| 07 | Investment | | not started | | | | |
| 08 | Retirement | | not started | | | | |
| 09 | Estate | | not started | | | | |
| 10 | Property | | not started | | | | |
| 11 | Goals and life events | | not started | | | | |
| 12 | Coordination and recommendations | | not started | | | | |
| 13 | UK tax and TaxConfigService | | not started | | | | |
| 14 | Fyn (AI chat, tools, capture) | | not started | | | | |
| 15 | Subscriptions, tiers and billing | | not started | | | | |
| 16 | Preview personas and admin | | not started | | | | |
| 17 | Emails, notifications and push | `17-emails-notifications.md` | mapped | `28194e804` | 2026-09-14 | `map-emails-transactional-flow`, `map-emails-lifecycle-engine`, `map-emails-alerts-and-push` | MB-06, MB-14 to MB-17 |
| 18 | `/m` mobile web | | not started | | | | |
| 19 | iOS native | | not started | | | | |

Reports: `reports/mb-08-orphan-routes.md` (route-by-route dossier for MB-08, 2026-09-14).

Status values: `not started`, `overview`, `mapped`, `stale` (code moved past the stamped commit).

The section list is a starting order. Split, merge or reorder rows as the overview map reveals the real boundaries.
