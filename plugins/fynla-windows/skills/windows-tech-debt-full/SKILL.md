---
name: windows-tech-debt-full
description: Comprehensive codebase-wide audit for technical debt on Windows. Scans for duplicate code, dead code, convention drift, and architectural issues. This is the deep scan — use it periodically (weekly/monthly) or when the user asks for a full codebase health check on Windows. Triggers on "full tech debt audit", "codebase health check", "scan everything for debt", "find all duplicates", or "codebase quality review".
---

# Full Codebase Tech Debt Auditor (Windows)

Systematic scan of the entire Fynla codebase on Windows. This is a deep audit — it takes time but catches systemic issues that per-session checks miss.

## Execution Strategy

The codebase is large (378 Vue components, 174 services, 70 controllers). Scanning everything sequentially would be slow. Use parallel subagents to divide the work by module.

### Phase 1: Parallel Module Scans

Dispatch subagents (one per area) to scan in parallel:

| Agent | Scope | Focus |
|-------|-------|-------|
| Backend Services | `app/Services/` | Duplicate calculations, dead services, pattern violations |
| Controllers & HTTP | `app/Http/` | Route conventions, middleware gaps, response inconsistency |
| Models & Database | `app/Models/`, `database/` | Unused scopes, missing indexes, schema drift |
| Vue Components | `resources/js/components/` | Duplicate components, dead props, style violations |
| Stores & Services | `resources/js/store/`, `resources/js/services/` | Orphaned actions, dead API methods, state bloat |
| Tests | `tests/` | Coverage gaps, outdated mocks, test isolation issues |

Each subagent should return findings as structured JSON:
```json
{
  "area": "Backend Services",
  "findings": [
    {
      "severity": "critical|warning|suggestion",
      "category": "duplicate|dead-code|convention|complexity|security|inconsistency",
      "file": "path/to/file.php",
      "lines": "45-67",
      "description": "What's wrong",
      "suggestion": "How to fix it",
      "effort": "trivial|small|medium|large"
    }
  ]
}
```

### Phase 2: Cross-Cutting Analysis

After module scans complete, perform these codebase-wide checks:

#### A. Duplicate Code Detection

Search for known duplication patterns using Grep tool or PowerShell:

```powershell
# Find duplicate method names across services
Get-ChildItem -Path app\Services -Recurse -Filter "*.php" | Select-String -Pattern "public function calculate" | Sort-Object

Get-ChildItem -Path app\Services -Recurse -Filter "*.php" | Select-String -Pattern "public function get.*Total" | Sort-Object

# Find duplicate Vue component patterns (local formatCurrency instead of currencyMixin)
Get-ChildItem -Path resources\js\components -Recurse -Filter "*.vue" | Select-String -Pattern "formatCurrency|formatCurrencyWithPence" | Where-Object { $_.Line -notmatch "currencyMixin" }

# Find duplicate CSS patterns
Get-ChildItem -Path resources\js\components -Recurse -Filter "*.vue" | Select-String -Pattern "@keyframes|\.scrollbar|\.spinner|\.animate-"

# Find similar component files by name
Get-ChildItem -Path resources\js\components -Recurse -Filter "*.vue" | ForEach-Object { $_.Name } | Sort-Object | Group-Object | Where-Object { $_.Count -gt 1 }
```

#### B. Dead Code Detection

```powershell
# Find PHP classes never imported/used
Get-ChildItem -Path app\Services -Recurse -Filter "*.php" | ForEach-Object {
    $className = $_.BaseName
    $count = (Get-ChildItem -Path app -Recurse -Filter "*.php" | Select-String -Pattern $className | Where-Object { $_.Line -notmatch "class $className" }).Count
    if ($count -eq 0) { Write-Output "UNUSED: $className" }
}

# Find Vue components never imported
Get-ChildItem -Path resources\js\components -Recurse -Filter "*.vue" | ForEach-Object {
    $compName = $_.BaseName
    $count = (Get-ChildItem -Path resources\js -Recurse -Include "*.vue","*.js" | Select-String -Pattern $compName | Where-Object { $_.Line -notmatch "name: '$compName'" }).Count
    if ($count -eq 0) { Write-Output "UNUSED: $compName" }
}

# Find unused Vuex actions/mutations
Get-ChildItem -Path resources\js\store -Recurse -Filter "*.js" | Select-String -Pattern "dispatch|commit" | ForEach-Object { $_.Line } | Sort-Object | Group-Object | Sort-Object Count

# Find unused JS utility exports
Get-ChildItem -Path resources\js\utils -Recurse -Filter "*.js" | Select-String -Pattern "export "
```

#### C. Convention Drift

Check every file type against its CLAUDE.md conventions:

**PHP files — scan for:**
- Files missing `declare(strict_types=1);`
- Controllers using `DB` facade directly
- Services with hardcoded tax values (numbers like 325000, 175000, 12570, 20000, 60000)
- Models missing `Auditable` trait where they should have it
- Enum values outside canonical sets (`sole` instead of `individual`, etc.)

**Vue files — scan for:**
- Components with single-word names
- Hardcoded hex colours in `<style>` blocks
- Banned colour tokens (`amber-*`, `orange-*`, `primary-*`, `secondary-*`, `gray-*`)
- Local `formatCurrency` methods instead of `currencyMixin`
- Acronyms in user-facing template text (AA, DB, DC, S&S, MPAA — except ISA)
- Score displays in templates (look for `/100`, `score`, `rating` in template sections)
- `v-if` and `v-for` on the same element
- Missing `:key` on `v-for`

**CSS — scan for:**
- Custom `@keyframes` definitions that duplicate global ones
- Hardcoded hex values in `<style>` blocks
- Duplicated patterns from `app.css` (scrollbar, spinner, badge, accordion styles)

#### D. Architectural Health

- **Circular dependencies**: Services importing from controllers, components importing from views
- **Layer violations**: Vue components calling `DB` or Eloquent directly via API bypass
- **God files**: Any file over 800 lines
- **Orphaned migrations**: Migration files that reference tables/columns that no longer exist in the schema
- **Test coverage gaps**: Modules with services but no corresponding test files

#### E. Dependency & Config Health

```powershell
# Check for outdated composer packages with known vulnerabilities
composer audit 2>$null; if ($LASTEXITCODE -ne 0) { Write-Output "composer audit not available" }

# Check for outdated npm packages
npm audit --json 2>$null | Select-Object -First 50

# Check .env.example matches expected keys
Get-Content .env.example | Where-Object { $_ -notmatch "^#|^$" } | ForEach-Object { ($_ -split "=")[0] } | Sort-Object
```

### Phase 3: Aggregate & Report

Merge all findings, deduplicate, and sort by severity then effort.

Save the full report to `docs/tech-debt-report-full.md`:

```markdown
# Full Codebase Tech Debt Report

**Date:** [date]
**Codebase:** Fynla v[version from CLAUDE.md]
**Files scanned:** [count]
**Total issues:** [count]

## Executive Summary

| Severity | Count |
|----------|-------|
| Critical | [n] |
| Warning | [n] |
| Suggestion | [n] |

| Category | Count |
|----------|-------|
| Duplicate Code | [n] |
| Dead Code | [n] |
| Convention Violations | [n] |
| Complexity | [n] |
| Security | [n] |
| Inconsistency | [n] |

### Quick Wins (trivial effort, high impact)
[Top 5 issues that are easy to fix and improve quality most]

### High Priority (any effort, critical severity)
[All critical issues]

---

## Detailed Findings by Module

### Backend Services
[findings...]

### Controllers & HTTP
[findings...]

### Models & Database
[findings...]

### Vue Components
[findings...]

### Stores & Services (Frontend)
[findings...]

### Tests
[findings...]

## Cross-Cutting Issues

### Duplicate Code
[findings...]

### Dead Code
[findings...]

### Convention Drift
[findings...]

### Architectural Issues
[findings...]

---

## Recommended Action Plan

### Immediate (this week)
[Critical issues + quick wins]

### Short-term (this month)
[Warnings with small/medium effort]

### Backlog
[Suggestions and large-effort items]

---
*Generated by windows-tech-debt-full skill*
```

### Phase 4: Conversation Summary

After saving the report, print to the conversation:

1. **Headline stats** — total issues, breakdown by severity
2. **Top 5 most impactful issues** with file paths
3. **Top 5 quick wins** — trivial fixes that improve quality
4. **Overall health assessment** — one paragraph on the codebase's tech debt posture
5. **Path to the full report** for detailed review

## Important Guidelines

- **Be precise.** Every finding must have a file path, line number(s), and concrete suggestion. No vague "consider improving" without specifics.
- **Don't report style preferences.** Only flag verifiable issues against documented conventions (CLAUDE.md, fynlaDesignGuide.md) or objective code quality metrics.
- **Effort estimates matter.** Categorising by effort helps the user prioritise. A trivial fix that takes 2 minutes is more actionable than a large refactor.
- **Don't auto-fix.** This is an audit. Present findings and let the user decide what to act on.
- **Acknowledge what's good.** If a module is clean, say so. A report that's all negative misses the full picture.
- **Compare to previous reports.** If a previous `docs/tech-debt-report-full.md` exists, note what's improved and what's regressed since last audit.
