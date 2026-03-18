# Advisor Dashboard — Implementation Summary

**Date:** 17-18 March 2026
**PR:** #136 (merged to main)
**Spec:** `docs/superpowers/specs/2026-03-17-admin-advisor-design.md` (Feature 2)
**Plan:** `docs/superpowers/plans/2026-03-17-advisor-dashboard.md`
**Mockup:** `March/March17Updates/Advisor/advisor-dashboard-mockup.html`

---

## What Was Built

A financial advisor dashboard where advisors can view and manage their assigned clients, track communications and reviews, generate suitability reports, and impersonate client profiles with full audit logging.

### Feature Overview

- **Advisor Dashboard** (`/advisor`) — Stats cards, client overview table with P S I R E module dots, reviews due, recent activity feed
- **Client Management** — View all clients, search/filter, detailed client profiles with module status breakdown
- **Activity Tracking** — Log emails, phone calls, meetings, letters, suitability reports, reviews, notes per client
- **Review Scheduling** — Track overdue and upcoming reviews with configurable frequency per client
- **Client Impersonation** — Enter a client's profile to view their data (read-only), with violet banner and audit trail
- **Suitability Reports** — Dedicated view for tracking report sent/acknowledged dates
- **Communications** — Filtered activity log showing only advisor-client communications

### Architecture

```
Vue Component → API Service → Controller → Service → Models → DB
(AdvisorLayout)  (advisorService)  (AdvisorController)  (AdvisorDashboardService)
                                                        (ClientActivityService)
                                                        (AdvisorImpersonationService)
```

- `is_advisor` boolean flag on users — independent of RBAC role (user can be both admin AND advisor)
- Cache-based impersonation — stores state in `advisor_impersonation:{token_id}` with 8-hour TTL, no token replacement
- Household-aware — coupled clients appear as single rows, module status merges spouse data
- Production filter — preview personas hidden in production, shown in dev/staging for demo

---

## Database Changes

### New Tables

| Table | Purpose |
|-------|---------|
| `advisor_clients` | Links advisors to clients with status, review schedule, notes |
| `client_activities` | Tracks all advisor-client interactions (7 activity types) |

### Modified Tables

| Table | Change |
|-------|--------|
| `users` | Added `is_advisor` boolean column (default false, after `is_admin`) |

### RBAC

- New role: `advisor` (level 25)
- New permission: `advisor.access`
- `PermissionService::isAdvisor()` helper method

---

## API Endpoints (11)

All under `api/advisor/` with `auth:sanctum` + `advisor` middleware:

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/advisor/dashboard` | Dashboard stats (client count, reviews due, comms, reports) |
| GET | `/api/advisor/clients` | Client list with module status (cached 5 min) |
| GET | `/api/advisor/clients/{id}` | Client detail with activity timeline |
| GET | `/api/advisor/clients/{id}/modules` | Detailed module status breakdown |
| POST | `/api/advisor/clients/{id}/enter` | Start impersonation |
| POST | `/api/advisor/exit` | End impersonation |
| GET | `/api/advisor/activities` | Activity feed (filterable by client, type, date) |
| POST | `/api/advisor/activities` | Log new activity |
| PUT | `/api/advisor/activities/{id}` | Update activity |
| GET | `/api/advisor/reviews-due` | Overdue + upcoming reviews |
| GET | `/api/advisor/reports` | Suitability reports (filtered activities) |

---

## Frontend Components (16 files)

### Layout
- `AdvisorLayout.vue` — Standalone layout with dark top bar, sidebar navigation, content area

### Views (7)
- `AdvisorDashboard.vue` — Main dashboard matching mockup
- `AdvisorClientList.vue` — Full client table with search/filter/pagination
- `AdvisorClientDetail.vue` — Read-only client overview
- `AdvisorActivityLog.vue` — Activity feed with type/date/client filters
- `AdvisorReviewsDue.vue` — Review management cards
- `AdvisorReports.vue` — Suitability reports table

### Components (3)
- `ClientModuleDots.vue` — P S I R E status dots (complete=spring, partial=violet, empty=light-gray, skipped=eggshell)
- `ClientActivityForm.vue` — Modal form to log activities (emits `save`)
- `AdvisorBanner.vue` — Violet impersonation banner in `AppLayout.vue`

### Store & Service
- `store/modules/advisor.js` — Namespaced Vuex module
- `services/advisorService.js` — API wrapper (11 methods)

### Modified
- `store/index.js` — Registered advisor module
- `store/modules/auth.js` — Added `isAdvisor` getter
- `router/index.js` — Added `/advisor` routes + `requiresAdvisor` guard
- `layouts/AppLayout.vue` — Added impersonation banner

---

## Seeded Data

`AdvisorClientSeeder` sets chris@fynla.org as advisor and assigns 6 preview personas as clients:

| Persona | Avatar Colour | Overdue? |
|---------|--------------|----------|
| James & Emily Carter (young_family) | violet-500 | No |
| David & Sarah Mitchell (peak_earners) | raspberry-500 | Yes (93 days) |
| Margaret Thompson (widow) | spring-500 | No |
| Alex Chen (entrepreneur) | savannah-500 | Yes (118 days) |
| John Morgan (young_saver) | light-blue-500 | No |
| Robert & Patricia Williams (retired_couple) | horizon-500 | No |

Each client has 3-5 seeded activities (emails, phone calls, meetings, suitability reports).

---

## Tests

- 14 unit tests (UserModuleTrackingService, AdvisorDashboardService, AdvisorImpersonationService)
- 13 feature tests (AdvisorController endpoints)
- 4 middleware tests (AdvisorMiddleware, AdvisorImpersonationMiddleware)

---

## Security

- `AdvisorMiddleware` gates all `/api/advisor/*` routes behind `is_advisor` flag
- Impersonation guards: cannot enter admin accounts, cannot enter other advisor accounts, cannot nest impersonation
- All impersonation actions logged to `AuditLog` with `advisor_id` + `client_id`
- 8-hour auto-expiry on impersonation cache entries
- `CheckSubscription` middleware bypasses advisor routes (advisors don't need subscriptions)
- `PreviewWriteInterceptor` allows impersonation start/stop routes

---

## Known Issues / Future Work

1. Activity icons in Recent Activity feed show generic icons instead of type-specific (email/phone/meeting) — minor template fix needed
2. `widow` persona only appears if `PreviewUserSeeder` includes Margaret Thompson — seeder skips gracefully if missing
3. Impersonation flow not yet browser-tested end-to-end (enter → navigate → exit)
4. No "Communications" vs "Activity Log" distinction in sidebar active state highlighting
