# 01 - Overview & Architecture

## What Fynla Does

Fynla is a UK financial planning application. It covers five core modules (Protection, Savings, Investment, Retirement, Estate Planning) plus Goals and Life Events. Users enter their financial data, and the system analyses their position, projects future values, and generates recommendations.

The application serves both real users and preview personas (seeded test accounts that demonstrate the system without requiring real data).

## Tech Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend framework | Laravel | 10.x |
| Frontend framework | Vue.js | 3.x |
| State management | Vuex | 4.x |
| Routing (frontend) | Vue Router | 4.x |
| Database | MySQL | 8.x |
| CSS framework | Tailwind CSS | 3.4.18 |
| Build tool | Vite | 5.x |
| Charts | ApexCharts | 5.3.5 |
| API auth | Laravel Sanctum | 3.3 |
| MFA | Google 2FA (TOTP) | pragmarx/google2fa-laravel 2.3 |
| Testing (PHP) | Pest | 2.36 |
| Testing (JS) | Vitest | 3.2.4 |
| E2E testing | Playwright | 1.56.0 |
| PDF parsing | smalot/pdfparser | 2.12 |
| Excel export | phpoffice/phpspreadsheet | 5.3 |
| Code formatting | Laravel Pint (PSR-12) | 1.0 |

## Architecture

```
Browser (Vue.js SPA)
    |
    | Axios + Sanctum Bearer Token
    v
Laravel API (routes/api.php)
    |
    | Middleware: CORS -> Throttle -> SanitizeInput -> PreviewWriteInterceptor -> auth:sanctum
    v
Controller (validates request, checks ownership)
    |
    v
Agent (orchestrates module logic)
    |
    v
Services (calculations, data aggregation, tax computations)
    |
    v
Eloquent Models (database queries, relationships)
    |
    v
MySQL 8 Database
    |
    v
API Resource (transforms model to JSON)
    |
    v
JSON Response to Browser
```

The architecture has four layers:

1. **Controllers** receive requests, validate input, and check authorisation (user owns the record).
2. **Agents** orchestrate module-level operations. Each module has one agent that coordinates multiple services. Agents expose three standard methods: `analyze()`, `generateRecommendations()`, and `buildScenarios()`.
3. **Services** contain the domain logic: tax calculations, pension projections, Monte Carlo simulations, IHT computation. Services receive data only through method arguments.
4. **Models** handle database access through Eloquent ORM. Relationships, scopes, and accessors live here.

## Key Design Patterns

### Single-Record Joint Ownership
Joint assets use one database record, not two. The `user_id` field holds the primary owner. The `joint_owner_id` field holds the spouse. The `ownership_percentage` field stores the primary owner's share (the spouse's share is `100 - ownership_percentage`).

This pattern applies to: Property, Mortgage, SavingsAccount, InvestmentAccount, Chattel, BusinessInterest, Goal, LifeEvent, Liability.

### Agent-Service Separation
Agents orchestrate; services calculate. A `RetirementAgent` calls `PensionProjector`, `AnnualAllowanceChecker`, `ContributionOptimizer`, and `DecumulationPlanner` but performs no calculations itself.

### Cache Invalidation on Write
Every controller write operation (store, update, delete) calls `invalidateCache()` on the relevant agent. This clears cached analysis results so the next read returns fresh data.

### Preview Mode Interception
Preview users (`is_preview_user = true`) authenticate normally but all write operations are intercepted by `PreviewWriteInterceptor` middleware. The middleware returns fake success responses without touching the database. The frontend stores changes in `sessionStorage` only.

### Tax Configuration Service
All tax thresholds, rates, and allowances come from `TaxConfigService`, which reads seeded configuration from the database. This allows tax year updates without code changes.

### Standardised Response Format
All API responses follow: `{ success: bool, message: string, data: mixed }`.

## Project Statistics

| Category | Count |
|----------|-------|
| Migrations | 55 |
| Models | 49 |
| Controllers | 66 |
| Agents | 8 (Protection, Savings, Investment, Retirement, Estate, Goals, Coordinating, Base) |
| Services | 141 |
| API Resources | 10 |
| Form Requests | 64 |
| Middleware (custom) | 7 |
| Vue Components | 313 |
| Vuex Store Modules | 21 |
| Frontend Services | 35 |
| API Routes | ~250 |
| Test Files | 103 |
| Seeders | 12 |
| Config Files | 16 |
| Preview Personas | 6 |

## Modules at a Glance

| Module | Purpose | Key Calculations |
|--------|---------|-----------------|
| Protection | Life insurance, critical illness, income protection, disability, sickness | Needs analysis, coverage gaps, adequacy scoring (0-100) |
| Savings | Cash accounts, ISAs, emergency fund, savings goals | Emergency fund runway, ISA allowance tracking, goal progress |
| Investment | Portfolio analysis, holdings, rebalancing, tax optimisation | Monte Carlo simulations, efficient frontier, fee analysis, CGT harvesting |
| Retirement | DC pensions, DB pensions, state pension, decumulation | Pension projections, annual allowance check (GBP 60,000), required capital, income strategies |
| Estate | IHT liability, gifts, trusts, wills, bequests | IHT calculation (NRB, RNRB, taper), gifting strategy, projected estate at death |
| Goals | Financial goals, life events, projections | Goal progress, affordability, contribution streaks, net worth projections |
| Coordination | Cross-module holistic planning | Conflict resolution, priority ranking, cashflow allocation |
