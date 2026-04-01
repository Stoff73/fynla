# Fynla - UK Financial Planning System

A comprehensive financial planning web application designed for UK individuals and families, covering seven integrated modules: Protection, Savings, Investment, Retirement, Estate Planning, Goals & Life Events, and Coordination. Features an AI-powered chat assistant, unified financial plans with PDF export, and a complete design system.

![Laravel](https://img.shields.io/badge/Laravel-10.x-red?logo=laravel)
![Vue.js](https://img.shields.io/badge/Vue.js-3.x-green?logo=vue.js)
![PHP](https://img.shields.io/badge/PHP-8.2+-blue?logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-blue?logo=mysql)
![Tests](https://img.shields.io/badge/tests-1603%20passing-brightgreen)

---

## Quick Stats

| Metric | Count |
|--------|-------|
| Vue Components | 646 |
| PHP Services | 215 |
| PHP Controllers | 90 |
| Eloquent Models | 89 |
| API Endpoints | 550+ |
| Vuex Store Modules | 32 |
| Agents | 9 |
| Test Cases | 1,603+ |

---

## Table of Contents

- [Overview](#overview)
- [Current Status](#current-status)
- [Core Features](#core-features)
- [Module Features](#module-features)
- [Technology Stack](#technology-stack)
- [Installation](#installation)
- [Development](#development)
- [Testing](#testing)
- [Deployment](#deployment)
- [Documentation](#documentation)
- [Recent Updates](#recent-updates)

---

## Overview

**Fynla** is a UK-focused comprehensive financial planning application that helps individuals and families:

- **Analyze** their current financial situation across all major areas
- **Identify** gaps, risks, and opportunities
- **Plan** for their financial future with confidence
- **Track** progress towards financial goals
- **Generate** professional reports and recommendations

### Production URLs

| Environment | URL |
|-------------|-----|
| Primary | https://fynla.org |
| Legacy | https://csjones.co/fynla |

---

## Current Status

**Version**: v0.9.4
**Last Updated**: 1 April 2026
**Test Suite**: 1,603 tests passing

### Completion Status

| Area | Status | Notes |
|------|--------|-------|
| Foundation | 100% | Authentication, routing, testing framework |
| Core Modules | 100% | All 5 modules fully functional |
| Advanced Features | 100% | Portfolio optimization, Monte Carlo, IHT planning |
| User Management | 100% | Spouse accounts, joint ownership, data sharing |
| Admin Panel | 100% | User management, backups, tax configuration |
| Document Upload | 100% | AI-powered extraction (Claude Sonnet) |
| Security Compliance | 100% | TOTP MFA, GDPR, audit logging, RBAC |
| Preview Mode | 100% | 7 database-backed personas |
| Goals & Life Events | 100% | Goal tracking, projections, life events |
| Email Verification | 100% | 6-digit code verification for registration/login |
| Mobile Responsive | 90% | Dashboard and key views optimized |

---

## Core Features

### Authentication & Security

- **Email Verification**: 6-digit code verification for registration and login
- **TOTP Multi-Factor Authentication**: QR code setup, recovery codes, verification modal
- **Failed Login Tracking**: Progressive lockout (1min - 5min - 30min - 24hr)
- **Session Management**: View active sessions, revoke from Security Settings
- **GDPR Compliance**: Data export (JSON/CSV), data erasure, consent tracking
- **Audit Logging**: Auditable trait on 15 financial models
- **RBAC**: Role-based access (User, Support, Admin) with granular permissions
- **Secure Authentication**: Laravel Sanctum token-based API authentication
- **Rate Limiting**: 300 requests/minute for API, 5/minute for auth endpoints
- **Preview Mode**: Try the app without registration

### User Management

- **User Profiles**: Comprehensive personal and financial information
- **Spouse Accounts**: Auto-creation and linking with bidirectional access
- **Joint Ownership**: Support for jointly owned assets (properties, investments, savings)
- **Trust Ownership**: Track assets held in trust
- **Data Sharing**: Granular permissions for spouse data access
- **Family Members**: Track dependents with relationship types

### Dashboard

The main dashboard provides a unified view of your financial planning:

- **Net Worth Overview**: Real-time tracking of assets and liabilities
- **Wealth Summary**: Side-by-side household breakdown with spouse data
- **Estate Planning Summary**: IHT liability and probate readiness
- **Protection Overview**: Coverage status and policy summary
- **Plans Card**: Quick access to all planning modules

### Tax Configuration System

- **Database-Driven**: All UK tax values stored in database (never hardcoded)
- **Multi-Year Support**: Tax years 2021/22 through 2025/26
- **Admin Panel**: Easy tax year switching and value updates
- **Comprehensive Coverage**: Income tax, NI, CGT, dividend tax, IHT, stamp duty, ISA/pension allowances

### Document Upload with AI Extraction

Upload financial documents and let AI extract the data automatically:

- **Supported Formats**: PDF, PNG, JPG, JPEG, WebP, Excel (XLSX, XLS), CSV
- **AI-Powered**: Uses Claude Sonnet for intelligent data extraction
- **Document Types**: Pension statements, insurance policies, investment statements, mortgage/savings statements
- **Review & Confirm**: Review extracted data with confidence scores before saving

### Letter to Spouse

Emergency instructions document for surviving spouse:

- **4-Part Guide**: Immediate actions, account access, long-term plans, funeral wishes
- **Auto-Population**: Automatically aggregates data from all modules
- **Dual View**: Each spouse can edit their own and view partner's (read-only)

### Preview Mode

Try the full application with realistic financial data:

| Persona | Description | Net Worth |
|---------|-------------|-----------|
| Emily & James Carter | Young family with mortgage, workplace pensions | ~£100k |
| David & Sarah Mitchell | Peak earners, BTL property, complex pensions | ~£2.3m |
| Margaret Thompson | Retired widow with estate planning needs | ~£2.2m |
| Alex Chen | Single tech entrepreneur with SIPP | ~£550k |
| John Morgan | Young adult saver, LISA, Cash ISA, student loan | ~£25k |
| Patricia & Harold Bennett | Retired couple, DB pensions, IHT planning | ~£1.8m |
| Janice Taylor | University student, LISA, student loan | ~-£33k |

---

## Module Features

### Protection Module

**Purpose**: Analyze life insurance, critical illness, and income protection coverage

**Features**:
- Policy portfolio view with filtering and sorting
- Coverage gap analysis comparing recommended vs. current
- Adequacy scoring (0-100) based on 8 metrics
- Human capital calculation (lifetime earning potential)
- Premium affordability analysis
- Strategy tab with prioritized recommendations
- Professional report generation

**Policy Types Supported**:
- Life Insurance (Decreasing Term, Level Term, Whole of Life, Family Income Benefit)
- Critical Illness
- Income Protection
- Disability
- Sickness & Illness

### Savings Module

**Purpose**: Emergency fund analysis and savings goal tracking

**Features**:
- Emergency fund calculator (3-6 month runway)
- ISA allowance monitoring (£20,000 limit, cross-module)
- Liquidity ladder (immediate, notice, fixed)
- Savings goals with progress tracking
- Interest rate analysis

### Investment Module

**Purpose**: Portfolio tracking with optimization and goal-based planning

**Features**:
- Portfolio overview with holdings management
- Asset allocation visualization
- Account types: ISA, GIA, NS&I, Bonds, VCT, EIS
- Rebalancing recommendations
- Fee analysis (platform fees, fund OCFs, advisor fees)
- Tax efficiency scoring
- Annualized return calculations (gross and net of fees)
- Portfolio strategy recommendations

### Retirement Module

**Purpose**: Pension tracking, projection, and decumulation planning

**Features**:
- Pension inventory (DC, DB, State pensions)
- DC pension portfolio optimization with holdings management
- Advanced risk analytics (Alpha, Beta, Sharpe Ratio)
- Monte Carlo projections with scenario modeling
- Income projection with stacked area charts
- Annual allowance tracking (£60,000 + 3-year carry forward)
- Contribution optimization with tax relief calculations
- Retirement income planning with tax calculations
- Strategy recommendations

### Estate Planning Module

**Purpose**: IHT calculation, net worth tracking, and estate strategy

**Features**:
- IHT calculations (single and married scenarios)
- Net worth tracking with comprehensive asset/liability breakdown
- Gifting strategy (PET and CLT with 7-year taper relief)
- Trust management with beneficiary details
- Will planning with executor details
- Actuarial projections (life expectancy-based)
- Second death analysis with combined allowances
- Life policy strategy comparison
- Probate readiness scoring
- Chattels & valuables tracking with CGT calculator
- Business interests with Business Relief assessment

**IHT Allowances**:
- Nil Rate Band: £325,000
- Residence Nil Rate Band: £175,000
- Married couples: Combined £650,000 NRB + £350,000 RNRB

### Goals & Life Events Module

**Purpose**: Financial goal tracking with projections and life event planning

**Features**:
- Centralised goal management with automatic module assignment
- 8 goal types: Emergency fund, property, education, retirement, wealth, wedding, holiday, custom
- Visual progress bars and milestone tracker (25/50/75/100%)
- Contribution streak tracking with badges
- Life event management (income/expense impacts)
- Net worth projection chart using Future Value calculations
- Cash flow view with income, expenditure, and surplus
- Household view toggle for joint goals
- Dashboard integration with projection chart

---

## Technology Stack

### Backend

| Technology | Version | Purpose |
|------------|---------|---------|
| Laravel | 10.x | PHP Framework |
| PHP | 8.2+ | Server-side language |
| MySQL | 8.0+ | Database |
| Sanctum | - | API Authentication |
| Pest | - | Testing framework |
| Pint | - | Code formatting (PSR-12) |

### Frontend

| Technology | Version | Purpose |
|------------|---------|---------|
| Vue.js | 3.x | Frontend framework |
| Vuex | 4.x | State management |
| Vite | - | Build tool with HMR |
| Tailwind CSS | 3.x | Utility-first CSS |
| ApexCharts | - | Data visualization |
| Axios | - | HTTP client |

### Architecture

```
┌─────────────────────────────────────┐
│ Presentation Layer                  │
│ Vue.js 3 + ApexCharts + Tailwind   │
│ 378 Components + 21 Store Modules   │
└─────────────────┬───────────────────┘
                  │ REST API (457 endpoints)
                  ↓
┌─────────────────────────────────────┐
│ Application Layer                   │
│ 70 Controllers + 8 Agents           │
│ 174 Services + Business Logic       │
│ Claude AI (Document Extraction)     │
└─────────────────┬───────────────────┘
                  │ Eloquent ORM
                  ↓
┌─────────────────────────────────────┐
│ Data Layer                          │
│ MySQL 8.0+ (77 Models)             │
│ Memcached (calculation caching)    │
└─────────────────────────────────────┘
```

### Agent-Based System

Each module has an intelligent agent that orchestrates analysis:

| Agent | Purpose |
|-------|---------|
| ProtectionAgent | Life/CI/IP coverage analysis |
| SavingsAgent | Emergency fund & ISA tracking |
| InvestmentAgent | Portfolio analysis & Monte Carlo |
| RetirementAgent | Pension projections & readiness |
| EstateAgent | IHT calculation & estate strategy |
| GoalsAgent | Goals projection & life events |
| CoordinatingAgent | Cross-module holistic planning |
| RiskAgent | Automated risk profile calculation |

---

## Installation

### System Requirements

- **PHP**: 8.2 or higher
- **MySQL**: 8.0 or higher
- **Node.js**: 18.x or higher
- **Composer**: 2.5 or higher
- **RAM**: 4GB minimum, 8GB recommended

### Quick Start

```bash
# Clone repository
git clone <repository-url> fynla
cd fynla

# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Configure database in .env, then:
php artisan migrate

# Seed required data
php artisan db:seed --class=TaxConfigurationSeeder
php artisan db:seed --class=TaxProductReferenceSeeder
php artisan db:seed --class=UKLifeExpectancySeeder
php artisan db:seed --class=ActuarialLifeTablesSeeder
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=PreviewUserSeeder

# Start development servers
./dev.sh
```

### Demo Credentials

| Role | Email | Password |
|------|-------|----------|
| User | demo@fps.com | password |
| Admin | admin@fps.com | admin123 |

---

## Development

### Running Development Servers

**Recommended**: Use the startup script that handles everything:

```bash
./dev.sh
```

This script:
- Kills existing server processes
- Exports correct environment variables
- Clears Laravel and Vite caches
- Verifies MySQL connection
- Starts both Laravel (port 8000) and Vite (port 5173)

**Manual Alternative** (3 terminals):

```bash
# Terminal 1 - Laravel Backend
php artisan serve

# Terminal 2 - Vite Frontend
npm run dev

# Terminal 3 - Queue Worker (optional)
php artisan queue:work database
```

### Code Quality

```bash
# Format code (PSR-12)
./vendor/bin/pint

# Check without fixing
./vendor/bin/pint --test
```

---

## Testing

### Running Tests

```bash
# Run all tests
./vendor/bin/pest

# Run with increased memory (recommended)
php -d memory_limit=512M ./vendor/bin/pest

# Run specific suite
./vendor/bin/pest --testsuite=Unit
./vendor/bin/pest --testsuite=Feature
./vendor/bin/pest --testsuite=Architecture

# Run specific file
./vendor/bin/pest tests/Feature/Protection/ProtectionApiTest.php
```

### Test Coverage

| Suite | Tests | Description |
|-------|-------|-------------|
| Unit | 200+ | Service classes, calculations |
| Feature | 800+ | API endpoints, integrations |
| Architecture | 50+ | Coding standards enforcement |
| **Total** | **1,603** | All passing |

### After Running Tests

Tests may truncate database tables. Reseed required data:

```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
```

---

## Deployment

### Build for Production

Use the deployment-specific build scripts:

```bash
# For fynla.org (root deployment)
./deploy/fynla-org/build.sh

# For csjones.co/fynla (subdirectory deployment)
./deploy/csjones-fynla/build.sh
```

### Server Requirements

- PHP 8.2+ with extensions: BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML
- MySQL 8.0+
- Nginx or Apache with mod_rewrite
- SSL Certificate

### Post-Deployment

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

---

## Documentation

| Document | Purpose |
|----------|---------|
| `CLAUDE.md` | Development guidelines for Claude Code |
| `fynlaDesignGuide.md` | Design system v1.2.0 (single source of truth) |
| `March2Update/patch083.md` | v0.8.3 comprehensive patch report |
| `deploy/README.md` | Deployment configuration |
| `preview.md` | Preview mode architecture |

---

## Recent Updates

### 1 April 2026 - v0.9.4

- **Resource Pages Redesign** — Comprehensive redesign of all public resource pages: features (harvey balls comparison), FAQ (centralised data, category filters), security, glossary, our approach, one platform, financial companion, getting started. New advisors page and contact form with backend controller
- **Guides & Explainers** — Learning Centre replaced with categorised guide hub (Key Terms, Decision Support, Personal Journey Guides, Tax & Allowances). GuideNav and GuideArticleFooter shared components across 22 article pages
- **Feature Gating** — Tier-based access control: greyed sidebar items with upgrade tooltips for lower tiers, backend middleware enforcement, 10 automated tests
- **Journey Links** — Stage page CTAs pass life stage to registration, auto-select on onboarding. Security fix resets all Vuex stores and clears tokens on register/login
- **Dashboard Improvements** — Investment bar charts, empty state CTAs, Fyn chat toggle, ModuleStatusBar three-column redesign, net worth donut tooltips, matched card heights
- **Site-Wide** — Sentence case, in-place demo modal, Google Analytics in head, SEO structured data, sitemap with 60+ URLs, comparison page slug redirects, CSP updated for GA

### 15-30 March 2026 - v0.9.0 to v0.9.3.2

- **Decision Engine Upgrade (v0.9.0)** — 107 DB-driven recommendation triggers across 5 modules, data readiness gates, 9-phase investment pipeline
- **AI Form Fill** — xAI/Grok-powered form auto-fill deployed across 14 modules, tested on production
- **Full Code Review Remediation** — 94 issues fixed (tax compliance, security, design system, dead code)
- **Admin Tax Configuration** — 568 TaxConfigService values editable across 10 tabs
- **Power of Attorney** — Guided creation wizard, compliance checking, print/registration tracking
- **Subscription & Payments** — Revolut checkout, upgrade with proration, subscription management
- **Website Redesign** — Journey pages, calculators sidebar, mega menu, preview personas, pricing page

### 27 February - 5 March 2026 - v0.8.3

- **Financial Plans System (Complete Rebuild)** — Unified plan framework with 5 plan types (Investment, Protection, Retirement, Estate, Goal) following a consistent 6-section structure: executive summary, current situation, toggleable actions, what-if scenarios, dynamic conclusion, PDF export
- **Holistic Plan Rewrite** — Frontend-orchestrated aggregation of individual module plans with priority allocation against shared disposable income
- **AI Chat Assistant** — AI-powered chat assistant ("Fynla Assistant") with 17 tools, SSE streaming, and simulated AI for preview personas (zero API cost for demos)
- **Side Navigation Menu** — Collapsible left-side navigation with expanded/collapsed modes, mobile overlay, and persisted state
- **Student Preview Persona** — 7th persona (Janice Taylor, 21, university student) with optimised student dashboard
- **Design System Overhaul (v1.2.0)** — Complete visual rebrand: Raspberry CTAs, Horizon text, Spring success, Violet warnings, Eggshell backgrounds, Segoe UI typography
- **CSS Centralisation** — Eliminated 1,110 lines of duplicated CSS across 65 components, established CSS governance rules
- **Plan Enhancements** — Structured executive summaries, personal information sections, per-account/pension recommendations with reactive charts, admin-configurable plan values (PlanConfigService)
- **Code Audit** — Fixed 3 critical bugs, 7 important fixes, 12 simplifications across all plan services
- **Print/Save PDF** — Multi-plan print support with type-specific builders and cascading line charts

### 22 February 2026 - v0.8.1

- Security hardening (4 phases): data encryption at rest, brute force protection, account enumeration prevention, challenge token authentication
- Security headers: CSP hardened, permissions policy, session security, source maps disabled
- Model and API hardening: hidden attributes, mass assignment protection, generic error messages
- Revolut payment integration with subscription management
- Token storage migrated to sessionStorage exclusively

### 5-6 February 2026 - v0.7.0

- Laravel best practices audit: 12 Form Requests, 10 API Resources, IHTController extraction
- Goals projections rewritten with simple FV calculation
- Retirement planner: income tax slider, decumulation graph, DB/State pension fallback
- Wealth summary improvements, 500 error fixes across 8 API Resources

### 19 January 2026 - v0.6.2

- TOTP MFA, failed login tracking, session management, GDPR compliance, audit logging, RBAC
- Goals-based planning module with 8 goal types
- Automated risk profile calculator, financial statements
- Young Adult Saver and Retired Couple personas, 134 new security tests

---

## License

This project is proprietary software. All rights reserved.

**Disclaimer**: This system is for demonstration and analysis purposes only, not regulated financial advice.

---

## Support

- **Documentation**: See `CLAUDE.md` and update folders
- **Issues**: Create an issue in the repository

---

**Version**: v0.9.4 | **Last Updated**: 1 April 2026 | **Status**: Production Ready

Built with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
