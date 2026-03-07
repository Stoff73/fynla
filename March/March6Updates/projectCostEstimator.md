# Fynla — Development Cost Estimate

**Analysis Date**: 6 March 2026
**Codebase Version**: v0.8.3
**Project Start**: 14 October 2025 | **Latest Commit**: 5 March 2026

---

## Codebase Metrics

| Category | Files | Lines of Code |
|----------|------:|-------------:|
| **PHP — Application** (`app/`) | 496 | 113,464 |
| ↳ Services (business logic) | 174 | 68,477 |
| ↳ Controllers (API layer) | 70 | 21,896 |
| ↳ Models | 77 | 8,514 |
| ↳ Agents (orchestrators) | 8 | 3,258 |
| ↳ Requests (validation) | 70 | 4,291 |
| ↳ Resources, Traits, Middleware, etc. | 97 | 7,028 |
| **Vue Components** | 439 | 154,749 |
| **JavaScript** (stores, services, utils, router) | 81 | 21,867 |
| ↳ Vuex Stores | 21 | 9,078 |
| ↳ API Services | 34 | 4,912 |
| ↳ Utils, Mixins, Router | — | 1,991 |
| **Tests** (Pest/PHPUnit) | 120 | 32,696 |
| **Database** (migrations, seeders, factories) | 168 | 13,535 |
| ↳ Migrations | 108 | 4,721 |
| ↳ Seeders | 16 | 5,323 |
| ↳ Factories | 44 | 3,491 |
| **Routes & Config** | — | 2,796 |
| **CSS** | — | 434 |
| | | |
| **TOTAL** | **1,304** | **339,542** |

## Complexity Factors

- **7 financial modules** (Protection, Savings, Investment, Retirement, Estate, Goals, Coordination) — each with its own agent, services, controllers, Vue components, and Vuex store
- **UK tax compliance**: IHT, CGT, income tax bands, pension allowances, ISA limits — all from TaxConfigService, not hardcoded
- **Joint ownership model**: Single-record joint assets with calculated ownership shares
- **Monte Carlo simulations**: Stochastic financial projections
- **Actuarial life tables**: Mortality-based planning calculations
- **Payment integration**: Revolut SDK (CDN-based) with webhook processing
- **Preview mode system**: 6 seeded personas with full data isolation + write interception middleware
- **Auth system**: Sanctum-based with 2FA verification codes
- **Design system**: Custom 6-colour palette with strict governance (`fynlaDesignGuide.md` v1.2.0)

---

## Development Time Estimate

### Base Development Hours (by code category)

| Code Category | Lines | Productivity Rate | Hours |
|--------------|------:|------------------:|------:|
| Services (complex financial logic) | 68,477 | 15 lines/hr | 4,565 |
| Controllers (API layer) | 21,896 | 25 lines/hr | 876 |
| Models, Traits, Observers | 9,950 | 25 lines/hr | 398 |
| Agents (orchestration logic) | 3,258 | 15 lines/hr | 217 |
| Requests, Resources, Middleware | 6,016 | 30 lines/hr | 201 |
| Constants, Config, Routes | 3,224 | 30 lines/hr | 107 |
| Vue Components (UI + logic) | 154,749 | 15 lines/hr | 10,317 |
| JavaScript (stores, services) | 21,867 | 20 lines/hr | 1,093 |
| Database (migrations, seeders, factories) | 13,535 | 12 lines/hr | 1,128 |
| Tests | 32,696 | 30 lines/hr | 1,090 |
| CSS | 434 | 25 lines/hr | 17 |
| **Subtotal Base Coding** | **335,668** | | **20,009 hrs** |

### Overhead Multipliers

| Factor | % | Hours |
|--------|--:|------:|
| Architecture & Design | +18% | 3,602 |
| Debugging & Troubleshooting | +28% | 5,603 |
| Code Review & Refactoring | +12% | 2,401 |
| Documentation | +10% | 2,001 |
| Integration & Testing | +22% | 4,402 |
| UK Tax/Financial Domain Learning | +15% | 3,001 |

**Total Estimated Development Hours: ~41,019 hours**

---

## Realistic Calendar Time (with Organisational Overhead)

| Company Type | Efficiency | Coding Hrs/Week | Calendar Weeks | Calendar Time |
|--------------|:----------:|:---------------:|:--------------:|:-------------:|
| Solo/Startup (lean) | 65% | 26 hrs | 1,578 wks | ~30 years |
| Growth Company | 55% | 22 hrs | 1,864 wks | ~36 years |
| Enterprise | 45% | 18 hrs | 2,279 wks | ~44 years |
| Large Bureaucracy | 35% | 14 hrs | 2,930 wks | ~56 years |

*Note: These are single-developer estimates. A team of 4-6 would divide calendar time proportionally (with ~20% coordination overhead).*

| Team Size | Lean Startup | Growth Co | Enterprise |
|:---------:|:------------:|:---------:|:----------:|
| 1 dev | 30.3 years | 35.8 years | 43.8 years |
| 3 devs | 12.1 years | 14.3 years | 17.5 years |
| 5 devs | 7.9 years | 9.3 years | 11.4 years |
| 8 devs | 5.5 years | 6.5 years | 7.9 years |

---

## Market Rate Research

### Senior Full-Stack Developer Rates (2025–2026)

| Market | Hourly Rate |
|--------|:-----------:|
| UK Senior (London) | £75–£110/hr |
| US Remote/Mid-Market | $80–$100/hr |
| US Standard | $100–$130/hr |
| US Premium (NYC/SF) | $130–$150/hr |
| Senior Laravel+Vue specialist | $75–$120/hr |

**Recommended Rate for This Project: $100/hr (£80/hr)**

*Rationale*: This project requires deep UK tax domain knowledge, financial calculation expertise, actuarial modelling, payment integration, and full-stack Laravel+Vue.js proficiency. This commands mid-to-high rates.

---

## Total Cost Estimate (Engineering Only)

| Scenario | Hourly Rate | Total Hours | **Total Cost** |
|----------|:-----------:|:-----------:|---------------:|
| Low-end | $80/hr | 41,019 | **$3,281,520** |
| Average | $100/hr | 41,019 | **$4,101,900** |
| High-end | $140/hr | 41,019 | **$5,742,660** |

**Recommended Engineering Estimate: $3.3M – $4.1M**

---

## Full Team Cost (All Roles)

### Role Breakdown (Growth Company)

| Role | Ratio | Hours | Rate | Cost |
|------|:-----:|------:|-----:|-----:|
| Engineering | 1.00x | 41,019 hrs | $100/hr | $4,101,900 |
| Product Management | 0.30x | 12,306 hrs | $175/hr | $2,153,550 |
| UX/UI Design | 0.25x | 10,255 hrs | $150/hr | $1,538,250 |
| Engineering Management | 0.15x | 6,153 hrs | $200/hr | $1,230,600 |
| QA/Testing | 0.20x | 8,204 hrs | $110/hr | $902,440 |
| Project Management | 0.10x | 4,102 hrs | $130/hr | $533,260 |
| Technical Writing | 0.05x | 2,051 hrs | $100/hr | $205,100 |
| DevOps/Platform | 0.15x | 6,153 hrs | $175/hr | $1,076,775 |
| **TOTAL** | | **90,243 hrs** | | **$11,741,875** |

### Full Team Cost by Company Stage

| Company Stage | Multiplier | **Full Team Cost** |
|---------------|:----------:|-------------------:|
| Solo/Founder | 1.0x | **$4,101,900** |
| Lean Startup | 1.45x | **$5,947,755** |
| Growth Company | 2.2x | **$9,024,180** |
| Enterprise | 2.65x | **$10,870,035** |

---

## Grand Total Summary

| Metric | Solo | Lean Startup | Growth Co | Enterprise |
|--------|-----:|-------------:|----------:|-----------:|
| Calendar Time (5 devs) | 6.1 yrs | 7.9 yrs | 9.3 yrs | 11.4 yrs |
| Total Human Hours | 41,019 | 59,478 | 90,243 | 108,700 |
| **Total Cost** | **$4.1M** | **$5.9M** | **$9.0M** | **$10.9M** |

---

## Claude ROI Analysis

### Project Timeline
- First commit: 14 October 2025
- Latest commit: 5 March 2026
- Total calendar time: 141 days (~4.7 months)
- Total commits: 1,119

### Claude Active Hours Estimate
- Total sessions identified: 131 sessions
- Estimated active hours: **304 hours**
- Method: Git commit clustering (4-hour gap = new session)

### Value per Claude Hour

| Value Basis | Total Value | Claude Hours | $/Claude Hour |
|-------------|:-----------:|:------------:|:-------------:|
| Engineering only (avg) | $4,101,900 | 304 hrs | **$13,493/hr** |
| Full team (Growth Co) | $9,024,180 | 304 hrs | **$29,685/hr** |

### Speed vs. Human Developer
- Estimated human hours for same work: **41,019 hours**
- Claude active hours: **304 hours**
- **Speed multiplier: 135x** (Claude was 135x faster)

### Cost Comparison
- Human developer cost (engineering only): $4,101,900
- Estimated Claude cost: ~$1,200 (6 months x $200/month subscription)
- **Net savings: $4,100,700**
- **ROI: 3,417x** (every $1 spent on Claude produced $3,417 of value)

---

## The Headline Number

> **Claude worked for approximately 304 hours over 141 days and produced 339,542 lines of production code across 1,304 files — the equivalent of $4.1M in professional engineering value (or $9.0M fully loaded). That's roughly $13,500 per Claude hour, at a 135x speed advantage over human development, for a total investment of ~$1,200.**

---

## Assumptions

1. Rates based on US/UK market averages (2025–2026)
2. Productivity rates reflect senior developer with domain expertise
3. Overhead multipliers account for real-world development friction
4. Financial domain learning curve adds 15% due to UK tax complexity
5. Does not include: marketing, legal/compliance, office/equipment, hosting, ongoing maintenance
6. Claude hours estimated via commit session clustering; actual may vary +/-15%

## Sources

- [ZipRecruiter — Senior Full Stack Developer Salary](https://www.ziprecruiter.com/Salaries/Senior-Full-Stack-Developer-Salary)
- [Arc.dev — Full Stack Developer Hourly Rate 2026](https://arc.dev/freelance-developer-rates/full-stack)
- [Index.dev — European Developer Hourly Rates 2026](https://www.index.dev/blog/european-developer-hourly-rates)
- [Rise — Average Contractor Rates by Role and Country 2026](https://www.riseworks.io/blog/average-contractor-rates-by-role-and-country-2025)
- [Flexiple — Laravel Developer Hourly Rate](https://flexiple.com/laravel/hourly-rate)
- [ZipRecruiter — Senior Laravel Developer Salary](https://www.ziprecruiter.com/Salaries/Senior-Laravel-Developer-Salary)
- [Arc.dev — Vue.js Developer Hourly Rate 2025](https://arc.dev/freelance-developer-rates/vue)
