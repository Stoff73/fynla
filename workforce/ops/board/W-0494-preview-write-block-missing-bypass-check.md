---
id: W-0494
title: Four Architecture tests compare native filesystem paths and fail only on Windows
mission: M-0001-state-truth
owner: build-lead
status: done
severity: medium
surfaces: [web, m]
source: surfaced by the Architecture suite while running W-0001's gates, 2026-08-25
prior_art_checked: 2026-08-25
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Correction — this item was filed wrong

**Filed 2026-08-25 as "A preview write-block site does not check the
bypass-preview-mode ability", severity high, with a security reviewer attached.
That was wrong, and the title and severity above have been changed to match what
the defect actually is.**

The claim came from reading a red test name and inferring the failure it describes
without first reading the failure it produced. `07-quality-bar.md` §1 — nothing is
claimed that has not been demonstrated — applies to a bug report as much as to a
fix. A high-severity security item that turns out not to exist costs review
attention and, if it had been believed, could have prompted a change to
authorisation code that was already correct.

**There is no preview authorisation gap. No application code needed changing.**

## What it actually is

`PreviewBlockSitesCheckBypassTest` walks three directories and compares each file
against an ignore list authored with forward slashes:

    $relative = substr($file->getPathname(), strlen($root) + 1);

`getPathname()` returns native separators. On Windows that yields
`app/Http/Middleware\CheckSubscription.php` — the directory portion carries the
forward slashes the test supplied, the filename separator is a backslash — so
`in_array($relative, $ignore, true)` never matched and **all five deliberately
exempt files were reported as failures**.

Proven before anything was changed: re-running the test's own logic with
separators normalised gives **zero** offenders. The five reported files are
exactly the five on the ignore list.

Three more tests in the suite had the same defect in different clothes:

| Test | Symptom |
|---|---|
| `StoreBoundary\MortgageStoreBoundaryTest` | `str_replace($dir, ...)` never matched, so the "relative" path came out as `app/C:/Users/.../app/Services/Stores/MortgageStore.php` and missed the allowlist |
| `StoreBoundary\InvestmentAccountStoreBoundaryTest` | Identical |
| `OnlineReadinessDocumentsTest` | Emitted `July\July1Updates\...` against a register authored with forward slashes, so all 34 artefacts mismatched |

**CI runs Ubuntu, so all four pass there.** They are red only for developers on
Windows — who then learn to disregard a red Architecture suite, which is the
condition under which a real failure goes unnoticed. That is the actual risk, and
it is why this stays at medium rather than low.

This is the second Windows-portability defect found in the same session, after
W-0491. The pattern is that tooling written on macOS is not exercised on Windows.

## Fix

Normalise to forward slashes before comparing, in all four. `DIRECTORY_SEPARATOR`
is `/` on Linux and macOS, so each change is a no-op there and the logic is
unaltered on the platform CI runs.

- `PreviewBlockSitesCheckBypassTest` — normalise `$relative` before the ignore-list check.
- Both `StoreBoundary` tests — normalise `getRealPath()` **and** `$dir` before
  stripping, then `ltrim` on `/`. The original stripped with `DIRECTORY_SEPARATOR`,
  which on Windows trimmed a character the string did not begin with.
- `OnlineReadinessDocumentsTest` — normalise the real path and the root prefix.

## Verified

`vendor/bin/pest --testsuite=Architecture`:

- Before: **4 failed, 173 passed, 1 skipped, 4001 assertions**
- After: **0 failed, 177 passed, 1 skipped, 4296 assertions**

`php -l` clean on all four.

## Gaps

- **Windows only.** The fix is portable by inspection — every added call collapses
  to a no-op when `DIRECTORY_SEPARATOR` is `/` — but it was not *run* on macOS or
  Linux. CI will confirm on the next push.
- **No security review.** None is warranted: the diff touches four test files and
  no application, authorisation or preview-mode code. The reviewer attached when
  this was mis-filed has been removed.
- **No browser verification.** Nothing user-facing changed.

## 2026-09-01 — CLOSED, and the suite is now actually green

All four normalisations re-read in the code, not taken from the note:
`PreviewBlockSitesCheckBypassTest.php:71`, both `StoreBoundary` tests at `:78-80` /
`:68-70`, and `OnlineReadinessDocumentsTest.php:31-34`. Each collapses to a no-op where
`DIRECTORY_SEPARATOR` is `/`.

**The suite was still red on something else, and the point of this item is that a red
Architecture suite teaches developers to disregard it — so it is fixed here rather than
left for the next reader to skip past.**
`Tests\Architecture\StoreBoundary` failed on
`app/Services/UserProfile/UserProfileService.php:8` — `use App\Models\DCPension;`,
present since before this board run. The import existed solely for two private static
helpers, `monthlyEmployeeContribution()` and `isSalaryDeductedPension()` (W-0424), which
are pension-domain rules living on a profile service.

They now live in `app/Services/Retirement/PensionContributionRule.php` — a class that
takes a pension the caller already fetched through `PensionStore` and answers a question
about it, with no query and no mutation. Added to `PensionStoreBoundaryTest`'s allowlist
in the same category as `PensionDerivedColumnCalculator`, with the reason at the line.
`UserProfileService` no longer imports the model at all, which is the honest fix rather
than allowlisting a two-thousand-line service.

**Architecture suite: 151 passed, 1 skipped, 0 failed** — first clean run of this suite
in the board's records. Affected families re-run: **826 passed** across UserProfile /
Pension / Expenditure.

**Gaps unchanged:** the Windows fixes are still portable by inspection rather than run on
Windows; CI (Ubuntu) confirms the Linux side. No security review warranted, no browser
verification — nothing user-facing changed.
