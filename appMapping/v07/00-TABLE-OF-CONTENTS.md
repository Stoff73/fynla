# Fynla v0.7.0 Application Manual

**Generated**: 6 February 2026
**Source**: Codebase audit (code-only, no assumptions)
**Production**: https://fynla.org

This manual documents the Fynla financial planning application as it exists at version 0.7.0. The audit read every detail directly from source files. Where the code contradicts existing documentation, `11-DISCREPANCIES.md` notes the difference.

## Documents

| # | File | Contents |
|---|------|----------|
| 01 | [Overview & Architecture](01-OVERVIEW.md) | Tech stack, architecture diagram, project statistics, design patterns |
| 02 | [Database Schema](02-DATABASE.md) | All 55 migrations, 49 models, relationships, ER map, joint ownership pattern |
| 03 | [Authentication & Security](03-AUTHENTICATION-SECURITY.md) | Auth flows, MFA, middleware pipeline, rate limiting, CSRF, sessions, GDPR |
| 04 | [Backend Architecture](04-BACKEND-ARCHITECTURE.md) | 8 agents, 66 controllers, 141 services, API resources |
| 05 | [Frontend Architecture](05-FRONTEND-ARCHITECTURE.md) | Vue router, 21 Vuex stores, 313 components, services, mixins |
| 06 | [API Reference](06-API-REFERENCE.md) | Every route (HTTP method, URI, controller, middleware, purpose) |
| 07 | [Validation Rules](07-VALIDATION-RULES.md) | All 64 form requests with field-level validation rules |
| 08 | [Financial Calculations](08-FINANCIAL-CALCULATIONS.md) | IHT, pensions, Monte Carlo, tax bands, ISA, protection needs formulas |
| 09 | [Module Guide](09-MODULES.md) | Protection, Savings, Investment, Retirement, Estate, Goals detail |
| 10 | [Configuration & Deployment](10-CONFIGURATION-DEPLOYMENT.md) | Config files, environment variables, seeders, build scripts, deployment |
| 11 | [Discrepancies](11-DISCREPANCIES.md) | Differences between existing docs (CLAUDE.md, README) and actual code |

## Quick Reference

| Metric | Count |
|--------|-------|
| Vue Components | 313 (audit count) |
| PHP Services | 141 |
| Controllers | 66 |
| Models | 49 |
| Migrations | 55 |
| Vuex Store Modules | 21 |
| Agents | 8 |
| API Routes | ~250 |
| Form Requests | 64 |
| Test Files | 103 |
| Seeders | 12 |
| Config Files | 16 |
