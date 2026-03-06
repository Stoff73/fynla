# Fynla Security Report

**Scan Date:** 6 March 2026
**Remediation Date:** 6 March 2026
**Tool:** Trivy (filesystem scan) + npm audit + composer audit
**Scope:** Full codebase — vulnerabilities, licenses, misconfigurations, secrets
**Severity Levels:** All (critical, high, medium, low, unknown)

---

## Executive Summary

| Category | Found | Remediated | Remaining |
|----------|-------|------------|-----------|
| Production vulnerabilities (npm) | 15 | 15 | 0 |
| Production vulnerabilities (composer) | 4 | 4 | 0 |
| Vendored pip test dependencies | 14 | 0 | 14 (no risk) |
| Misconfigurations | 0 | - | 0 |
| Secrets (false positives) | 8 | - | 0 (not real) |
| Copyleft licenses | 5 | - | 5 (acceptable) |

**Status: All actionable vulnerabilities remediated. `npm audit` and `composer audit` report 0 vulnerabilities. All 1033 unit tests pass.**

---

## Remediation Actions Taken

### npm upgrades

| Package | Before | After | CVEs Resolved |
|---------|--------|-------|---------------|
| html2pdf.js | 0.12.1 | 0.14.0 | CVE-2026-22787 |
| jspdf (transitive via html2pdf.js) | 3.0.4 | 4.2.0 | CVE-2025-68428 (CRITICAL), CVE-2026-24737, CVE-2026-25940, CVE-2026-25535, CVE-2026-25755, CVE-2026-24133, CVE-2026-24043, CVE-2026-24040 |
| dompurify (transitive via jspdf) | 3.3.1 | 3.3.2 | CVE-2026-0540 (XSS bypass) |
| rollup (override, transitive via vite) | 4.52.5 | 4.59.0 | CVE-2026-27606 |
| esbuild (override, transitive via vite) | 0.21.5 | 0.25.12 | GHSA-67mh-4wv8-2f99 |
| axios | 1.6.4 | 1.9.0 | GHSA-43fc-jf86-j433 (DoS) |
| glob | 10.3.x | 10.5.0 | GHSA-5j98-mcp5-4vw2 (command injection) |
| minimatch | 9.0.x | 10.0.x | GHSA-3ppc-4f35-3m26, GHSA-7r86-cg39-jmmj, GHSA-23c5-xmqv-rm74 (ReDoS) |

### Composer upgrades

| Package | Before | After | CVEs Resolved |
|---------|--------|-------|---------------|
| symfony/http-foundation | v6.4.26 | v6.4.35 | CVE-2025-64500 |
| symfony/process | v6.4.26 | v6.4.33 | CVE-2026-24739 |
| psy/psysh | v0.12.12 | v0.12.20 | CVE-2026-25129 |
| phpunit/phpunit | 10.5.36 | 10.5.63 | CVE-2026-24765 (unsafe deserialization) |

### package.json changes

- `html2pdf.js` version bumped from `^0.12.1` to `^0.14.0`
- Added `overrides` block to force `esbuild@^0.25.0` and `rollup@^4.59.0` (Vite 5 pins older ranges)

---

## Remaining Findings (no action required)

### Vendored Pip Test Dependencies (14 findings — no risk)

These are Python packages inside `vendor/mockery/`. They are not installed, not imported, not executed at runtime, and not exposed to any network interface.

| CVE | Package | Current | Severity |
|-----|---------|---------|----------|
| CVE-2026-24049 | wheel | 0.43.0 | HIGH |
| CVE-2025-47273 | setuptools | 69.2.0 | HIGH |
| CVE-2024-6345 | setuptools | 69.2.0 | HIGH |
| CVE-2025-66471 | urllib3 | 2.2.1 | HIGH |
| CVE-2026-21441 | urllib3 | 2.2.1 | HIGH |
| CVE-2025-66418 | urllib3 | 2.2.1 | HIGH |
| CVE-2025-27516 | Jinja2 | 3.1.4 | MEDIUM |
| CVE-2024-56326 | Jinja2 | 3.1.4 | MEDIUM |
| CVE-2024-56201 | Jinja2 | 3.1.4 | MEDIUM |
| CVE-2025-50182 | urllib3 | 2.2.1 | MEDIUM |
| CVE-2024-37891 | urllib3 | 2.2.1 | MEDIUM |
| CVE-2025-50181 | urllib3 | 2.2.1 | MEDIUM |
| CVE-2024-47081 | requests | 2.31.0 | MEDIUM |
| CVE-2024-35195 | requests | 2.31.0 | MEDIUM |

These will naturally resolve when Mockery releases updates that vendor newer Python packages.

### Secrets (8 findings — all false positives)

All 8 detections are example JWT tokens in Revolut's OpenAPI specification files:

| File | Instances |
|------|-----------|
| `revolut/revolut-openapi-master/json/open-banking.json` | 4 |
| `revolut/revolut-openapi-master/yaml/open-banking.yaml` | 4 |

These are documentation examples, not real credentials.

### Misconfigurations

None found.

### License Review

| License | Count | Location | Risk |
|---------|-------|----------|------|
| GPL-2.0-only | 2 | composer.lock | Acceptable (SaaS, server-side) |
| GPL-3.0-only | 2 | composer.lock | Acceptable (SaaS, server-side) |
| LGPL-3.0-only | 1 | composer.lock | Acceptable (library usage) |
| MIT | ~150 | composer.lock + package-lock.json | None |

GPL/LGPL is acceptable because Fynla is a SaaS application — code runs server-side and is not distributed.

---

## Verification

```
$ npm audit
found 0 vulnerabilities

$ composer audit
No security vulnerability advisories found.

$ ./vendor/bin/pest --testsuite=Unit
Tests: 1033 passed (3210 assertions)

$ ./vendor/bin/pest --testsuite=Architecture
Tests: 73 deprecated (168 assertions)  # PHP 8.5 deprecation warnings only
```

---

*Report generated from Trivy filesystem scans. Remediation completed 6 March 2026.*
